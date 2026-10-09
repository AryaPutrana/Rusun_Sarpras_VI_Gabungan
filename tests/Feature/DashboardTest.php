<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\Item;
use App\Models\MonitoringLocation;
use App\Models\Rusun;
use App\Models\User;
use App\Models\WaterMonitoring;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $petugas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
    }

    /** @test */
    public function dashboard_displays_monitoring_stats_correctly()
    {
        $location1 = MonitoringLocation::factory()->create(['status' => 'aktif']);
        $location2 = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitorings with different kondisi
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location1->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
            'sesi' => 'pagi',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location2->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
            'sesi' => 'siang',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location1->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'tekanan_air_kecil',
            'sesi' => 'sore',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('total', 3);
        $response->assertViewHas('rangeCount', 3);
        $response->assertViewHas('activeLocationsCount', 2);
        $response->assertViewHas('warningLocations');

        // Check kondisi counts
        $rangeCounts = $response->viewData('rangeCounts');
        $this->assertEquals(1, $rangeCounts['normal'] ?? 0);
        $this->assertEquals(1, $rangeCounts['debit_turun'] ?? 0);
        $this->assertEquals(1, $rangeCounts['tekanan_air_kecil'] ?? 0);

        // Check warning locations (non-normal kondisi)
        $warningLocations = $response->viewData('warningLocations');
        $this->assertCount(2, $warningLocations);
    }

    /** @test */
    public function dashboard_filters_by_preset_date_ranges()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitoring for today
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Create monitoring for 5 days ago
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->subDays(5)->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        // Create monitoring for 10 days ago (outside 7 days)
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->subDays(10)->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Test "hari_ini" preset
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));
        $response->assertOk();
        $response->assertViewHas('rangeCount', 1);
        $response->assertViewHas('rangeLabel', 'Hari Ini');

        // Test "7hari" preset
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => '7hari']));
        $response->assertOk();
        $response->assertViewHas('rangeCount', 2);
        $response->assertViewHas('rangeLabel', '7 Hari Terakhir');

        // Test "bulan_ini" preset
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'bulan_ini']));
        $response->assertOk();
        $response->assertViewHas('rangeCount', 2);
        $response->assertViewHas('rangeLabel', 'Bulan Ini');
    }

    /** @test */
    public function dashboard_filters_by_custom_date_range()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitorings on specific dates
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-10-05',
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-10-07',
            'kondisi' => 'debit_turun',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-10-10',
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', [
            'dari' => '2026-10-05',
            'sampai' => '2026-10-07',
        ]));

        $response->assertOk();
        $response->assertViewHas('rangeCount', 2);
        $response->assertViewHas('dateDari', '2026-10-05');
        $response->assertViewHas('dateSampai', '2026-10-07');
    }

    /** @test */
    public function dashboard_calculates_sesi_coverage_correctly()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // 1 location × 1 day × 3 sesi = 3 possible sessions
        // We'll create 2 sessions, so coverage should be 66-67%
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'sesi' => 'siang',
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));

        $response->assertOk();
        $sesiCoverage = $response->viewData('sesiCoverage');
        $this->assertGreaterThanOrEqual(66, $sesiCoverage);
        $this->assertLessThanOrEqual(67, $sesiCoverage);
    }

    /** @test */
    public function dashboard_calculates_normal_percent_correctly()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create 3 normal and 1 non-normal (75% normal)
        WaterMonitoring::factory()->count(3)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));

        $response->assertOk();
        $normalPercent = $response->viewData('normalPercent');
        $this->assertEquals(75, $normalPercent);
    }

    /** @test */
    public function dashboard_shows_latest_status_per_location_using_row_number()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create older monitoring
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->subDays(2)->toDateString(),
            'waktu' => '10:00:00',
            'kondisi' => 'debit_turun',
        ]);

        // Create latest monitoring
        $latest = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'waktu' => '14:00:00',
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $latestByLocation = $response->viewData('latestByLocation');

        $this->assertTrue($latestByLocation->has($location->id));
        $this->assertEquals($latest->id, $latestByLocation->get($location->id)->id);
        $this->assertEquals('normal', $latestByLocation->get($location->id)->kondisi);
    }

    /** @test */
    public function dashboard_displays_finance_summary_for_current_month()
    {
        $financeLocation = FinanceLocation::factory()->create(['status' => 'aktif']);

        // Create finance reports for current month
        FinanceReport::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => now()->startOfMonth()->addDays(5)->toDateString(),
            'kondisi' => 'normal',
        ]);

        FinanceReport::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => now()->startOfMonth()->addDays(10)->toDateString(),
            'kondisi' => 'debit_air_kurang',
        ]);

        // Create finance report for previous month (should not be counted)
        FinanceReport::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => now()->subMonth()->toDateString(),
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('financeTotal', 2);
        $response->assertViewHas('financeBulan', now()->format('Y-m'));

        $financeCounts = $response->viewData('financeCounts');
        $this->assertEquals(1, $financeCounts['normal']);
        $this->assertEquals(1, $financeCounts['debit_air_kurang']);
    }

    /** @test */
    public function dashboard_finance_section_updates_via_ajax()
    {
        $financeLocation = FinanceLocation::factory()->create(['status' => 'aktif']);

        // Create finance report for specific month
        FinanceReport::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => '2026-09-15',
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('dashboard.finance-section', ['bulan' => '2026-09'])
        );

        $response->assertOk();
        $response->assertViewIs('partials.finance_section');
        $response->assertViewHas('financeTotal', 1);
        $response->assertViewHas('financeBulan', '2026-09');
    }

    /** @test */
    public function dashboard_displays_inventory_overview()
    {
        $rusun = Rusun::factory()->create();

        // Create items
        Item::factory()->count(5)->create();

        // Create withdrawals
        $withdrawal1 = Withdrawal::factory()->create([
            'rusun_id' => $rusun->id,
            'taken_at' => now(),
            'total_quantity' => 10,
            'total_value' => 50000,
        ]);

        $withdrawal2 = Withdrawal::factory()->create([
            'rusun_id' => $rusun->id,
            'taken_at' => now()->subMonth(),
            'total_quantity' => 5,
            'total_value' => 25000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalItems', 5);
        $response->assertViewHas('totalWithdrawals', 2);
        $response->assertViewHas('withdrawalsThisMonth', 1);
        $response->assertViewHas('totalValue', 75000);
    }

    /** @test */
    public function dashboard_shows_low_stock_alerts()
    {
        // Create items with low stock
        Item::factory()->create([
            'item_code' => 'ITM001',
            'name' => 'Low Stock Item 1',
            'stock' => 5,
            'min_stock' => 10,
        ]);

        Item::factory()->create([
            'item_code' => 'ITM002',
            'name' => 'Low Stock Item 2',
            'stock' => 3,
            'min_stock' => 5,
        ]);

        // Create item with good stock
        Item::factory()->create([
            'item_code' => 'ITM003',
            'name' => 'Good Stock Item',
            'stock' => 100,
            'min_stock' => 10,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $lowStockItems = $response->viewData('lowStockItems');

        $this->assertCount(2, $lowStockItems);
        $this->assertEquals('ITM002', $lowStockItems[0]->item_code); // Ordered by stock asc
        $this->assertEquals('ITM001', $lowStockItems[1]->item_code);
    }

    /** @test */
    public function dashboard_shows_recent_withdrawals()
    {
        $rusun = Rusun::factory()->create();
        $item = Item::factory()->create();

        // Create withdrawals
        $withdrawal1 = Withdrawal::factory()->create([
            'rusun_id' => $rusun->id,
            'taken_at' => now(),
            'taken_by' => 'John Doe',
        ]);

        $withdrawal2 = Withdrawal::factory()->create([
            'rusun_id' => $rusun->id,
            'taken_at' => now()->subDays(1),
            'taken_by' => 'Jane Smith',
        ]);

        // Add items to withdrawals
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal1->id,
            'item_id' => $item->id,
        ]);

        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal2->id,
            'item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $recentWithdrawals = $response->viewData('recentWithdrawals');

        $this->assertCount(2, $recentWithdrawals);
        $this->assertEquals('John Doe', $recentWithdrawals[0]->taken_by); // Most recent first
        $this->assertEquals('Jane Smith', $recentWithdrawals[1]->taken_by);
    }

    /** @test */
    public function dashboard_chart_data_is_accurate()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitorings with specific distribution
        WaterMonitoring::factory()->count(5)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->count(2)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        WaterMonitoring::factory()->count(3)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'tekanan_air_kecil',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));

        $response->assertOk();

        $chartLabels = $response->viewData('chartLabels');
        $chartData = $response->viewData('chartData');

        $this->assertEquals(['Normal', 'Debit Turun', 'Tekanan Air Kecil'], $chartLabels);
        $this->assertEquals([5, 2, 3], $chartData);
    }

    /** @test */
    public function dashboard_stacked_chart_per_location_is_accurate()
    {
        $location1 = MonitoringLocation::factory()->create(['status' => 'aktif', 'nama_lokasi' => 'Location A']);
        $location2 = MonitoringLocation::factory()->create(['status' => 'aktif', 'nama_lokasi' => 'Location B']);

        // Location 1: 3 normal, 1 debit_turun
        WaterMonitoring::factory()->count(3)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location1->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location1->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        // Location 2: 1 normal, 2 tekanan_air_kecil
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location2->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->count(2)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location2->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'tekanan_air_kecil',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));

        $response->assertOk();

        $stackLabels = $response->viewData('stackLabels');
        $stackNormal = $response->viewData('stackNormal');
        $stackDebit = $response->viewData('stackDebit');
        $stackTekanan = $response->viewData('stackTekanan');

        $this->assertEquals(['Location A', 'Location B'], $stackLabels);
        $this->assertEquals([3, 1], $stackNormal);
        $this->assertEquals([1, 0], $stackDebit);
        $this->assertEquals([0, 2], $stackTekanan);
    }

    /** @test */
    public function petugas_only_sees_their_own_data()
    {
        $otherPetugas = User::factory()->create(['role' => 'petugas']);
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitoring by logged in petugas
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Create monitoring by other petugas
        WaterMonitoring::factory()->create([
            'user_id' => $otherPetugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        $response = $this->actingAs($this->petugas)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('total', 1); // Only their own data
        $response->assertViewHas('rangeCount', 1);
    }

    /** @test */
    public function admin_sees_all_data()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create monitoring by petugas
        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Create monitoring by another petugas
        $otherPetugas = User::factory()->create(['role' => 'petugas']);
        WaterMonitoring::factory()->create([
            'user_id' => $otherPetugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'debit_turun',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('total', 2); // All data
        $response->assertViewHas('rangeCount', 2);
    }

    /** @test */
    public function dashboard_handles_empty_data_gracefully()
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('total', 0);
        $response->assertViewHas('rangeCount', 0);
        $response->assertViewHas('sesiCoverage', 0);
        $response->assertViewHas('normalPercent', 0);
        $response->assertSee('Belum ada data pemeriksaan');
    }

    /** @test */
    public function dashboard_validates_date_range_correctly()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Test invalid date format - should fallback to today
        $response = $this->actingAs($this->admin)->get(route('dashboard', [
            'dari' => 'invalid-date',
            'sampai' => '2026-10-09',
        ]));

        $response->assertOk();
        $response->assertViewHas('dateDari', now()->toDateString());
        $response->assertViewHas('dateSampai', now()->toDateString());

        // Test sampai before dari - should be corrected
        $response = $this->actingAs($this->admin)->get(route('dashboard', [
            'dari' => '2026-10-10',
            'sampai' => '2026-10-05',
        ]));

        $response->assertOk();
        // Should fallback to today when invalid range
        $response->assertViewHas('dateDari', now()->toDateString());
    }

    /** @test */
    public function dashboard_prevents_future_dates()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        // Try to query future dates
        $futureDate = now()->addDays(5)->toDateString();
        $response = $this->actingAs($this->admin)->get(route('dashboard', [
            'dari' => now()->toDateString(),
            'sampai' => $futureDate,
        ]));

        $response->assertOk();
        // sampai should be capped at today
        $response->assertViewHas('dateSampai', now()->toDateString());
    }

    /** @test */
    public function dashboard_shows_sesi_compliance_per_location()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif', 'nama_lokasi' => 'Test Location']);

        // Create 2 pagi, 1 siang, 0 sore for today
        WaterMonitoring::factory()->count(2)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
        ]);

        WaterMonitoring::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'sesi' => 'siang',
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['preset' => 'hari_ini']));

        $response->assertOk();
        $sesiPerLokasi = $response->viewData('sesiPerLokasi');

        $this->assertTrue($sesiPerLokasi->has($location->id));
        $sesiData = $sesiPerLokasi->get($location->id);

        $this->assertEquals(2, $sesiData->get('pagi', 0));
        $this->assertEquals(1, $sesiData->get('siang', 0));
        $this->assertEquals(0, $sesiData->get('sore', 0));
    }

    /** @test */
    public function dashboard_requires_authentication()
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function finance_section_ajax_requires_authentication()
    {
        $response = $this->get(route('dashboard.finance-section'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function dashboard_displays_recent_monitoring_history()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        // Create 15 monitorings, should only show 10 most recent
        WaterMonitoring::factory()->count(15)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->toDateString(),
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $recent = $response->viewData('recent');

        $this->assertCount(10, $recent); // Should limit to 10
    }

    /** @test */
    public function dashboard_finance_month_filter_works_correctly()
    {
        $financeLocation = FinanceLocation::factory()->create(['status' => 'aktif']);

        // September data
        FinanceReport::factory()->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => '2026-09-15',
            'kondisi' => 'normal',
        ]);

        // October data
        FinanceReport::factory()->count(2)->create([
            'user_id' => $this->petugas->id,
            'location_id' => $financeLocation->id,
            'tanggal' => '2026-10-05',
            'kondisi' => 'debit_air_kurang',
        ]);

        // Query September
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['bulan' => '2026-09']));

        $response->assertOk();
        $response->assertViewHas('financeTotal', 1);
        $response->assertViewHas('financeBulan', '2026-09');

        // Query October
        $response = $this->actingAs($this->admin)->get(route('dashboard', ['bulan' => '2026-10']));

        $response->assertOk();
        $response->assertViewHas('financeTotal', 2);
        $response->assertViewHas('financeBulan', '2026-10');
    }

    /** @test */
    public function dashboard_low_stock_items_limit_to_five()
    {
        // Create 10 low stock items
        for ($i = 1; $i <= 10; $i++) {
            Item::factory()->create([
                'item_code' => "ITM00{$i}",
                'name' => "Low Stock Item {$i}",
                'stock' => $i,
                'min_stock' => 20,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $lowStockItems = $response->viewData('lowStockItems');

        $this->assertCount(5, $lowStockItems); // Should limit to 5
        $this->assertEquals('ITM001', $lowStockItems[0]->item_code); // Lowest stock first
    }
}
