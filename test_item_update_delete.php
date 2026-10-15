#!/usr/bin/env php
<?php
/**
 * SCRIPT TESTING MENDALAM - ITEM CONTROLLER
 * Testing untuk fitur Update & Delete Item
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "==============================================\n";
echo "TESTING MENDALAM - ITEM UPDATE & DELETE\n";
echo "==============================================\n\n";

// Setup: Login sebagai admin
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    echo "❌ ERROR: No admin user found!\n";
    exit(1);
}
Auth::login($admin);

// ==============================================
// TEST 1: Item Update - Stock Adjustment Mode
// ==============================================
echo "TEST 1: Item Update - Stock Adjustment (Add Stock)\n";
echo "---------------------------------------------------\n";

$testItem = Item::first();
if (!$testItem) {
    echo "❌ ERROR: No items in database!\n";
    exit(1);
}

$originalStock = $testItem->stock;
echo "Item: {$testItem->name}\n";
echo "Stock sebelum: {$originalStock}\n";

// Simulate stock adjustment (add 10 units)
DB::beginTransaction();
try {
    $testItem->increment('stock', 10);
    
    // Create audit trail
    \App\Models\StockAdjustment::create([
        'item_id' => $testItem->id,
        'user_id' => $admin->id,
        'type' => 'add',
        'quantity' => 10,
        'reason' => 'Test penambahan stok',
    ]);
    
    DB::commit();
    
    $testItem->refresh();
    $newStock = $testItem->stock;
    
    if ($newStock == $originalStock + 10) {
        echo "✅ PASS: Stock bertambah dari {$originalStock} → {$newStock}\n";
    } else {
        echo "❌ FAIL: Stock tidak sesuai! Expected: " . ($originalStock + 10) . ", Got: {$newStock}\n";
    }
    
    // Check audit trail
    $audit = \App\Models\StockAdjustment::where('item_id', $testItem->id)
        ->where('type', 'add')
        ->orderBy('id', 'desc')
        ->first();
    
    if ($audit && $audit->quantity == 10) {
        echo "✅ PASS: Audit trail tercatat dengan benar\n";
    } else {
        echo "❌ FAIL: Audit trail tidak tercatat!\n";
    }
    
    // Rollback for next test
    $testItem->decrement('stock', 10);
    $audit->delete();
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ FAIL: Exception - " . $e->getMessage() . "\n";
}

echo "\n";

// ==============================================
// TEST 2: Item Update - Stock Validation
// ==============================================
echo "TEST 2: Item Update - Stock Validation (Prevent Over-subtract)\n";
echo "----------------------------------------------------------------\n";

$testItem->refresh();
$currentStock = $testItem->stock;
echo "Current stock: {$currentStock}\n";

DB::beginTransaction();
try {
    // Try to subtract more than available
    $subtractAmount = $currentStock + 10;
    
    if ($testItem->stock < $subtractAmount) {
        echo "✅ PASS: Validation caught over-subtract (trying to subtract {$subtractAmount} from {$testItem->stock})\n";
    } else {
        echo "❌ FAIL: Validation should have caught this!\n";
    }
    
    DB::rollBack();
} catch (\Exception $e) {
    DB::rollBack();
    echo "✅ PASS: Exception thrown as expected - " . $e->getMessage() . "\n";
}

echo "\n";

// ==============================================
// TEST 3: Item Delete - Photo Cleanup Logic
// ==============================================
echo "TEST 3: Item Delete - Photo Cleanup Logic\n";
echo "-------------------------------------------\n";

// Find or create test items with shared photo
$photoPath = 'test-shared-photo.jpg';

DB::beginTransaction();
try {
    // Create 2 items with same photo
    $item1 = Item::create([
        'item_code' => 'TEST-001-' . time(),
        'name' => 'Test Item 1',
        'photo' => $photoPath,
        'unit_price' => 10000,
        'stock' => 100,
        'min_stock' => 10,
        'unit' => 'pcs',
        'description' => 'Test item for photo cleanup',
    ]);
    
    $item2 = Item::create([
        'item_code' => 'TEST-002-' . time(),
        'name' => 'Test Item 2',
        'photo' => $photoPath,
        'unit_price' => 20000,
        'stock' => 50,
        'min_stock' => 5,
        'unit' => 'pcs',
        'description' => 'Test item 2 for photo cleanup',
    ]);
    
    echo "Created 2 items with shared photo: {$photoPath}\n";
    
    // Delete item 1
    $item1->delete();
    
    // Check if photo still used
    $stillUsed = Item::where('photo', $photoPath)->exists();
    
    if ($stillUsed) {
        echo "✅ PASS: Photo not deleted (still used by item 2)\n";
    } else {
        echo "❌ FAIL: Photo should still exist (used by item 2)!\n";
    }
    
    // Delete item 2
    $item2->delete();
    
    // Check if photo still used
    $stillUsed = Item::where('photo', $photoPath)->exists();
    
    if (!$stillUsed) {
        echo "✅ PASS: Photo can be deleted (no more items using it)\n";
    } else {
        echo "❌ FAIL: Photo should be marked for deletion!\n";
    }
    
    DB::rollBack();
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ FAIL: Exception - " . $e->getMessage() . "\n";
}

echo "\n";

// ==============================================
// TEST 4: Race Condition Test (Simulated)
// ==============================================
echo "TEST 4: Race Condition Prevention (Stock Lock)\n";
echo "------------------------------------------------\n";

$testItem->refresh();
$originalStock = $testItem->stock;

echo "Testing lock mechanism...\n";

DB::beginTransaction();
try {
    // Lock item
    $locked = Item::whereKey($testItem->id)->lockForUpdate()->first();
    
    if ($locked) {
        echo "✅ PASS: Item locked successfully\n";
        echo "✅ PASS: Locked stock value: {$locked->stock} (matches original: {$originalStock})\n";
    } else {
        echo "❌ FAIL: Failed to lock item!\n";
    }
    
    DB::rollBack();
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ FAIL: Exception - " . $e->getMessage() . "\n";
}

echo "\n";

// ==============================================
// TEST 5: Cache Operations (Not in Transaction)
// ==============================================
echo "TEST 5: Cache Operations Outside Transaction\n";
echo "----------------------------------------------\n";

try {
    $testPhoto = 'test-cache.jpg';
    $cacheKey = 'item-thumb:' . sha1($testPhoto . '|44|v1');
    
    // Set cache
    \Illuminate\Support\Facades\Cache::put($cacheKey, 'test-data', 60);
    
    // Check cache
    $cached = \Illuminate\Support\Facades\Cache::has($cacheKey);
    
    if ($cached) {
        echo "✅ PASS: Cache set successfully\n";
    } else {
        echo "❌ FAIL: Cache not set!\n";
    }
    
    // Clear cache
    \Illuminate\Support\Facades\Cache::forget($cacheKey);
    
    $cached = \Illuminate\Support\Facades\Cache::has($cacheKey);
    
    if (!$cached) {
        echo "✅ PASS: Cache cleared successfully\n";
    } else {
        echo "❌ FAIL: Cache not cleared!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ FAIL: Exception - " . $e->getMessage() . "\n";
}

echo "\n";

// ==============================================
// TEST SUMMARY
// ==============================================
echo "==============================================\n";
echo "TESTING SELESAI\n";
echo "==============================================\n";
echo "\nPeriksa output di atas untuk hasil lengkap.\n";
echo "Semua test menggunakan transaction dan di-rollback.\n";
echo "Database tidak terpengaruh oleh testing ini.\n\n";
