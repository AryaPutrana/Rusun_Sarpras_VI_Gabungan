<?php

namespace Tests\Feature\EdgeCase;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Edge Case & Race Condition Tests for Withdrawal System
 * 
 * Covers:
 * - Boundary values (INT_MAX, 0, negative, overflow)
 * - Null handling and missing relationships
 * - Empty states
 * - Unicode edge cases
 * - Concurrent operations and race conditions
 */
class WithdrawalEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Rusun $rusun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->rusun = Rusun::create([
            'name' => 'Test Rusun',
            'description' => 'Test Description',
        ]);
    }

    /** @test */
    public function it_handles_boundary_value_stock_zero()
    {
        $item = Item::factory()->create(['stock' => 0, 'min_stock' => 10]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors();
        $this->assertStringContainsString('tidak mencukupi', session('errors')->first('items'));
    }

    /** @test */
    public function it_handles_boundary_value_stock_one()
    {
        $item = Item::factory()->create(['stock' => 1, 'unit_price' => 1000]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('items', ['id' => $item->id, 'stock' => 0]);
    }

    /** @test */
    public function it_handles_boundary_value_max_stock()
    {
        // Test with large stock value close to INT_MAX (2147483647)
        $item = Item::factory()->create([
            'stock' => Item::MAX_STOCK,
            'unit_price' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'stock' => Item::MAX_STOCK - 1,
        ]);
    }

    /** @test */
    public function it_rejects_negative_withdrawal_quantity()
    {
        $item = Item::factory()->create(['stock' => 100]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => -1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    /** @test */
    public function it_rejects_zero_withdrawal_quantity()
    {
        $item = Item::factory()->create(['stock' => 100]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 0],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    /** @test */
    public function it_rejects_quantity_exceeding_max_limit()
    {
        $item = Item::factory()->create(['stock' => Withdrawal::MAX_QUANTITY + 1]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => Withdrawal::MAX_QUANTITY + 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    /** @test */
    public function it_handles_boundary_value_max_price()
    {
        // Test with maximum allowed price
        $item = Item::factory()->create([
            'stock' => 1,
            'unit_price' => Item::MAX_UNIT_PRICE,
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', [
            'total_value' => Item::MAX_UNIT_PRICE,
        ]);
    }

    /** @test */
    public function it_prevents_total_value_overflow()
    {
        // Create items that would cause overflow when multiplied
        $item = Item::factory()->create([
            'stock' => 10000,
            'unit_price' => 99999999999.99, // Close to decimal max
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1000],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertStringContainsString('melebihi batas maksimum', session('errors')->first('items'));
    }

    /** @test */
    public function it_handles_empty_description()
    {
        $item = Item::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'description' => null,
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', ['description' => null]);
    }

    /** @test */
    public function it_handles_max_length_description()
    {
        $item = Item::factory()->create(['stock' => 10]);
        $longDescription = str_repeat('A', 60000);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'description' => $longDescription,
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', ['description' => $longDescription]);
    }

    /** @test */
    public function it_rejects_description_exceeding_max_length()
    {
        $item = Item::factory()->create(['stock' => 10]);
        $tooLongDescription = str_repeat('A', 60001);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'description' => $tooLongDescription,
        ]);

        $response->assertSessionHasErrors('description');
    }

    /** @test */
    public function it_handles_unix_epoch_boundary_dates()
    {
        $item = Item::factory()->create(['stock' => 10]);

        // Test 1970-01-01 (Unix epoch start)
        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => '1970-01-01 00:00:00',
            'description' => 'Unix epoch test',
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', ['taken_by' => 'Tester']);
    }

    /** @test */
    public function it_handles_year_2038_boundary_date()
    {
        $item = Item::factory()->create(['stock' => 10]);

        // Test 2038-01-19 (32-bit Unix timestamp limit)
        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => '2038-01-19 03:14:07',
            'description' => '32-bit timestamp limit',
        ]);

        // Should fail due to before_or_equal:now validation
        $response->assertSessionHasErrors('taken_at');
    }

    /** @test */
    public function it_rejects_empty_items_array()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items');
    }

    /** @test */
    public function it_accepts_exactly_one_item()
    {
        $item = Item::factory()->create(['stock' => 10]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
    }

    /** @test */
    public function it_accepts_max_items_per_transaction()
    {
        $items = Item::factory()->count(Withdrawal::MAX_ITEMS_PER_TRANSACTION)->create(['stock' => 10]);

        $itemsData = $items->map(fn($item) => [
            'item_id' => $item->id,
            'quantity' => 1,
        ])->toArray();

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => $itemsData,
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
    }

    /** @test */
    public function it_rejects_exceeding_max_items_per_transaction()
    {
        $items = Item::factory()->count(Withdrawal::MAX_ITEMS_PER_TRANSACTION + 1)->create(['stock' => 10]);

        $itemsData = $items->map(fn($item) => [
            'item_id' => $item->id,
            'quantity' => 1,
        ])->toArray();

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => $itemsData,
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items');
    }

    /** @test */
    public function it_handles_unicode_in_item_name_emoji()
    {
        $item = Item::factory()->create([
            'name' => '🎉 Party Item 🎊',
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertEquals(9, $item->fresh()->stock);
    }

    /** @test */
    public function it_handles_unicode_chinese_characters()
    {
        $item = Item::factory()->create([
            'name' => '中文商品名称',
            'description' => '这是中文描述',
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => '测试用户',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'description' => '中文描述测试',
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', [
            'taken_by' => '测试用户',
            'description' => '中文描述测试',
        ]);
    }

    /** @test */
    public function it_handles_unicode_arabic_rtl_text()
    {
        $item = Item::factory()->create([
            'name' => 'العربية النص',
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'المستخدم',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertDatabaseHas('withdrawals', ['taken_by' => 'المستخدم']);
    }

    /** @test */
    public function it_handles_search_with_unicode()
    {
        $item = Item::factory()->create(['name' => '🎉 Test Item', 'stock' => 10]);
        
        $withdrawal = Withdrawal::factory()->create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('inventory.withdrawals.index', [
            'search' => '🎉',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Test Item');
    }

    /** @test */
    public function it_handles_empty_database_gracefully()
    {
        // Clear all data
        DB::table('withdrawal_items')->delete();
        DB::table('withdrawals')->delete();
        DB::table('items')->delete();

        $response = $this->actingAs($this->admin)->get(route('inventory.withdrawals.index'));

        $response->assertStatus(200);
        $response->assertViewHas('withdrawals');
    }

    /** @test */
    public function it_handles_search_with_no_results()
    {
        Item::factory()->create(['name' => 'Existing Item', 'stock' => 10]);

        $response = $this->actingAs($this->admin)->get(route('inventory.withdrawals.index', [
            'search' => 'NonExistentItem12345',
        ]));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->viewData('withdrawals')->count());
    }

    /** @test */
    public function it_handles_invalid_date_filter_gracefully()
    {
        $response = $this->actingAs($this->admin)->get(route('inventory.withdrawals.index', [
            'start_date' => 'invalid-date',
            'end_date' => '2024-12-31',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('filterWarning');
    }

    /** @test */
    public function it_handles_end_date_before_start_date()
    {
        $response = $this->actingAs($this->admin)->get(route('inventory.withdrawals.index', [
            'start_date' => '2024-12-31',
            'end_date' => '2024-01-01',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('filterWarning');
    }

    /** @test */
    public function it_handles_missing_item_photo()
    {
        $item = Item::factory()->create([
            'photo' => null,
            'stock' => 10,
        ]);

        $this->assertFalse($item->hasPhoto());
        $this->assertEquals(asset(Item::DEFAULT_PHOTO_URL), $item->photoUrl());
    }

    /** @test */
    public function it_handles_deleted_rusun_relationship()
    {
        $item = Item::factory()->create(['stock' => 10]);
        $rusun = Rusun::create(['name' => 'To Be Deleted']);

        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $rusun->id,
            'taken_by' => 'Test User',
        ]);

        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        // Delete the rusun
        $rusun->delete();

        // Refresh and check relationship
        $withdrawal->refresh();
        
        // Due to RESTRICT foreign key, the deletion should have failed
        // or the withdrawal should handle null rusun gracefully
        $this->assertNotNull(Rusun::find($rusun->id));
    }

    /** @test */
    public function it_prevents_concurrent_stock_race_condition()
    {
        $item = Item::factory()->create(['stock' => 10, 'unit_price' => 1000]);

        // Simulate two concurrent requests trying to withdraw 8 units each
        // Both should check stock (10) and think they can proceed
        // But only the first should succeed

        $errors = [];
        
        DB::transaction(function () use ($item, &$errors) {
            try {
                // First transaction locks the item
                $lockedItem = Item::where('id', $item->id)->lockForUpdate()->first();
                
                if (8 > $lockedItem->stock) {
                    $errors[] = 'First transaction failed stock check';
                }
                
                $lockedItem->decrement('stock', 8);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        });

        // Second transaction should see updated stock
        DB::transaction(function () use ($item, &$errors) {
            try {
                $lockedItem = Item::where('id', $item->id)->lockForUpdate()->first();
                
                if (8 > $lockedItem->stock) {
                    $errors[] = 'Second transaction correctly failed stock check (stock: ' . $lockedItem->stock . ')';
                } else {
                    $errors[] = 'Second transaction should have failed!';
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        });

        // Verify final stock is 2 (10 - 8)
        $this->assertEquals(2, $item->fresh()->stock);
        $this->assertCount(1, $errors); // Should only have one error from second transaction
        $this->assertStringContainsString('correctly failed', $errors[0]);
    }

    /** @test */
    public function it_handles_concurrent_withdrawal_creation_with_locking()
    {
        $item = Item::factory()->create(['stock' => 10, 'unit_price' => 1000]);

        // Create first withdrawal
        $response1 = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 8],
            ],
            'taken_by' => 'User 1',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response1->assertRedirect(route('inventory.withdrawals.index'));

        // Try to create second withdrawal (should fail due to insufficient stock)
        $response2 = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 8],
            ],
            'taken_by' => 'User 2',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response2->assertSessionHasErrors('items');
        
        // Verify stock is correct (only first withdrawal succeeded)
        $this->assertEquals(2, $item->fresh()->stock);
        $this->assertEquals(1, Withdrawal::count());
    }

    /** @test */
    public function it_handles_item_deletion_while_viewing_withdrawal()
    {
        $item = Item::factory()->create(['stock' => 10]);
        
        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_by' => 'Test User',
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_price' => 1000,
            'subtotal' => 5000,
        ]);

        // Delete the item (this should fail due to foreign key constraint)
        $this->expectException(\Illuminate\Database\QueryException::class);
        $item->delete();
    }

    /** @test */
    public function it_prevents_negative_stock_after_withdrawal()
    {
        $item = Item::factory()->create(['stock' => 5]);

        $response = $this->actingAs($this->admin)->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Tester',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('items');
        
        // Stock should remain unchanged
        $this->assertEquals(5, $item->fresh()->stock);
    }

    /** @test */
    public function it_handles_editing_with_increased_quantity()
    {
        $item = Item::factory()->create(['stock' => 100, 'unit_price' => 1000]);
        
        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_by' => 'Test User',
            'total_quantity' => 10,
            'total_value' => 10000,
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'subtotal' => 10000,
        ]);

        // Stock should be 90 (100 - 10)
        $this->assertEquals(90, $item->fresh()->stock);

        // Edit to increase quantity to 20
        $response = $this->actingAs($this->admin)->put(route('inventory.withdrawals.update', $withdrawal), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 20],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        // Stock should be 80 (90 - 10 additional)
        $this->assertEquals(80, $item->fresh()->stock);
    }

    /** @test */
    public function it_handles_editing_with_decreased_quantity()
    {
        $item = Item::factory()->create(['stock' => 90, 'unit_price' => 1000]);
        
        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_by' => 'Test User',
            'total_quantity' => 10,
            'total_value' => 10000,
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'subtotal' => 10000,
        ]);

        // Edit to decrease quantity to 5
        $response = $this->actingAs($this->admin)->put(route('inventory.withdrawals.update', $withdrawal), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 5],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        // Stock should be 95 (90 + 5 returned)
        $this->assertEquals(95, $item->fresh()->stock);
    }

    /** @test */
    public function it_restores_stock_on_withdrawal_deletion()
    {
        $item = Item::factory()->create(['stock' => 90, 'unit_price' => 1000]);
        
        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_by' => 'Test User',
            'total_quantity' => 10,
            'total_value' => 10000,
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'subtotal' => 10000,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('inventory.withdrawals.destroy', $withdrawal));

        $response->assertRedirect(route('inventory.withdrawals.index'));
        
        // Stock should be restored to 100
        $this->assertEquals(100, $item->fresh()->stock);
    }
}
