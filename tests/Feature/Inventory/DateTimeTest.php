<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Rusun $rusun;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->rusun = Rusun::factory()->create();
        $this->item = Item::factory()->create(['stock' => 1000, 'unit_price' => 50000]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function timezone_consistency_wib()
    {
        // Set timezone to WIB (Asia/Jakarta, UTC+7)
        config(['app.timezone' => 'Asia/Jakarta']);
        
        $wibTime = Carbon::create(2026, 10, 9, 14, 30, 0, 'Asia/Jakarta'); // 2:30 PM WIB
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $wibTime->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        
        // Verify the stored time matches WIB
        $this->assertEquals($wibTime->toDateTimeString(), $withdrawal->taken_at->toDateTimeString());
        
        // Verify it's actually 07:30 UTC (14:30 - 7 hours)
        $this->assertEquals('07:30:00', $withdrawal->taken_at->utc()->format('H:i:s'));
    }

    /** @test */
    public function midnight_boundary_transition()
    {
        // Test withdrawal at 23:59:59
        $justBeforeMidnight = Carbon::create(2026, 10, 9, 23, 59, 59);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $justBeforeMidnight->toDateTimeString(),
        ]);

        $withdrawal1 = Withdrawal::first();
        $this->assertEquals('2026-10-09', $withdrawal1->taken_at->toDateString());
        $this->assertEquals('23:59:59', $withdrawal1->taken_at->toTimeString());

        // Test withdrawal at 00:00:00 next day
        $justAfterMidnight = Carbon::create(2026, 10, 10, 0, 0, 0);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $justAfterMidnight->toDateTimeString(),
        ]);

        // Get the second withdrawal by taken_at time
        $withdrawal2 = Withdrawal::where('taken_at', $justAfterMidnight)->first();
        $this->assertNotNull($withdrawal2);
        $this->assertEquals('2026-10-10', $withdrawal2->taken_at->toDateString());
        $this->assertEquals('00:00:00', $withdrawal2->taken_at->toTimeString());
    }

    /** @test */
    public function month_end_date_handling_january_to_february()
    {
        // January 31 -> Should not cause issues
        $jan31 = Carbon::create(2026, 1, 31, 12, 0, 0);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $jan31->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::where('taken_at', $jan31)->first();
        $this->assertNotNull($withdrawal);
        $this->assertEquals('2026-01-31', $withdrawal->taken_at->toDateString());

        // February 28 (non-leap year)
        $feb28 = Carbon::create(2026, 2, 28, 12, 0, 0);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $feb28->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::where('taken_at', $feb28)->first();
        $this->assertNotNull($withdrawal);
        $this->assertEquals('2026-02-28', $withdrawal->taken_at->toDateString());
    }

    /** @test */
    public function leap_year_handling_february_29()
    {
        // 2024 is a leap year - February has 29 days
        $feb29_2024 = Carbon::create(2024, 2, 29, 12, 0, 0);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $feb29_2024->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        $this->assertEquals('2024-02-29', $withdrawal->taken_at->toDateString());
        
        // Verify it's properly stored and retrieved
        $this->assertTrue($withdrawal->taken_at->isLeapYear());
        $this->assertEquals(29, $withdrawal->taken_at->day);
        $this->assertEquals(2, $withdrawal->taken_at->month);
    }

    /** @test */
    public function date_range_filter_inclusive_boundaries()
    {
        // Create withdrawals on different dates
        $dates = [
            Carbon::create(2026, 10, 5, 10, 0, 0),
            Carbon::create(2026, 10, 10, 10, 0, 0),
            Carbon::create(2026, 10, 15, 10, 0, 0),
            Carbon::create(2026, 10, 20, 10, 0, 0),
        ];

        foreach ($dates as $date) {
            Withdrawal::create([
                'taken_by' => 'Test User',
                'rusun_id' => $this->rusun->id,
                'total_quantity' => 10,
                'total_value' => 500000,
                'taken_at' => $date,
            ]);
        }

        // Filter: 2026-10-10 to 2026-10-15 (inclusive)
        $response = $this->get(route('inventory.withdrawals.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
        ]));

        $response->assertStatus(200);
        
        // Should return 2 withdrawals (Oct 10 and Oct 15)
        $withdrawals = $response->viewData('withdrawals');
        $this->assertEquals(2, $withdrawals->total());
        
        // Verify dates are within range
        foreach ($withdrawals as $withdrawal) {
            $date = $withdrawal->taken_at->toDateString();
            $this->assertGreaterThanOrEqual('2026-10-10', $date);
            $this->assertLessThanOrEqual('2026-10-15', $date);
        }
    }

    /** @test */
    public function date_range_filter_single_day()
    {
        // Create withdrawals on same day at different times
        $morning = Carbon::create(2026, 10, 10, 8, 0, 0);
        $afternoon = Carbon::create(2026, 10, 10, 14, 0, 0);
        $evening = Carbon::create(2026, 10, 10, 20, 0, 0);
        
        $otherDay = Carbon::create(2026, 10, 11, 10, 0, 0);

        foreach ([$morning, $afternoon, $evening, $otherDay] as $date) {
            Withdrawal::create([
                'taken_by' => 'Test User',
                'rusun_id' => $this->rusun->id,
                'total_quantity' => 10,
                'total_value' => 500000,
                'taken_at' => $date,
            ]);
        }

        // Filter: single day 2026-10-10
        $response = $this->get(route('inventory.withdrawals.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
        ]));

        $response->assertStatus(200);
        
        $withdrawals = $response->viewData('withdrawals');
        $this->assertEquals(3, $withdrawals->total(), 'Should return all 3 withdrawals from Oct 10');
    }

    /** @test */
    public function future_date_validation_rejects_future_dates()
    {
        $futureDate = Carbon::now()->addDays(5);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $futureDate->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('taken_at');
        $this->assertEquals(0, Withdrawal::count(), 'No withdrawal should be created');
    }

    /** @test */
    public function current_datetime_is_valid()
    {
        $now = Carbon::now();
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $now->toDateTimeString(),
        ]);

        $response->assertRedirect(route('inventory.withdrawals.index'));
        $this->assertEquals(1, Withdrawal::count());
    }

    /** @test */
    public function date_range_filter_handles_invalid_format()
    {
        Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 10,
            'total_value' => 500000,
            'taken_at' => Carbon::now(),
        ]);

        // Invalid date format
        $response = $this->get(route('inventory.withdrawals.index', [
            'start_date' => '10/09/2026', // Wrong format
            'end_date' => '2026-10-15',
        ]));

        $response->assertStatus(200);
        
        // Should show warning and ignore filter
        $filterWarning = $response->viewData('filterWarning');
        $this->assertNotNull($filterWarning);
        $this->assertStringContainsString('tidak valid', $filterWarning);
    }

    /** @test */
    public function date_range_filter_handles_end_before_start()
    {
        Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 10,
            'total_value' => 500000,
            'taken_at' => Carbon::now(),
        ]);

        // End date before start date
        $response = $this->get(route('inventory.withdrawals.index', [
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-10',
        ]));

        $response->assertStatus(200);
        
        // Should show warning
        $filterWarning = $response->viewData('filterWarning');
        $this->assertNotNull($filterWarning);
        $this->assertStringContainsString('lebih kecil', $filterWarning);
    }

    /** @test */
    public function date_stored_and_retrieved_consistently()
    {
        $testDate = Carbon::create(2026, 3, 15, 13, 45, 30);
        
        Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 10,
            'total_value' => 500000,
            'taken_at' => $testDate,
        ]);

        $withdrawal = Withdrawal::first();
        
        // Verify exact datetime is preserved
        $this->assertEquals('2026-03-15 13:45:30', $withdrawal->taken_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function date_comparison_for_ordering()
    {
        // Create withdrawals in non-chronological order
        $dates = [
            Carbon::create(2026, 10, 15, 10, 0, 0),
            Carbon::create(2026, 10, 5, 10, 0, 0),
            Carbon::create(2026, 10, 20, 10, 0, 0),
            Carbon::create(2026, 10, 10, 10, 0, 0),
        ];

        foreach ($dates as $date) {
            Withdrawal::create([
                'taken_by' => 'Test User',
                'rusun_id' => $this->rusun->id,
                'total_quantity' => 10,
                'total_value' => 500000,
                'taken_at' => $date,
            ]);
        }

        // Query should order by taken_at DESC (newest first)
        $response = $this->get(route('inventory.withdrawals.index'));
        
        $withdrawals = $response->viewData('withdrawals');
        
        // First should be Oct 20, last should be Oct 5
        $this->assertEquals('2026-10-20', $withdrawals->first()->taken_at->toDateString());
        $this->assertEquals('2026-10-05', $withdrawals->last()->taken_at->toDateString());
    }

    /** @test */
    public function daylight_saving_time_transition_handled()
    {
        // Note: Indonesia (WIB) doesn't use DST, but test with timezone that does
        config(['app.timezone' => 'America/New_York']);
        
        // DST transition in 2026: March 8, 2:00 AM -> 3:00 AM
        // Create withdrawal at 1:30 AM (before DST)
        $beforeDST = Carbon::create(2026, 3, 8, 1, 30, 0, 'America/New_York');
        
        Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 10,
            'total_value' => 500000,
            'taken_at' => $beforeDST,
        ]);

        $withdrawal = Withdrawal::first();
        
        // Should be stored consistently
        $this->assertEquals('2026-03-08', $withdrawal->taken_at->toDateString());
        $this->assertEquals('01:30:00', $withdrawal->taken_at->toTimeString());
    }

    /** @test */
    public function date_filter_with_time_component_ignored()
    {
        // Create withdrawal at specific time
        $withdrawal = Withdrawal::create([
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'total_quantity' => 10,
            'total_value' => 500000,
            'taken_at' => Carbon::create(2026, 10, 10, 15, 30, 0),
        ]);

        // Filter by date only (should use whereDate, not where)
        $response = $this->get(route('inventory.withdrawals.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
        ]));

        $withdrawals = $response->viewData('withdrawals');
        
        // Should find the withdrawal regardless of time component
        $this->assertEquals(1, $withdrawals->total());
    }

    /** @test */
    public function old_date_historical_data_support()
    {
        // Test with date from several years ago
        $oldDate = Carbon::create(2020, 5, 15, 10, 0, 0);
        
        $response = $this->post(route('inventory.withdrawals.store'), [
            'items' => [
                ['item_id' => $this->item->id, 'quantity' => 10],
            ],
            'taken_by' => 'Test User',
            'rusun_id' => $this->rusun->id,
            'taken_at' => $oldDate->toDateTimeString(),
        ]);

        $withdrawal = Withdrawal::first();
        $this->assertEquals('2020-05-15', $withdrawal->taken_at->toDateString());
        
        // Should be accepted (it's in the past)
        $this->assertEquals(1, Withdrawal::count());
    }
}
