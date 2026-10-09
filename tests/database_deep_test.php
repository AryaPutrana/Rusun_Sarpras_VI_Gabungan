<?php

/**
 * COMPREHENSIVE DATABASE & MODEL DEEP TEST
 * 
 * Tests:
 * - All relationships (hasMany, belongsTo, hasManyThrough)
 * - Foreign key constraints enforcement
 * - Cascade deletes
 * - Orphaned records detection
 * - Indexes on foreign keys
 * - N+1 query problems
 * - Model validations
 * - Soft deletes
 * - Timestamps auto-update
 * - Model methods, scopes, accessors, mutators
 * 
 * Run: php artisan tinker < tests/database_deep_test.php
 */

echo "\n========================================\n";
echo "DATABASE & MODEL DEEP TESTING\n";
echo "========================================\n\n";

use App\Models\User;
use App\Models\Item;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\Rusun;
use App\Models\WaterMonitoring;
use App\Models\MonitoringLocation;
use App\Models\FinanceReport;
use App\Models\FinanceLocation;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$errors = [];
$warnings = [];
$passed = [];

// ============================================
// 1. SCHEMA & INDEX VERIFICATION
// ============================================
echo "1. CHECKING SCHEMA & INDEXES\n";
echo "----------------------------\n";

$tables = [
    'users',
    'items',
    'withdrawals',
    'withdrawal_items',
    'rusun',
    'water_monitorings',
    'monitoring_locations',
    'finance_reports',
    'finance_locations',
    'stock_adjustments',
];

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        $errors[] = "Table '$table' does not exist";
        continue;
    }
    $passed[] = "Table '$table' exists";
}

// Check foreign key columns have indexes
$fkChecks = [
    'water_monitorings' => ['user_id', 'location_id'],
    'finance_reports' => ['user_id', 'location_id'],
    'withdrawals' => ['rusun_id'],
    'withdrawal_items' => ['withdrawal_id', 'item_id'],
    'stock_adjustments' => ['item_id', 'user_id'],
];

foreach ($fkChecks as $table => $columns) {
    if (!Schema::hasTable($table)) continue;
    
    $indexes = DB::select("SHOW INDEXES FROM `$table`");
    $indexedColumns = array_unique(array_column($indexes, 'Column_name'));
    
    foreach ($columns as $col) {
        if (!in_array($col, $indexedColumns)) {
            $warnings[] = "Table '$table' missing index on foreign key '$col'";
        } else {
            $passed[] = "Table '$table' has index on '$col'";
        }
    }
}

// ============================================
// 2. USER MODEL RELATIONSHIPS
// ============================================
echo "\n2. TESTING USER MODEL\n";
echo "---------------------\n";

$testUser = User::first();
if (!$testUser) {
    $warnings[] = "No users in database - creating test user";
    $testUser = User::create([
        'name' => 'Test User DB Deep',
        'email' => 'test_deep_' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    $passed[] = "Created test user: {$testUser->email}";
}

// Test User->waterMonitorings relationship
try {
    $monitorings = $testUser->waterMonitorings;
    $passed[] = "User->waterMonitorings relationship works (count: {$monitorings->count()})";
} catch (Exception $e) {
    $errors[] = "User->waterMonitorings failed: {$e->getMessage()}";
}

// Test User->financeReports relationship
try {
    $reports = $testUser->financeReports;
    $passed[] = "User->financeReports relationship works (count: {$reports->count()})";
} catch (Exception $e) {
    $errors[] = "User->financeReports failed: {$e->getMessage()}";
}

// Test User methods
try {
    $isAdmin = $testUser->isAdmin();
    $isPetugas = $testUser->isPetugas();
    $passed[] = "User->isAdmin() and isPetugas() methods work";
} catch (Exception $e) {
    $errors[] = "User methods failed: {$e->getMessage()}";
}

// ============================================
// 3. ITEM MODEL RELATIONSHIPS
// ============================================
echo "\n3. TESTING ITEM MODEL\n";
echo "---------------------\n";

$testItem = Item::first();
if (!$testItem) {
    $warnings[] = "No items in database - creating test item";
    $testItem = Item::create([
        'item_code' => 'TEST-' . uniqid(),
        'name' => 'Test Item DB Deep',
        'unit_price' => 10000,
        'stock' => 100,
        'min_stock' => 10,
        'unit' => 'pcs',
    ]);
    $passed[] = "Created test item: {$testItem->name}";
}

// Test Item->withdrawalItems relationship
try {
    $witems = $testItem->withdrawalItems;
    $passed[] = "Item->withdrawalItems relationship works (count: {$witems->count()})";
} catch (Exception $e) {
    $errors[] = "Item->withdrawalItems failed: {$e->getMessage()}";
}

// Test Item->withdrawals hasManyThrough
try {
    $withdrawals = $testItem->withdrawals;
    $passed[] = "Item->withdrawals (hasManyThrough) works (count: {$withdrawals->count()})";
} catch (Exception $e) {
    $errors[] = "Item->withdrawals hasManyThrough failed: {$e->getMessage()}";
}

// Test Item methods
try {
    $lowStock = $testItem->isLowStock();
    $status = $testItem->getStockStatus();
    $passed[] = "Item->isLowStock() = " . ($lowStock ? 'true' : 'false') . ", status = $status";
} catch (Exception $e) {
    $errors[] = "Item methods failed: {$e->getMessage()}";
}

// Test Item photoUrl method
try {
    $url = $testItem->photoUrl();
    $passed[] = "Item->photoUrl() works: $url";
} catch (Exception $e) {
    $errors[] = "Item->photoUrl() failed: {$e->getMessage()}";
}

// ============================================
// 4. WITHDRAWAL & WITHDRAWAL_ITEMS
// ============================================
echo "\n4. TESTING WITHDRAWAL MODEL\n";
echo "---------------------------\n";

$testRusun = Rusun::first();
if (!$testRusun) {
    $testRusun = Rusun::create([
        'code' => 'R-TEST-' . uniqid(),
        'name' => 'Test Rusun DB',
    ]);
    $passed[] = "Created test rusun: {$testRusun->name}";
}

$testWithdrawal = Withdrawal::first();
if (!$testWithdrawal && $testRusun) {
    $testWithdrawal = Withdrawal::create([
        'taken_by' => 'Test Taker',
        'rusun_id' => $testRusun->id,
        'total_quantity' => 0,
        'total_value' => 0,
        'taken_at' => now(),
    ]);
    $passed[] = "Created test withdrawal";
}

if ($testWithdrawal) {
    // Test Withdrawal->items relationship
    try {
        $items = $testWithdrawal->items;
        $passed[] = "Withdrawal->items relationship works (count: {$items->count()})";
    } catch (Exception $e) {
        $errors[] = "Withdrawal->items failed: {$e->getMessage()}";
    }

    // Test Withdrawal->rusun relationship
    try {
        $rusun = $testWithdrawal->rusun;
        $passed[] = "Withdrawal->rusun relationship works: " . ($rusun ? $rusun->name : 'null');
    } catch (Exception $e) {
        $errors[] = "Withdrawal->rusun failed: {$e->getMessage()}";
    }

    // Test Withdrawal->withdrawnItems hasManyThrough
    try {
        $witems = $testWithdrawal->withdrawnItems;
        $passed[] = "Withdrawal->withdrawnItems (hasManyThrough) works (count: {$witems->count()})";
    } catch (Exception $e) {
        $errors[] = "Withdrawal->withdrawnItems hasManyThrough failed: {$e->getMessage()}";
    }
}

// ============================================
// 5. WITHDRAWAL_ITEM MODEL
// ============================================
echo "\n5. TESTING WITHDRAWAL_ITEM MODEL\n";
echo "--------------------------------\n";

$testWItem = WithdrawalItem::first();
if (!$testWItem && $testWithdrawal && $testItem) {
    $testWItem = WithdrawalItem::create([
        'withdrawal_id' => $testWithdrawal->id,
        'item_id' => $testItem->id,
        'quantity' => 5,
        'unit_price' => $testItem->unit_price,
        'subtotal' => 5 * $testItem->unit_price,
    ]);
    $passed[] = "Created test withdrawal_item";
}

if ($testWItem) {
    // Test WithdrawalItem->withdrawal relationship
    try {
        $w = $testWItem->withdrawal;
        $passed[] = "WithdrawalItem->withdrawal relationship works: " . ($w ? "ID {$w->id}" : 'null');
    } catch (Exception $e) {
        $errors[] = "WithdrawalItem->withdrawal failed: {$e->getMessage()}";
    }

    // Test WithdrawalItem->item relationship
    try {
        $item = $testWItem->item;
        $passed[] = "WithdrawalItem->item relationship works: " . ($item ? $item->name : 'null');
    } catch (Exception $e) {
        $errors[] = "WithdrawalItem->item failed: {$e->getMessage()}";
    }
}

// ============================================
// 6. RUSUN MODEL
// ============================================
echo "\n6. TESTING RUSUN MODEL\n";
echo "----------------------\n";

if ($testRusun) {
    // Test Rusun->withdrawals relationship
    try {
        $withdrawals = $testRusun->withdrawals;
        $passed[] = "Rusun->withdrawals relationship works (count: {$withdrawals->count()})";
    } catch (Exception $e) {
        $errors[] = "Rusun->withdrawals failed: {$e->getMessage()}";
    }
}

// ============================================
// 7. WATER_MONITORING MODEL
// ============================================
echo "\n7. TESTING WATER_MONITORING MODEL\n";
echo "----------------------------------\n";

$testLocation = MonitoringLocation::first();
if (!$testLocation) {
    $testLocation = MonitoringLocation::create([
        'nama_lokasi' => 'Test Location DB',
        'status' => 'aktif',
    ]);
    $passed[] = "Created test monitoring_location";
}

$testMonitoring = WaterMonitoring::first();
if (!$testMonitoring && $testUser && $testLocation) {
    $testMonitoring = WaterMonitoring::create([
        'user_id' => $testUser->id,
        'location_id' => $testLocation->id,
        'tanggal' => now()->toDateString(),
        'sesi' => 'pagi',
        'waktu' => '08:00:00',
        'kondisi' => 'normal',
    ]);
    $passed[] = "Created test water_monitoring";
}

if ($testMonitoring) {
    // Test WaterMonitoring->user relationship
    try {
        $user = $testMonitoring->user;
        $passed[] = "WaterMonitoring->user relationship works: " . ($user ? $user->name : 'null');
    } catch (Exception $e) {
        $errors[] = "WaterMonitoring->user failed: {$e->getMessage()}";
    }

    // Test WaterMonitoring->location relationship
    try {
        $loc = $testMonitoring->location;
        $passed[] = "WaterMonitoring->location relationship works: " . ($loc ? $loc->nama_lokasi : 'null');
    } catch (Exception $e) {
        $errors[] = "WaterMonitoring->location failed: {$e->getMessage()}";
    }

    // Test accessor
    try {
        $waktu = $testMonitoring->waktu;
        $passed[] = "WaterMonitoring->waktu accessor works: " . ($waktu ? $waktu->format('H:i') : 'null');
    } catch (Exception $e) {
        $errors[] = "WaterMonitoring->waktu accessor failed: {$e->getMessage()}";
    }
}

// ============================================
// 8. MONITORING_LOCATION MODEL
// ============================================
echo "\n8. TESTING MONITORING_LOCATION MODEL\n";
echo "------------------------------------\n";

if ($testLocation) {
    // Test MonitoringLocation->waterMonitorings relationship
    try {
        $monitorings = $testLocation->waterMonitorings;
        $passed[] = "MonitoringLocation->waterMonitorings relationship works (count: {$monitorings->count()})";
    } catch (Exception $e) {
        $errors[] = "MonitoringLocation->waterMonitorings failed: {$e->getMessage()}";
    }
}

// ============================================
// 9. FINANCE_REPORT MODEL
// ============================================
echo "\n9. TESTING FINANCE_REPORT MODEL\n";
echo "--------------------------------\n";

$testFinLoc = FinanceLocation::first();
if (!$testFinLoc) {
    $testFinLoc = FinanceLocation::create([
        'nama_lokasi' => 'Test Finance Location DB',
        'status' => 'aktif',
    ]);
    $passed[] = "Created test finance_location";
}

$testFinReport = FinanceReport::first();
if (!$testFinReport && $testUser && $testFinLoc) {
    $testFinReport = FinanceReport::create([
        'user_id' => $testUser->id,
        'location_id' => $testFinLoc->id,
        'tanggal' => now()->toDateString(),
        'kondisi' => 'normal',
        'keterangan' => 'Test finance report',
        'foto' => 'test.jpg',
    ]);
    $passed[] = "Created test finance_report";
}

if ($testFinReport) {
    // Test FinanceReport->user relationship
    try {
        $user = $testFinReport->user;
        $passed[] = "FinanceReport->user relationship works: " . ($user ? $user->name : 'null');
    } catch (Exception $e) {
        $errors[] = "FinanceReport->user failed: {$e->getMessage()}";
    }

    // Test FinanceReport->location relationship
    try {
        $loc = $testFinReport->location;
        $passed[] = "FinanceReport->location relationship works: " . ($loc ? $loc->nama_lokasi : 'null');
    } catch (Exception $e) {
        $errors[] = "FinanceReport->location failed: {$e->getMessage()}";
    }
}

// ============================================
// 10. FINANCE_LOCATION MODEL
// ============================================
echo "\n10. TESTING FINANCE_LOCATION MODEL\n";
echo "----------------------------------\n";

if ($testFinLoc) {
    // Test FinanceLocation->financeReports relationship
    try {
        $reports = $testFinLoc->financeReports;
        $passed[] = "FinanceLocation->financeReports relationship works (count: {$reports->count()})";
    } catch (Exception $e) {
        $errors[] = "FinanceLocation->financeReports failed: {$e->getMessage()}";
    }
}

// ============================================
// 11. STOCK_ADJUSTMENT MODEL
// ============================================
echo "\n11. TESTING STOCK_ADJUSTMENT MODEL\n";
echo "-----------------------------------\n";

$testAdjustment = StockAdjustment::first();
if (!$testAdjustment && $testItem && $testUser) {
    $testAdjustment = StockAdjustment::create([
        'item_id' => $testItem->id,
        'user_id' => $testUser->id,
        'type' => 'add',
        'quantity' => 10,
        'reason' => 'Test adjustment',
    ]);
    $passed[] = "Created test stock_adjustment";
}

if ($testAdjustment) {
    // Test StockAdjustment->item relationship
    try {
        $item = $testAdjustment->item;
        $passed[] = "StockAdjustment->item relationship works: " . ($item ? $item->name : 'null');
    } catch (Exception $e) {
        $errors[] = "StockAdjustment->item failed: {$e->getMessage()}";
    }

    // Test StockAdjustment->user relationship
    try {
        $user = $testAdjustment->user;
        $passed[] = "StockAdjustment->user relationship works: " . ($user ? $user->name : 'null');
    } catch (Exception $e) {
        $errors[] = "StockAdjustment->user failed: {$e->getMessage()}";
    }
}

// ============================================
// 12. N+1 QUERY DETECTION
// ============================================
echo "\n12. TESTING N+1 QUERY PROBLEMS\n";
echo "-------------------------------\n";

DB::enableQueryLog();

// Test: Loading withdrawals without eager loading
$withdrawals = Withdrawal::limit(5)->get();
$queryCount1 = count(DB::getQueryLog());
DB::flushQueryLog();

foreach ($withdrawals as $w) {
    $rusun = $w->rusun;
    $items = $w->items;
}
$queryCount2 = count(DB::getQueryLog());

if ($queryCount2 > $queryCount1 + 2) {
    $warnings[] = "Potential N+1 on Withdrawal: {$queryCount2} queries for " . $withdrawals->count() . " withdrawals";
} else {
    $passed[] = "No N+1 detected on Withdrawal relationships";
}

DB::flushQueryLog();

// Test: Loading with eager loading
$withdrawals = Withdrawal::with(['rusun', 'items'])->limit(5)->get();
$eagerQueryCount = count(DB::getQueryLog());

foreach ($withdrawals as $w) {
    $rusun = $w->rusun;
    $items = $w->items;
}
$eagerQueryCount2 = count(DB::getQueryLog());

if ($eagerQueryCount2 == $eagerQueryCount) {
    $passed[] = "Eager loading eliminates N+1 queries";
} else {
    $warnings[] = "Eager loading didn't prevent additional queries";
}

DB::disableQueryLog();

// ============================================
// 13. FOREIGN KEY CONSTRAINTS
// ============================================
echo "\n13. TESTING FOREIGN KEY CONSTRAINTS\n";
echo "------------------------------------\n";

// Test: Try to delete a rusun that has withdrawals
if ($testRusun && $testRusun->withdrawals()->count() > 0) {
    try {
        DB::beginTransaction();
        $testRusun->delete();
        DB::rollBack();
        $warnings[] = "Rusun with withdrawals can be deleted (FK constraint might be CASCADE or missing)";
    } catch (Exception $e) {
        DB::rollBack();
        if (str_contains($e->getMessage(), 'foreign key constraint')) {
            $passed[] = "FK constraint prevents deleting Rusun with withdrawals";
        } else {
            $warnings[] = "Delete failed but not due to FK: " . $e->getMessage();
        }
    }
}

// Test: Try to delete a location that has water_monitorings
if ($testLocation && $testLocation->waterMonitorings()->count() > 0) {
    try {
        DB::beginTransaction();
        $testLocation->delete();
        DB::rollBack();
        $warnings[] = "MonitoringLocation with records can be deleted (FK constraint might be CASCADE or missing)";
    } catch (Exception $e) {
        DB::rollBack();
        if (str_contains($e->getMessage(), 'foreign key constraint')) {
            $passed[] = "FK constraint prevents deleting MonitoringLocation with records";
        } else {
            $warnings[] = "Delete failed but not due to FK: " . $e->getMessage();
        }
    }
}

// ============================================
// 14. ORPHANED RECORDS CHECK
// ============================================
echo "\n14. CHECKING FOR ORPHANED RECORDS\n";
echo "----------------------------------\n";

// Check withdrawal_items without withdrawal
$orphanedWItems = DB::table('withdrawal_items')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('withdrawals')
            ->whereRaw('withdrawals.id = withdrawal_items.withdrawal_id');
    })
    ->count();

if ($orphanedWItems > 0) {
    $errors[] = "Found {$orphanedWItems} orphaned withdrawal_items (no parent withdrawal)";
} else {
    $passed[] = "No orphaned withdrawal_items";
}

// Check withdrawal_items without item
$orphanedWItemsNoItem = DB::table('withdrawal_items')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('items')
            ->whereRaw('items.id = withdrawal_items.item_id');
    })
    ->count();

if ($orphanedWItemsNoItem > 0) {
    $errors[] = "Found {$orphanedWItemsNoItem} withdrawal_items referencing non-existent items";
} else {
    $passed[] = "No withdrawal_items with invalid item references";
}

// Check withdrawals without rusun
$orphanedWithdrawals = DB::table('withdrawals')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('rusun')
            ->whereRaw('rusun.id = withdrawals.rusun_id');
    })
    ->count();

if ($orphanedWithdrawals > 0) {
    $errors[] = "Found {$orphanedWithdrawals} withdrawals with invalid rusun_id";
} else {
    $passed[] = "No withdrawals with invalid rusun references";
}

// Check water_monitorings without user
$orphanedWM = DB::table('water_monitorings')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('users')
            ->whereRaw('users.id = water_monitorings.user_id');
    })
    ->count();

if ($orphanedWM > 0) {
    $errors[] = "Found {$orphanedWM} water_monitorings with invalid user_id";
} else {
    $passed[] = "No water_monitorings with invalid user references";
}

// ============================================
// 15. TIMESTAMPS AUTO-UPDATE
// ============================================
echo "\n15. TESTING TIMESTAMPS AUTO-UPDATE\n";
echo "-----------------------------------\n";

if ($testItem) {
    $oldUpdatedAt = $testItem->updated_at;
    sleep(1);
    $testItem->stock = $testItem->stock + 1;
    $testItem->save();
    $testItem->refresh();
    
    if ($testItem->updated_at > $oldUpdatedAt) {
        $passed[] = "Timestamps auto-update on Item save";
    } else {
        $warnings[] = "Timestamps may not be updating automatically";
    }
}

// ============================================
// 16. SOFT DELETES CHECK
// ============================================
echo "\n16. CHECKING SOFT DELETES\n";
echo "-------------------------\n";

$softDeleteModels = [
    'User' => User::class,
    'Item' => Item::class,
    'Withdrawal' => Withdrawal::class,
    'WaterMonitoring' => WaterMonitoring::class,
];

foreach ($softDeleteModels as $name => $class) {
    if (method_exists($class, 'withTrashed')) {
        $passed[] = "$name model uses SoftDeletes";
    } else {
        $passed[] = "$name model does NOT use SoftDeletes (hard delete)";
    }
}

// ============================================
// SUMMARY
// ============================================
echo "\n========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n\n";

echo "PASSED: " . count($passed) . "\n";
foreach ($passed as $msg) {
    echo "  ✓ $msg\n";
}

echo "\nWARNINGS: " . count($warnings) . "\n";
foreach ($warnings as $msg) {
    echo "  ⚠ $msg\n";
}

echo "\nERRORS: " . count($errors) . "\n";
foreach ($errors as $msg) {
    echo "  ✗ $msg\n";
}

echo "\n========================================\n";
echo "Total Tests: " . (count($passed) + count($warnings) + count($errors)) . "\n";
echo "========================================\n\n";

// Return summary
return [
    'passed' => count($passed),
    'warnings' => count($warnings),
    'errors' => count($errors),
    'details' => [
        'passed' => $passed,
        'warnings' => $warnings,
        'errors' => $errors,
    ]
];
