# 🔧 RENCANA PERBAIKAN SISTEM
## Sistem Informasi Sarpras UPRS VI - Gabungan

**Tanggal Dibuat:** 15 Oktober 2026  
**Berdasarkan:** BUG_REPORT_GABUNGAN_SISTEM_SARPRAS.md  
**Total Bug:** 21 items (3 Critical, 5 High, 7 Medium, 6 Low)

---

## 📋 OVERVIEW STRATEGI

### Pendekatan
**2-STAGE REPAIR STRATEGY**

**STAGE 1: Critical + High Priority** (8 bugs)
- Focus: Stability, Security, Data Integrity
- Timeline: 3-5 hari kerja
- Risk: HIGH jika tidak diperbaiki
- Branch: `bugfix/stage-1-critical-high`

**STAGE 2: Medium + Low Priority** (13 bugs)
- Focus: Code Quality, UX, Maintainability
- Timeline: 1-2 minggu
- Risk: MEDIUM - bisa ditunda jika resource terbatas
- Branch: `bugfix/stage-2-medium-low`

### Prinsip Eksekusi
✅ **READ FIRST** - Baca file lengkap sebelum edit  
✅ **TEST AFTER EACH FIX** - Jalankan test setelah 2-3 fixes  
✅ **ATOMIC COMMITS** - 1 commit per 3-5 related bugs  
✅ **VERIFY BEFORE PUSH** - Syntax check + manual test  
✅ **ROLLBACK READY** - Document cara undo setiap change  

---

## 🚨 STAGE 1: CRITICAL + HIGH PRIORITY

**Target:** 8 bugs (BUG #1-8)  
**Estimasi:** 3-5 hari kerja  
**Priority:** MUST FIX sebelum production deployment

### EXECUTION ORDER (Easiest → Hardest)

---

### ✅ FIX #1: Add Route-Level Throttling (BUG #4)
**Difficulty:** ⭐ Easy  
**Time:** 30 menit  
**Risk:** Low  

#### Files to Modify:
1. `app/Providers/RouteServiceProvider.php`
2. `routes/web.php`

#### Steps:

**A. Define Named Throttle Limiter**
```php
// app/Providers/RouteServiceProvider.php
// Tambahkan di method boot() setelah throttle API definition

RateLimiter::for('login', function (Request $request) {
    $email = $request->input('email', '');
    return Limit::perMinute(5)->by($email.'|'.$request->ip());
});

RateLimiter::for('exports', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return Limit::perMinute(3);
    }
    $limit = $user->isAdmin() ? 50 : 10;
    return Limit::perMinute($limit)->by($user->id);
});
```

**B. Apply Middleware to Routes**
```php
// routes/web.php line 33
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')  // ✅ ADD THIS
    ->name('login.attempt');

// routes/web.php line 109
Route::middleware(['throttle:exports'])->group(function () {  // ✅ CHANGE throttle:10,1
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])
        ->name('reports.pdf');
    Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])
        ->name('reports.excel');
});
```

#### Verification:
```bash
# Test throttling
for i in {1..10}; do curl -X POST http://localhost:8000/login -d "email=test@test.com&password=wrong"; done
# Should get 429 after 5 attempts

php artisan test --filter=AuthenticationFlowTest
```

#### Rollback:
```bash
git revert <commit-hash>
```

---

### ✅ FIX #2: Add CSRF Test Coverage (BUG #7)
**Difficulty:** ⭐⭐ Medium  
**Time:** 1-2 jam  
**Risk:** Low  

#### Files to Create:
1. `tests/Feature/Security/CsrfProtectionTest.php`

#### Implementation:

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_login_requires_csrf_token()
    {
        $this->withMiddleware();
        
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(419); // Token Mismatch
    }

    public function test_item_creation_requires_csrf_token()
    {
        $this->withMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/inventory/items', [
            'item_code' => 'BRG-001',
            'name' => 'Test Item',
            'unit_price' => 1000,
            'stock' => 10,
            'unit' => 'pcs',
        ]);

        $response->assertStatus(419);
    }

    public function test_withdrawal_creation_requires_csrf_token()
    {
        $this->withMiddleware();
        $user = User::factory()->create(['role' => 'petugas']);

        $response = $this->actingAs($user)->post('/inventory/withdrawals', [
            'items' => [['item_id' => 1, 'quantity' => 1]],
            'taken_by' => 'Test',
            'rusun_id' => 1,
            'taken_at' => now(),
        ]);

        $response->assertStatus(419);
    }

    public function test_delete_routes_require_csrf_token()
    {
        $this->withMiddleware();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->delete('/inventory/items/1');

        $response->assertStatus(419);
    }
}
```

#### Verification:
```bash
php artisan test tests/Feature/Security/CsrfProtectionTest.php
# Should show 4 passing tests
```

---

### ✅ FIX #3: Complete Incomplete Tests (BUG #8)
**Difficulty:** ⭐⭐ Medium  
**Time:** 2-3 jam  
**Risk:** Low  

#### Steps:

**A. Identify Incomplete Tests**
```bash
php artisan test 2>&1 | grep -A 5 "incomplete\|skipped"
# Output will show which tests are incomplete
```

**B. Review and Complete**
Untuk setiap incomplete test:
- Jika implementable → complete the test
- Jika blocked by external dependency → document reason and keep skipped
- Jika no longer relevant → remove test

**C. Target 100% completion atau documented skip reasons**

#### Verification:
```bash
php artisan test
# Target: 0 incomplete, all skipped have clear reasons
```

---

### ✅ FIX #4: Fix Nullable Date Formatting (BUG #1)
**Difficulty:** ⭐⭐⭐ Medium-High  
**Time:** 3-4 jam  
**Risk:** Medium (banyak file)  

#### Strategy:
Audit SEMUA blade templates dan tambahkan nullsafe operator.

#### Files to Check:
```bash
# Generate list of files with date formatting
grep -r "->format\|->translatedFormat" resources/views/ --include="*.blade.php" -l
```

#### Pattern to Find & Replace:

**FIND:**
```blade
{{ $item->created_at->format('d/m/Y') }}
{{ $withdrawal->taken_at->translatedFormat('d M Y') }}
{{ $monitoring->tanggal->format('Y-m-d') }}
```

**REPLACE:**
```blade
{{ $item->created_at?->format('d/m/Y') ?? '-' }}
{{ $withdrawal->taken_at?->translatedFormat('d M Y') ?? '-' }}
{{ $monitoring->tanggal?->format('Y-m-d') ?? '-' }}
```

#### Critical Files (from audit):
1. `resources/views/inventory/items/index.blade.php`
2. `resources/views/inventory/items/show.blade.php`
3. `resources/views/inventory/withdrawals/index.blade.php`
4. `resources/views/inventory/withdrawals/show.blade.php`
5. `resources/views/inventory/reports/index.blade.php`
6. `resources/views/dashboard.blade.php`
7. `resources/views/monitoring/*.blade.php`

#### Verification:
```bash
# Create test with null dates
php artisan tinker
DB::table('items')->insert(['item_code' => 'TEST-NULL', 'name' => 'Test', 'created_at' => null, ...]);

# Visit pages, should see '-' instead of crash
php artisan serve
# Navigate to /inventory/items
```

#### Automated Check:
```bash
# Verify all date accesses use nullsafe
grep -r "\->\(format\|translatedFormat\)" resources/views/ --include="*.blade.php" | grep -v "?->"
# Should return 0 results (or only safe ones like now()->format())
```

---

### ✅ FIX #5: Fix Cache Poisoning Risk (BUG #2)
**Difficulty:** ⭐⭐⭐ Medium-High  
**Time:** 2-3 jam  
**Risk:** Medium  

#### Files to Modify:
1. `app/Http/Controllers/Inventory/ItemController.php`
2. `app/Models/Item.php`

#### Strategy:
Move `Cache::forget()` INSIDE transaction (safe, idempotent) dan ensure `photoThumbnail()` TIDAK dipanggil dalam transaction.

#### Changes:

**A. ItemController Update Method**
```php
// Line 290-301 - AFTER transaction succeeds
if ($oldPhoto) {
    $stillUsed = Item::where('photo', $oldPhoto)
        ->where('id', '!=', $item->id)
        ->exists();
    if (!$stillUsed) {
        Storage::delete('public/items/'.$oldPhoto);
        
        // ✅ SAFE: Cache::forget() is idempotent
        $oldKey = 'item-thumb:'.sha1($oldPhoto.'|44|v1');
        Cache::forget($oldKey);
    }
}
```

**B. ItemController Destroy Method**
```php
// Line 323-338 - Move cache invalidation INSIDE transaction
DB::transaction(function () use ($item, $photoPath) {
    $item->delete();

    if ($photoPath) {
        $stillUsed = Item::where('photo', $photoPath)->exists();
        
        if (!$stillUsed) {
            Storage::delete('public/items/'.$photoPath);

            // ✅ MOVE THIS INSIDE transaction
            $cacheKey = 'item-thumb:'.sha1($photoPath.'|44|v1');
            Cache::forget($cacheKey);
        }
    }
});
```

**C. Add Comment Warning**
```php
// Add to Item::photoThumbnail() method
/**
 * WARNING: Do NOT call this method inside DB::transaction()
 * Cache operations are not transactional and will cause orphaned entries.
 * Generate thumbnails on-demand (lazy loading) after transaction commits.
 * 
 * @param int $max Maximum dimension in pixels
 * @return array{data: string, width: int, height: int}|null
 */
public function photoThumbnail(int $max = 44): ?array
```

#### Verification:
```bash
# Test item creation with transaction rollback
php artisan tinker
DB::transaction(function() {
    $item = Item::create([...]);
    // Simulate error
    throw new Exception('Test rollback');
});

# Check cache - should be empty for that item
Cache::get('item-thumb:...');  // Should be null
```

---

### ✅ FIX #6: Add Memory Protection for PDF Export (BUG #3)
**Difficulty:** ⭐⭐⭐⭐ Hard  
**Time:** 3-4 jam  
**Risk:** Medium-High  

#### Files to Modify:
1. `app/Http/Controllers/Inventory/ReportController.php`

#### Strategy:
- Reduce limit dari 1000 → 500
- Add memory check
- Compress thumbnails lebih agresif
- Add user warning

#### Changes:

**A. Reduce PDF Limit**
```php
// Line 237-240
// OLD: $pdfLimit = 1000;
const PDF_EXPORT_LIMIT = 500;  // ✅ Reduced from 1000

$pdfLimit = self::PDF_EXPORT_LIMIT;

$details = $this->withdrawalDetailQuery($startDate, $endDate, $rusunId)
    ->limit($pdfLimit)
    ->get();
```

**B. Add Memory Check**
```php
// Add after line 245
// Memory safety check
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
$currentUsage = memory_get_usage(true);
$availableMemory = $memoryLimitBytes - $currentUsage;

// Estimate: 100KB per item with photo
$estimatedNeeded = $details->count() * 100 * 1024;

if ($estimatedNeeded > $availableMemory * 0.8) {
    return redirect()->route('inventory.reports.index')
        ->with('error', 'Data terlalu besar untuk PDF. Gunakan Excel export atau kurangi rentang tanggal.');
}
```

**C. Add Helper Method**
```php
// Add to ReportController
/**
 * Parse PHP memory_limit string to bytes.
 * 
 * @param string $limit e.g., "128M", "1G"
 * @return int Bytes
 */
private function parseMemoryLimit(string $limit): int
{
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit)-1]);
    $value = (int) $limit;
    
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}
```

**D. Update View Warning**
```blade
// resources/views/inventory/reports/index.blade.php
@if($detailLimitReached)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Perhatian:</strong> Data melebihi {{ $detailLimitReached ? 5000 : 500 }} baris.
        Untuk dataset besar, gunakan <strong>Excel Export</strong> yang lebih efisien.
    </div>
@endif
```

#### Verification:
```bash
# Test with large dataset
php artisan tinker
// Create 600 withdrawals with items
Withdrawal::factory()->count(600)->has(WithdrawalItem::factory()->count(3))->create();

// Try PDF export
# Should either:
# 1. Succeed if memory sufficient
# 2. Show warning if memory insufficient
# 3. NOT crash with HTTP 500
```

---

### ✅ FIX #7: Simplify Stock Mode Validation (BUG #5)
**Difficulty:** ⭐⭐⭐⭐ Hard  
**Time:** 2-3 jam  
**Risk:** High (critical business logic)  

#### Files to Modify:
1. `app/Http/Controllers/Inventory/ItemController.php`

#### Strategy:
**REQUIRE** stock_mode field explicitly. Reject mixed inputs.

#### Changes:

**A. Add Validation Rule**
```php
// Line 128 - Add stock_mode as required
$validated = $request->validate([
    'item_code' => 'required|max:50|unique:items,item_code,'.$id,
    'name' => 'required|max:255',
    'photo' => ['nullable', 'file', new ValidImage(), 'max:5120'],
    'unit_price' => ['required', 'numeric', 'min:0', 'max:'.Item::MAX_UNIT_PRICE],
    'min_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
    'unit' => 'required|max:50',
    'description' => 'nullable|string|max:60000',
    'stock_mode' => ['required', 'in:adjustment,direct'],  // ✅ WAJIB
    'add_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_ADD_STOCK],
    'subtract_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
    'subtract_reason' => ['nullable', 'string', 'max:255'],
    'new_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
    'stock_change_reason' => ['nullable', 'string', 'max:255'],
], [
    'stock_mode.required' => 'Pilih mode update stok.',
    // ... other messages
]);
```

**B. Remove Auto-Detection Logic**
```php
// DELETE lines 149-162 (auto-detection code)
// REPLACE with simple assignment:
$stockMode = $validated['stock_mode'];
```

**C. Add Mixed Fields Detection**
```php
// Add after line 162
// Detect mixed fields submission (user error atau JS manipulation)
$hasAdjustmentInput = (!empty($validated['add_stock']) && $validated['add_stock'] > 0) 
                   || (!empty($validated['subtract_stock']) && $validated['subtract_stock'] > 0);
$hasDirectInput = isset($validated['new_stock']) && $validated['new_stock'] != $item->stock;

if ($stockMode === 'adjustment' && $hasDirectInput) {
    throw ValidationException::withMessages([
        'new_stock' => 'Mode Adjustment tidak menerima input Stok Baru. Kosongkan field ini.',
    ]);
}

if ($stockMode === 'direct' && $hasAdjustmentInput) {
    throw ValidationException::withMessages([
        'add_stock' => 'Mode Edit Langsung tidak menerima input Tambah/Kurangi Stok. Kosongkan fields ini.',
    ]);
}
```

#### Verification:
```bash
php artisan test tests/Feature/Inventory/StockManagementTest.php
# All stock-related tests should pass

# Manual test:
# 1. Edit item, pilih Adjustment, isi add_stock
# 2. Submit → Success
# 3. Edit item, pilih Direct, isi new_stock DAN add_stock
# 4. Submit → Validation error
```

---

### ✅ FIX #8: Fix JS Value Preservation (BUG #6)
**Difficulty:** ⭐⭐⭐ Medium-High  
**Time:** 1-2 jam  
**Risk:** Medium (UX critical)  

#### Files to Modify:
1. `resources/views/inventory/items/edit.blade.php`

#### Changes:

**A. Preserve old() Values on Page Load**
```javascript
// Line 246-262 - REWRITE DOMContentLoaded handler
document.addEventListener('DOMContentLoaded', function() {
    // ONLY set visibility, DON'T reset values
    const mode = document.querySelector('input[name="stock_mode"]:checked').value;
    const adjustmentSection = document.getElementById('adjustment_mode_section');
    const directSection = document.getElementById('direct_mode_section');
    
    // Show/hide sections based on mode
    if (mode === 'adjustment') {
        adjustmentSection.style.display = 'block';
        directSection.style.display = 'none';
    } else {
        adjustmentSection.style.display = 'none';
        directSection.style.display = 'block';
    }
    
    // ✅ PRESERVE old() values - DON'T reset on load
    // Values are already set by Blade {{ old('field') }}
    
    // Check subtract reason visibility based on CURRENT value
    toggleSubtractReason();
});
```

**B. Only Reset on Mode CHANGE (user action)**
```javascript
// Line 188-229 - UPDATE toggleStockMode()
function toggleStockMode() {
    const mode = document.querySelector('input[name="stock_mode"]:checked').value;
    const adjustmentSection = document.getElementById('adjustment_mode_section');
    const directSection = document.getElementById('direct_mode_section');
    
    if (mode === 'adjustment') {
        adjustmentSection.style.display = 'block';
        directSection.style.display = 'none';
        
        // Reset direct mode fields (user switched away from them)
        document.getElementById('new_stock').value = {{ $item->stock }};
        document.getElementById('stock_change_reason').value = '';
    } else {
        adjustmentSection.style.display = 'none';
        directSection.style.display = 'block';
        
        // Reset adjustment mode fields (user switched away from them)
        document.getElementById('add_stock').value = '';
        document.getElementById('subtract_stock').value = '';
        document.getElementById('subtract_reason').value = '';
        document.getElementById('subtract_reason_wrapper').style.display = 'none';
    }
}

// Attach to radio change event ONLY (not on page load)
document.querySelectorAll('input[name="stock_mode"]').forEach(radio => {
    radio.addEventListener('change', toggleStockMode);
});
```

#### Verification:
```bash
# Manual test:
# 1. Edit item
# 2. Pilih mode Direct, isi new_stock = 500, KOSONGKAN reason
# 3. Submit → Validation error (reason required)
# 4. Back to form
# 5. ✅ new_stock should still show 500 (from old())
# 6. ❌ OLD BUG: would reset to current stock
```

---

### 📊 STAGE 1 SUMMARY

**Total Fixes:** 8 bugs  
**Estimated Time:** 15-20 jam (3-5 hari @ 4-5 jam/hari)  
**Files Modified:** ~15 files  
**Tests Added:** ~10 new tests  

**Git Strategy:**
```bash
# Start stage 1
git checkout main
git pull origin main
git checkout -b bugfix/stage-1-critical-high

# After each 2-3 fixes
git add .
git commit -m "Fix: BUG #1-3 - Throttling, CSRF tests, incomplete tests"
php artisan test  # ✅ Verify

# Continue...
git commit -m "Fix: BUG #4-6 - Nullable dates, cache poisoning, memory protection"
php artisan test  # ✅ Verify

git commit -m "Fix: BUG #7-8 - Stock validation, JS preservation"
php artisan test  # ✅ Verify

# Final verification
php artisan test
php -l app/**/*.php  # Syntax check
npm run build  # ✅ Vite build success

# Push for review
git push origin bugfix/stage-1-critical-high
# Create PR to main
```

**Verification Checklist:**
- [ ] All tests pass (target: 327+ tests, 0 incomplete)
- [ ] No syntax errors
- [ ] Manual testing on key flows:
  - [ ] Login (with throttle test)
  - [ ] Item create/edit (stock management)
  - [ ] Withdrawal create/edit
  - [ ] PDF export (memory test)
- [ ] Code review by senior dev
- [ ] Merge to main

---

## 🔄 STAGE 2: MEDIUM + LOW PRIORITY

**Target:** 13 bugs (BUG #9-21)  
**Estimasi:** 1-2 minggu  
**Priority:** Backlog (bisa ditunda jika resource terbatas)

### Quick Overview (Detail can be expanded later)

**Medium Priority (7 bugs):**
- BUG #9: i18n untuk error messages (3-4 jam)
- BUG #10: Audit trail untuk withdrawal changes (4-6 jam)
- BUG #11: Named throttle untuk exports (1 jam)
- BUG #12: Database name standardization (30 menit)
- BUG #13: WEBP validation + test (2 jam)
- BUG #14: Index verification + EXPLAIN queries (3-4 jam)
- BUG #15: Photo storage error handling (2 jam)

**Low Priority (6 bugs):**
- BUG #16: Cleanup unused migrations (1 jam)
- BUG #17: Add PHPDoc (4-6 jam)
- BUG #18: Replace magic numbers with constants (2-3 jam)
- BUG #19: Add metrics/logging (6-8 jam)
- BUG #20: Remove inline styles (2-3 jam)
- BUG #21: Backup documentation (2-3 jam)

**Total Stage 2 Time:** 35-45 jam (1-2 minggu)

---

## 📖 POST-REPAIR ACTIVITIES

### 1. Documentation Update
- [ ] Update README.md dengan bug fixes
- [ ] Create CHANGELOG.md
- [ ] Document new features (named throttling, memory protection)
- [ ] Update deployment guide

### 2. Knowledge Transfer
- [ ] Team walkthrough untuk critical fixes
- [ ] Update onboarding docs
- [ ] Create troubleshooting guide

### 3. Monitoring Setup
- [ ] Setup error tracking (Sentry/Bugsnag)
- [ ] Add performance monitoring
- [ ] Configure log rotation
- [ ] Setup backup automation

### 4. Continuous Improvement
- [ ] Setup CI/CD pipeline
- [ ] Add pre-commit hooks (PHP CS Fixer, PHPStan)
- [ ] Schedule monthly code reviews
- [ ] Plan for Laravel 11 upgrade

---

## 🎯 SUCCESS CRITERIA

### Stage 1 Success:
✅ All 8 critical+high bugs fixed  
✅ Test passing rate: 100% (0 incomplete)  
✅ No production crashes for 1 week after deployment  
✅ Memory usage stable on PDF exports  
✅ Login throttling working correctly  

### Stage 2 Success:
✅ All 13 medium+low bugs addressed  
✅ Code quality score improved  
✅ Documentation complete  
✅ Team trained on new patterns  

### Overall Success:
✅ Zero regression bugs introduced  
✅ User satisfaction improved (fewer errors)  
✅ System performance stable  
✅ Technical debt reduced by 70%  
✅ Ready for next feature development  

---

## ⚠️ RISK MITIGATION

### High-Risk Changes
**FIX #4 (Nullable dates)** - Many files touched
- Mitigation: Test thoroughly, create backup branch
- Rollback: Keep git history clean

**FIX #5 (Stock validation)** - Business logic
- Mitigation: Extensive testing, QA review
- Rollback: Feature flag to revert to old logic

**FIX #6 (Memory protection)** - Performance impact
- Mitigation: Load testing before production
- Rollback: Increase memory_limit if users complain

### Testing Strategy
```bash
# Before each commit
php artisan test

# Before push
php artisan test --coverage-html coverage
# Review coverage report

# Before merge to main
php artisan test --parallel
php artisan serve
# Manual QA checklist
```

---

## 📞 SUPPORT & ESCALATION

**Jika menemui masalah saat eksekusi:**

1. **Blocker ditemukan?**
   - Document blocker
   - Skip bug temporarily
   - Continue dengan bug lain
   - Eskalasi ke team lead

2. **Test gagal setelah fix?**
   - Analyze root cause
   - Check if test expectation wrong
   - Revert commit jika tidak bisa fix dalam 1 jam
   - Get second opinion

3. **Production hotfix needed?**
   - Create separate hotfix branch
   - Fast-track review
   - Deploy immediately
   - Backport to main

---

**READY TO EXECUTE**  
Status: ⏸️ **WAITING FOR APPROVAL**

**Next Action:** Review dokumen ini, approve scope, dan berikan signal "LANJUTKAN PERBAIKAN"

---

**Signature:** Kiro AI Agent  
**Approved By:** _____________  
**Start Date:** _____________  
**Expected Completion:** _____________
