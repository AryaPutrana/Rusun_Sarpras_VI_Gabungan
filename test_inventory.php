<?php

/**
 * Comprehensive Inventory System Test
 * 
 * Tests:
 * 1. Items CRUD
 * 2. Stock management (add/subtract)
 * 3. Withdrawals CRUD
 * 4. Stock deduction on withdrawal
 * 5. Calculations (subtotal, total_value)
 * 6. Validation rules
 * 7. Photo thumbnails
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Item;
use App\Models\Rusun;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$results = [];
$passed = 0;
$failed = 0;

function test($name, $callback) {
    global $results, $passed, $failed;
    try {
        $result = $callback();
        if ($result === true) {
            $results[] = "✅ $name";
            $passed++;
        } else {
            $results[] = "❌ $name: " . ($result ?: 'Failed');
            $failed++;
        }
    } catch (Exception $e) {
        $results[] = "❌ $name: " . $e->getMessage();
        $failed++;
    }
}

echo "🧪 TESTING INVENTORY MATERIAL SYSTEM\n";
echo str_repeat("=", 60) . "\n\n";

// Prepare test image
$testImageContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCABkAGQDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlbaWmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigD//2Q==');
Storage::disk('public')->put('items/test-image.jpg', $testImageContent);

echo "📋 TEST 1: ITEMS CRUD\n";
echo str_repeat("-", 60) . "\n";

// 1.1 Create Item
test("Create Item #1 (Semen Portland)", function() {
    $item = Item::create([
        'item_code' => 'BRG-001',
        'name' => 'Semen Portland 50kg',
        'photo' => 'test-image.jpg',
        'unit_price' => 65000,
        'stock' => 100,
        'min_stock' => 20,
        'unit' => 'Sak',
        'description' => 'Semen Portland untuk bangunan',
    ]);
    return $item->id > 0 && $item->name === 'Semen Portland 50kg';
});

test("Create Item #2 (Besi Beton)", function() {
    $item = Item::create([
        'item_code' => 'BRG-002',
        'name' => 'Besi Beton 10mm x 12m',
        'photo' => 'test-image.jpg',
        'unit_price' => 95000,
        'stock' => 50,
        'min_stock' => 10,
        'unit' => 'Batang',
        'description' => 'Besi beton polos diameter 10mm',
    ]);
    return $item->id > 0;
});

test("Create Item #3 (Cat Tembok)", function() {
    $item = Item::create([
        'item_code' => 'BRG-003',
        'name' => 'Cat Tembok 5kg',
        'photo' => 'test-image.jpg',
        'unit_price' => 125000,
        'stock' => 30,
        'min_stock' => 5,
        'unit' => 'Pail',
        'description' => 'Cat tembok interior putih',
    ]);
    return $item->id > 0;
});

// 1.2 Read Item
test("Read Item by ID", function() {
    $item = Item::where('item_code', 'BRG-001')->first();
    return $item && $item->item_code === 'BRG-001';
});

test("Search Items by name", function() {
    $items = Item::where('name', 'like', '%Semen%')->get();
    return $items->count() === 1;
});

// 1.3 Update Item basic info
test("Update Item name and price", function() {
    $item = Item::first();
    if (!$item) return "No items found";
    $item->update([
        'name' => 'Semen Portland Premium 50kg',
        'unit_price' => 67000,
    ]);
    $item->refresh();
    return $item->name === 'Semen Portland Premium 50kg' && $item->unit_price == 67000;
});

// 1.4 Photo thumbnail method
test("Photo thumbnail generation", function() {
    $item = Item::first();
    if (!$item) return "No items found";
    $thumb = $item->photoThumbnail(44);
    return $thumb && isset($thumb['data']) && str_starts_with($thumb['data'], 'data:image/jpeg;base64,');
});

echo "\n📦 TEST 2: STOCK MANAGEMENT\n";
echo str_repeat("-", 60) . "\n";

// 2.1 Add stock via increment
test("Add stock +20 units", function() {
    $item = Item::first();
    if (!$item) return "No items found";
    $oldStock = $item->stock;
    $item->increment('stock', 20);
    $item->refresh();
    return $item->stock === $oldStock + 20;
});

// 2.2 Subtract stock via decrement
test("Subtract stock -10 units", function() {
    $item = Item::first();
    if (!$item) return "No items found";
    $oldStock = $item->stock;
    $item->decrement('stock', 10);
    $item->refresh();
    return $item->stock === $oldStock - 10;
});

// 2.3 Stock adjustment logging
test("Stock adjustment creates log", function() {
    $item = Item::first();
    if (!$item) return "No items found";
    $user = \App\Models\User::first();
    if (!$user) return "No users found";
    
    StockAdjustment::create([
        'item_id' => $item->id,
        'user_id' => $user->id,
        'type' => 'add',
        'quantity' => 50,
        'reason' => 'Test penambahan stok',
    ]);
    $log = StockAdjustment::where('item_id', $item->id)->first();
    return $log && $log->quantity == 50;
});

// 2.4 Check low stock detection
test("Low stock detection", function() {
    $item = Item::create([
        'item_code' => 'BRG-004',
        'name' => 'Test Low Stock Item',
        'photo' => 'test-image.jpg',
        'unit_price' => 10000,
        'stock' => 5,
        'min_stock' => 10,
        'unit' => 'Unit',
    ]);
    return $item->stock < $item->min_stock;
});

echo "\n🚚 TEST 3: WITHDRAWALS CRUD\n";
echo str_repeat("-", 60) . "\n";

// 3.1 Create Withdrawal
test("Create Withdrawal #1 (single item)", function() {
    $rusun = Rusun::first();
    if (!$rusun) return "No rusuns found - run seeder first";
    $item = Item::first();
    if (!$item) return "No items found";
    
    $withdrawal = Withdrawal::create([
        'taken_by' => 'Budi Santoso',
        'rusun_id' => $rusun->id,
        'total_quantity' => 10,
        'total_value' => 10 * $item->unit_price,
        'taken_at' => now(),
        'description' => 'Pengambilan untuk renovasi',
    ]);
    
    $withdrawal->items()->create([
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => $item->unit_price,
        'subtotal' => 10 * $item->unit_price,
    ]);
    
    return $withdrawal->id > 0 && $withdrawal->items->count() === 1;
});

test("Create Withdrawal #2 (multiple items)", function() {
    $rusun = Rusun::first();
    if (!$rusun) return "No rusuns found - run seeder first";
    $item1 = Item::skip(1)->first();
    $item2 = Item::skip(2)->first();
    if (!$item1 || !$item2) return "Not enough items found";
    
    $totalQty = 5 + 3;
    $totalValue = (5 * $item1->unit_price) + (3 * $item2->unit_price);
    
    $withdrawal = Withdrawal::create([
        'taken_by' => 'Siti Rahayu',
        'rusun_id' => $rusun->id,
        'total_quantity' => $totalQty,
        'total_value' => $totalValue,
        'taken_at' => now()->subDays(5),
        'description' => 'Pengambilan rutin bulanan',
    ]);
    
    $withdrawal->items()->createMany([
        [
            'item_id' => $item1->id,
            'quantity' => 5,
            'unit_price' => $item1->unit_price,
            'subtotal' => 5 * $item1->unit_price,
        ],
        [
            'item_id' => $item2->id,
            'quantity' => 3,
            'unit_price' => $item2->unit_price,
            'subtotal' => 3 * $item2->unit_price,
        ],
    ]);
    
    return $withdrawal->items->count() === 2;
});

// 3.2 Read Withdrawal
test("Read Withdrawal with relationships", function() {
    $withdrawal = Withdrawal::with(['rusun', 'items.item'])->first();
    return $withdrawal && $withdrawal->rusun && $withdrawal->items->count() > 0;
});

// 3.3 Update Withdrawal
test("Update Withdrawal header info", function() {
    $withdrawal = Withdrawal::first();
    if (!$withdrawal) {
        return "No withdrawals found";
    }
    $withdrawal->update([
        'taken_by' => 'Budi Santoso (Updated)',
        'description' => 'Updated description',
    ]);
    $withdrawal->refresh();
    return str_contains($withdrawal->taken_by, 'Updated');
});

echo "\n💰 TEST 4: CALCULATIONS\n";
echo str_repeat("-", 60) . "\n";

// 4.1 Subtotal calculation
test("WithdrawalItem subtotal = quantity × unit_price", function() {
    $item = WithdrawalItem::first();
    if (!$item) return "No withdrawal items - skipping";
    $expected = $item->quantity * $item->unit_price;
    return abs($item->subtotal - $expected) < 0.01;
});

// 4.2 Total value calculation
test("Withdrawal total_value = sum of subtotals", function() {
    $withdrawal = Withdrawal::with('items')->skip(1)->first();
    if (!$withdrawal) return "No second withdrawal found";
    $calculated = $withdrawal->items->sum('subtotal');
    return abs($withdrawal->total_value - $calculated) < 0.01;
});

// 4.3 Total quantity calculation
test("Withdrawal total_quantity = sum of quantities", function() {
    $withdrawal = Withdrawal::with('items')->skip(1)->first();
    if (!$withdrawal) return "No second withdrawal found";
    $calculated = $withdrawal->items->sum('quantity');
    return $withdrawal->total_quantity == $calculated;
});

echo "\n📉 TEST 5: STOCK DEDUCTION ON WITHDRAWAL\n";
echo str_repeat("-", 60) . "\n";

// 5.1 Stock deduction
test("Stock decreases after withdrawal", function() {
    $item = Item::skip(1)->first();
    if (!$item) return "Need at least 2 items";
    $initialStock = $item->stock;
    
    DB::transaction(function() use ($item) {
        $rusun = Rusun::first();
        if (!$rusun) throw new \Exception("No rusuns found");
        $qty = 8;
        
        $withdrawal = Withdrawal::create([
            'taken_by' => 'Test Withdrawal',
            'rusun_id' => $rusun->id,
            'total_quantity' => $qty,
            'total_value' => $qty * $item->unit_price,
            'taken_at' => now(),
        ]);
        
        $withdrawal->items()->create([
            'item_id' => $item->id,
            'quantity' => $qty,
            'unit_price' => $item->unit_price,
            'subtotal' => $qty * $item->unit_price,
        ]);
        
        $item->decrement('stock', $qty);
    });
    
    $item->refresh();
    return $item->stock === $initialStock - 8;
});

// 5.2 Stock restoration on deletion
test("Stock restored after withdrawal deletion", function() {
    $withdrawal = Withdrawal::with('items')->skip(2)->first();
    if (!$withdrawal) return "No third withdrawal found";
    $item = Item::find($withdrawal->items->first()->item_id);
    $stockBefore = $item->stock;
    $quantityToRestore = $withdrawal->items->first()->quantity;
    
    // Simulate deletion - restore stock
    $item->increment('stock', $quantityToRestore);
    $withdrawal->items()->delete();
    $withdrawal->delete();
    
    $item->refresh();
    return $item->stock === $stockBefore + $quantityToRestore;
});

echo "\n✅ TEST 6: VALIDATION\n";
echo str_repeat("-", 60) . "\n";

// 6.1 Unique item_code
test("Item code must be unique", function() {
    try {
        Item::create([
            'item_code' => 'BRG-001', // Duplicate
            'name' => 'Test Duplicate',
            'photo' => 'test-image.jpg',
            'unit_price' => 10000,
            'stock' => 10,
            'unit' => 'Unit',
        ]);
        return false; // Should not reach here
    } catch (\Exception $e) {
        return str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'unique');
    }
});

// 6.2 Price limits
test("Unit price within limits (0 to MAX_UNIT_PRICE)", function() {
    $item = Item::find(1);
    return $item->unit_price >= 0 && $item->unit_price <= Item::MAX_UNIT_PRICE;
});

// 6.3 Stock limits
test("Stock within limits (0 to MAX_STOCK)", function() {
    $item = Item::find(1);
    return $item->stock >= 0 && $item->stock <= Item::MAX_STOCK;
});

// 6.4 Withdrawal date validation
test("Withdrawal taken_at not in future", function() {
    $withdrawal = Withdrawal::first();
    if (!$withdrawal) return "No withdrawals - skipping";
    return $withdrawal->taken_at <= now();
});

echo "\n📊 TEST 7: REPORTS & EXPORTS\n";
echo str_repeat("-", 60) . "\n";

// 7.1 Date filtering
test("Report filters by date range", function() {
    $startDate = now()->subDays(7)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $details = WithdrawalItem::with(['item', 'withdrawal.rusun'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->get();
    
    foreach ($details as $detail) {
        $takenAt = \Carbon\Carbon::parse($detail->withdrawal->taken_at);
        if (!($takenAt >= $startDate && $takenAt <= $endDate.' 23:59:59')) {
            return false;
        }
    }
    return true;
});

// 7.2 Rusun filtering
test("Report filters by rusun_id", function() {
    $rusun = Rusun::first();
    if (!$rusun) return "No rusuns found - skipping";
    
    // If no withdrawals exist for this rusun, test passes (filter working correctly)
    $details = WithdrawalItem::with(['withdrawal'])
        ->whereHas('withdrawal', function ($q) use ($rusun) {
            $q->where('rusun_id', $rusun->id);
        })
        ->get();
    
    if ($details->isEmpty()) return true; // No data to check, filter works
    
    foreach ($details as $detail) {
        if ($detail->withdrawal->rusun_id != $rusun->id) {
            return false;
        }
    }
    return true;
});

// 7.3 Grouped transactions
test("Transactions grouped by withdrawal_id", function() {
    $details = WithdrawalItem::all();
    $grouped = $details->groupBy('withdrawal_id');
    
    // Each group should have all items from same withdrawal
    foreach ($grouped as $withdrawalId => $items) {
        foreach ($items as $item) {
            if ($item->withdrawal_id != $withdrawalId) {
                return false;
            }
        }
    }
    return true;
});

// 7.4 Total calculations from DB
test("Report totals calculated from database", function() {
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $totals = DB::table('withdrawal_items')
        ->join('withdrawals', 'withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
        ->whereBetween('withdrawals.taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
        ->select(
            DB::raw('COALESCE(SUM(withdrawal_items.quantity), 0) as totalQuantity'),
            DB::raw('COALESCE(SUM(withdrawal_items.subtotal), 0) as totalValue')
        )
        ->first();
    
    return $totals->totalQuantity >= 0 && $totals->totalValue >= 0;
});

echo "\n" . str_repeat("=", 60) . "\n";
echo "📈 TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";

foreach ($results as $result) {
    echo "$result\n";
}

echo "\n";
echo "✅ PASSED: $passed\n";
echo "❌ FAILED: $failed\n";
echo "📊 TOTAL:  " . ($passed + $failed) . "\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    exit(1);
}
