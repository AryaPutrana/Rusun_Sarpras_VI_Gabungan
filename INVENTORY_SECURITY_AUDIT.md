# INVENTORY SYSTEM SECURITY AUDIT
**Date:** 2026-10-15  
**System:** Laravel 11 Inventory Module - Items, Withdrawals, Reports  
**Scope:** Stock management, concurrent transactions, validation, reports  

---

## CRITICAL ISSUES

### BUG #1: Race Condition in Stock Adjustment Logic
**Severity:** HIGH  
**Location:** `ItemController::update()` lines 189-221  
**Description:** When using adjustment mode with BOTH add_stock and subtract_stock in same request, the add operation executes first (line 191 increment), then subtract validation (line 205) checks against the ALREADY INCREMENTED value instead of original stock. This allows users to subtract more than the original stock amount.

**Scenario:**
- Original stock: 100 units
- User submits: add_stock=50, subtract_stock=140, subtract_reason="correction"
- Line 191: stock becomes 150 (100 + 50)
- Line 205: checks if 150 >= 140 → PASSES
- Line 211: stock becomes 10 (150 - 140)
- Result: User extracted 90 units more than original stock (140 - 50 = 90 net reduction from 100 to 10)

**Impact:** Stock integrity violation. Allows negative effective stock through combined operations.

**Fix:**
```php
// Capture original stock before any modifications
$originalStock = $locked->stock;

if (!empty($validated['add_stock']) && $validated['add_stock'] > 0) {
    $locked->increment('stock', $validated['add_stock']);
    // audit trail...
}

if (!empty($validated['subtract_stock']) && $validated['subtract_stock'] > 0) {
    // Check against ORIGINAL stock, not current
    if ($originalStock < $validated['subtract_stock']) {
        throw ValidationException::withMessages([
            'subtract_stock' => 'Stok tidak mencukupi. Stok saat ini: '.$originalStock.' unit.',
        ]);
    }
    $locked->decrement('stock', $validated['subtract_stock']);
    // audit trail...
}
```

---

### BUG #2: Decimal Precision Loss in Price Calculations
**Severity:** HIGH  
**Location:** `WithdrawalController::store()` line 182, `::update()` line 377  
**Description:** Monetary calculations use PHP float arithmetic instead of precise decimal math. `unit_price` is DECIMAL(15,2) in database but calculations like `$subtotal = $quantity * $item->unit_price` use floating-point, causing precision errors.

**Example:**
```php
$quantity = 333;
$unit_price = 99.99; // Cast to float
$subtotal = 333 * 99.99; // Float: 33296.67000000001 (precision error)
// Database stores: 33296.67 (rounded)
// Accumulated error over many transactions
```

**Impact:** Financial discrepancies in reports. Cents lost across thousands of transactions. Audit trail corruption.

**Fix:** Use BC Math for all monetary calculations:
```php
$subtotal = bcmul((string)$quantity, (string)$item->unit_price, 2);
$totalValue = bcadd($totalValue, $subtotal, 2);

// Validation:
if (bccomp($totalValue, (string)Withdrawal::MAX_SUBTOTAL, 2) > 0) {
    throw ValidationException::withMessages([...]);
}
```

---

### BUG #3: Potential Integer Overflow in Subtotal Calculation
**Severity:** HIGH  
**Location:** `WithdrawalController::store()` line 182, `::update()` line 377  
**Description:** Individual line item subtotal can overflow DECIMAL(15,2) before total validation.

**Calculation:**
- MAX_QUANTITY = 1,000,000
- MAX_UNIT_PRICE = 1,000,000,000
- Subtotal = 1,000,000 × 1,000,000,000 = 1,000,000,000,000,000 (1 quadrillion)
- DECIMAL(15,2) max = 9,999,999,999,999.99 (9.9 trillion)

**Impact:** MySQL strict mode throws QueryException (HTTP 500). Data corruption in non-strict mode.

**Fix:** Validate individual line items BEFORE accumulation:
```php
foreach ($validated['items'] as $line) {
    $item = $items->get((int)$line['item_id']);
    $quantity = (int)$line['quantity'];
    
    // Validate individual subtotal
    $maxSubtotalPerItem = 9999999999999.99;
    if (bcmul((string)$quantity, (string)$item->unit_price, 2) > (string)$maxSubtotalPerItem) {
        throw ValidationException::withMessages([
            'items' => "Subtotal untuk {$item->name} melebihi batas. Kurangi jumlah atau harga.",
        ]);
    }
    
    $subtotal = bcmul((string)$quantity, (string)$item->unit_price, 2);
    // ... rest of logic
}
```

---

### BUG #4: Excel Export NOT Streaming - Memory Exhaustion Risk
**Severity:** HIGH  
**Location:** `ReportController::exportExcel()` line 329-331  
**Description:** Comment claims "CSV streaming" but query uses `->limit(5000)->get()` which loads ALL 5000 records into memory at once. With eager loading of relationships (`with(['item', 'withdrawal.rusun'])`), this can consume 50-100MB+ RAM.

**Impact:** Production memory exhaustion under concurrent export requests. Server crash.

**Fix:** Use chunked queries:
```php
public function exportExcel(Request $request)
{
    // ... validation ...
    
    $filename = 'laporan-pengambilan-material-'.date('Y-m-d').'.csv';
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ];

    return Response::stream(function () use ($startDate, $endDate, $rusunId, $rusunName) {
        $file = fopen('php://output', 'w');
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        // Headers
        fputcsv($file, ['LAPORAN PENGAMBILAN MATERIAL']);
        // ... other headers ...
        
        $no = 1;
        $totalTransactions = 0;
        $totalQuantity = 0;
        $totalValue = 0.0;
        
        // CHUNK the query instead of loading all
        Withdrawal::with(['rusun', 'items.item'])
            ->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($rusunId && $rusunId != 'all', fn($q) => $q->where('rusun_id', $rusunId))
            ->orderBy('taken_at', 'desc')
            ->chunk(200, function ($withdrawals) use ($file, &$no, &$totalQuantity, &$totalValue) {
                foreach ($withdrawals as $withdrawal) {
                    // ... format and write row ...
                    $totalQuantity += $withdrawal->total_quantity;
                    $totalValue += $withdrawal->total_value;
                    $no++;
                }
            });
        
        // Summary
        fputcsv($file, []);
        fputcsv($file, ['Total Transaksi', $no - 1]);
        fputcsv($file, ['Total Barang Diambil', $totalQuantity]);
        fputcsv($file, ['Total Nilai', 'Rp'.number_format($totalValue, 0, ',', '.')]);
        
        fclose($file);
    }, 200, $headers);
}
```

---

### BUG #5: Report Data Race Condition - Inconsistent Totals
**Severity:** MEDIUM  
**Location:** `ReportController::index()` lines 182-195  
**Description:** Report queries execute in separate statements without transaction isolation:
1. Line 182-184: Get detail rows with limit(5000)
2. Line 188: Count transactions (separate query)
3. Line 191: Calculate totals (separate query)

Between these queries, new withdrawals can be inserted, causing totals to include transactions not shown in details list.

**Impact:** Report shows "Total: 150 transactions, Rp 10,000,000" but table only displays 148 transactions worth Rp 9,800,000. Confusing for auditors.

**Fix:** Use database transaction with REPEATABLE READ or capture timestamp:
```php
$snapshot = now()->toDateTimeString();

$details = $this->withdrawalDetailQuery($startDate, $endDate, $rusunFilterId)
    ->where('withdrawals.created_at', '<=', $snapshot)
    ->limit(5000)
    ->get();

$transactions = $this->groupedByTransaction($details);

$totalTransactions = $this->countTransactions($startDate, $endDate, $rusunFilterId)
    ->where('created_at', '<=', $snapshot)
    ->count();

$totals = $this->detailTotals($startDate, $endDate, $rusunFilterId)
    ->where('created_at', '<=', $snapshot);
```

Or wrap in read-only transaction:
```php
[$transactions, $totalTransactions, $totals] = DB::transaction(function () use (...) {
    // All queries here
    return [$transactions, $total, $totals];
}, 5, null); // attempts, deadlockTimeout
```

---

### BUG #6: Missing Database Index on withdrawal_items.item_id
**Severity:** MEDIUM  
**Location:** `database/migrations/2026_10_05_100000_create_withdrawal_items_table.php`  
**Description:** Foreign key `item_id` constrained but not explicitly indexed. While some databases auto-index foreign keys, MySQL InnoDB does NOT always create optimal index for reverse lookups.

**Query Impact:**
- `WithdrawalController::index()` line 57-59: Search across items via `whereHas('items.item', ...)`
- Item deletion check: `Item::whereHas('withdrawals')` uses reverse relation
- Report queries joining withdrawal_items → items

Without index: Full table scan on withdrawal_items for each item lookup.

**Fix:** Add migration:
```php
Schema::table('withdrawal_items', function (Blueprint $table) {
    $table->index('item_id', 'idx_withdrawal_items_item_id');
});
```

---

## HIGH SEVERITY ISSUES

### ISSUE #7: Authorization Bypass - Missing Role Check on Inventory Routes
**Severity:** HIGH  
**Location:** `routes/web.php` lines 100-117  
**Description:** Inventory routes only protected by `auth` middleware. Comment says "Semua role dapat akses" (all roles can access) but system has 3 roles: admin, petugas, viewer. If viewer role exists and is authenticated, they gain full CRUD access to inventory including deletion.

**Impact:** Privilege escalation. Unauthorized data modification.

**Fix:** Add explicit role middleware:
```php
Route::prefix('inventory')->name('inventory.')
    ->middleware(['auth', 'role:admin,petugas']) // Add role check
    ->group(function () {
        // ... routes
    });
```

Or differentiate permissions:
```php
// Read-only for all authenticated
Route::prefix('inventory')->name('inventory.')->middleware('auth')->group(function () {
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/withdrawals', ...);
    Route::get('/reports', ...);
});

// Write operations for admin + petugas only
Route::prefix('inventory')->name('inventory.')
    ->middleware(['auth', 'role:admin,petugas'])
    ->group(function () {
        Route::resource('items', ItemController::class)->except(['index', 'show']);
        Route::resource('withdrawals', WithdrawalController::class)->except(['index', 'show']);
    });
```

---

### ISSUE #8: File Upload Extension Not Validated
**Severity:** HIGH  
**Location:** `ItemController::store()` line 71, `::update()` line 257  
**Description:** Photo filename uses user-controlled extension: `Str::uuid().'.'.$photo->extension()`. While `ValidImage` rule checks magic bytes, it doesn't restrict extension. Attacker uploads file with double extension like `shell.php.jpg`:
- Magic bytes: Valid JPEG header (passes ValidImage)
- Extension: .jpg (passes file validation)
- Server misconfiguration: Some servers execute .php.jpg as PHP

**Impact:** Remote code execution if storage disk misconfigured or symbolic link created.

**Fix:** Whitelist extensions explicitly:
```php
// In ItemController::store() and ::update()
$photo = $request->file('photo');
$originalExtension = $photo->extension();

// Whitelist only safe image extensions
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array(strtolower($originalExtension), $allowedExtensions, true)) {
    throw ValidationException::withMessages([
        'photo' => 'Ekstensi file tidak diizinkan. Hanya JPG, PNG, dan WebP.',
    ]);
}

// Force extension to lowercase
$filename = Str::uuid().'.'.strtolower($originalExtension);
```

Or force JPEG conversion:
```php
$filename = Str::uuid().'.jpg';
$photo->storeAs('public/items', $filename);

// Re-encode as JPEG to strip metadata and force safe format
$path = Storage::disk('public')->path('items/'.$filename);
$image = imagecreatefromstring(file_get_contents($path));
imagejpeg($image, $path, 85);
imagedestroy($image);
```

---

### ISSUE #9: Photo Deletion Race Condition
**Severity:** MEDIUM  
**Location:** `ItemController::update()` lines 276-286, `::destroy()` lines 315-324  
**Description:** After successful item update/delete, old photo deletion checks if photo still used by other items, then deletes file. Race condition exists:

**Timeline:**
1. Transaction commits, item A updated to new photo
2. Line 277: Check `Item::where('photo', $oldPhoto)->exists()` → FALSE
3. **[RACE WINDOW]** Another request updates item B to use $oldPhoto
4. Line 281: `Storage::delete()` removes file
5. Item B now references deleted file → broken image

**Impact:** Data loss. Broken images in production.

**Fix:** Use advisory locking or accept edge case:
```php
// After transaction commits
if ($oldPhoto) {
    // Sleep briefly to allow concurrent transactions to commit
    usleep(100000); // 100ms
    
    $stillUsed = Item::where('photo', $oldPhoto)
        ->where('id', '!=', $item->id)
        ->lockForUpdate() // Advisory lock
        ->exists();
    
    if (!$stillUsed) {
        Storage::delete('public/items/'.$oldPhoto);
        Cache::forget('item-thumb:'.sha1($oldPhoto.'|44|v1'));
    }
}
```

Or implement soft-delete for files with cleanup job:
```php
// Mark file for deletion instead of immediate delete
\App\Models\OrphanedFile::create([
    'disk' => 'public',
    'path' => 'items/'.$oldPhoto,
    'marked_at' => now(),
]);

// Scheduled job runs daily:
// - Check each orphaned file
// - If no items reference it for 24 hours
// - Delete permanently
```

---

### ISSUE #10: PDF Export Memory Risk Despite Limit
**Severity:** MEDIUM  
**Location:** `ReportController::exportPdf()` lines 252-254, 257-260  
**Description:** PDF limited to 1000 rows and memory boosted to 512M. However:
- Line 258-260: Loads 1000 withdrawal_items with relationships eager loaded
- Each item has photo → `photoThumbnail()` generates base64 image
- 1000 items × 50KB thumbnail average = 50MB just for images
- DomPDF rendering overhead: 3-5× memory multiplier
- Concurrent PDF requests: 3 users × 512M = 1.5GB RAM spike

**Impact:** Out of memory errors during peak usage. Server instability.

**Fix:** 
1. Reduce limit based on photo presence:
```php
$hasPhotos = $this->withdrawalDetailQuery($startDate, $endDate, $rusunId)
    ->whereHas('item', fn($q) => $q->whereNotNull('photo'))
    ->exists();

$pdfLimit = $hasPhotos ? 500 : 1000;
```

2. Queue PDF generation for large reports:
```php
if ($totalCount > 500) {
    \App\Jobs\GeneratePdfReport::dispatch($validated, auth()->user());
    return redirect()->back()->with('info', 
        'Laporan sedang dibuat. Anda akan menerima notifikasi saat selesai.'
    );
}
```

3. Limit thumbnail resolution for PDF:
```php
// In Item::photoThumbnail()
public function photoThumbnail(int $max = 44, bool $forPdf = false): ?array
{
    if ($forPdf && $max > 32) {
        $max = 32; // Force smaller thumbnails in PDF
    }
    // ... rest of method
}
```

---

## MEDIUM SEVERITY ISSUES

### ISSUE #11: No Database Constraint on Negative Stock
**Severity:** MEDIUM  
**Location:** `database/migrations/2026_09_29_032826_create_items_table.php` line 20  
**Description:** `stock` column is `integer('stock')->default(0)` without CHECK constraint. While application logic prevents negative stock through locking, database constraint provides defense in depth. Direct database manipulation or migration bugs could create negative stock.

**Impact:** Data integrity violation. Reports show negative inventory.

**Fix:** Add CHECK constraint:
```php
Schema::table('items', function (Blueprint $table) {
    DB::statement('ALTER TABLE items ADD CONSTRAINT chk_stock_non_negative CHECK (stock >= 0)');
});
```

For min_stock:
```php
DB::statement('ALTER TABLE items ADD CONSTRAINT chk_min_stock_non_negative CHECK (min_stock >= 0)');
```

---

### ISSUE #12: Weak CSV Formula Injection Protection
**Severity:** MEDIUM  
**Location:** `ReportController::exportExcel()` lines 379-385  
**Description:** Formula injection protection checks first character for `=+-@` and prefixes with `'`. However:
1. Only sanitizes string values - numbers pass through
2. Excel DDE attacks: `=cmd|'/c calc'!A1`
3. Google Sheets: `=IMPORTXML()`, `=IMAGE()`

**Current code:**
```php
if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'])) {
    return "'".$value;
}
```

**Impact:** Malicious user sets `taken_by` to `=cmd|'/c calc'!A1`, exports CSV, victim opens → code execution.

**Fix:** Stronger sanitization:
```php
$sanitize = function ($value) {
    if (!is_string($value)) {
        return $value;
    }
    
    $value = trim($value);
    if ($value === '') {
        return $value;
    }
    
    // Check for formula injection patterns
    $dangerousChars = ['=', '+', '-', '@', '\t', '\r', '\n', '|'];
    if (in_array($value[0], $dangerousChars, true)) {
        // Prefix with single quote AND space
        return "' " . $value;
    }
    
    // Check for hidden formulas (tab/newline before =)
    if (preg_match('/^[\t\r\n\s]*[=+\-@]/', $value)) {
        return "' " . $value;
    }
    
    return $value;
};
```

---

### ISSUE #13: Missing CSRF Protection on Export Routes
**Severity:** LOW (False Positive)  
**Location:** `routes/web.php` lines 112-115  
**Description:** Export routes use GET method. CSRF tokens not required for GET requests, but exports should use POST to prevent:
- Link prefetching attacks
- Browser history leaking filtered data
- Accidental caching of sensitive reports

**Impact:** Minor. Report URLs in browser history contain filter parameters.

**Fix:** Change exports to POST:
```php
Route::middleware('throttle:exports')->group(function () {
    Route::post('/reports/export/pdf', [ReportController::class, 'exportPdf'])
        ->name('reports.pdf');
    Route::post('/reports/export/excel', [ReportController::class, 'exportExcel'])
        ->name('reports.excel');
});
```

Update view to use forms instead of links.

---

### ISSUE #14: Rate Limiter Bypass via Multiple Accounts
**Severity:** LOW  
**Location:** `RouteServiceProvider::configureRateLimiting()` line 57-64  
**Description:** Export throttle is per-user ID. Attacker creates multiple petugas accounts → 10 exports/min per account. With 10 accounts = 100 exports/min, bypassing intended rate limit.

**Impact:** Resource exhaustion through distributed accounts.

**Fix:** Add global IP-based limit:
```php
RateLimiter::for('exports', function (Request $request) {
    $user = $request->user();
    
    // Global IP limit: 30/min regardless of user
    $ipLimit = Limit::perMinute(30)->by($request->ip());
    
    if (!$user) {
        return $ipLimit;
    }
    
    // Per-user limit
    $userLimit = $user->role === 'admin' ? 50 : 10;
    $userLimitObj = Limit::perMinute($userLimit)->by($user->id);
    
    // Return both limits (Laravel will enforce strictest)
    return [$ipLimit, $userLimitObj];
});
```

---

## RECOMMENDATIONS (Not Bugs)

### REC #1: Add Audit Trail for Item Deletion
**Priority:** MEDIUM  
**Location:** `ItemController::destroy()`  
**Recommendation:** Currently item deletion prevented if withdrawal history exists. Add soft deletes or permanent audit log:

```php
// Before deletion
\App\Models\AuditLog::create([
    'user_id' => auth()->id(),
    'action' => 'item_deleted',
    'model' => 'Item',
    'model_id' => $item->id,
    'old_values' => $item->toArray(),
    'ip_address' => request()->ip(),
]);

$item->delete();
```

---

### REC #2: Add Stock Adjustment Approval Workflow
**Priority:** LOW  
**Location:** `ItemController::update()` adjustment mode  
**Recommendation:** Large stock adjustments (>100 units or >50% of current stock) should require admin approval:

```php
$adjustment = $validated['add_stock'] ?? 0 + $validated['subtract_stock'] ?? 0;
$percentChange = ($adjustment / $locked->stock) * 100;

if ($adjustment > 100 || $percentChange > 50) {
    \App\Models\StockAdjustmentRequest::create([
        'item_id' => $locked->id,
        'requested_by' => auth()->id(),
        'type' => $validated['add_stock'] > 0 ? 'add' : 'subtract',
        'quantity' => $adjustment,
        'reason' => $validated['subtract_reason'] ?? $validated['stock_change_reason'],
        'status' => 'pending',
    ]);
    
    return redirect()->back()->with('info', 
        'Perubahan stok besar memerlukan persetujuan admin.'
    );
}
```

---

### REC #3: Add Withdrawal Approval for High-Value Items
**Priority:** LOW  
**Location:** `WithdrawalController::store()`  
**Recommendation:** Withdrawals exceeding threshold (e.g., Rp 10,000,000) require supervisor approval:

```php
if ($totalValue > 10000000) {
    $withdrawal->update(['status' => 'pending_approval', 'approved_by' => null]);
    
    // Don't deduct stock yet, deduct on approval
    
    return redirect()->route('inventory.withdrawals.show', $withdrawal)
        ->with('info', 'Pengambilan menunggu persetujuan admin (nilai > Rp 10 juta).');
}
```

---

### REC #4: Add Composite Index for Report Date Range Queries
**Priority:** MEDIUM  
**Location:** Database indexes  
**Recommendation:** While index exists on `taken_at`, add covering index for common report query:

```php
Schema::table('withdrawals', function (Blueprint $table) {
    // Covering index for report aggregation
    $table->index(['taken_at', 'rusun_id', 'total_quantity', 'total_value'], 
        'idx_withdrawals_report_covering');
});
```

This allows index-only scans without table lookups.

---

### REC #5: Implement Stock Level Alerts
**Priority:** LOW  
**Location:** New feature  
**Recommendation:** Add automated alerts when stock reaches min_stock threshold:

```php
// In WithdrawalController after stock deduction
foreach ($detailRows as $row) {
    $item = $items->get($row['item_id']);
    $item->decrement('stock', $row['quantity']);
    
    // Check if stock now below threshold
    if ($item->stock <= $item->min_stock && $item->min_stock > 0) {
        \App\Notifications\LowStockAlert::dispatch($item);
    }
}
```

---

## SUMMARY

**Total Issues Found:** 14 bugs + 5 recommendations  
**Critical:** 3 issues (race condition, decimal precision, overflow)  
**High:** 7 issues (memory exhaustion, auth bypass, file upload)  
**Medium:** 4 issues (indexes, constraints, CSV injection)  

**Priority Fix Order:**
1. BUG #1 - Stock adjustment race condition (data integrity)
2. BUG #2 - Decimal precision in calculations (financial accuracy)
3. BUG #7 - Authorization bypass (security)
4. BUG #3 - Overflow validation (crash prevention)
5. BUG #4 - Excel streaming (production stability)
6. BUG #8 - File extension validation (RCE prevention)
7. BUG #6 - Missing index (performance)
8. BUG #5 - Report race condition (data consistency)

**Estimated Effort:** 3-4 days for all critical/high issues
