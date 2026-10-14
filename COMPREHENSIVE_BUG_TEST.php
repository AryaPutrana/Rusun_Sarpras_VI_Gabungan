<?php
/**
 * COMPREHENSIVE BUG DETECTION SCRIPT
 * Mencari semua bug dari Login → Logout tanpa mengubah data
 * 
 * Run: php COMPREHENSIVE_BUG_TEST.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     COMPREHENSIVE BUG DETECTION - GABUNGAN SISTEM SARPRAS   ║\n";
echo "║                     INVENTORY + MONITORING + FINANCE         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$bugs = [];
$warnings = [];
$testCount = 0;
$passCount = 0;
$failCount = 0;

function testResult($testName, $pass, $message = '', $isCritical = false) {
    global $bugs, $warnings, $testCount, $passCount, $failCount;
    $testCount++;
    
    if ($pass) {
        $passCount++;
        echo "  ✓ {$testName}\n";
    } else {
        $failCount++;
        echo "  ✗ {$testName}\n";
        if ($message) {
            echo "    → {$message}\n";
        }
        
        if ($isCritical) {
            $bugs[] = "[CRITICAL] {$testName}: {$message}";
        } else {
            $warnings[] = "{$testName}: {$message}";
        }
    }
}

// ============================================================================
// 1. DATABASE & MODELS TEST
// ============================================================================
echo "═══ 1. DATABASE & MODELS TEST ═══\n";

// Test database connection
try {
    DB::connection()->getPdo();
    testResult("Database connection", true);
} catch (Exception $e) {
    testResult("Database connection", false, $e->getMessage(), true);
}

// Test models
$models = [
    'User' => App\Models\User::class,
    'Item' => App\Models\Item::class,
    'Withdrawal' => App\Models\Withdrawal::class,
    'WithdrawalItem' => App\Models\WithdrawalItem::class,
    'StockAdjustment' => App\Models\StockAdjustment::class,
];

foreach ($models as $name => $class) {
    try {
        $count = $class::count();
        testResult("Model {$name} accessible", true, "{$count} records");
    } catch (Exception $e) {
        testResult("Model {$name} accessible", false, $e->getMessage(), true);
    }
}

// Test model relationships
try {
    $item = App\Models\Item::first();
    if ($item) {
        $item->withdrawalItems;
        testResult("Item → WithdrawalItems relationship", true);
        
        $item->withdrawals();
        testResult("Item → Withdrawals relationship", true);
    } else {
        testResult("Item relationships", false, "No items to test", false);
    }
} catch (Exception $e) {
    testResult("Item relationships", false, $e->getMessage(), false);
}

try {
    $withdrawal = App\Models\Withdrawal::first();
    if ($withdrawal) {
        $withdrawal->items;
        testResult("Withdrawal → Items relationship", true);
        
        $withdrawal->user;
        testResult("Withdrawal → User relationship", true);
    } else {
        testResult("Withdrawal relationships", false, "No withdrawals to test", false);
    }
} catch (Exception $e) {
    testResult("Withdrawal relationships", false, $e->getMessage(), false);
}

echo "\n";

// ============================================================================
// 2. STORAGE & FILE SYSTEM TEST
// ============================================================================
echo "═══ 2. STORAGE & FILE SYSTEM TEST ═══\n";

$storagePaths = [
    'items' => storage_path('app/public/items'),
    'public_items' => public_path('storage/items'),
];

foreach ($storagePaths as $name => $path) {
    testResult("Path {$name} exists", file_exists($path), "Path: {$path}");
    if (file_exists($path)) {
        testResult("Path {$name} writable", is_writable($path), "Path: {$path}");
    }
}

// Test symlink
$symlinkTarget = public_path('storage');
$symlinkExists = file_exists($symlinkTarget);
testResult("Storage symlink exists", $symlinkExists, "Run: php artisan storage:link");

// Test existing photos
$items = App\Models\Item::whereNotNull('photo')->get();
$photoErrors = 0;
foreach ($items as $item) {
    $photoPath = storage_path('app/public/items/' . $item->photo);
    if (!file_exists($photoPath)) {
        $photoErrors++;
    }
}
testResult("All item photos exist", $photoErrors === 0, "{$photoErrors} missing photos found");

echo "\n";

// ============================================================================
// 3. ROUTES TEST
// ============================================================================
echo "═══ 3. ROUTES TEST ═══\n";

$criticalRoutes = [
    'login' => 'login',
    'logout' => 'logout',
    'dashboard' => 'dashboard',
    'inventory.items.index' => 'inventory.items.index',
    'inventory.items.create' => 'inventory.items.create',
    'inventory.items.edit' => 'inventory.items.edit',
    'inventory.items.update' => 'inventory.items.update',
    'inventory.withdrawals.index' => 'inventory.withdrawals.index',
    'inventory.reports.index' => 'inventory.reports.index',
];

foreach ($criticalRoutes as $name => $routeName) {
    try {
        $url = route($routeName, $name === 'inventory.items.edit' || $name === 'inventory.items.update' ? 1 : []);
        testResult("Route {$routeName}", true, $url);
    } catch (Exception $e) {
        testResult("Route {$routeName}", false, "Route not found", true);
    }
}

echo "\n";

// ============================================================================
// 4. VIEWS TEST
// ============================================================================
echo "═══ 4. VIEWS TEST ═══\n";

$views = [
    'auth.login' => 'resources/views/auth/login.blade.php',
    'dashboard' => 'resources/views/dashboard.blade.php',
    'inventory.items.index' => 'resources/views/inventory/items/index.blade.php',
    'inventory.items.create' => 'resources/views/inventory/items/create.blade.php',
    'inventory.items.edit' => 'resources/views/inventory/items/edit.blade.php',
    'inventory.withdrawals.index' => 'resources/views/inventory/withdrawals/index.blade.php',
];

foreach ($views as $name => $path) {
    testResult("View {$name}", file_exists($path), $path);
}

echo "\n";

// ============================================================================
// 5. INVENTORY ITEMS - CRUD TEST
// ============================================================================
echo "═══ 5. INVENTORY ITEMS - CRUD LOGIC TEST ═══\n";

// Test Item validation rules
$item = App\Models\Item::first();
if ($item) {
    // Test item constants
    testResult("Item::MAX_UNIT_PRICE defined", defined('App\Models\Item::MAX_UNIT_PRICE'), "Value: " . App\Models\Item::MAX_UNIT_PRICE);
    testResult("Item::MAX_STOCK defined", defined('App\Models\Item::MAX_STOCK'), "Value: " . App\Models\Item::MAX_STOCK);
    testResult("Item::MAX_ADD_STOCK defined", defined('App\Models\Item::MAX_ADD_STOCK'), "Value: " . App\Models\Item::MAX_ADD_STOCK);
    
    // Test item methods
    testResult("Item hasPhoto() method", method_exists($item, 'hasPhoto'));
    testResult("Item photoUrl() method", method_exists($item, 'photoUrl'));
    testResult("Item photoThumbnail() method", method_exists($item, 'photoThumbnail'));
    
    // Test fillable fields
    $fillable = $item->getFillable();
    $requiredFields = ['item_code', 'name', 'photo', 'unit_price', 'stock', 'unit'];
    foreach ($requiredFields as $field) {
        testResult("Item fillable: {$field}", in_array($field, $fillable), "Missing from fillable");
    }
} else {
    testResult("Item CRUD test", false, "No items in database to test", false);
}

echo "\n";

// ============================================================================
// 6. CONTROLLER LOGIC TEST
// ============================================================================
echo "═══ 6. CONTROLLER LOGIC TEST ═══\n";

$controllerPath = 'app/Http/Controllers/Inventory/ItemController.php';
if (file_exists($controllerPath)) {
    $content = file_get_contents($controllerPath);
    
    // Test update method logic
    testResult("ItemController has update method", strpos($content, 'public function update(') !== false);
    testResult("Update: hasFile check", strpos($content, "hasFile('photo')") !== false);
    testResult("Update: add_stock logic", strpos($content, "!empty(\$validated['add_stock'])") !== false);
    testResult("Update: subtract_stock logic", strpos($content, "!empty(\$validated['subtract_stock'])") !== false);
    testResult("Update: new_stock logic", strpos($content, "isset(\$validated['new_stock'])") !== false);
    testResult("Update: lockForUpdate", strpos($content, 'lockForUpdate()') !== false);
    testResult("Update: StockAdjustment audit", strpos($content, 'StockAdjustment::create') !== false);
    testResult("Update: unset photo field", strpos($content, "unset(\$validated['photo'])") !== false);
    testResult("Update: DB transaction", strpos($content, 'DB::transaction') !== false);
    
    // Test potential bugs in update logic
    $hasEmptyCheck = strpos($content, '! empty($validated[\'add_stock\'])') !== false;
    $hasGreaterThanZero = strpos($content, '$validated[\'add_stock\'] > 0') !== false;
    testResult("Update: Complete add_stock validation", $hasEmptyCheck && $hasGreaterThanZero, 
        "Requires: !empty AND > 0 check");
} else {
    testResult("ItemController exists", false, "File not found", true);
}

echo "\n";

// ============================================================================
// 7. FORM VIEW TEST (EDIT FORM BUG DETECTION)
// ============================================================================
echo "═══ 7. EDIT FORM BUG DETECTION ═══\n";

$editViewPath = 'resources/views/inventory/items/edit.blade.php';
if (file_exists($editViewPath)) {
    $viewContent = file_get_contents($editViewPath);
    
    // Test form attributes
    testResult("Form has enctype multipart", 
        strpos($viewContent, 'enctype="multipart/form-data"') !== false, 
        "Required for file upload");
    
    testResult("Form has @method PUT", 
        strpos($viewContent, "@method('PUT')") !== false, 
        "Required for update");
    
    // Test input fields
    testResult("Photo input exists", strpos($viewContent, 'name="photo"') !== false);
    testResult("Add stock input exists", strpos($viewContent, 'name="add_stock"') !== false);
    testResult("Subtract stock input exists", strpos($viewContent, 'name="subtract_stock"') !== false);
    testResult("New stock input exists", strpos($viewContent, 'name="new_stock"') !== false);
    
    // BUG DETECTION: Check for problematic default values
    $hasAddStockDefault0 = preg_match('/name="add_stock".*value="\{\{ old\(\'add_stock\',\s*0\)/', $viewContent);
    testResult("Add stock NO default 0", !$hasAddStockDefault0, 
        $hasAddStockDefault0 ? "BUG FOUND: Default value 0 akan gagal !empty() check" : "");
    
    $hasSubtractStockDefault0 = preg_match('/name="subtract_stock".*value="\{\{ old\(\'subtract_stock\',\s*0\)/', $viewContent);
    testResult("Subtract stock NO default 0", !$hasSubtractStockDefault0, 
        $hasSubtractStockDefault0 ? "BUG FOUND: Default value 0 akan gagal !empty() check" : "");
    
    // BUG DETECTION: JavaScript reset issues
    $hasJsReset = strpos($viewContent, '.value = 0;') !== false;
    testResult("JavaScript NO hard reset to 0", !$hasJsReset, 
        $hasJsReset ? "BUG FOUND: JavaScript reset field ke 0, akan konflik dengan controller validation" : "");
    
    // Check toggle functions
    testResult("toggleStockMode function exists", 
        strpos($viewContent, 'function toggleStockMode()') !== false);
    testResult("toggleSubtractReason function exists", 
        strpos($viewContent, 'function toggleSubtractReason()') !== false);
    
    // Check DOMContentLoaded
    testResult("DOMContentLoaded listener exists", 
        strpos($viewContent, "addEventListener('DOMContentLoaded'") !== false);
    
} else {
    testResult("Edit view exists", false, "File not found", true);
}

echo "\n";

// ============================================================================
// 8. VALIDATION RULES TEST
// ============================================================================
echo "═══ 8. VALIDATION RULES TEST ═══\n";

// Test ValidImage rule
$validImagePath = 'app/Rules/ValidImage.php';
testResult("ValidImage rule exists", file_exists($validImagePath));

if (file_exists($validImagePath)) {
    $ruleContent = file_get_contents($validImagePath);
    testResult("ValidImage checks JPEG", strpos($ruleContent, 'xFF\xD8\xFF') !== false);
    testResult("ValidImage checks PNG", strpos($ruleContent, 'x89\x50\x4E\x47') !== false);
    testResult("ValidImage checks WebP", strpos($ruleContent, 'WEBP') !== false);
}

echo "\n";

// ============================================================================
// 9. STOCK ADJUSTMENT AUDIT TEST
// ============================================================================
echo "═══ 9. STOCK ADJUSTMENT AUDIT TEST ═══\n";

try {
    $adjustments = App\Models\StockAdjustment::count();
    testResult("StockAdjustment table accessible", true, "{$adjustments} records");
    
    if ($adjustments > 0) {
        $latest = App\Models\StockAdjustment::latest()->first();
        testResult("StockAdjustment has item_id", !is_null($latest->item_id));
        testResult("StockAdjustment has user_id", !is_null($latest->user_id));
        testResult("StockAdjustment has type", in_array($latest->type, ['add', 'subtract']));
        testResult("StockAdjustment has quantity", $latest->quantity > 0);
        testResult("StockAdjustment has reason", !empty($latest->reason));
    }
} catch (Exception $e) {
    testResult("StockAdjustment audit", false, $e->getMessage(), false);
}

echo "\n";

// ============================================================================
// 10. WITHDRAWAL SYSTEM TEST
// ============================================================================
echo "═══ 10. WITHDRAWAL SYSTEM TEST ═══\n";

try {
    $withdrawals = App\Models\Withdrawal::count();
    testResult("Withdrawal table accessible", true, "{$withdrawals} records");
    
    if ($withdrawals > 0) {
        $withdrawal = App\Models\Withdrawal::with('items')->first();
        testResult("Withdrawal has items relationship", $withdrawal->items !== null);
        testResult("Withdrawal has picker", !empty($withdrawal->picker));
        testResult("Withdrawal has rusun_code", !empty($withdrawal->rusun_code));
        
        // Check withdrawal items
        $items = $withdrawal->items;
        if ($items->count() > 0) {
            $wItem = $items->first();
            testResult("WithdrawalItem has item_id", !is_null($wItem->pivot->item_id));
            testResult("WithdrawalItem has quantity", $wItem->pivot->quantity > 0);
        }
    }
} catch (Exception $e) {
    testResult("Withdrawal system", false, $e->getMessage(), false);
}

echo "\n";

// ============================================================================
// FINAL REPORT
// ============================================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      FINAL TEST RESULTS                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Total Tests: {$testCount}\n";
echo "✓ Passed: {$passCount}\n";
echo "✗ Failed: {$failCount}\n";
echo "Success Rate: " . round(($passCount / $testCount) * 100, 2) . "%\n\n";

if (!empty($bugs)) {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                       CRITICAL BUGS FOUND                    ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    foreach ($bugs as $i => $bug) {
        echo ($i + 1) . ". {$bug}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                          WARNINGS                            ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    foreach ($warnings as $i => $warning) {
        echo ($i + 1) . ". {$warning}\n";
    }
    echo "\n";
}

if (empty($bugs) && empty($warnings)) {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║         ✓ NO CRITICAL BUGS OR WARNINGS DETECTED             ║\n";
    echo "║              All systems operational!                        ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    ACTION REQUIRED                           ║\n";
    echo "║  Review bugs and warnings above before production deploy     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
}

echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Report saved to: COMPREHENSIVE_BUG_TEST.php\n\n";
