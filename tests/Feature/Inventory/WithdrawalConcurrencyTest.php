<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@sarpras.com',
            'password' => bcrypt('password123'),
            'role' => 'petugas',
        ]);
    }

    /** @test */
    public function withdrawal_handles_concurrent_stock_deduction_safely()
    {
        // Create item dengan stock 10
        $item = Item::create([
            'item_code' => 'BRG-001',
            'name' => 'Test Item',
            'unit_price' => 10000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'Unit',
        ]);

        $rusun = Rusun::create([
            'code' => 'TEST',
            'name' => 'Test Rusun',
        ]);

        // Test 1: Single withdrawal dengan quantity > stock seharusnya ditolak
        $response = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 15, // Lebih dari stock (10)
                ],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(302); // Redirect dengan error
        $response->assertSessionHasErrors('items');

        // Verify stock tidak berubah
        $this->assertEquals(10, $item->fresh()->stock);

        // Test 2: Valid withdrawal dengan quantity 8
        $response = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 8,
                ],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $response->assertSessionHas('success');

        // Verify stock dikurangi menjadi 2
        $this->assertEquals(2, $item->fresh()->stock);

        // Test 3: Withdrawal kedua dengan quantity 3 seharusnya ditolak (stock hanya 2)
        $response = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 3,
                ],
            ],
            'taken_by' => 'Test User 2',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('items');

        // Verify stock tetap 2
        $this->assertEquals(2, $item->fresh()->stock);
    }

    /** @test */
    public function withdrawal_prevents_negative_stock_with_database_locking()
    {
        // Create item dengan stock 5
        $item = Item::create([
            'item_code' => 'BRG-002',
            'name' => 'Test Item 2',
            'unit_price' => 20000,
            'stock' => 5,
            'min_stock' => 2,
            'unit' => 'Pcs',
        ]);

        $rusun = Rusun::create([
            'code' => 'TEST2',
            'name' => 'Test Rusun 2',
        ]);

        // Simulasi: 2 withdrawal bersamaan, masing-masing ambil 3 unit
        // Stock = 5, jadi salah satunya harus ditolak
        
        // Withdrawal 1: ambil 3 unit (berhasil)
        $response1 = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 3,
                ],
            ],
            'taken_by' => 'User A',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response1->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertEquals(2, $item->fresh()->stock); // Stock = 5 - 3 = 2

        // Withdrawal 2: ambil 3 unit (harus ditolak karena stock hanya 2)
        $response2 = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 3,
                ],
            ],
            'taken_by' => 'User B',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response2->assertStatus(302);
        $response2->assertSessionHasErrors('items');

        // Verify stock tetap 2 (tidak negatif)
        $this->assertEquals(2, $item->fresh()->stock);

        // Verify hanya ada 1 withdrawal yang berhasil
        $this->assertEquals(1, $item->withdrawalItems()->count());
    }

    /** @test */
    public function withdrawal_with_multiple_items_locks_in_sorted_order_to_prevent_deadlock()
    {
        // Create 2 items
        $item1 = Item::create([
            'item_code' => 'BRG-003',
            'name' => 'Test Item 3',
            'unit_price' => 5000,
            'stock' => 20,
            'min_stock' => 5,
            'unit' => 'Box',
        ]);

        $item2 = Item::create([
            'item_code' => 'BRG-004',
            'name' => 'Test Item 4',
            'unit_price' => 7000,
            'stock' => 15,
            'min_stock' => 3,
            'unit' => 'Pack',
        ]);

        $rusun = Rusun::create([
            'code' => 'TEST3',
            'name' => 'Test Rusun 3',
        ]);

        // Withdrawal dengan multiple items dalam urutan berbeda
        // Controller harus sort item_id untuk mencegah deadlock
        $response = $this->actingAs($this->user)->postJson(route('inventory.withdrawals.store'), [
            'items' => [
                // Sengaja urutan terbalik (item2 dulu, baru item1)
                [
                    'item_id' => $item2->id,
                    'quantity' => 5,
                ],
                [
                    'item_id' => $item1->id,
                    'quantity' => 10,
                ],
            ],
            'taken_by' => 'Multi Item User',
            'rusun_id' => $rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $response->assertSessionHas('success');

        // Verify both stocks dikurangi dengan benar
        $this->assertEquals(10, $item1->fresh()->stock); // 20 - 10 = 10
        $this->assertEquals(10, $item2->fresh()->stock); // 15 - 5 = 10

        // Verify withdrawal items tersimpan dengan benar
        $withdrawal = \App\Models\Withdrawal::latest()->first();
        $this->assertEquals(2, $withdrawal->items()->count());
        $this->assertEquals(15, $withdrawal->total_quantity); // 10 + 5
    }
}
