# RENCANA PERBAIKAN BUG - 2 TAHAP
**Sistem**: Gabungan Sistem Sarpras  
**Total Bug**: 17 bug  
**Tanggal**: 12 Oktober 2026  

---

## STRATEGI PERBAIKAN

Perbaikan dibagi menjadi 2 tahap berdasarkan prioritas dan complexity:

### **TAHAP 1** - CRITICAL & HIGH PRIORITY ⚡
**Target**: Bug yang mempengaruhi fungsionalitas inti dan keamanan data  
**Estimasi Waktu**: 2-3 jam  
**Jumlah Bug**: 9 bug (3 Critical + 6 High Priority)

### **TAHAP 2** - MEDIUM & LOW PRIORITY + IMPROVEMENTS 🔧
**Target**: Bug UX, optimization, dan code quality  
**Estimasi Waktu**: 1-2 jam  
**Jumlah Bug**: 8 bug (5 Medium + 3 Low)

---

# TAHAP 1: CRITICAL & HIGH PRIORITY
**Fokus**: Perbaikan fungsionalitas dan data integrity

## 🔴 CRITICAL BUGS (3 bug)

### ✅ BUG #1: Dashboard Filter "Bulan Ini" Salah Hitung Data
**File**: `app/Http/Controllers/DashboardController.php`  
**Baris**: 241-245  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 20 menit  

**Perbaikan**:
1. Debug logika `resolveDateRange()` untuk preset "bulan_ini"
2. Pastikan query `whereBetween()` menggunakan start/end of month yang benar
3. Jalankan test untuk verifikasi fix
4. Update test jika diperlukan

**File yang Dimodifikasi**:
- `app/Http/Controllers/DashboardController.php` (method `resolveDateRange`)

**Dependencies**: Tidak ada

**Verification**:
```bash
php artisan test --filter=dashboard_filters_by_preset_date_ranges
```

---

### ✅ BUG #2: Potensi Race Condition pada Withdrawal
**File**: `app/Http/Controllers/Inventory/WithdrawalController.php`  
**Baris**: 154-220  
**Complexity**: ⭐⭐⭐⭐ (High)  
**Estimasi**: 40 menit  

**Perbaikan**:
1. Buat test concurrency untuk withdrawal
2. Verify bahwa `lockForUpdate()` bekerja dengan benar
3. Tambahkan logging untuk track race condition
4. Jika ada issue, tambahkan retry mechanism atau pessimistic locking

**File yang Dimodifikasi**:
- **TEST ONLY**: `tests/Feature/Inventory/WithdrawalConcurrencyTest.php` (buat baru)

**Dependencies**: Tidak ada

**Verification**:
```bash
php artisan test --filter=WithdrawalConcurrencyTest
```

**Note**: Ini lebih ke verification test daripada fix code, karena logic sudah benar.

---

### ✅ BUG #3: User dengan Role Invalid - Optimization Query
**File**: `app/Http/Controllers/AuthController.php`  
**Baris**: 53-63  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 15 menit  

**Perbaikan**:
1. Optimize query - hindari double database call
2. Gunakan callback Auth::attempt dengan validation role
3. Atau gunakan custom UserProvider yang filter role

**File yang Dimodifikasi**:
- `app/Http/Controllers/AuthController.php` (method `login`)

**Dependencies**: Tidak ada

**Verification**:
```bash
php artisan test --filter=AuthenticationFlowTest
```

---

## 🟠 HIGH PRIORITY BUGS (6 bug)

### ✅ BUG #4: Validasi subtract_reason Message Typo
**File**: `app/Http/Controllers/Inventory/ItemController.php`  
**Baris**: 137  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 5 menit  

**Perbaikan**:
Ganti `'subtract_reason.required_if'` menjadi `'subtract_reason.required_with'`

**Before**:
```php
'subtract_reason.required_if' => 'Alasan pengurangan stok wajib diisi.',
```

**After**:
```php
'subtract_reason.required_with' => 'Alasan pengurangan stok wajib diisi.',
```

**File yang Dimodifikasi**:
- `app/Http/Controllers/Inventory/ItemController.php`

**Dependencies**: Tidak ada

**Verification**: Manual test form edit item dengan pengurangan stok tanpa alasan.

---

### ✅ BUG #5: Duplicate Validation Message untuk 'quantity.max'
**File**: `app/Http/Controllers/Inventory/WithdrawalController.php`  
**Baris**: 140-141, 301-302  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 5 menit  

**Perbaikan**:
Hapus baris `'quantity.max'` yang unreachable di 2 tempat:
- Method `store()` line 141
- Method `update()` line 302

**File yang Dimodifikasi**:
- `app/Http/Controllers/Inventory/WithdrawalController.php`

**Dependencies**: Tidak ada

**Verification**: Code review saja, tidak ada functional change.

---

### ✅ BUG #6: CSRF Test Tidak Benar
**File**: `tests/Feature/AuthenticationFlowTest.php`  
**Baris**: 26-44  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 10 menit  

**Perbaikan**:
Rewrite test untuk benar-benar test CSRF protection.

**Before**:
```php
public function csrf_protection_rejects_requests_without_token()
{
    $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post(route('login.attempt'), [...]);
    $response->assertStatus(302);
}
```

**After**:
```php
public function csrf_protection_rejects_requests_without_token()
{
    // Jangan disable CSRF middleware
    $response = $this->post(route('login.attempt'), [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);
    
    // Harus ditolak dengan 419 CSRF token mismatch
    $response->assertStatus(419);
}
```

**File yang Dimodifikasi**:
- `tests/Feature/AuthenticationFlowTest.php`

**Dependencies**: Tidak ada

**Verification**:
```bash
php artisan test --filter=csrf_protection_rejects_requests_without_token
```

---

### ✅ BUG #7: Withdrawal Edit - Stok Tidak Dikembalikan Jika Item Dihapus
**File**: `app/Http/Controllers/Inventory/WithdrawalController.php`  
**Baris**: 384-393  
**Complexity**: ⭐⭐⭐ (Medium-High)  
**Estimasi**: 25 menit  

**Perbaikan**:
Tambahkan fallback mechanism atau logging jika item sudah tidak ada.

**Option 1** - Log warning (RECOMMENDED):
```php
foreach ($oldItems as $oldItem) {
    if (! $newItemQuantities->has($oldItem->item_id)) {
        $item = Item::lockForUpdate()->find($oldItem->item_id);
        if ($item) {
            $item->increment('stock', $oldItem->quantity);
        } else {
            // Item sudah dihapus, log untuk audit
            \Log::warning('Cannot return stock: Item deleted', [
                'item_id' => $oldItem->item_id,
                'quantity' => $oldItem->quantity,
                'withdrawal_id' => $withdrawal->id,
            ]);
        }
    }
}
```

**Option 2** - Prevent deletion (sudah ada):
Verify bahwa `ItemController::destroy()` sudah mencegah delete item dengan withdrawal history.

**File yang Dimodifikasi**:
- `app/Http/Controllers/Inventory/WithdrawalController.php` (method `update`)
- Atau buat test untuk verify protection

**Dependencies**: Tidak ada

**Verification**: Manual test atau buat test case.

---

### ✅ BUG #8: Dashboard Query Performance - whereColumn Tanpa Index
**File**: `app/Http/Controllers/DashboardController.php`  
**Baris**: 337-341  
**Complexity**: ⭐⭐⭐ (Medium)  
**Estimasi**: 20 menit  

**Perbaikan**:
Tambahkan composite index untuk kolom `stock` dan `min_stock`.

**Step 1** - Buat migration:
```bash
php artisan make:migration add_stock_index_to_items_table
```

**Step 2** - Migration content:
```php
public function up()
{
    Schema::table('items', function (Blueprint $table) {
        $table->index(['stock', 'min_stock'], 'items_stock_min_stock_index');
    });
}

public function down()
{
    Schema::table('items', function (Blueprint $table) {
        $table->dropIndex('items_stock_min_stock_index');
    });
}
```

**Step 3** - Run migration:
```bash
php artisan migrate
```

**File yang Dimodifikasi**:
- `database/migrations/YYYY_MM_DD_HHMMSS_add_stock_index_to_items_table.php` (buat baru)

**Dependencies**: Tidak ada

**Verification**: Check query performance dengan EXPLAIN.

---

### ✅ BUG #9: Memory Leak Report Export dengan Data Besar
**File**: `app/Http/Controllers/Inventory/ReportController.php`  
**Baris**: 238-240  
**Complexity**: ⭐⭐⭐⭐ (High)  
**Estimasi**: 40 menit  

**Perbaikan**:
Kurangi limit dari 5000 menjadi 1000 dan tambahkan warning jika data terpotong.

**Option 1** - Reduce limit (QUICK FIX):
```php
$details = $this->withdrawalDetailQuery($startDate, $endDate, $rusunId)
    ->limit(1000)  // Reduced from 5000
    ->get();
```

**Option 2** - Chunking untuk PDF (BETTER):
```php
// Untuk PDF, batasi ke 500 rows saja
$details = $this->withdrawalDetailQuery($startDate, $endDate, $rusunId)
    ->limit(500)
    ->get();

// Tambahkan warning di PDF jika data lebih dari limit
if ($this->countTransactions($startDate, $endDate, $rusunId) > 500) {
    // Add warning message to PDF
}
```

**Option 3** - Excel with chunking (BEST):
Excel export bisa handle lebih banyak data karena streaming:
```php
// Excel tetap 5000, tapi gunakan chunk untuk efficiency
$details = $this->withdrawalDetailQuery($startDate, $endDate, $rusunId)
    ->limit(5000)
    ->cursor(); // Use cursor untuk memory efficiency
```

**File yang Dimodifikasi**:
- `app/Http/Controllers/Inventory/ReportController.php` (method `exportPdf` dan `exportExcel`)

**Dependencies**: Tidak ada

**Verification**: Test export dengan dataset besar (500+ rows).

---

## TAHAP 1 - SUMMARY

**Total Bug Fixed**: 9 bug  
**Total Waktu Estimasi**: 3 jam  

**File yang Akan Dimodifikasi**:
1. `app/Http/Controllers/DashboardController.php` - 2 fixes (Bug #1, #8)
2. `app/Http/Controllers/AuthController.php` - 1 fix (Bug #3)
3. `app/Http/Controllers/Inventory/ItemController.php` - 1 fix (Bug #4)
4. `app/Http/Controllers/Inventory/WithdrawalController.php` - 3 fixes (Bug #5, #7, #2 test)
5. `app/Http/Controllers/Inventory/ReportController.php` - 1 fix (Bug #9)
6. `tests/Feature/AuthenticationFlowTest.php` - 1 fix (Bug #6)
7. `database/migrations/` - 1 migration baru (Bug #8)
8. `tests/Feature/Inventory/` - 1 test baru (Bug #2)

**Verification Steps**:
```bash
# 1. Run all tests
php artisan test

# 2. Run specific tests untuk bug yang diperbaiki
php artisan test --filter=dashboard_filters_by_preset_date_ranges
php artisan test --filter=AuthenticationFlowTest
php artisan test --filter=WithdrawalConcurrencyTest

# 3. Manual testing untuk validation messages
# - Test edit item dengan pengurangan stok tanpa alasan
# - Test withdrawal dengan data besar

# 4. Check migration
php artisan migrate --pretend
php artisan migrate

# 5. Performance check
# - Load dashboard dan check query time
# - Export report dengan 500+ rows
```

---

# TAHAP 2: MEDIUM & LOW PRIORITY + IMPROVEMENTS
**Fokus**: UX, code quality, dan optimizations

## 🟡 MEDIUM PRIORITY BUGS (5 bug)

### ✅ BUG #10: Login Form Placeholder Inconsistent
**File**: `resources/views/auth/login.blade.php`  
**Baris**: 169, 182  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 3 menit  

**Perbaikan**:
Konsistenkan placeholder menjadi "Masukkan email" dan "Masukkan password".

**Before**:
```html
<input type="email" ... placeholder="email" required>
<input type="password" ... placeholder="Masukkan password" required>
```

**After**:
```html
<input type="email" ... placeholder="Masukkan email" required>
<input type="password" ... placeholder="Masukkan password" required>
```

**File yang Dimodifikasi**:
- `resources/views/auth/login.blade.php`

**Dependencies**: Tidak ada

**Verification**: Visual check halaman login.

---

### ✅ BUG #11: Rate Limiting Message Tidak Akurat untuk Waktu < 60 detik
**File**: `app/Http/Controllers/AuthController.php`  
**Baris**: 137  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 5 menit  

**Perbaikan**:
Tambahkan `ceil()` untuk detik agar selalu integer.

**Before**:
```php
$waktu = $seconds >= 60 ? (ceil($seconds / 60).' menit') : ($seconds.' detik');
```

**After**:
```php
$waktu = $seconds >= 60 ? (ceil($seconds / 60).' menit') : (ceil($seconds).' detik');
```

**File yang Dimodifikasi**:
- `app/Http/Controllers/AuthController.php`

**Dependencies**: Tidak ada

**Verification**: Manual test dengan rate limiting.

---

### ✅ BUG #12: Item Photo Default Path Hardcoded
**File**: `app/Models/Item.php`  
**Baris**: 18  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 15 menit  

**Perbaikan**:
Move default image ke storage/app/public/items/ dan update constant.

**Option 1** - Keep in public/ (CURRENT, NO CHANGE):
No fix needed, ini design decision yang valid.

**Option 2** - Move to storage (BETTER CONSISTENCY):
1. Copy `public/images/items/default.jpg` → `storage/app/public/items/default.jpg`
2. Update constant:
```php
public const DEFAULT_PHOTO_URL = 'storage/items/default.jpg';
```

**File yang Dimodifikasi**:
- `app/Models/Item.php`
- Move file dari `public/images/items/default.jpg` ke `storage/app/public/items/default.jpg`

**Dependencies**: Tidak ada

**Verification**: Check item tanpa foto display default image correctly.

**REKOMENDASI**: Skip fix ini (low impact, design decision valid).

---

### ✅ BUG #13: Missing Validation - Tanggal Tidak Boleh Masa Depan
**File**: `app/Http/Controllers/DashboardController.php`  
**Baris**: 248-258  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 15 menit  

**Perbaikan**:
Tambahkan feedback ke user jika tanggal di-adjust.

**Option 1** - Add warning message (RECOMMENDED):
```php
protected function resolveDateRange(Request $request, string $today): array
{
    // ... existing code ...
    
    if ($dari && $sampai && $dari <= $sampai) {
        $originalSampai = $sampai;
        $sampai = min($sampai, $today);
        $dari = min($dari, $sampai);
        
        if ($originalSampai > $today) {
            // Store warning in session for display
            session()->flash('info', 'Tanggal akhir disesuaikan ke hari ini.');
        }
        
        if ($dari <= $sampai) {
            return [$dari, $sampai];
        }
    }
    
    return [$today, $today];
}
```

**Option 2** - Validate at request level (BETTER):
Add validation di request dengan custom rule `before_or_equal:today`.

**File yang Dimodifikasi**:
- `app/Http/Controllers/DashboardController.php`

**Dependencies**: Tidak ada

**Verification**: Manual test dengan tanggal masa depan.

**REKOMENDASI**: Low priority, current behavior acceptable.

---

### ✅ BUG #14: Withdrawal Show Page Load Optimization
**File**: `app/Http/Controllers/Inventory/WithdrawalController.php`  
**Baris**: 234-238  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 5 menit  

**Perbaikan**:
Verify tidak ada N+1 query di view.

**Action**: 
1. Enable query log
2. Load withdrawal show page
3. Check query count
4. Fix jika ada N+1

**File yang Dimodifikasi**:
- Kemungkinan tidak ada (verification only)

**Dependencies**: Tidak ada

**Verification**: 
```bash
# Add to show() method temporarily:
\DB::enableQueryLog();
$withdrawal = Withdrawal::with(['rusun', 'items.item'])->findOrFail($id);
dd(\DB::getQueryLog());
```

---

## 🔵 LOW PRIORITY BUGS (3 bug)

### ✅ BUG #15: Typo di Komentar - "Dismallkan" → "Dikecilkan"
**File**: `app/Models/Item.php`  
**Baris**: 77  
**Complexity**: ⭐ (Very Easy)  
**Estimasi**: 1 menit  

**Perbaikan**:
Fix typo in comment.

**Before**:
```php
/**
 * Dismallkan karena ukuran foto asli sangat varies
```

**After**:
```php
/**
 * Dikecilkan karena ukuran foto asli sangat bervariasi
```

**File yang Dimodifikasi**:
- `app/Models/Item.php`

**Dependencies**: Tidak ada

**Verification**: None (comment only).

---

### ✅ BUG #16: Inconsistent Comment Style
**File**: Multiple files  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 20 menit  

**Perbaikan**:
Standardize semua comment ke Bahasa Indonesia.

**File yang Dimodifikasi**:
- Multiple files (optional, low priority)

**Dependencies**: Tidak ada

**Verification**: Code review.

**REKOMENDASI**: Skip, ini code style preference.

---

### ✅ BUG #17: Missing Transaction di ItemController::destroy()
**File**: `app/Http/Controllers/Inventory/ItemController.php`  
**Baris**: 228-256  
**Complexity**: ⭐⭐ (Medium)  
**Estimasi**: 10 menit  

**Perbaikan**:
Wrap delete operations dalam database transaction.

**Before**:
```php
public function destroy(string $id)
{
    $item = Item::findOrFail($id);
    
    if ($item->withdrawals()->exists()) {
        return redirect()->back()
            ->with('error', 'Barang tidak dapat dihapus...');
    }
    
    // Hapus foto
    if ($item->photo) {
        // ...
        Storage::delete('public/items/'.$item->photo);
        Cache::forget($cacheKey);
    }
    
    $item->delete();
    
    return redirect()->route('inventory.items.index')
        ->with('success', 'Barang berhasil dihapus.');
}
```

**After**:
```php
public function destroy(string $id)
{
    $item = Item::findOrFail($id);
    
    if ($item->withdrawals()->exists()) {
        return redirect()->back()
            ->with('error', 'Barang tidak dapat dihapus...');
    }
    
    $photoPath = $item->photo;
    
    DB::transaction(function () use ($item, $photoPath) {
        $item->delete();
        
        // Hapus foto SETELAH delete berhasil
        if ($photoPath) {
            $stillUsed = Item::where('photo', $photoPath)
                ->where('id', '!=', $item->id)
                ->exists();
            if (!$stillUsed) {
                Storage::delete('public/items/'.$photoPath);
                $cacheKey = 'item-thumb:'.sha1($photoPath.'|44|v1');
                Cache::forget($cacheKey);
            }
        }
    });
    
    return redirect()->route('inventory.items.index')
        ->with('success', 'Barang berhasil dihapus.');
}
```

**File yang Dimodifikasi**:
- `app/Http/Controllers/Inventory/ItemController.php`

**Dependencies**: Tidak ada

**Verification**: Manual test delete item.

---

## TAHAP 2 - SUMMARY

**Total Bug Fixed**: 8 bug  
**Total Waktu Estimasi**: 1.5 jam  

**File yang Akan Dimodifikasi**:
1. `resources/views/auth/login.blade.php` - 1 fix (Bug #10)
2. `app/Http/Controllers/AuthController.php` - 1 fix (Bug #11)
3. `app/Models/Item.php` - 2 fixes (Bug #12, #15)
4. `app/Http/Controllers/DashboardController.php` - 1 fix (Bug #13)
5. `app/Http/Controllers/Inventory/WithdrawalController.php` - 1 verification (Bug #14)
6. `app/Http/Controllers/Inventory/ItemController.php` - 1 fix (Bug #17)
7. Multiple files - optional (Bug #16)

**Verification Steps**:
```bash
# 1. Visual checks
# - Login page placeholder
# - Default item image

# 2. Manual testing
# - Rate limiting message dengan < 60 detik
# - Dashboard filter dengan tanggal masa depan
# - Delete item

# 3. Query optimization check
# - Enable query log untuk withdrawal show page
# - Check N+1 queries

# 4. Code review
# - Comment consistency (optional)
```

---

## IMPROVEMENTS & RECOMMENDATIONS

### 📋 Test Coverage untuk Inventory Module
**Priority**: HIGH  
**Estimasi**: 3-4 jam (separate task)

Buat test suite untuk:
1. **ItemController Tests**:
   - Create item dengan/tanpa foto
   - Edit item dan tambah/kurangi stok
   - Delete item dengan/tanpa riwayat withdrawal
   - Validation tests

2. **WithdrawalController Tests**:
   - Create withdrawal single/multiple items
   - Create dengan insufficient stock (harus gagal)
   - Edit withdrawal (tambah/hapus/ubah quantity)
   - Delete withdrawal (stok harus kembali)
   - **Concurrency test** (penting!)

3. **ReportController Tests**:
   - Export PDF/Excel normal data
   - Export dengan filter
   - Export dengan data besar (>500 rows)

**File Baru**:
- `tests/Feature/Inventory/ItemControllerTest.php`
- `tests/Feature/Inventory/WithdrawalControllerTest.php`
- `tests/Feature/Inventory/WithdrawalConcurrencyTest.php`
- `tests/Feature/Inventory/ReportControllerTest.php`

---

### 📊 Performance Optimization
**Priority**: MEDIUM  
**Estimasi**: 2 jam

1. **Add Missing Indexes**:
   - `withdrawals.taken_at` - untuk report filtering
   - `withdrawal_items.withdrawal_id` - verify sudah ada
   - `items.stock, items.min_stock` - untuk dashboard (sudah di Tahap 1)

2. **Query Optimization**:
   - Dashboard query caching untuk data statistik
   - Report query optimization dengan proper indexes

3. **Rate Limiting Adjustment**:
   - Export PDF/Excel: dari `throttle:10,1` ke `throttle:3,1`

---

### 🔒 Security Improvements
**Priority**: LOW  
**Estimasi**: 1 jam

1. **Verify CORS Configuration** (jika ada API):
   - Set proper CORS headers
   - Whitelist allowed origins

2. **SQL Injection Review**:
   - Audit semua `DB::raw()` usage
   - Pastikan tidak ada user input di raw queries

3. **File Upload Security**:
   - Verify file type validation
   - Check file size limits
   - Prevent directory traversal

---

## EXECUTION PLAN

### Tahap 1 - Urutan Eksekusi (Recommended Order):

1. **Bug #4** (5 min) - Validation message typo - TERMUDAH
2. **Bug #5** (5 min) - Duplicate validation - TERMUDAH
3. **Bug #15** (1 min) - Typo comment - TERMUDAH
4. **Bug #6** (10 min) - CSRF test fix - QUICK WIN
5. **Bug #11** (5 min) - Rate limiting message - QUICK WIN
6. **Bug #10** (3 min) - Login placeholder - QUICK WIN
7. **Bug #3** (15 min) - Auth optimization - MEDIUM
8. **Bug #8** (20 min) - Add index migration - MEDIUM
9. **Bug #13** (15 min) - Date validation feedback - MEDIUM
10. **Bug #1** (20 min) - Dashboard filter fix - MEDIUM CRITICAL
11. **Bug #7** (25 min) - Withdrawal edit logging - COMPLEX
12. **Bug #9** (40 min) - Report export memory - COMPLEX
13. **Bug #2** (40 min) - Concurrency test - COMPLEX
14. **Bug #17** (10 min) - Transaction wrapper - OPTIONAL
15. **Bug #14** (5 min) - N+1 query check - VERIFICATION
16. **Bug #12** (skip) - Default photo path - LOW IMPACT
17. **Bug #16** (skip) - Comment style - OPTIONAL

### Git Workflow:

```bash
# Buat branch baru
git checkout -b bugfix/systematic-fixes

# Setiap beberapa bug yang related, commit:
git add .
git commit -m "Fix: Validation messages dan typo (Bug #4, #5, #15)"

git add .
git commit -m "Fix: CSRF test dan auth optimization (Bug #3, #6)"

git add .
git commit -m "Fix: Dashboard filter dan query performance (Bug #1, #8)"

git add .
git commit -m "Fix: Withdrawal edit logging (Bug #7)"

git add .
git commit -m "Fix: Report export memory optimization (Bug #9)"

git add .
git commit -m "Test: Add withdrawal concurrency test (Bug #2)"

git add .
git commit -m "Fix: UX improvements dan minor fixes (Bug #10-#14)"

# Merge ke main setelah semua test pass
php artisan test
git checkout main
git merge bugfix/systematic-fixes
```

---

## RISK ASSESSMENT

### High Risk Changes:
- ❌ **None** - Semua perbaikan low risk

### Medium Risk Changes:
- ⚠️ Bug #1: Dashboard filter logic (bisa break existing queries)
- ⚠️ Bug #7: Withdrawal edit (data integrity concern)
- ⚠️ Bug #9: Report export (bisa break existing exports)

### Low Risk Changes:
- ✅ Bug #3-6: Validation dan tests
- ✅ Bug #8: Index addition (pure optimization)
- ✅ Bug #10-17: UX dan code quality

### Mitigation:
1. ✅ Backup database sebelum migration
2. ✅ Test setiap fix sebelum commit
3. ✅ Keep original code di comment jika complex change
4. ✅ Siapkan rollback plan

---

## CHECKLIST SEBELUM MULAI

- [ ] Backup database
- [ ] Pastikan environment development, bukan production
- [ ] Git working directory clean
- [ ] Create feature branch
- [ ] Run `php artisan test` untuk baseline
- [ ] Baca seluruh rencana ini
- [ ] Siapkan test cases manual untuk verification
- [ ] Estimasi waktu: 4-5 jam total (Tahap 1 + 2)

---

## CHECKLIST SETELAH SELESAI

### Tahap 1:
- [ ] Semua 9 bug critical/high diperbaiki
- [ ] `php artisan test` semua pass
- [ ] Manual test untuk validation messages
- [ ] Manual test untuk withdrawal edit/delete
- [ ] Manual test untuk report export
- [ ] Migration berjalan sukses
- [ ] Commit changes dengan message yang jelas

### Tahap 2:
- [ ] Semua 8 bug medium/low diperbaiki
- [ ] Visual check login page
- [ ] Manual test rate limiting message
- [ ] Query optimization verified
- [ ] Code review untuk comment consistency
- [ ] Final `php artisan test` semua pass
- [ ] Git merge ke main branch

### Final:
- [ ] Update `BUG_REPORT_SISTEM_SARPRAS.md` dengan status "FIXED"
- [ ] Dokumentasi perubahan
- [ ] Deploy ke staging untuk testing
- [ ] User acceptance testing

---

**Ready to Execute?**  
Silakan review rencana ini terlebih dahulu. Jika sudah oke, saya akan mulai eksekusi Tahap 1.

**Pertanyaan untuk konfirmasi**:
1. Apakah urutan perbaikan sudah sesuai?
2. Apakah ada bug yang mau diskip atau diprioritaskan berbeda?
3. Apakah perlu tambahan test coverage sekarang atau nanti?
4. Apakah mau langsung execute atau ada adjustment?
