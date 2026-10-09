<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationTest extends TestCase
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
    public function price_times_quantity_equals_subtotal_with_correct_precision()
    {
        $item = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 1234.56, // 2 decimal places
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 7],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));

        $withdrawal = Withdrawal::first();
        $withdrawalItem = $withdrawal->items->first();

        // 1234.56 * 7 = 8641.92
        $expectedSubtotal = 8641.92;
        
        $this->assertEquals($expectedSubtotal, (float) $withdrawalItem->subtotal, 'Subtotal calculation should be precise');
        $this->assertEquals($expectedSubtotal, (float) $withdrawal->total_value, 'Total value should match subtotal');
    }

    /** @test */
    public function total_value_calculation_with_multiple_items()
    {
        $item1 = Item::factory()->create(['stock' => 1000, 'unit_price' => 1500.75]);
        $item2 = Item::factory()->create(['stock' => 1000, 'unit_price' => 2250.50]);
        $item3 = Item::factory()->create(['stock' => 1000, 'unit_price' => 999.99]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 10], // 15007.50
                ['item_id' => $item2->id, 'quantity' => 5],  // 11252.50
                ['item_id' => $item3->id, 'quantity' => 3],  // 2999.97
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // Expected: 15007.50 + 11252.50 + 2999.97 = 29259.97
        $expectedTotal = 29259.97;
        
        $this->assertEquals($expectedTotal, (float) $withdrawal->total_value, 'Total value should sum all subtotals correctly');
        $this->assertEquals(18, $withdrawal->total_quantity, 'Total quantity should be 10+5+3=18');
    }

    /** @test */
    public function large_number_handling_millions()
    {
        // Test with price approaching 1 billion (max allowed)
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 999999999.99, // Close to Item::MAX_UNIT_PRICE (1 billion)
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // 999999999.99 * 2 = 1999999999.98
        $expectedTotal = 1999999999.98;
        
        $this->assertEquals($expectedTotal, (float) $withdrawal->total_value, 'Large number calculation should be accurate');
    }

    /** @test */
    public function overflow_prevention_for_decimal_column_limit()
    {
        // Withdrawal::MAX_SUBTOTAL is 9999999999999.99 (DECIMAL(15,2) max)
        $item = Item::factory()->create([
            'stock' => 1000000,
            'unit_price' => 50000000, // 50 million
        ]);

        // Attempting to withdraw quantity that would overflow: 50M * 300,000 = 15 trillion (exceeds limit)
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 300000],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertStringContainsString('melebihi batas maksimum', $response->getSession()->get('errors')->first('items'));
        
        // No withdrawal should be created
        $this->assertEquals(0, Withdrawal::count());
        
        // Stock should remain unchanged
        $this->assertEquals(1000000, $item->fresh()->stock);
    }

    /** @test */
    public function decimal_precision_maintained_with_repeating_decimals()
    {
        // Test with prices that might cause rounding issues
        $item = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 33.33, // Repeating decimal when divided
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 7],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        $withdrawalItem = $withdrawal->items->first();

        // 33.33 * 7 = 233.31
        $expectedSubtotal = 233.31;
        
        $this->assertEquals($expectedSubtotal, (float) $withdrawalItem->subtotal);
    }

    /** @test */
    public function decimal_rounding_behavior_for_half_cent()
    {
        // Test rounding behavior: does 0.5 round up?
        // In PHP, (int)(0.5) = 0, but number_format and bcmath round 0.5 up
        // MySQL DECIMAL stores exact values without automatic rounding
        
        $item = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 10.005, // This might get rounded to 10.01 or 10.00 depending on cast
        ]);

        // When saved to DECIMAL(15,2), 10.005 should round to 10.01 (banker's rounding or round half up)
        // Let's verify what actually gets stored
        $item->refresh();
        
        // DECIMAL(15,2) will store 10.01 (MySQL rounds .005 to nearest even = 10.00, but in INSERT it's 10.01)
        $actualPrice = (float) $item->unit_price;
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // The subtotal should match whatever was actually stored
        $this->assertEquals($actualPrice, (float) $withdrawal->total_value);
        
        // Test another scenario: 10.01 * 3 = 30.03 (exact)
        $item2 = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 10.01,
        ]);

        $response2 = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item2->id, 'quantity' => 3],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal2 = Withdrawal::latest()->first();
        $this->assertEquals(30.03, (float) $withdrawal2->total_value);
    }

    /** @test */
    public function currency_display_formatting_preserves_two_decimals()
    {
        $item = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 1500.00, // Whole number
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 5],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // Should be stored as 7500.00, not 7500
        $this->assertEquals('7500.00', $withdrawal->total_value);
        
        // Test with .50 cents
        $item2 = Item::factory()->create([
            'stock' => 1000,
            'unit_price' => 1234.50,
        ]);

        $response2 = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item2->id, 'quantity' => 2],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal2 = Withdrawal::latest()->first();
        $this->assertEquals('2469.00', $withdrawal2->total_value);
    }

    /** @test */
    public function zero_price_item_calculation()
    {
        // Edge case: free items (price = 0)
        $item = Item::factory()->create([
            'stock' => 100,
            'unit_price' => 0.00,
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        $this->assertEquals(0.00, (float) $withdrawal->total_value);
        $this->assertEquals(10, $withdrawal->total_quantity);
        
        // Stock should still be deducted
        $this->assertEquals(90, $item->fresh()->stock);
    }

    /** @test */
    public function single_cent_precision_calculation()
    {
        // Test with single cent values
        $item = Item::factory()->create([
            'stock' => 10000,
            'unit_price' => 0.01, // 1 cent
        ]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 999],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // 0.01 * 999 = 9.99
        $this->assertEquals(9.99, (float) $withdrawal->total_value);
    }

    /** @test */
    public function maximum_quantity_per_item_enforced()
    {
        $item = Item::factory()->create([
            'stock' => 2000000,
            'unit_price' => 100,
        ]);

        // Withdrawal::MAX_QUANTITY is 1000000
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item->id, 'quantity' => 1000001], // Exceeds max
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertEquals(0, Withdrawal::count());
    }

    /** @test */
    public function total_quantity_sums_correctly_across_multiple_items()
    {
        $item1 = Item::factory()->create(['stock' => 1000, 'unit_price' => 100]);
        $item2 = Item::factory()->create(['stock' => 1000, 'unit_price' => 200]);
        $item3 = Item::factory()->create(['stock' => 1000, 'unit_price' => 300]);

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $item1->id, 'quantity' => 50],
                ['item_id' => $item2->id, 'quantity' => 75],
                ['item_id' => $item3->id, 'quantity' => 100],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // Total quantity should be 50 + 75 + 100 = 225
        $this->assertEquals(225, $withdrawal->total_quantity);
        
        // Total value should be (50*100) + (75*200) + (100*300) = 5000 + 15000 + 30000 = 50000
        $this->assertEquals(50000.00, (float) $withdrawal->total_value);
    }

    /** @test */
    public function calculation_accuracy_with_many_decimal_operations()
    {
        // Test accumulation of many small calculations
        $items = [];
        for ($i = 0; $i < 10; $i++) {
            $items[] = Item::factory()->create([
                'stock' => 1000,
                'unit_price' => 9.99,
            ]);
        }

        $withdrawalItems = [];
        foreach ($items as $item) {
            $withdrawalItems[] = ['item_id' => $item->id, 'quantity' => 7];
        }

        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => $withdrawalItems,
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // Each item: 9.99 * 7 = 69.93
        // Total: 69.93 * 10 = 699.30
        $this->assertEquals(699.30, (float) $withdrawal->total_value);
        $this->assertEquals(70, $withdrawal->total_quantity);
    }
}
