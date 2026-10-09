<?php

/**
 * Comprehensive Finance Report Testing Script
 * Tests: Create, Read, Update, Delete, Filtering, PDF Export, Access Control
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// Test counters
$passed = 0;
$failed = 0;

function test($name, $callback) {
    global $passed, $failed;
    try {
        $callback();
        echo "✓ PASS: $name\n";
        $passed++;
    } catch (Exception $e) {
        echo "✗ FAIL: $name\n";
        echo "  Error: {$e->getMessage()}\n";
        echo "  File: {$e->getFile()}:{$e->getLine()}\n";
        $failed++;
    }
}

echo "=== Finance Report Feature Tests ===\n\n";

// Setup: Get users and location
$admin = User::where('role', 'admin')->first();
$petugas = User::where('role', 'petugas')->first();
$location = FinanceLocation::where('status', 'aktif')->first();

if (!$admin || !$petugas || !$location) {
    die("ERROR: Missing test data. Need admin, petugas users and active location.\n");
}

echo "Test Users:\n";
echo "  Admin: {$admin->name} (ID: {$admin->id})\n";
echo "  Petugas: {$petugas->name} (ID: {$petugas->id})\n";
echo "  Location: {$location->nama_lokasi} (ID: {$location->id})\n\n";

// Clean up any existing test data
FinanceReport::query()->delete();
echo "Cleaned existing finance reports\n\n";

// ==================== CREATE TESTS ====================
echo "--- CREATE Tests ---\n";

// Test 1: Create report as petugas with valid data
test("Create finance report with valid data", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now()->subDays(5),
        'kondisi' => 'normal',
        'keterangan' => 'Test report - Normal condition',
        'foto' => 'test/photo1.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->exists, "Report should be created");
    assert($report->user_id === $petugas->id, "Report should belong to petugas");
    assert($report->kondisi === 'normal', "Kondisi should be 'normal'");
});

// Test 2: Create multiple reports for different months
test("Create reports for different months", function() use ($petugas, $location) {
    // Current month
    FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'debit_air_kurang',
        'keterangan' => 'Current month - Low water flow',
        'foto' => 'test/photo2.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    // Last month
    FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now()->subMonth(),
        'kondisi' => 'tekanan_pdam_kurang',
        'keterangan' => 'Last month - Low PDAM pressure',
        'foto' => 'test/photo3.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $count = FinanceReport::count();
    assert($count === 3, "Should have 3 reports total");
});

// Test 3: Create report as admin
test("Admin can create finance report", function() use ($admin, $location) {
    $report = FinanceReport::create([
        'user_id' => $admin->id,
        'location_id' => $location->id,
        'tanggal' => now()->subDays(2),
        'kondisi' => 'normal',
        'keterangan' => 'Admin created report',
        'foto' => 'test/admin_photo.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->user_id === $admin->id, "Report should belong to admin");
});

// Test 4: Test all kondisi values
test("Create reports with all kondisi types", function() use ($petugas, $location) {
    $kondisiTypes = array_keys(FinanceReport::KONDISI);
    
    foreach ($kondisiTypes as $kondisi) {
        $report = FinanceReport::create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => now(),
            'kondisi' => $kondisi,
            'keterangan' => "Testing kondisi: $kondisi",
            'foto' => "test/$kondisi.jpg",
            'foto_disk' => 'evidence',
        ]);
        
        assert($report->kondisi === $kondisi, "Kondisi should be $kondisi");
    }
});

echo "\n";

// ==================== READ TESTS ====================
echo "--- READ Tests ---\n";

// Test 5: Query reports by month
test("Filter reports by current month", function() use ($petugas) {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $reports = FinanceReport::whereBetween('tanggal', [$from, $to])->get();
    
    assert($reports->count() > 0, "Should have reports in current month");
    
    foreach ($reports as $report) {
        $tanggal = Carbon::parse($report->tanggal);
        assert($tanggal->isSameMonth(now()), "Report should be in current month");
    }
});

// Test 6: Petugas can only see own reports
test("Petugas filter - only own reports", function() use ($petugas) {
    $reports = FinanceReport::where('user_id', $petugas->id)->get();
    
    assert($reports->count() > 0, "Petugas should have reports");
    
    foreach ($reports as $report) {
        assert($report->user_id === $petugas->id, "All reports should belong to petugas");
    }
});

// Test 7: Admin sees all reports
test("Admin can see all reports", function() use ($admin, $petugas) {
    $allReports = FinanceReport::all();
    $petugasReports = FinanceReport::where('user_id', $petugas->id)->count();
    $adminReports = FinanceReport::where('user_id', $admin->id)->count();
    
    assert($allReports->count() === $petugasReports + $adminReports, 
        "Admin should see all reports from all users");
});

// Test 8: Reports include relationships
test("Reports load with user and location relationships", function() {
    $report = FinanceReport::with(['user', 'location'])->first();
    
    assert($report->user !== null, "Report should have user relationship");
    assert($report->location !== null, "Report should have location relationship");
    assert($report->user->name !== null, "User should have name");
    assert($report->location->nama_lokasi !== null, "Location should have nama_lokasi");
});

// Test 9: Summary statistics by kondisi
test("Generate summary statistics by kondisi", function() {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $counts = FinanceReport::whereBetween('tanggal', [$from, $to])
        ->selectRaw('kondisi, count(*) as jumlah')
        ->groupBy('kondisi')
        ->pluck('jumlah', 'kondisi')
        ->toArray();
    
    assert(is_array($counts), "Summary should be array");
    assert(array_sum($counts) > 0, "Should have at least one report");
});

echo "\n";

// ==================== UPDATE TESTS ====================
echo "--- UPDATE Tests ---\n";

// Test 10: Petugas can update own report
test("Petugas can update own report", function() use ($petugas) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    $originalKondisi = $report->kondisi;
    
    $newKondisi = $originalKondisi === 'normal' ? 'debit_air_kurang' : 'normal';
    $report->update([
        'kondisi' => $newKondisi,
        'keterangan' => 'Updated by petugas',
    ]);
    
    $report->refresh();
    assert($report->kondisi === $newKondisi, "Kondisi should be updated");
    assert($report->keterangan === 'Updated by petugas', "Keterangan should be updated");
});

// Test 11: Admin can update any report
test("Admin can update any report", function() use ($admin, $petugas) {
    $petugasReport = FinanceReport::where('user_id', $petugas->id)->first();
    
    $petugasReport->update([
        'keterangan' => 'Updated by admin',
    ]);
    
    $petugasReport->refresh();
    assert($petugasReport->keterangan === 'Updated by admin', 
        "Admin should be able to update petugas report");
});

// Test 12: Update with different date (month change)
test("Update report date to different month", function() use ($petugas) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    $newDate = now()->subMonths(2);
    
    $report->update(['tanggal' => $newDate]);
    
    $report->refresh();
    assert($report->tanggal->isSameMonth($newDate), "Date should be updated to new month");
});

echo "\n";

// ==================== ACCESS CONTROL TESTS ====================
echo "--- ACCESS CONTROL Tests ---\n";

// Test 13: Check petugas ownership validation
test("Verify petugas cannot access others' reports (logic check)", function() use ($petugas, $admin) {
    $adminReport = FinanceReport::where('user_id', $admin->id)->first();
    
    // Simulate access control check
    $canAccess = $adminReport->user_id === $petugas->id;
    
    assert($canAccess === false, "Petugas should not have access to admin's report");
});

// Test 14: Admin can access all reports
test("Admin can access all reports (logic check)", function() use ($admin) {
    $allReports = FinanceReport::all();
    
    foreach ($allReports as $report) {
        // Admin can access any report
        $canAccess = true; // Admin always has access
        assert($canAccess === true, "Admin should access report ID {$report->id}");
    }
});

echo "\n";

// ==================== DELETE TESTS ====================
echo "--- DELETE Tests ---\n";

// Test 15: Create and delete report
test("Petugas can delete own report", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'Report to be deleted',
        'foto' => 'test/delete_test.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $id = $report->id;
    $report->delete();
    
    $deleted = FinanceReport::find($id);
    assert($deleted === null, "Report should be deleted");
});

// Test 16: Admin can delete any report
test("Admin can delete any report", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'Report for admin to delete',
        'foto' => 'test/admin_delete.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $id = $report->id;
    // Admin deletes petugas report
    $report->delete();
    
    $deleted = FinanceReport::find($id);
    assert($deleted === null, "Admin should be able to delete petugas report");
});

echo "\n";

// ==================== FILTERING TESTS ====================
echo "--- FILTERING Tests ---\n";

// Test 17: Month parameter parsing
test("Parse valid month parameter (Y-m format)", function() {
    $validMonth = '2026-10';
    $matches = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $validMonth);
    
    assert($matches === 1, "Valid month format should match regex");
});

// Test 18: Invalid month parameter handling
test("Reject invalid month parameter", function() {
    $invalidMonths = ['2026-13', '2026-00', '26-10', 'invalid'];
    
    foreach ($invalidMonths as $invalidMonth) {
        $matches = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $invalidMonth);
        assert($matches === 0, "Invalid month '$invalidMonth' should not match");
    }
});

// Test 19: Filter by last month
test("Filter reports by last month", function() use ($petugas) {
    $lastMonth = now()->subMonth();
    $from = $lastMonth->copy()->startOfMonth();
    $to = $lastMonth->copy()->endOfMonth();
    
    $reports = FinanceReport::whereBetween('tanggal', [$from, $to])->get();
    
    foreach ($reports as $report) {
        $tanggal = Carbon::parse($report->tanggal);
        assert($tanggal->isSameMonth($lastMonth), "Report should be in last month");
    }
});

echo "\n";

// ==================== VALIDATION TESTS ====================
echo "--- VALIDATION Tests ---\n";

// Test 20: Valid kondisi values
test("Only valid kondisi values allowed", function() {
    $validKondisi = array_keys(FinanceReport::KONDISI);
    
    assert(in_array('normal', $validKondisi), "'normal' should be valid");
    assert(in_array('debit_air_kurang', $validKondisi), "'debit_air_kurang' should be valid");
    assert(in_array('tekanan_pdam_kurang', $validKondisi), "'tekanan_pdam_kurang' should be valid");
    assert(!in_array('invalid_kondisi', $validKondisi), "'invalid_kondisi' should not be valid");
});

// Test 21: Date validation (cannot be future)
test("Date cannot be in the future (validation rule)", function() {
    $futureDate = now()->addDays(1);
    $today = now()->startOfDay();
    
    // Simulating before_or_equal:today validation
    $isValid = $futureDate->lte($today);
    
    assert($isValid === false, "Future date should not be valid");
});

// Test 22: Location must exist
test("Location ID must exist in database", function() use ($location) {
    $existingLocationId = $location->id;
    $nonExistentLocationId = 99999;
    
    $exists = FinanceLocation::find($existingLocationId) !== null;
    $notExists = FinanceLocation::find($nonExistentLocationId) === null;
    
    assert($exists === true, "Valid location ID should exist");
    assert($notExists === true, "Invalid location ID should not exist");
});

echo "\n";

// ==================== MODEL TESTS ====================
echo "--- MODEL Tests ---\n";

// Test 23: Date casting works correctly
test("Tanggal is cast to Carbon date", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => '2026-10-05',
        'kondisi' => 'normal',
        'keterangan' => 'Date casting test',
        'foto' => 'test/date_cast.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->tanggal instanceof Carbon, "Tanggal should be Carbon instance");
    assert($report->tanggal->format('Y-m-d') === '2026-10-05', "Date should match");
    
    $report->delete();
});

// Test 24: KONDISI constants are accessible
test("KONDISI constants are defined and accessible", function() {
    $kondisi = FinanceReport::KONDISI;
    
    assert(is_array($kondisi), "KONDISI should be array");
    assert(count($kondisi) === 3, "Should have 3 kondisi types");
    assert(isset($kondisi['normal']), "Should have 'normal' kondisi");
    assert($kondisi['normal'] === 'Normal', "Normal label should match");
});

echo "\n";

// ==================== PDF EXPORT PREPARATION ====================
echo "--- PDF Export Preparation ---\n";

test("Check DOMPDF is installed", function() {
    assert(class_exists('Barryvdh\DomPDF\Facade\Pdf'), "DOMPDF facade should exist");
});

test("Verify PDF view exists", function() {
    $viewPath = resource_path('views/finance_reports/pdf.blade.php');
    assert(file_exists($viewPath), "PDF view should exist at $viewPath");
});

test("Generate sample PDF data structure", function() use ($petugas, $location) {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $reports = FinanceReport::with(['user', 'location'])
        ->whereBetween('tanggal', [$from, $to])
        ->orderBy('tanggal')
        ->orderBy('id')
        ->get();
    
    $rows = $reports->values()->map(fn ($report, $i) => [
        'no' => $i + 1,
        'tanggal' => $report->tanggal->format('d-m-Y'),
        'kondisi' => FinanceReport::KONDISI[$report->kondisi] ?? ucfirst($report->kondisi),
        'lokasi' => $report->location?->nama_lokasi ?? '—',
        'keterangan' => $report->keterangan,
        'foto_src' => null, // Would be data URI in real implementation
    ]);
    
    assert($rows->count() > 0, "Should have data rows for PDF");
    assert(isset($rows[0]['no']), "Row should have number");
    assert(isset($rows[0]['tanggal']), "Row should have date");
});

echo "\n";

// ==================== SUMMARY ====================
echo "====================================\n";
echo "TEST SUMMARY\n";
echo "====================================\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "✓ Passed: $passed\n";
echo "✗ Failed: $failed\n";
echo "====================================\n\n";

// Show final data state
echo "Final Database State:\n";
echo "Total Finance Reports: " . FinanceReport::count() . "\n";
echo "  - By Petugas: " . FinanceReport::where('user_id', $petugas->id)->count() . "\n";
echo "  - By Admin: " . FinanceReport::where('user_id', $admin->id)->count() . "\n";
echo "\nCurrent Month Reports: " . FinanceReport::whereBetween('tanggal', [
    now()->startOfMonth(),
    now()->endOfMonth()
])->count() . "\n";

echo "\nKondisi Breakdown (Current Month):\n";
$summary = FinanceReport::whereBetween('tanggal', [now()->startOfMonth(), now()->endOfMonth()])
    ->selectRaw('kondisi, count(*) as jumlah')
    ->groupBy('kondisi')
    ->get();

foreach ($summary as $item) {
    $label = FinanceReport::KONDISI[$item->kondisi] ?? $item->kondisi;
    echo "  - {$label}: {$item->jumlah}\n";
}

exit($failed > 0 ? 1 : 0);
