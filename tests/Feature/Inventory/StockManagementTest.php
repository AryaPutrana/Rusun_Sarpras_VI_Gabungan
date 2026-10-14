<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Rusun $rusun;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->rusun = Rusun::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function withdrawal_deducts_correct_stock_amount()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 30],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
            'description' => 'Test withdrawal',
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        $item->refresh();
        $this->assertEquals(70, $item->stock, 'Stock should be reduced by 30');
    }

    /** @test */
    public function stock_cannot_go_negative()
    {
        $item = Item::factory()->create([
            'stock' => 10,
            'unit_price' => 50000,
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 15],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('tidak mencukupi', $response->getSession()->get('errors')->first('items'));
        
        $item->refresh();
        $this->assertEquals(10, $item->stock, 'Stock should remain unchanged');
    }

    /** @test */
    public function concurrent_withdrawals_prevent_race_condition()
    {
        $item = Item::factory()->create([
            'stock' => 50,
            'unit_price' => 50000,
        ]);

        // Simulate two concurrent transactions
        DB::beginTransaction();
        
        try {
            // First withdrawal locks the item
            $lockedItem1 = Item::where('id', $item->id)->lockForUpdate()->first();
            $this->assertEquals(50, $lockedItem1->stock);
            
            // Try concurrent access (this would wait in real concurrent scenario)
            // We'll test that the lock prevents inconsistent reads
            $lockedItem2 = Item::where('id', $item->id)->lockForUpdate()->first();
            
            $lockedItem1->decrement('stock', 20);
            $lockedItem2->refresh();
            
            $this->assertEquals(30, $lockedItem2->stock, 'Second lock should see updated value');
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $item->refresh();
        $this->assertEquals(30, $item->stock);
    }

    /** @test */
    public function stock_adjustment_creates_audit_trail()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        $initialAuditCount = StockAdjustment::count();

        // Add stock via item update
        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'add_stock' => 50,
        ]);

        $this->assertEquals($initialAuditCount + 1, StockAdjustment::count(), 'Audit record should be created');
        
        $audit = StockAdjustment::latest()->first();
        $this->assertEquals($item->id, $audit->item_id);
        $this->assertEquals($this->user->id, $audit->user_id);
        $this->assertEquals('add', $audit->type);
        $this->assertEquals(50, $audit->quantity);

        $item->refresh();
        $this->assertEquals(150, $item->stock);
    }

    /** @test */
    public function min_stock_alert_detects_low_stock_correctly()
    {
        $lowStockItem = Item::factory()->create([
            'stock' => 5,
            'min_stock' => 10,
        ]);

        $normalStockItem = Item::factory()->create([
            'stock' => 50,
            'min_stock' => 10,
        ]);

        $emptyStockItem = Item::factory()->create([
            'stock' => 0,
            'min_stock' => 10,
        ]);

        $this->assertTrue($lowStockItem->isLowStock(), 'Should detect low stock');
        $this->assertFalse($normalStockItem->isLowStock(), 'Should not detect low stock');
        $this->assertTrue($emptyStockItem->isLowStock(), 'Should detect empty stock');

        $this->assertEquals('low', $lowStockItem->getStockStatus());
        $this->assertEquals('empty', $emptyStockItem->getStockStatus());
        $this->assertEquals('good', $normalStockItem->getStockStatus());
    }

    /** @test */
    public function rollback_on_failed_withdrawal_restores_stock()
    {
        $item1 = Item::factory()->create(['stock' => 100, 'unit_price' => 50000]);
        $item2 = Item::factory()->create(['stock' => 5, 'unit_price' => 75000]);

        // Attempt withdrawal with one item that has insufficient stock
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 20],
                ['item_id' => $item2->id, 'quantity' => 10], // This will fail
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors();

        // Neither item should have stock changed due to transaction rollback
        $item1->refresh();
        $item2->refresh();
        
        $this->assertEquals(100, $item1->stock, 'First item stock should be unchanged');
        $this->assertEquals(5, $item2->stock, 'Second item stock should be unchanged');
        $this->assertEquals(0, Withdrawal::count(), 'No withdrawal should be created');
    }

    /** @test */
    public function stock_restored_on_withdrawal_delete()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        // Create withdrawal
        $withdrawal = Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 30,
            'total_value' => 1500000,
            'taken_at' => now(),
        ]);

        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 30,
            'unit_price' => 50000,
            'subtotal' => 1500000,
        ]);

        $item->decrement('stock', 30);
        $item->refresh();
        $this->assertEquals(70, $item->stock);

        // Delete withdrawal - stock should be restored
        $response = $this->delete(route('inventory.withdrawals.destroy', $withdrawal->id));
        
        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        $item->refresh();
        $this->assertEquals(100, $item->stock, 'Stock should be restored after withdrawal deletion');
        $this->assertEquals(0, Withdrawal::count(), 'Withdrawal should be deleted');
    }

    /** @test */
    public function withdrawal_update_adjusts_stock_correctly()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        // Create initial withdrawal
        $withdrawal = Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 20,
            'total_value' => 1000000,
            'taken_at' => now(),
        ]);

        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 20,
            'unit_price' => 50000,
            'subtotal' => 1000000,
        ]);

        $item->decrement('stock', 20);
        $item->refresh();
        $this->assertEquals(80, $item->stock);

        // Update withdrawal to increase quantity
        $response = $this->put(route('inventory.withdrawals.update', $withdrawal->id), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 35], // Increased from 20 to 35
            ],
            'taken_by' => 'Test User Updated',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        $item->refresh();
        $this->assertEquals(65, $item->stock, 'Stock should be reduced by additional 15 (35-20)');
    }

    /** @test */
    public function withdrawal_update_with_reduced_quantity_returns_stock()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        // Create initial withdrawal
        $withdrawal = Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 50,
            'total_value' => 2500000,
            'taken_at' => now(),
        ]);

        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 50,
            'unit_price' => 50000,
            'subtotal' => 2500000,
        ]);

        $item->decrement('stock', 50);
        $item->refresh();
        $this->assertEquals(50, $item->stock);

        // Update withdrawal to decrease quantity
        $response = $this->put(route('inventory.withdrawals.update', $withdrawal->id), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 25], // Decreased from 50 to 25
            ],
            'taken_by' => 'Test User Updated',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        $item->refresh();
        $this->assertEquals(75, $item->stock, 'Stock should be increased by 25 (50-25)');
    }

    /** @test */
    public function stock_adjustment_with_subtract_creates_audit_trail()
    {
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 50000,
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'subtract_stock' => 15,
            'subtract_reason' => 'Barang rusak',
        ]);

        $response->assertRedirect(route('inventory.items.index'));

        $audit = StockAdjustment::where('type', 'subtract')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals($item->id, $audit->item_id);
        $this->assertEquals('subtract', $audit->type);
        $this->assertEquals(15, $audit->quantity);
        $this->assertEquals('Barang rusak', $audit->reason);

        $item->refresh();
        $this->assertEquals(85, $item->stock);
    }

    /** @test */
    public function cannot_subtract_more_stock_than_available()
    {
        $item = Item::factory()->create([
            'stock' => 10,
            'unit_price' => 50000,
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'subtract_stock' => 15,
            'subtract_reason' => 'Test',
        ]);

        $response->assertSessionHasErrors('subtract_stock');
        
        $item->refresh();
        $this->assertEquals(10, $item->stock, 'Stock should remain unchanged');
    }

    /** @test */
    public function multiple_items_withdrawal_locks_in_order_to_prevent_deadlock()
    {
        $item1 = Item::factory()->create(['id' => 1, 'stock' => 100, 'unit_price' => 50000]);
        $item2 = Item::factory()->create(['id' => 2, 'stock' => 200, 'unit_price' => 75000]);
        $item3 = Item::factory()->create(['id' => 3, 'stock' => 150, 'unit_price' => 100000]);

        // Submit items in non-sorted order - controller should sort them
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => 3, 'quantity' => 10],
                ['item_id' => 1, 'quantity' => 15],
                ['item_id' => 2, 'quantity' => 20],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));

        // Verify all stocks were decremented
        $this->assertEquals(85, $item1->fresh()->stock);
        $this->assertEquals(180, $item2->fresh()->stock);
        $this->assertEquals(140, $item3->fresh()->stock);
    }

    /** @test */
    public function form_submit_add_stock_works_even_when_new_stock_is_sent()
    {
        $item = Item::factory()->create([
            'stock' => 27,
            'unit_price' => 50000,
            'unit' => 'pcs',
        ]);

        // Payload meniru form edit asli: mode adjustment, tetapi field mode
        // direct (new_stock) tetap ikut terkirim dengan nilai stok lama.
        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'min_stock' => $item->min_stock,
            'stock_mode' => 'adjustment',
            'add_stock' => 10,
            'subtract_stock' => 0,
            'subtract_reason' => '',
            'new_stock' => $item->stock,
            'stock_change_reason' => '',
        ]);

        $response->assertRedirect(route('inventory.items.index'));
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(37, $item->stock, 'Penambahan stok harus tersimpan meski new_stock ikut terkirim');

        $audit = StockAdjustment::where('type', 'add')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals(10, $audit->quantity);
    }

    /** @test */
    public function form_submit_subtract_stock_works_even_when_new_stock_is_sent()
    {
        $item = Item::factory()->create([
            'stock' => 27,
            'unit_price' => 50000,
            'unit' => 'pcs',
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'min_stock' => $item->min_stock,
            'stock_mode' => 'adjustment',
            'add_stock' => 0,
            'subtract_stock' => 7,
            'subtract_reason' => 'Barang rusak',
            'new_stock' => $item->stock,
            'stock_change_reason' => '',
        ]);

        $response->assertRedirect(route('inventory.items.index'));
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(20, $item->stock, 'Pengurangan stok harus tersimpan meski new_stock ikut terkirim');
    }

    /** @test */
    public function subtract_without_reason_is_rejected_in_adjustment_mode()
    {
        $item = Item::factory()->create([
            'stock' => 27,
            'unit_price' => 50000,
            'unit' => 'pcs',
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'min_stock' => $item->min_stock,
            'stock_mode' => 'adjustment',
            'add_stock' => 0,
            'subtract_stock' => 5,
            'subtract_reason' => '',
            'new_stock' => $item->stock,
        ]);

        $response->assertSessionHasErrors('subtract_reason');

        $item->refresh();
        $this->assertEquals(27, $item->stock, 'Stok tidak boleh berubah saat validasi gagal');
    }

    /** @test */
    public function form_submit_direct_edit_sets_new_stock()
    {
        $item = Item::factory()->create([
            'stock' => 27,
            'unit_price' => 50000,
            'unit' => 'pcs',
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'min_stock' => $item->min_stock,
            'stock_mode' => 'direct',
            'add_stock' => 0,
            'subtract_stock' => 0,
            'subtract_reason' => '',
            'new_stock' => 100,
            'stock_change_reason' => 'Stock opname',
        ]);

        $response->assertRedirect(route('inventory.items.index'));
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(100, $item->stock, 'Mode direct harus mengganti stok dengan angka baru');

        $audit = StockAdjustment::where('type', 'add')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals(73, $audit->quantity);
    }

    /** @test */
    public function update_photo_alone_does_not_require_stock_reason()
    {
        Storage::fake('public');
        Storage::fake('local');

        $item = Item::factory()->create([
            'stock' => 27,
            'unit_price' => 50000,
            'unit' => 'pcs',
        ]);

        $response = $this->put(route('inventory.items.update', $item->id), [
            'item_code' => $item->item_code,
            'name' => $item->name,
            'unit_price' => $item->unit_price,
            'unit' => $item->unit,
            'min_stock' => $item->min_stock,
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'stock_mode' => 'adjustment',
            'add_stock' => 0,
            'subtract_stock' => 0,
            'subtract_reason' => '',
            'new_stock' => $item->stock,
            'stock_change_reason' => '',
        ]);

        $response->assertRedirect(route('inventory.items.index'));
        $response->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNotNull($item->photo, 'Foto baru harus tersimpan di kolom photo');
        $this->assertEquals(27, $item->stock, 'Stok tidak boleh berubah saat hanya ganti foto');
    }
}
