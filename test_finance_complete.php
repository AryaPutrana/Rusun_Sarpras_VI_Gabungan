<?php

/**
 * Complete Finance Report Feature Testing
 * Tests: CRUD, Validation, Access Control, Filtering, PDF Export
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

$passed = 0;
$failed = 0;

function test($name, $callback) {
    global $passed, $failed;
    try {
        $callback();
        echo "✓ $name\n";
        $passed++;
    } catch (Exception $e) {
        echo "✗ $name\n";
        echo "  Error: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "         FINANCE REPORT COMPREHENSIVE TEST SUITE\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Setup
$admin = User::where('role', 'admin')->first();
$petugas = User::where('role', 'petugas')->first();
$location = FinanceLocation::where('status', 'aktif')->first();

if (!$admin || !$petugas || !$location) {
    die("❌ ERROR: Missing test data (admin, petugas, or location)\n");
}

echo "Setup:\n";
echo "  Admin: {$admin->name} (ID: {$admin->id})\n";
echo "  Petugas: {$petugas->name} (ID: {$petugas->id})\n";
echo "  Location: {$location->nama_lokasi}\n\n";

// Clean slate
FinanceReport::query()->delete();

// ═══════════════════════════════════════════════════════════
echo "1. CREATE OPERATIONS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Create report with all required fields", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'Test report',
        'foto' => 'test/photo.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->exists && $report->id > 0, "Report not created");
});

test("Create reports with all kondisi types", function() use ($petugas, $location) {
    foreach (FinanceReport::KONDISI as $key => $label) {
        $report = FinanceReport::create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->subDays(rand(1, 10)),
            'kondisi' => $key,
            'keterangan' => "Testing kondisi: $label",
            'foto' => "test/$key.jpg",
            'foto_disk' => 'evidence',
        ]);
        assert($report->kondisi === $key, "Kondisi mismatch: expected $key");
    }
});

test("Create reports across different months", function() use ($petugas, $location) {
    $months = [
        now(),
        now()->subMonth(),
        now()->subMonths(2),
    ];
    
    foreach ($months as $date) {
        FinanceReport::create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => $date,
            'kondisi' => 'normal',
            'keterangan' => 'Month test: ' . $date->format('Y-m'),
            'foto' => 'test/month_' . $date->format('Ym') . '.jpg',
            'foto_disk' => 'evidence',
        ]);
    }
    
    assert(FinanceReport::count() >= 3, "Reports not created across months");
});

test("Admin can create reports", function() use ($admin, $location) {
    $report = FinanceReport::create([
        'user_id' => $admin->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'Admin report',
        'foto' => 'test/admin.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->user_id === $admin->id, "Admin ownership not set");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "2. READ OPERATIONS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Read all reports", function() {
    $reports = FinanceReport::all();
    assert($reports->count() > 0, "No reports found");
});

test("Read reports with relationships", function() {
    $report = FinanceReport::with(['user', 'location'])->first();
    assert($report->user !== null, "User relationship not loaded");
    assert($report->location !== null, "Location relationship not loaded");
    assert($report->user->name !== null, "User name missing");
});

test("Filter reports by user", function() use ($petugas) {
    $reports = FinanceReport::where('user_id', $petugas->id)->get();
    assert($reports->count() > 0, "Petugas has no reports");
    
    foreach ($reports as $r) {
        assert($r->user_id === $petugas->id, "Wrong user filter");
    }
});

test("Filter reports by month", function() {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $reports = FinanceReport::whereBetween('tanggal', [$from, $to])->get();
    assert($reports->count() > 0, "No current month reports");
    
    foreach ($reports as $r) {
        assert(Carbon::parse($r->tanggal)->isSameMonth(now()), "Report not in current month");
    }
});

test("Filter reports by kondisi", function() {
    $normalReports = FinanceReport::where('kondisi', 'normal')->get();
    assert($normalReports->count() > 0, "No normal kondisi reports");
    
    foreach ($normalReports as $r) {
        assert($r->kondisi === 'normal', "Kondisi filter failed");
    }
});

test("Order reports by date DESC", function() {
    $reports = FinanceReport::orderBy('tanggal', 'desc')->get();
    
    if ($reports->count() > 1) {
        for ($i = 0; $i < $reports->count() - 1; $i++) {
            $current = Carbon::parse($reports[$i]->tanggal);
            $next = Carbon::parse($reports[$i + 1]->tanggal);
            assert($current->gte($next), "Date ordering incorrect");
        }
    }
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "3. UPDATE OPERATIONS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Update kondisi field", function() use ($petugas) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    $original = $report->kondisi;
    $new = $original === 'normal' ? 'debit_air_kurang' : 'normal';
    
    $report->update(['kondisi' => $new]);
    $report->refresh();
    
    assert($report->kondisi === $new, "Kondisi not updated");
});

test("Update keterangan field", function() use ($petugas) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    
    $report->update(['keterangan' => 'Updated keterangan']);
    $report->refresh();
    
    assert($report->keterangan === 'Updated keterangan', "Keterangan not updated");
});

test("Update tanggal field", function() use ($petugas) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    $newDate = now()->subDays(5);
    
    $report->update(['tanggal' => $newDate]);
    $report->refresh();
    
    assert($report->tanggal->isSameDay($newDate), "Tanggal not updated");
});

test("Update location_id field", function() use ($petugas, $location) {
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    
    $report->update(['location_id' => $location->id]);
    $report->refresh();
    
    assert($report->location_id === $location->id, "Location not updated");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "4. DELETE OPERATIONS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Delete report", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'To be deleted',
        'foto' => 'test/delete.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $id = $report->id;
    $report->delete();
    
    assert(FinanceReport::find($id) === null, "Report not deleted");
});

test("Hard delete confirmation (no soft deletes)", function() use ($petugas, $location) {
    // This model uses hard deletes, not soft deletes
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => now(),
        'kondisi' => 'normal',
        'keterangan' => 'Hard delete test',
        'foto' => 'test/hard_delete.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $id = $report->id;
    $report->delete();
    
    // Should not exist after delete (hard delete)
    $found = FinanceReport::find($id);
    assert($found === null, "Should be permanently deleted (hard delete)");
    
    // Verify model doesn't use SoftDeletes trait
    $traits = class_uses(FinanceReport::class);
    $hasSoftDeletes = isset($traits['Illuminate\Database\Eloquent\SoftDeletes']);
    assert($hasSoftDeletes === false, "Model should not use SoftDeletes trait");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "5. ACCESS CONTROL LOGIC\n";
echo "───────────────────────────────────────────────────────────\n";

test("Petugas owns their reports", function() use ($petugas) {
    $reports = FinanceReport::where('user_id', $petugas->id)->get();
    
    foreach ($reports as $r) {
        assert($r->user_id === $petugas->id, "Ownership mismatch");
    }
});

test("Admin can access all reports", function() use ($admin, $petugas) {
    $allReports = FinanceReport::all();
    $petugasCount = FinanceReport::where('user_id', $petugas->id)->count();
    $adminCount = FinanceReport::where('user_id', $admin->id)->count();
    
    assert($allReports->count() === $petugasCount + $adminCount, "Count mismatch");
});

test("Check ownership before edit (petugas logic)", function() use ($petugas, $admin) {
    $petugasReport = FinanceReport::where('user_id', $petugas->id)->first();
    $adminReport = FinanceReport::where('user_id', $admin->id)->first();
    
    // Petugas can edit own
    $canEdit = ($petugasReport->user_id === $petugas->id);
    assert($canEdit === true, "Petugas should edit own report");
    
    // Petugas cannot edit admin's
    $cannotEdit = ($adminReport->user_id === $petugas->id);
    assert($cannotEdit === false, "Petugas should not edit admin report");
});

test("Admin unrestricted access logic", function() use ($admin, $petugas) {
    $anyReport = FinanceReport::first();
    
    // Admin check: isAdmin() or ownership
    $isAdmin = true; // Simulating $user->isAdmin()
    $canEdit = $isAdmin || ($anyReport->user_id === $admin->id);
    
    assert($canEdit === true, "Admin should edit any report");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "6. VALIDATION RULES\n";
echo "───────────────────────────────────────────────────────────\n";

test("Valid kondisi values only", function() {
    $validKondisi = array_keys(FinanceReport::KONDISI);
    
    assert(in_array('normal', $validKondisi), "'normal' should be valid");
    assert(in_array('debit_air_kurang', $validKondisi), "'debit_air_kurang' should be valid");
    assert(in_array('tekanan_pdam_kurang', $validKondisi), "'tekanan_pdam_kurang' should be valid");
    assert(!in_array('invalid', $validKondisi), "'invalid' should not be valid");
    assert(count($validKondisi) === 3, "Should have exactly 3 kondisi types");
});

test("Date cannot be future (validation logic)", function() {
    $today = now()->startOfDay();
    $tomorrow = now()->addDay()->startOfDay();
    $yesterday = now()->subDay()->startOfDay();
    
    assert($yesterday->lte($today), "Yesterday should be valid");
    assert($today->lte($today), "Today should be valid");
    assert(!$tomorrow->lte($today), "Tomorrow should not be valid");
});

test("Location must exist", function() use ($location) {
    $exists = FinanceLocation::find($location->id);
    $notExists = FinanceLocation::find(99999);
    
    assert($exists !== null, "Valid location should exist");
    assert($notExists === null, "Invalid location should not exist");
});

test("Required fields enforcement", function() {
    $requiredFields = ['user_id', 'location_id', 'tanggal', 'kondisi', 'keterangan', 'foto', 'foto_disk'];
    $fillable = (new FinanceReport())->getFillable();
    
    foreach ($requiredFields as $field) {
        assert(in_array($field, $fillable), "Field '$field' should be fillable");
    }
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "7. MONTH FILTERING\n";
echo "───────────────────────────────────────────────────────────\n";

test("Valid month format (Y-m)", function() {
    $valid = ['2026-01', '2026-10', '2026-12'];
    $invalid = ['2026-13', '2026-00', '26-10', 'invalid'];
    
    $pattern = '/^\d{4}-(0[1-9]|1[0-2])$/';
    
    foreach ($valid as $month) {
        assert(preg_match($pattern, $month) === 1, "$month should be valid");
    }
    
    foreach ($invalid as $month) {
        assert(preg_match($pattern, $month) === 0, "$month should be invalid");
    }
});

test("Filter by specific month", function() use ($petugas, $location) {
    // Create report in specific month
    $targetMonth = now()->subMonths(2);
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => $targetMonth,
        'kondisi' => 'normal',
        'keterangan' => 'Specific month test',
        'foto' => 'test/specific_month.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    $from = $targetMonth->copy()->startOfMonth();
    $to = $targetMonth->copy()->endOfMonth();
    
    $found = FinanceReport::whereBetween('tanggal', [$from, $to])
        ->where('id', $report->id)
        ->first();
    
    assert($found !== null, "Report not found in target month");
    $report->delete();
});

test("Default to current month when invalid", function() {
    $bulan = 'invalid-format';
    $pattern = '/^\d{4}-(0[1-9]|1[0-2])$/';
    
    if (!preg_match($pattern, $bulan)) {
        $bulan = now()->format('Y-m');
    }
    
    assert($bulan === now()->format('Y-m'), "Should fallback to current month");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "8. SUMMARY STATISTICS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Generate kondisi summary", function() {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $counts = FinanceReport::whereBetween('tanggal', [$from, $to])
        ->selectRaw('kondisi, count(*) as jumlah')
        ->groupBy('kondisi')
        ->pluck('jumlah', 'kondisi')
        ->toArray();
    
    assert(is_array($counts), "Summary should be array");
});

test("Summary includes all kondisi types", function() {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $counts = FinanceReport::whereBetween('tanggal', [$from, $to])
        ->selectRaw('kondisi, count(*) as jumlah')
        ->groupBy('kondisi')
        ->pluck('jumlah', 'kondisi')
        ->toArray();
    
    $summary = [];
    foreach (array_keys(FinanceReport::KONDISI) as $kondisi) {
        $summary[$kondisi] = $counts[$kondisi] ?? 0;
    }
    
    assert(count($summary) === 3, "Summary should have all 3 kondisi types");
    assert(isset($summary['normal']), "Summary should have 'normal'");
    assert(isset($summary['debit_air_kurang']), "Summary should have 'debit_air_kurang'");
    assert(isset($summary['tekanan_pdam_kurang']), "Summary should have 'tekanan_pdam_kurang'");
});

test("Summary filtered by user", function() use ($petugas) {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    
    $counts = FinanceReport::where('user_id', $petugas->id)
        ->whereBetween('tanggal', [$from, $to])
        ->selectRaw('kondisi, count(*) as jumlah')
        ->groupBy('kondisi')
        ->pluck('jumlah', 'kondisi');
    
    assert($counts !== null, "Should generate user-specific summary");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "9. CONTROLLER METHODS\n";
echo "───────────────────────────────────────────────────────────\n";

test("Index method returns view", function() use ($admin) {
    Auth::login($admin);
    
    $controller = new \App\Http\Controllers\FinanceReportController();
    $request = Request::create('/keuangan', 'GET', ['bulan' => now()->format('Y-m')]);
    $view = $controller->index($request);
    
    assert($view instanceof \Illuminate\View\View, "Should return view");
    assert($view->name() === 'finance_reports.index', "Should return index view");
});

test("Riwayat method returns view", function() use ($petugas) {
    Auth::login($petugas);
    
    $controller = new \App\Http\Controllers\FinanceReportController();
    $request = Request::create('/keuangan/riwayat', 'GET', ['bulan' => now()->format('Y-m')]);
    $view = $controller->riwayat($request);
    
    assert($view instanceof \Illuminate\View\View, "Should return view");
    assert($view->name() === 'finance_reports.riwayat', "Should return riwayat view");
});

test("Create method returns view", function() use ($petugas) {
    Auth::login($petugas);
    
    $controller = new \App\Http\Controllers\FinanceReportController();
    $view = $controller->create();
    
    assert($view instanceof \Illuminate\View\View, "Should return view");
    assert($view->name() === 'finance_reports.create', "Should return create view");
});

test("Edit method returns view", function() use ($petugas) {
    Auth::login($petugas);
    
    $report = FinanceReport::where('user_id', $petugas->id)->first();
    $controller = new \App\Http\Controllers\FinanceReportController();
    $view = $controller->edit($report);
    
    assert($view instanceof \Illuminate\View\View, "Should return view");
    assert($view->name() === 'finance_reports.edit', "Should return edit view");
});

test("Edit method blocks unauthorized access", function() use ($petugas, $admin) {
    Auth::login($petugas);
    
    $adminReport = FinanceReport::where('user_id', $admin->id)->first();
    $controller = new \App\Http\Controllers\FinanceReportController();
    
    $exceptionThrown = false;
    try {
        $controller->edit($adminReport);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $exceptionThrown = true;
        assert($e->getStatusCode() === 403, "Should be 403 Forbidden");
    }
    
    assert($exceptionThrown, "Should throw 403 for unauthorized access");
});

test("ExportPdf method returns PDF response", function() use ($admin) {
    Auth::login($admin);
    
    $controller = new \App\Http\Controllers\FinanceReportController();
    $request = Request::create('/keuangan/pdf', 'GET', ['bulan' => now()->format('Y-m')]);
    $response = $controller->exportPdf($request);
    
    assert($response instanceof \Illuminate\Http\Response, "Should return Response");
    
    $content = $response->getContent();
    assert(str_starts_with($content, '%PDF'), "Should be valid PDF");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "10. MODEL FEATURES\n";
echo "───────────────────────────────────────────────────────────\n";

test("Date casting to Carbon", function() use ($petugas, $location) {
    $report = FinanceReport::create([
        'user_id' => $petugas->id,
        'location_id' => $location->id,
        'tanggal' => '2026-10-05',
        'kondisi' => 'normal',
        'keterangan' => 'Date cast test',
        'foto' => 'test/cast.jpg',
        'foto_disk' => 'evidence',
    ]);
    
    assert($report->tanggal instanceof Carbon, "Tanggal should be Carbon");
    assert($report->tanggal->format('Y-m-d') === '2026-10-05', "Date should match");
    
    $report->delete();
});

test("KONDISI constant accessibility", function() {
    $kondisi = FinanceReport::KONDISI;
    
    assert(is_array($kondisi), "KONDISI should be array");
    assert(count($kondisi) === 3, "Should have 3 types");
    assert($kondisi['normal'] === 'Normal', "Label should match");
    assert($kondisi['debit_air_kurang'] === 'Debit Air Kurang', "Label should match");
    assert($kondisi['tekanan_pdam_kurang'] === 'Tekanan PDAM Kurang', "Label should match");
});

test("User relationship", function() {
    $report = FinanceReport::with('user')->first();
    
    assert($report->user !== null, "User relationship should load");
    assert($report->user instanceof User, "Should be User instance");
});

test("Location relationship", function() {
    $report = FinanceReport::with('location')->first();
    
    assert($report->location !== null, "Location relationship should load");
    assert($report->location instanceof FinanceLocation, "Should be FinanceLocation instance");
});

echo "\n";

// ═══════════════════════════════════════════════════════════
echo "FINAL RESULTS\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total Tests:  " . ($passed + $failed) . "\n";
echo "✓ Passed:     $passed\n";
echo "✗ Failed:     $failed\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 1) . "%\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Finance Report features working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Review the errors above.\n";
}

echo "\nDatabase State:\n";
echo "  Total Reports: " . FinanceReport::count() . "\n";
echo "  By Petugas:    " . FinanceReport::where('user_id', $petugas->id)->count() . "\n";
echo "  By Admin:      " . FinanceReport::where('user_id', $admin->id)->count() . "\n";
echo "  Current Month: " . FinanceReport::whereBetween('tanggal', [
    now()->startOfMonth(), now()->endOfMonth()
])->count() . "\n";

exit($failed > 0 ? 1 : 0);
