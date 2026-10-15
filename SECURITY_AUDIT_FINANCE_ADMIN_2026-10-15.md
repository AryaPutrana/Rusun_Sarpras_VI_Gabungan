# SECURITY AUDIT REPORT - FINANCE & ADMIN FEATURES
**Date:** 2026-10-15  
**Scope:** Finance Reports, Finance Locations, User Management, Monitoring Locations, Rusun Management  
**Laravel Version:** 11

---

## 🔴 CRITICAL ISSUES

### BUG-001: Inventory Module Lacks Admin Authorization
**Severity:** CRITICAL  
**Location:** `routes/web.php` lines 98-117  
**Description:** The entire inventory module (items, withdrawals, reports) is accessible to ALL authenticated users (admin + petugas). Routes are wrapped in `middleware('auth')` but NOT in `middleware('role:admin')`. Comment states "Semua role dapat akses inventory" but context indicates admin-only access is required.

**Impact:**
- Petugas can view/create/edit/delete inventory items
- Petugas can create/edit/delete withdrawals
- Petugas can access inventory reports
- Complete authorization bypass if inventory should be admin-only

**Fix:**
```php
// Move inventory routes inside admin middleware
Route::middleware('role:admin')->group(function () {
    Route::get('/keuangan', [FinanceReportController::class, 'index'])->name('keuangan.index');
    Route::get('/keuangan/pdf', [FinanceReportController::class, 'exportPdf'])->name('keuangan.pdf');
    Route::resource('lokasi', MonitoringLocationController::class)->except('show');
    Route::resource('lokasi-bulanan', FinanceLocationController::class)
        ->parameters(['lokasi-bulanan' => 'lokasiBulanan'])
        ->names([...])
        ->except('show');
    Route::resource('pengguna', UserController::class)->except('show');
    
    // Add inventory here if admin-only
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('items', ItemController::class);
        Route::resource('withdrawals', WithdrawalController::class);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::middleware('throttle:exports')->group(function () {
            Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');
            Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.excel');
        });
    });
});
```

---

### BUG-002: Missing Rusun Management CRUD
**Severity:** HIGH  
**Location:** Missing controller in `app/Http/Controllers/Admin/`  
**Description:** Context requires "rusun CRUD (code unique, 6 codes: RABEK/UMEN/TIPAR/ALBO/CBT/KM2)" but no RusunController exists. The Rusun model exists and is used in WithdrawalController, but there's no web interface to manage rusun records (create, edit, delete).

**Impact:**
- Cannot add new rusun locations through admin panel
- Cannot edit existing rusun names
- Cannot delete unused rusun records
- Must manually insert/update via database

**Fix:**
Create `app/Http/Controllers/Admin/RusunController.php` with full CRUD operations:
- Validate `code` uniqueness and restrict to allowed codes
- Prevent deletion if rusun has withdrawal history (foreign key is RESTRICT)
- Add routes under admin middleware

---

## 🟠 HIGH SEVERITY ISSUES

### ISSUE-001: Finance Location Status Not Validated on Report Creation/Update
**Severity:** HIGH  
**Location:** 
- `app/Http/Requests/StoreFinanceReportRequest.php` line 28
- `app/Http/Requests/UpdateFinanceReportRequest.php` line 19
- `app/Http/Controllers/FinanceReportController.php` store() and update() methods

**Description:** Validation rules check `exists:finance_locations,id` but don't verify that the location status is 'aktif'. While the form dropdown filters by status, a user can manipulate POST data to submit a 'nonaktif' location_id.

**Impact:**
- Finance reports can be created/updated with inactive locations
- Violates business rule that only active locations should be selectable
- Data integrity issue

**Fix:**
```php
// In StoreFinanceReportRequest and UpdateFinanceReportRequest
public function rules(): array
{
    return [
        'location_id' => [
            'required',
            'integer',
            'exists:finance_locations,id',
            Rule::exists('finance_locations', 'id')->where('status', 'aktif'),
        ],
        // ... other rules
    ];
}

// Update error message
public function messages(): array
{
    return [
        'location_id.exists' => 'Lokasi tidak valid atau sudah nonaktif.',
        // ... other messages
    ];
}
```

---

### ISSUE-002: Race Condition in Last Admin Deletion Check
**Severity:** HIGH  
**Location:** `app/Http/Controllers/Admin/UserController.php` lines 90-113  
**Description:** The check for "cannot delete last admin" (line 90) and the actual delete (line 113) are not atomic. Between the count check and deletion, another concurrent request could delete an admin, leaving zero admins in the system.

**Impact:**
- Could result in zero admin users (locked out of admin panel)
- Requires database restoration to recover
- Unlikely but possible in high-concurrency scenarios

**Fix:**
```php
public function destroy(User $pengguna): RedirectResponse
{
    if ($pengguna->id === Auth::id()) {
        return redirect()->route('pengguna.index')
            ->with('error', 'Anda tidak dapat menghapus akun sendiri.');
    }

    DB::beginTransaction();
    try {
        // Lock and re-check admin count within transaction
        if ($pengguna->isAdmin()) {
            $adminCount = User::where('role', 'admin')
                ->lockForUpdate()
                ->count();
            
            if ($adminCount <= 1) {
                DB::rollBack();
                return redirect()->route('pengguna.index')
                    ->with('error', 'Tidak dapat menghapus admin terakhir.');
            }
        }

        if ($pengguna->waterMonitorings()->exists()) {
            DB::rollBack();
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki data pemeriksaan.');
        }

        if ($pengguna->financeReports()->exists()) {
            DB::rollBack();
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki data laporan bulanan.');
        }

        if (DB::table('stock_adjustments')->where('user_id', $pengguna->id)->exists()) {
            DB::rollBack();
            return redirect()->route('pengguna.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat penyesuaian stok.');
        }

        $pengguna->delete();
        DB::commit();

        return redirect()->route('pengguna.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

---

## 🟡 MEDIUM SEVERITY ISSUES

### ISSUE-003: User Role in Fillable Array (Defense in Depth)
**Severity:** MEDIUM  
**Location:** `app/Models/User.php` line 30  
**Description:** The User model has 'role' in the `$fillable` array. While UpdateUserRequest correctly excludes 'role' from update validation (line 68 of UserController only allows name/email/password), the model doesn't have explicit guarded protection. If request validation is bypassed (e.g., via console, API, or bug), role could be changed via mass assignment.

**Impact:**
- Potential privilege escalation if request validation is bypassed
- Defense-in-depth violation
- Low likelihood but high impact

**Fix:**
```php
// Option 1: Remove role from fillable after creation
protected $fillable = [
    'name',
    'email',
    'password',
    // 'role' removed - must be set explicitly
];

// In UserController::store(), explicitly set role
public function store(StoreUserRequest $request): RedirectResponse
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);
    
    $user->role = $request->role;
    $user->save();

    return redirect()->route('pengguna.index')
        ->with('success', 'Pengguna berhasil ditambahkan.');
}

// Option 2: Add guarded protection
protected $guarded = [];

// And use forceFill for role changes only during creation
```

---

### RECOMMENDATION-001: Missing Database Index on finance_reports.location_id
**Severity:** MEDIUM  
**Location:** Database migration for `finance_reports`  
**Description:** The `location_id` foreign key should have an index for query performance. Laravel creates indexes on foreign key columns by default, but verification is needed.

**Impact:**
- Slow queries when filtering reports by location
- Performance degradation with large datasets

**Fix:**
```php
// Verify index exists in migration or create new migration
Schema::table('finance_reports', function (Blueprint $table) {
    $table->index('location_id'); // If not already indexed by foreign key
});
```

---

## 🟢 LOW SEVERITY / RECOMMENDATIONS

### RECOMMENDATION-002: Add Validation for Finance Location Keterangan Length
**Severity:** LOW  
**Location:** `app/Http/Requests/StoreFinanceLocationRequest.php` line 28  
**Description:** The `keterangan` field is validated as 'nullable|string' but has no max length. The database column likely has a limit (TEXT = 65,535 bytes) but explicit validation would be clearer.

**Impact:**
- Potential database error if extremely long text is submitted
- Poor user experience (error at DB level instead of validation)

**Fix:**
```php
'keterangan' => ['nullable', 'string', 'max:5000'],
```

---

### RECOMMENDATION-003: Add CSRF Token Validation Test
**Severity:** LOW  
**Location:** All POST/PUT/DELETE routes  
**Description:** Laravel has CSRF protection enabled by default via `VerifyCsrfToken` middleware. No issues found in code review, but automated testing should verify CSRF tokens are required.

**Impact:**
- CSRF attacks if middleware is accidentally disabled

**Fix:**
- Add automated tests to verify CSRF protection on critical endpoints

---

## ✅ SECURITY CONTROLS IN PLACE (GOOD)

### ✓ XSS Protection
- Blade templates use `{{ }}` syntax which auto-escapes output
- PhotoController sets `X-Content-Type-Options: nosniff` header (line 89)
- No raw HTML output found in controllers

### ✓ Authorization Checks
- RoleMiddleware properly checks user roles (lines 17-30)
- FinanceReportController enforces ownership checks for petugas (edit line 149, update line 171, destroy line 238)
- PhotoController restricts photo access to owners and admins (lines 41-52)

### ✓ Duplicate Prevention
- Finance location `nama_lokasi` has unique constraint (StoreFinanceLocationRequest line 27)
- User `email` has unique constraint (StoreUserRequest line 27, UpdateUserRequest line 27)
- Rusun `code` has unique database constraint (migration line 16)

### ✓ Validation Rules
- Password minimum 8 characters (StoreUserRequest line 28)
- Email format validation enforced
- Finance report date cannot exceed today (StoreFinanceReportRequest line 27)
- Photo upload restricted to jpeg/jpg/png, max 5MB (line 31)

### ✓ Cascade Delete Protection
- Finance location deletion blocked if used in reports (FinanceLocationController line 72)
- Monitoring location deletion blocked if used (MonitoringLocationController line 69)
- User deletion blocked if has related data (UserController lines 95-111)
- Rusun foreign key uses RESTRICT (migration 2026_10_05_100200)

### ✓ Self-Modification Protection
- Users cannot delete themselves (UserController line 85)
- Cannot delete last admin (UserController line 90)

### ✓ SQL Injection Protection
- All queries use Eloquent ORM or query builder
- User input is parameterized
- No raw SQL with concatenated user input found

### ✓ File Upload Security
- Photo validation enforces image mimes
- UUID filenames prevent path traversal (PhotoStorage)
- Private storage for finance/monitoring photos
- Authorization check before serving files (PhotoController)

---

## SUMMARY

**Total Issues Found:** 6 Critical/High, 3 Medium/Low

**Critical/High Priority Fixes Required:**
1. ✅ Add admin middleware to inventory routes OR confirm petugas access is intentional
2. ✅ Create RusunController with CRUD operations
3. ✅ Add status validation for finance location selection
4. ✅ Fix race condition in admin deletion with transaction lock

**Medium Priority Improvements:**
5. Remove 'role' from User fillable or add guarded protection
6. Verify database indexes on foreign keys
7. Add length limits to text fields

**Overall Security Posture:** MODERATE
- Good foundational security practices (CSRF, XSS, SQL injection prevention)
- Authorization logic is present but inventory module has critical gap
- Missing feature (RusunController) creates management gap
- Race condition and validation gaps need attention

---

## TESTING RECOMMENDATIONS

1. **Authorization Testing:** Verify petugas cannot access inventory if it should be admin-only
2. **Concurrent Testing:** Test multiple admin deletions simultaneously
3. **Validation Testing:** Attempt to submit nonaktif location_id via direct POST
4. **CSRF Testing:** Verify all state-changing endpoints require CSRF token
5. **XSS Testing:** Test all text inputs for stored XSS vulnerabilities
6. **File Upload Testing:** Test photo uploads with malicious filenames/content types

---

**Auditor Notes:**
- Code quality is generally good with proper use of Laravel conventions
- Authorization checks are implemented but inconsistently placed (route vs controller)
- Missing rusun management is a significant feature gap
- Database constraints are properly implemented
- Photo storage and access control is well-designed
