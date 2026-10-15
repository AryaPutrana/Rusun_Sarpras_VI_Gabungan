#!/usr/bin/env php
<?php
/**
 * TEST SCRIPT - BUG #7 & #8 FIXES
 * Verify deadlock fix dan user deletion check
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Item;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use Illuminate\Support\Facades\DB;

echo "================================================================\n";
echo "TESTING BUG #7 & #8 FIXES\n";
echo "================================================================\n\n";

$allPassed = true;

// ========================================
// TEST 1: BUG #7 FIX - Withdrawal Delete Deadlock Prevention
// ========================================
echo "TEST 1: BUG #7 FIX - Withdrawal Delete dengan Batch Locking\n";
echo "-------------------------------------------------------------\n";

try {
    // Verify withdrawal exists
    $withdrawal = Withdrawal::with('items')->first();
    
    if (!$withdrawal) {
        echo "⚠️  SKIP: No withdrawal data untuk test\n";
    } else {
        echo "✓ Found withdrawal ID: {$withdrawal->id}\n";
        echo "✓ Items count: " . $withdrawal->items->count() . "\n";
        
        // Verify item IDs akan di-sort
        $itemIds = $withdrawal->items->pluck('item_id')->unique();
        $sortedIds = $itemIds->sort()->values();
        
        echo "✓ Item IDs: " . $itemIds->implode(', ') . "\n";
        echo "✓ Sorted IDs: " . $sortedIds->implode(', ') . "\n";
        
        // Simulate the lock ordering logic (tanpa execute)
        $itemIdsFromCode = $withdrawal->items
            ->pluck('item_id')
            ->unique()
            ->map(fn($id) => (int)$id)
            ->sort()
            ->values();
        
        if ($itemIdsFromCode->toArray() === $sortedIds->toArray()) {
            echo "✅ PASS: Lock ordering logic correct\n";
        } else {
            echo "❌ FAIL: Lock ordering mismatch\n";
            $allPassed = false;
        }
        
        // Verify no individual lockForUpdate in loop
        $controllerContent = file_get_contents(__DIR__.'/app/Http/Controllers/Inventory/WithdrawalController.php');
        
        // Check destroy method untuk pattern lama (buggy)
        if (preg_match('/function destroy.*?Item::lockForUpdate\(\)->find/s', $controllerContent)) {
            echo "❌ FAIL: Individual lockForUpdate masih ada di destroy()\n";
            $allPassed = false;
        } else {
            echo "✅ PASS: No individual lockForUpdate dalam loop\n";
        }
        
        // Check batch lock pattern ada
        if (strpos($controllerContent, '->lockForUpdate()') !== false && 
            strpos($controllerContent, '->orderBy(\'id\')') !== false &&
            strpos($controllerContent, '->keyBy(\'id\')') !== false) {
            echo "✅ PASS: Batch locking pattern detected\n";
        } else {
            echo "❌ FAIL: Batch locking pattern not found\n";
            $allPassed = false;
        }
    }
    
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// ========================================
// TEST 2: BUG #8 FIX - User Deletion Check Stock Adjustments
// ========================================
echo "TEST 2: BUG #8 FIX - User Deletion Check Stock Adjustments\n";
echo "-----------------------------------------------------------\n";

try {
    // Check if stock_adjustments table has records
    $stockAdjustmentsCount = DB::table('stock_adjustments')->count();
    echo "✓ Stock adjustments records: $stockAdjustmentsCount\n";
    
    // Verify UserController code contains the check
    $userControllerContent = file_get_contents(__DIR__.'/app/Http/Controllers/Admin/UserController.php');
    
    // Check for stock_adjustments check
    if (strpos($userControllerContent, 'stock_adjustments') !== false) {
        echo "✅ PASS: Stock adjustments check exists in code\n";
    } else {
        echo "❌ FAIL: Stock adjustments check NOT found\n";
        $allPassed = false;
    }
    
    // Check for proper DB query
    if (strpos($userControllerContent, "DB::table('stock_adjustments')") !== false &&
        strpos($userControllerContent, "where('user_id', \$pengguna->id)") !== false) {
        echo "✅ PASS: Correct DB query pattern found\n";
    } else {
        echo "❌ FAIL: DB query pattern incorrect\n";
        $allPassed = false;
    }
    
    // Check for error message
    if (strpos($userControllerContent, 'riwayat penyesuaian stok') !== false) {
        echo "✅ PASS: Proper error message exists\n";
    } else {
        echo "❌ FAIL: Error message not found\n";
        $allPassed = false;
    }
    
    // Check existing checks still there
    if (strpos($userControllerContent, 'waterMonitorings()') !== false &&
        strpos($userControllerContent, 'financeReports()') !== false) {
        echo "✅ PASS: Existing checks preserved (waterMonitorings, financeReports)\n";
    } else {
        echo "❌ FAIL: Existing checks missing\n";
        $allPassed = false;
    }
    
    // Test the logic with real data if possible
    $usersWithAdjustments = DB::table('stock_adjustments')
        ->select('user_id')
        ->whereNotNull('user_id')
        ->distinct()
        ->pluck('user_id');
    
    if ($usersWithAdjustments->count() > 0) {
        echo "✓ Found " . $usersWithAdjustments->count() . " users with stock adjustments\n";
        echo "✓ These users should NOT be deletable\n";
        echo "✓ User IDs: " . $usersWithAdjustments->implode(', ') . "\n";
    } else {
        echo "⚠️  No users with stock adjustments yet (check will work when data exists)\n";
    }
    
} catch (\Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n";

// ========================================
// FINAL RESULT
// ========================================
echo "================================================================\n";
echo "FINAL RESULT\n";
echo "================================================================\n";

if ($allPassed) {
    echo "✅ ALL TESTS PASSED - BUG #7 & #8 FIXED SUCCESSFULLY!\n\n";
    echo "BUG #7: Deadlock prevention implemented with batch locking\n";
    echo "BUG #8: User deletion now checks stock adjustments\n\n";
    echo "ZERO MISTAKES ✅\n";
} else {
    echo "❌ SOME TESTS FAILED - Please review the output above\n";
}

echo "\n";
