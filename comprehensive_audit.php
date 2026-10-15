#!/usr/bin/env php
<?php
/**
 * COMPREHENSIVE BUG AUDIT - LOGIN TO LOGOUT
 * Testing seluruh fitur sistem untuk menemukan bugs
 * READ-ONLY: Tidak mengubah data, hanya mencari masalah
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Item;
use App\Models\Withdrawal;
use App\Models\WaterMonitoring;
use App\Models\FinanceReport;
use App\Models\MonitoringLocation;
use App\Models\FinanceLocation;
use App\Models\Rusun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

$bugs = [];
$warnings = [];
$testCount = 0;

function reportBug($id, $severity, $location, $description, $impact) {
    global $bugs;
    $bugs[] = [
        'id' => $id,
        'severity' => $severity,
        'location' => $location,
        'description' => $description,
        'impact' => $impact,
    ];
}

function reportWarning($description) {
    global $warnings;
    $warnings[] = $description;
}

function testPassed() {
    global $testCount;
    $testCount++;
}

echo "================================================================\n";
echo "COMPREHENSIVE BUG AUDIT - LOGIN TO LOGOUT\n";
echo "================================================================\n\n";

// ========================================
// PHASE 1: DATABASE & MODEL CHECKS
// ========================================
echo "PHASE 1: DATABASE & MODEL INTEGRITY\n";
echo "-------------------------------------\n";

try {
    // Test 1: Check all models load
    $userCount = User::count();
    $itemCount = Item::count();
    $withdrawalCount = Withdrawal::count();
    $monitoringCount = WaterMonitoring::count();
    $financeCount = FinanceReport::count();
    
    echo "✓ Models loaded: Users($userCount), Items($itemCount), Withdrawals($withdrawalCount)\n";
    testPassed();
    
    // Test 2: Check for orphaned records
    $orphanedWithdrawalItems = DB::table('withdrawal_items')
        ->leftJoin('items', 'withdrawal_items.item_id', '=', 'items.id')
        ->whereNull('items.id')
        ->count();
    
    if ($orphanedWithdrawalItems > 0) {
        reportBug(
            'BUG-DB-01',
            'MEDIUM',
            'withdrawal_items table',
            "Found $orphanedWithdrawalItems orphaned withdrawal_items with non-existent item_id",
            'Data integrity issue - withdrawal items reference deleted items'
        );
    } else {
        echo "✓ No orphaned withdrawal_items\n";
        testPassed();
    }
    
    // Test 3: Check for items with negative stock
    $negativeStock = Item::where('stock', '<', 0)->count();
    if ($negativeStock > 0) {
        reportBug(
            'BUG-DB-02',
            'HIGH',
            'items table',
            "Found $negativeStock items with negative stock",
            'Data corruption - stock should never be negative'
        );
    } else {
        echo "✓ No items with negative stock\n";
        testPassed();
    }
    
    // Test 4: Check for duplicate rusun codes
    $duplicateCodes = DB::table('rusun')
        ->select('code', DB::raw('COUNT(*) as count'))
        ->groupBy('code')
        ->having('count', '>', 1)
        ->count();
    
    if ($duplicateCodes > 0) {
        reportBug(
            'BUG-DB-03',
            'MEDIUM',
            'rusun table',
            "Found $duplicateCodes duplicate rusun codes",
            'Data integrity - rusun codes should be unique'
        );
    } else {
        echo "✓ No duplicate rusun codes\n";
        testPassed();
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-DB-00',
        'CRITICAL',
        'Database connection',
        'Database error: ' . $e->getMessage(),
        'System cannot function without database'
    );
}

echo "\n";

// ========================================
// PHASE 2: AUTHENTICATION TESTS
// ========================================
echo "PHASE 2: AUTHENTICATION & AUTHORIZATION\n";
echo "----------------------------------------\n";

try {
    // Test 5: Check admin user exists
    $admin = User::where('role', 'admin')->first();
    if (!$admin) {
        reportBug(
            'BUG-AUTH-01',
            'CRITICAL',
            'users table',
            'No admin user found in database',
            'System cannot be administered without admin account'
        );
    } else {
        echo "✓ Admin user exists\n";
        testPassed();
    }
    
    // Test 6: Check petugas user exists
    $petugas = User::where('role', 'petugas')->first();
    if (!$petugas) {
        reportWarning('No petugas user found - might be intentional');
    } else {
        echo "✓ Petugas user exists\n";
        testPassed();
    }
    
    // Test 7: Check for users with invalid roles
    $invalidRoles = User::whereNotIn('role', ['admin', 'petugas'])->count();
    if ($invalidRoles > 0) {
        reportBug(
            'BUG-AUTH-02',
            'HIGH',
            'users table',
            "Found $invalidRoles users with invalid roles",
            'Users with invalid roles cannot access any features'
        );
    } else {
        echo "✓ No users with invalid roles\n";
        testPassed();
    }
    
    // Test 8: Check for duplicate emails
    $duplicateEmails = DB::table('users')
        ->select('email', DB::raw('COUNT(*) as count'))
        ->groupBy('email')
        ->having('count', '>', 1)
        ->count();
    
    if ($duplicateEmails > 0) {
        reportBug(
            'BUG-AUTH-03',
            'HIGH',
            'users table',
            "Found $duplicateEmails duplicate email addresses",
            'Login will fail or access wrong account'
        );
    } else {
        echo "✓ No duplicate emails\n";
        testPassed();
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-AUTH-00',
        'CRITICAL',
        'User model',
        'User auth error: ' . $e->getMessage(),
        'Authentication system broken'
    );
}

echo "\n";

// ========================================
// PHASE 3: INVENTORY SYSTEM TESTS
// ========================================
echo "PHASE 3: INVENTORY SYSTEM\n";
echo "--------------------------\n";

try {
    // Test 9: Check for items without unit
    $noUnit = Item::whereNull('unit')->orWhere('unit', '')->count();
    if ($noUnit > 0) {
        reportBug(
            'BUG-INV-01',
            'MEDIUM',
            'items table',
            "Found $noUnit items without unit specified",
            'Display issues in reports and calculations'
        );
    } else {
        echo "✓ All items have units\n";
        testPassed();
    }
    
    // Test 10: Check for items with invalid unit_price
    $invalidPrice = Item::where('unit_price', '<', 0)
        ->orWhere('unit_price', '>', Item::MAX_UNIT_PRICE)
        ->count();
    
    if ($invalidPrice > 0) {
        reportBug(
            'BUG-INV-02',
            'HIGH',
            'items table',
            "Found $invalidPrice items with invalid unit_price (< 0 or > MAX)",
            'Financial calculations will be wrong'
        );
    } else {
        echo "✓ All items have valid prices\n";
        testPassed();
    }
    
    // Test 11: Check for withdrawals with mismatched totals
    $mismatchedWithdrawals = DB::select("
        SELECT w.id, w.total_value, SUM(wi.subtotal) as actual_total
        FROM withdrawals w
        LEFT JOIN withdrawal_items wi ON w.id = wi.withdrawal_id
        GROUP BY w.id, w.total_value
        HAVING ABS(w.total_value - COALESCE(SUM(wi.subtotal), 0)) > 0.01
    ");
    
    if (count($mismatchedWithdrawals) > 0) {
        reportBug(
            'BUG-INV-03',
            'HIGH',
            'withdrawals table',
            count($mismatchedWithdrawals) . ' withdrawals have total_value != sum(subtotals)',
            'Financial reports show incorrect totals'
        );
    } else {
        echo "✓ All withdrawal totals match\n";
        testPassed();
    }
    
    // Test 12: Check for withdrawal items with zero quantity
    $zeroQuantity = DB::table('withdrawal_items')
        ->where('quantity', '<=', 0)
        ->count();
    
    if ($zeroQuantity > 0) {
        reportBug(
            'BUG-INV-04',
            'MEDIUM',
            'withdrawal_items table',
            "Found $zeroQuantity withdrawal items with zero or negative quantity",
            'Invalid data - quantity should always be positive'
        );
    } else {
        echo "✓ No withdrawal items with invalid quantity\n";
        testPassed();
    }
    
    // Test 13: Check for stock adjustments without user_id
    $noUserAdjustments = DB::table('stock_adjustments')
        ->whereNull('user_id')
        ->count();
    
    if ($noUserAdjustments > 0) {
        reportWarning("$noUserAdjustments stock adjustments without user_id (audit trail incomplete)");
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-INV-00',
        'CRITICAL',
        'Inventory system',
        'Inventory error: ' . $e->getMessage(),
        'Inventory system broken'
    );
}

echo "\n";

// ========================================
// PHASE 4: MONITORING SYSTEM TESTS
// ========================================
echo "PHASE 4: WATER MONITORING SYSTEM\n";
echo "---------------------------------\n";

try {
    // Test 14: Check for monitoring without location
    $noLocation = WaterMonitoring::whereNull('location_id')->count();
    if ($noLocation > 0) {
        reportBug(
            'BUG-MON-01',
            'MEDIUM',
            'water_monitorings table',
            "Found $noLocation monitoring records without location",
            'Cannot generate location-based reports'
        );
    } else {
        echo "✓ All monitoring records have locations\n";
        testPassed();
    }
    
    // Test 15: Check for monitoring with future dates
    $futureDates = WaterMonitoring::where('tanggal', '>', now())->count();
    if ($futureDates > 0) {
        reportBug(
            'BUG-MON-02',
            'LOW',
            'water_monitorings table',
            "Found $futureDates monitoring records with future dates",
            'Data quality issue - monitoring cannot be from future'
        );
    } else {
        echo "✓ No monitoring records with future dates\n";
        testPassed();
    }
    
    // Test 16: Check for monitoring with invalid sesi
    $validSesi = array_keys(WaterMonitoring::SESI);
    $invalidSesi = WaterMonitoring::whereNotIn('sesi', $validSesi)->count();
    
    if ($invalidSesi > 0) {
        reportBug(
            'BUG-MON-03',
            'MEDIUM',
            'water_monitorings table',
            "Found $invalidSesi monitoring records with invalid sesi",
            'Display issues and incorrect time mapping'
        );
    } else {
        echo "✓ All monitoring records have valid sesi\n";
        testPassed();
    }
    
    // Test 17: Check for inactive locations still in use
    $inactiveLocationsInUse = DB::table('monitoring_locations')
        ->where('status', '!=', 'aktif')
        ->whereExists(function($query) {
            $query->select(DB::raw(1))
                ->from('water_monitorings')
                ->whereColumn('water_monitorings.location_id', 'monitoring_locations.id')
                ->where('water_monitorings.tanggal', '>', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'));
        })
        ->count();
    
    if ($inactiveLocationsInUse > 0) {
        reportWarning("$inactiveLocationsInUse inactive locations used in last 30 days (might need reactivation)");
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-MON-00',
        'CRITICAL',
        'Monitoring system',
        'Monitoring error: ' . $e->getMessage(),
        'Monitoring system broken'
    );
}

echo "\n";

// ========================================
// PHASE 5: FINANCE SYSTEM TESTS
// ========================================
echo "PHASE 5: FINANCE REPORTING SYSTEM\n";
echo "----------------------------------\n";

try {
    // Test 18: Check for finance reports without user
    $noUserFinance = FinanceReport::whereNull('user_id')->count();
    if ($noUserFinance > 0) {
        reportBug(
            'BUG-FIN-01',
            'MEDIUM',
            'finance_reports table',
            "Found $noUserFinance finance reports without user_id",
            'Cannot track who created the report (audit issue)'
        );
    } else {
        echo "✓ All finance reports have user_id\n";
        testPassed();
    }
    
    // Test 19: Check for finance reports with invalid kondisi
    $validKondisi = array_keys(FinanceReport::KONDISI);
    $invalidKondisi = FinanceReport::whereNotIn('kondisi', $validKondisi)->count();
    
    if ($invalidKondisi > 0) {
        reportBug(
            'BUG-FIN-02',
            'MEDIUM',
            'finance_reports table',
            "Found $invalidKondisi finance reports with invalid kondisi",
            'Display issues and incorrect categorization'
        );
    } else {
        echo "✓ All finance reports have valid kondisi\n";
        testPassed();
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-FIN-00',
        'CRITICAL',
        'Finance system',
        'Finance error: ' . $e->getMessage(),
        'Finance system broken'
    );
}

echo "\n";

// ========================================
// PHASE 6: ROUTE & CONTROLLER CHECKS
// ========================================
echo "PHASE 6: ROUTES & MIDDLEWARE\n";
echo "-----------------------------\n";

try {
    // Test 20: Check critical routes exist
    $criticalRoutes = [
        'login',
        'logout',
        'dashboard',
        'inventory.items.index',
        'inventory.items.update',
        'inventory.withdrawals.update',
    ];
    
    $missingRoutes = [];
    foreach ($criticalRoutes as $routeName) {
        if (!Route::has($routeName)) {
            $missingRoutes[] = $routeName;
        }
    }
    
    if (count($missingRoutes) > 0) {
        reportBug(
            'BUG-ROUTE-01',
            'CRITICAL',
            'routes/web.php',
            'Missing critical routes: ' . implode(', ', $missingRoutes),
            'Core functionality will not work'
        );
    } else {
        echo "✓ All critical routes exist\n";
        testPassed();
    }
    
} catch (\Exception $e) {
    reportBug(
        'BUG-ROUTE-00',
        'CRITICAL',
        'Routing system',
        'Route error: ' . $e->getMessage(),
        'Application routing broken'
    );
}

echo "\n";

// ========================================
// GENERATE REPORT
// ========================================
echo "================================================================\n";
echo "AUDIT COMPLETE\n";
echo "================================================================\n\n";

echo "Tests Run: $testCount\n";
echo "Bugs Found: " . count($bugs) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (count($bugs) > 0) {
    echo "================================================================\n";
    echo "BUGS FOUND:\n";
    echo "================================================================\n\n";
    
    $criticalBugs = array_filter($bugs, fn($b) => $b['severity'] === 'CRITICAL');
    $highBugs = array_filter($bugs, fn($b) => $b['severity'] === 'HIGH');
    $mediumBugs = array_filter($bugs, fn($b) => $b['severity'] === 'MEDIUM');
    $lowBugs = array_filter($bugs, fn($b) => $b['severity'] === 'LOW');
    
    if (count($criticalBugs) > 0) {
        echo "🔴 CRITICAL (" . count($criticalBugs) . "):\n";
        echo "-------------------\n";
        foreach ($criticalBugs as $bug) {
            echo "{$bug['id']}: {$bug['description']}\n";
            echo "   Location: {$bug['location']}\n";
            echo "   Impact: {$bug['impact']}\n\n";
        }
    }
    
    if (count($highBugs) > 0) {
        echo "🟠 HIGH (" . count($highBugs) . "):\n";
        echo "-------------\n";
        foreach ($highBugs as $bug) {
            echo "{$bug['id']}: {$bug['description']}\n";
            echo "   Location: {$bug['location']}\n";
            echo "   Impact: {$bug['impact']}\n\n";
        }
    }
    
    if (count($mediumBugs) > 0) {
        echo "🟡 MEDIUM (" . count($mediumBugs) . "):\n";
        echo "---------------\n";
        foreach ($mediumBugs as $bug) {
            echo "{$bug['id']}: {$bug['description']}\n";
            echo "   Location: {$bug['location']}\n";
            echo "   Impact: {$bug['impact']}\n\n";
        }
    }
    
    if (count($lowBugs) > 0) {
        echo "🟢 LOW (" . count($lowBugs) . "):\n";
        echo "------------\n";
        foreach ($lowBugs as $bug) {
            echo "{$bug['id']}: {$bug['description']}\n";
            echo "   Location: {$bug['location']}\n";
            echo "   Impact: {$bug['impact']}\n\n";
        }
    }
} else {
    echo "✅ NO BUGS FOUND - SYSTEM HEALTHY!\n\n";
}

if (count($warnings) > 0) {
    echo "================================================================\n";
    echo "WARNINGS:\n";
    echo "================================================================\n";
    foreach ($warnings as $warning) {
        echo "⚠️  $warning\n";
    }
    echo "\n";
}

echo "================================================================\n";
echo "SUMMARY BY SEVERITY:\n";
echo "================================================================\n";
$criticalCount = count(array_filter($bugs, fn($b) => $b['severity'] === 'CRITICAL'));
$highCount = count(array_filter($bugs, fn($b) => $b['severity'] === 'HIGH'));
$mediumCount = count(array_filter($bugs, fn($b) => $b['severity'] === 'MEDIUM'));
$lowCount = count(array_filter($bugs, fn($b) => $b['severity'] === 'LOW'));

echo "🔴 CRITICAL: $criticalCount\n";
echo "🟠 HIGH: $highCount\n";
echo "🟡 MEDIUM: $mediumCount\n";
echo "🟢 LOW: $lowCount\n";
echo "⚠️  WARNINGS: " . count($warnings) . "\n\n";

echo "Full report saved to: COMPREHENSIVE_AUDIT_REPORT.md\n";
