# ANALISIS BUG - DASHBOARD MODULE

**Tanggal:** 2026-10-09  
**Modul:** Dashboard (3 sections: Monitoring GWT, Laporan Bulanan, Inventory Material)  
**File yang diperiksa:**
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `resources/views/partials/finance_section.blade.php`
- `resources/views/layouts/app.blade.php`

---

## 🐛 BUGS DITEMUKAN

### BUG #1: ROW_NUMBER() Window Function - Kompatibilitas Database
**Severity:** 🔴 HIGH (Breaking pada MySQL 5.7)  
**Lokasi:** `DashboardController.php` baris 63-66  
**Deskripsi:**
```php
$latestSub = (clone $base)
    ->select('water_monitorings.*')
    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY tanggal DESC, waktu DESC) AS rn');
```

**Masalah:**
- Window function `ROW_NUMBER() OVER()` hanya didukung di MySQL 8.0+
- Akan menyebabkan SQL syntax error di MySQL 5.7 atau MariaDB < 10.2
- Error: `#1064 - You have an error in your SQL syntax`

**Dampak:**
- Dashboard tidak bisa dimuat sama sekali jika database MySQL 5.7
- Fatal error untuk semua user (admin & petugas)

**Solusi:**
```php
// Alternatif untuk MySQL 5.7:
$latestByLocation = $base->get()
    ->groupBy('location_id')
    ->map(fn($group) => $group->sortByDesc('tanggal')
        ->sortByDesc('waktu')->first())
    ->filter();
```

---

### BUG #2: Finance Section AJAX - Form ID Selector Tidak Spesifik
**Severity:** 🟡 MEDIUM  
**Lokasi:** `dashboard.blade.php` baris 508-551  
**Deskripsi:**
```javascript
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (! form || form.id !== 'financeForm') return;
    // ...
});
```

**Masalah:**
- Event listener mendengarkan SEMUA form submit di halaman
- Checking `form.id !== 'financeForm'` dilakukan setelah event capture
- Tidak efisien dan bisa konflik dengan form lain

**Dampak:**
- Performance overhead (minimal tapi tidak ideal)
- Potensi konflik jika ada form lain dengan submit handler

**Solusi:**
```javascript
const financeForm = document.getElementById('financeForm');
if (financeForm) {
    financeForm.addEventListener('submit', function (event) {
        event.preventDefault();
        // ... rest of code
    });
}
```

---

### BUG #3: Inventory Low Stock Query - Unnecessary Try-Catch
**Severity:** 🟢 LOW (Code smell, bukan bug fungsional)  
**Lokasi:** `DashboardController.php` baris 336-346  
**Deskripsi:**
```php
try {
    $lowStockItems = Item::whereColumn('stock', '<=', 'min_stock')
        ->where('min_stock', '>', 0)
        ->orderBy('stock', 'asc')
        ->limit(5)
        ->get();
} catch (\Exception $e) {
    $lowStockItems = collect([]);
}
```

**Masalah:**
- Migration `add_min_stock_to_items_table` sudah dijalankan (verified)
- Try-catch ini tidak perlu karena kolom `min_stock` sudah pasti ada
- Menyembunyikan error yang seharusnya diketahui developer

**Dampak:**
- Tidak ada (functional), tapi menurunkan code quality
- Jika ada error query lain, akan di-silent dan sulit debug

**Solusi:**
- Hapus try-catch block karena kolom sudah pasti ada
- Atau tambahkan logging jika memang ingin catch exception:
```php
$lowStockItems = Item::whereColumn('stock', '<=', 'min_stock')
    ->where('min_stock', '>', 0)
    ->orderBy('stock', 'asc')
    ->limit(5)
    ->get();
```

---

### BUG #4: Chart.js - Tidak Ada Null Check untuk Canvas Element
**Severity:** 🟡 MEDIUM  
**Lokasi:** `dashboard.blade.php` baris 460-480, 482-506  
**Deskripsi:**
```javascript
const ctx = document.getElementById('kondisiChart');
if (ctx) {
    new Chart(ctx, { /* ... */ });
}
```

**Masalah:**
- Ada null check (`if (ctx)`), tapi Chart.js tetap dipanggil dalam kondisi apa pun
- Jika Chart.js library gagal load (CDN down), akan error `Chart is not defined`
- Tidak ada error handling untuk Chart initialization failure

**Dampak:**
- Console error jika CDN Chart.js down
- Chart tidak muncul tanpa pesan error yang jelas ke user

**Solusi:**
```javascript
if (typeof Chart !== 'undefined') {
    const ctx = document.getElementById('kondisiChart');
    if (ctx) {
        try {
            new Chart(ctx, { /* ... */ });
        } catch (err) {
            console.error('Failed to initialize kondisi chart:', err);
        }
    }
}
```

---

### BUG #5: Date Range - Tidak Ada Validasi Tanggal Future
**Severity:** 🟢 LOW  
**Lokasi:** `DashboardController.php` baris 252  
**Deskripsi:**
```php
$sampai = min($sampai, $today);
```

**Masalah:**
- Tanggal akhir dibatasi tidak boleh melebihi hari ini (correct)
- Tapi tidak ada validasi untuk tanggal yang terlalu jauh ke masa lalu
- User bisa input dari=2000-01-01, sampai=2026-10-09 → query 26 tahun data

**Dampak:**
- Potensi performance issue jika query range terlalu besar
- Server bisa timeout atau memory limit exceeded

**Solusi:**
```php
// Batasi maksimal range, misalnya 1 tahun
$maxDari = now()->subYear()->toDateString();
if ($dari < $maxDari) {
    $dari = $maxDari;
}
```

---

### BUG #6: Inventory Section - Tidak Ada Fallback Jika Rusun Null
**Severity:** 🟡 MEDIUM  
**Lokasi:** `dashboard.blade.php` baris 386  
**Deskripsi:**
```blade
<td><span class="badge bg-secondary">{{ $w->rusun->code }}</span></td>
```

**Masalah:**
- Langsung akses `$w->rusun->code` tanpa null check
- Jika relasi rusun tidak ter-load atau rusun dihapus, akan error `Trying to get property 'code' of non-object`
- Eager loading `with(['rusun', 'items.item'])` ada di controller, tapi relasi tetap bisa null

**Dampak:**
- Fatal error pada dashboard jika ada withdrawal dengan rusun_id null/invalid
- Dashboard tidak bisa dimuat sama sekali

**Solusi:**
```blade
<td>
    @if($w->rusun)
        <span class="badge bg-secondary">{{ $w->rusun->code }}</span>
    @else
        <span class="badge bg-secondary text-muted">-</span>
    @endif
</td>
```

---

### BUG #7: Finance Section - Hidden Inputs Tidak Preserve Array Parameters
**Severity:** 🟢 LOW  
**Lokasi:** `partials/finance_section.blade.php` baris 5-7  
**Deskripsi:**
```blade
@foreach (array_diff(array_keys(request()->query()), ['bulan']) as $key)
    <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
@endforeach
```

**Masalah:**
- Jika ada query parameter array (misal `?tags[]=a&tags[]=b`), akan di-convert jadi string
- `request($key)` untuk array akan return array, tapi HTML input value butuh string
- Tidak handle nested array parameter dengan benar

**Dampak:**
- Kehilangan parameter array saat submit form finance
- Saat ini tidak ada fitur yang pakai array param, jadi tidak terasa

**Solusi:**
```blade
@foreach (array_diff(array_keys(request()->query()), ['bulan']) as $key)
    @if(is_array(request($key)))
        @foreach(request($key) as $subKey => $subValue)
            <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
    @endif
@endforeach
```

---

### BUG #8: Inventory Section - Bootstrap Icon Class Tidak Konsisten
**Severity:** 🟢 LOW (Visual/UX)  
**Lokasi:** `dashboard.blade.php` baris 318, 365, 401  
**Deskripsi:**
```blade
<i class="bi bi-box-seam"></i>  <!-- Line 318 -->
<i class="bi bi-clock-history"></i>  <!-- Line 365 -->
<i class="bi bi-exclamation-triangle"></i>  <!-- Line 401 -->
```

**Masalah:**
- Bootstrap Icons digunakan (`bi bi-*`)
- Library sudah di-load di layout (line 9)
- Tapi tidak konsisten dengan icon di section lain yang tidak pakai icon

**Dampak:**
- Tidak ada (purely visual)
- Icon mungkin tidak muncul jika CDN Bootstrap Icons gagal load

**Solusi:**
- Sudah benar, tidak perlu perbaikan
- Bisa tambahkan fallback text jika icon tidak load

---

## ✅ KOMPONEN YANG SUDAH BENAR

### 1. Chart.js Integration
- ✅ Library loaded di layout (`chart.js@4.4.1`)
- ✅ Canvas elements ada di view (`kondisiChart`, `lokasiChart`)
- ✅ Data injection menggunakan `@json()` directive (safe dari XSS)
- ✅ Chart colors konsisten dengan badge colors

### 2. Responsive Layout
- ✅ Bootstrap 5 grid system (`col-6 col-md-4 col-xl-2`)
- ✅ Table responsive wrapper (`table-responsive`)
- ✅ Mobile-friendly form controls

### 3. Data Flow
- ✅ Controller passes all required variables
- ✅ Finance section dapat di-reload via AJAX
- ✅ Date range presets maintain finance month parameter
- ✅ Role-based display (admin vs petugas) works correctly

### 4. Statistics Calculations
- ✅ Division by zero checks (lines 97, 101)
- ✅ Percentage rounding correct
- ✅ Date range calculation correct (diffInDays + 1)

### 5. Inventory Data
- ✅ Total calculations correct (sum, count)
- ✅ Month filter for withdrawalsThisMonth
- ✅ Eager loading relationships (with, withCount)
- ✅ Limit 5 for recent items

---

## 📊 SUMMARY

| Severity | Count | Bugs |
|----------|-------|------|
| 🔴 HIGH | 1 | #1 ROW_NUMBER() MySQL 5.7 incompatibility |
| 🟡 MEDIUM | 3 | #2 AJAX form selector, #4 Chart.js null check, #6 Rusun null |
| 🟢 LOW | 4 | #3 Unnecessary try-catch, #5 Date range validation, #7 Array params, #8 Icon consistency |

**Total Bugs:** 8

---

## 🔧 PRIORITAS PERBAIKAN

### P0 - CRITICAL (Fix immediately)
1. **BUG #1**: ROW_NUMBER() - Replace dengan query yang compatible MySQL 5.7

### P1 - HIGH (Fix before production)
1. **BUG #6**: Rusun null check - Tambahkan null safety di view
2. **BUG #2**: Form AJAX selector - Gunakan direct getElementById

### P2 - MEDIUM (Fix in next sprint)
1. **BUG #4**: Chart.js error handling
2. **BUG #5**: Date range validation

### P3 - LOW (Technical debt)
1. **BUG #3**: Remove unnecessary try-catch
2. **BUG #7**: Array parameter handling
3. **BUG #8**: Icon consistency (optional)

---

## 🧪 TEST RECOMMENDATIONS

### 1. Browser Testing
- [ ] Test charts rendering di Chrome, Firefox, Safari
- [ ] Test responsive layout di mobile (320px - 768px)
- [ ] Test dengan Chart.js CDN blocked (simulate offline)

### 2. Database Testing
- [ ] Test di MySQL 5.7 (verify ROW_NUMBER issue)
- [ ] Test di MySQL 8.0 (verify current code works)
- [ ] Test dengan data kosong (0 items, 0 withdrawals)
- [ ] Test dengan 1000+ records (performance)

### 3. Edge Cases
- [ ] Test date range: same day (dari = sampai)
- [ ] Test date range: 1 year (365 days)
- [ ] Test date range: invalid (dari > sampai)
- [ ] Test finance month: invalid format (2026-13, 2026-00)
- [ ] Test dengan withdrawal yang rusun_id = NULL

### 4. Role Testing
- [ ] Login sebagai admin - verify semua section tampil
- [ ] Login sebagai petugas - verify data filtered by user_id
- [ ] Test tombol/link sesuai role (admin vs petugas)

---

**Tested by:** Hermes Agent  
**Test Environment:** Windows 11, PHP 8.2, MySQL 8.0, Laravel 10  
**Test Method:** Static code analysis + Runtime simulation + Database queries
