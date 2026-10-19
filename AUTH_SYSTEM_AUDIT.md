# Authentication & User Management System Audit
**Date**: 2026-10-16  
**Application**: Gabungan Sistem Sarpras (Laravel 10.50.3, PHP 8.1.10)  
**Branch**: bugfix/tahap-1-critical-high-priority  
**Audit Type**: Investigation Only (No Modifications)

---

## Executive Summary

The authentication system has **solid foundational security** with proper CSRF protection, session management, role-based access control, and rate limiting. However, there are **critical missing features** (password reset) and **security enhancements needed** (password policy, session hardening).

### Security Rating: **B+ (Good, with gaps)**
- ✅ Core authentication flow secure
- ✅ CSRF protection fully implemented
- ✅ Role-based access control working
- ⚠️ No password recovery mechanism
- ⚠️ Weak password policy
- ⚠️ Email enumeration vulnerability

---

## 1. Login Flow Analysis

### ✅ WORKING CORRECTLY

#### 1.1 Authentication Flow
- **Route**: `POST /login` (route name: `login.attempt`)
- **Controller**: `AuthController@login`
- **Middleware**: `guest`, `throttle:login`
- **CSRF Protection**: ✅ Active (form includes `@csrf` token)

#### 1.2 Input Validation
```php
Validated Fields:
- email: required|email
- password: required
- remember: optional boolean
```
- ✅ Email format validation
- ✅ Required field validation
- ✅ Proper error messages in Indonesian

#### 1.3 Security Features
| Feature | Status | Implementation |
|---------|--------|----------------|
| CSRF Protection | ✅ Working | VerifyCsrfToken middleware active |
| Rate Limiting | ✅ Working | 5 attempts/min per email+IP |
| Session Regeneration | ✅ Working | `$request->session()->regenerate()` |
| Role Validation | ✅ Working | Only admin/petugas allowed |
| Remember Me | ✅ Working | Creates remember_token |
| Intended URL Redirect | ✅ Working | `redirect()->intended()` |

#### 1.4 Rate Limiting Implementation
**Two-Layer Protection** (potential conflict):
1. **Route-level throttle**: `throttle:login` (5 attempts/min via RouteServiceProvider)
2. **Controller-level rate limiter**: Custom `RateLimiter::hit()` implementation

**Issue**: Test failure indicates conflict between these two mechanisms.

```php
// RouteServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    $email = $request->input('email', '');
    return Limit::perMinute(5)->by($email.'|'.$request->ip());
});

// AuthController.php - lines 100-130
protected function hasTooManyLoginAttempts(Request $request): bool
{
    return RateLimiter::tooManyAttempts($this->throttleKey($request), 5);
}
```

**Test Result**: `AuthenticationFlowTest::test_rate_limiting_tracks_failed_login_attempts` FAILS
- Expected: `302 redirect` with error message
- Actual: `429 Too Many Requests` (from route-level throttle)
- Root Cause: Route middleware returns 429 before controller logic executes

---

## 2. Logout Functionality

### ✅ SECURE IMPLEMENTATION

#### 2.1 Logout Route
- **Route**: `POST /logout` (route name: `logout`)
- **Method**: POST only (prevents CSRF attacks via GET)
- **Middleware**: `auth` (authenticated users only)
- **CSRF Protection**: ✅ Required

#### 2.2 Security Measures
```php
// AuthController@logout (lines 87-95)
Auth::logout();                          // ✅ Invalidate authentication
$request->session()->invalidate();       // ✅ Destroy session data
$request->session()->regenerateToken();  // ✅ Regenerate CSRF token
return redirect()->route('login');       // ✅ Redirect to login
```

#### 2.3 UI Implementation
**Location**: `resources/views/layouts/app.blade.php` (lines 168-171)
```html
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
</form>
```
✅ Proper CSRF token inclusion  
✅ POST method (not vulnerable to CSRF via links)  
✅ Accessible from all authenticated pages

#### 2.4 Test Coverage
All logout tests **PASSING**:
- ✅ Session invalidation
- ✅ CSRF token regeneration
- ✅ Prevents access to protected routes post-logout
- ✅ Redirects to login page

---

## 3. Session Management

### ⚠️ PARTIALLY SECURE (Needs Hardening)

#### 3.1 Configuration (`config/session.php`)
| Setting | Value | Security Impact | Recommendation |
|---------|-------|-----------------|----------------|
| `lifetime` | 120 minutes | ✅ Reasonable | Consider reducing to 60 min |
| `expire_on_close` | false | ⚠️ Session persists | Consider `true` for higher security |
| `driver` | file | ⚠️ Scalability issue | OK for small deployments |
| `encrypt` | false | ⚠️ Session data readable | Consider `true` for sensitive data |
| `http_only` | true | ✅ XSS protection | Good |
| `same_site` | lax | ✅ CSRF mitigation | Good |
| `secure` | env('SESSION_SECURE_COOKIE') | ⚠️ Not enforced | **MUST be `true` in production** |

#### 3.2 Session Security Gaps
1. **No HTTPS Enforcement in Config**
   - `.env` doesn't set `SESSION_SECURE_COOKIE=true`
   - Cookies sent over HTTP in development
   - **Risk**: Session hijacking via network sniffing

2. **No Session Encryption**
   - Session data stored in plaintext
   - File permissions protect it, but not encrypted
   - **Risk**: If server compromised, all sessions readable

3. **No Idle Timeout Warning**
   - Users not warned before session expires
   - No auto-save of form data
   - **UX Issue**: Data loss if session expires during form fill

4. **No Concurrent Session Management**
   - User can login from multiple devices simultaneously
   - No "logout everywhere" feature
   - **Risk**: Stolen credentials used on multiple devices

#### 3.3 Session Regeneration
✅ **Properly implemented** on:
- Login success (`$request->session()->regenerate()`)
- Logout (`$request->session()->regenerateToken()`)

This prevents **session fixation attacks**.

---

## 4. CSRF Protection

### ✅ FULLY IMPLEMENTED

#### 4.1 Middleware Configuration
**File**: `app/Http/Kernel.php` (line 37)
```php
'web' => [
    \\App\\Http\\Middleware\\EncryptCookies::class,
    \\Illuminate\\Session\\Middleware\\StartSession::class,
    \\App\\Http\\Middleware\\VerifyCsrfToken::class,  // ✅ Active
    // ...
],
```

#### 4.2 CSRF Token Coverage
| Route Type | CSRF Required | Status |
|------------|---------------|--------|
| Login (POST) | ✅ Yes | ✅ Enforced |
| Logout (POST) | ✅ Yes | ✅ Enforced |
| All Form Submissions | ✅ Yes | ✅ Enforced |
| Export Routes (POST) | ✅ Yes | ✅ Enforced |

#### 4.3 Exclusions
**File**: `app/Http/Middleware/VerifyCsrfToken.php`
```php
protected $except = [
    // Empty - no exclusions
];
```
✅ **No routes excluded** - excellent security posture

#### 4.4 Test Results
**All CSRF tests PASSING**:
- ✅ Login route uses web middleware
- ✅ Inventory routes use web middleware
- ✅ No critical routes excluded
- ✅ Requests with valid CSRF token succeed
- ✅ Export routes have throttle middleware

---

## 5. Password Validation & Security

### ⚠️ WEAK PASSWORD POLICY

#### 5.1 Current Password Rules
**User Creation** (`StoreUserRequest.php`):
```php
'password' => ['required', 'string', 'min:8', 'confirmed']
```

**User Update** (`UpdateUserRequest.php`):
```php
'password' => ['nullable', 'string', 'min:8', 'confirmed']
```

#### 5.2 Password Policy Gaps
| Requirement | Implemented | Risk Level |
|-------------|-------------|------------|
| Minimum length (8 chars) | ✅ Yes | - |
| Maximum length | ❌ No | Low |
| Uppercase letter | ❌ No | **HIGH** |
| Lowercase letter | ❌ No | **HIGH** |
| Number | ❌ No | **HIGH** |
| Special character | ❌ No | **HIGH** |
| Common password check | ❌ No | **HIGH** |
| Dictionary word check | ❌ No | Medium |
| Password history | ❌ No | Medium |
| Expiration policy | ❌ No | Low |

#### 5.3 Password Storage
✅ **Secure Implementation**:
- Uses `Hash::make()` (bcrypt algorithm)
- Automatically salted
- Cost factor: Laravel default (10 rounds)
- Passwords never logged or displayed

#### 5.4 Test Credentials in Seeder
⚠️ **Weak Default Passwords**:
```php
// database/seeders/UserSeeder.php
['email' => 'admin@example.com', 'password' => 'password'],
['email' => 'petugas@example.com', 'password' => 'password'],
```

**Recommendation**: 
- Use stronger default passwords
- Force password change on first login
- Add warning comment about production use

---

## 6. Role-Based Access Control (RBAC)

### ✅ PROPERLY IMPLEMENTED

#### 6.1 Role Definitions
**File**: `app/Models/User.php`
```php
public const ROLES = [
    'admin' => 'Admin',
    'petugas' => 'Petugas',
];
```

#### 6.2 Role Middleware
**File**: `app/Http/Middleware/RoleMiddleware.php`
```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    if (!$user) return redirect()->route('login');
    if (!in_array($user->role, $roles)) abort(403);
    return $next($request);
}
```

#### 6.3 Route Protection Matrix
| Route Group | Admin | Petugas | Guest |
|-------------|-------|---------|-------|
| Dashboard | ✅ | ✅ | ❌ (redirect login) |
| Monitoring View | ✅ | ✅ | ❌ |
| Monitoring Create | ❌ | ✅ | ❌ |
| Keuangan List | ✅ | ❌ | ❌ |
| Keuangan Create | ❌ | ✅ | ❌ |
| User Management | ✅ | ❌ | ❌ |
| Location Management | ✅ | ❌ | ❌ |
| Inventory (All) | ✅ | ✅ | ❌ |

#### 6.4 Role Validation on Login
✅ **Pre-authentication role check** (lines 59-67):
```php
$user = \App\Models\User::where('email', $credentials['email'])->first();

if ($user && !in_array($user->role, ['admin', 'petugas'])) {
    return back()->withErrors([
        'email' => 'Akun Anda tidak memiliki akses ke sistem.',
    ]);
}
```

**Security Benefit**: Invalid role users never receive a session, even momentarily.

#### 6.5 Test Coverage
All RBAC tests **PASSING**:
- ✅ Admin can access monitoring
- ✅ Petugas can access monitoring
- ✅ Invalid roles blocked (403 response)
- ✅ Guest redirected to login

---

## 7. User Management (Admin Functions)

### ✅ SECURE WITH AUDIT TRAIL PROTECTION

#### 7.1 User CRUD Operations
**Controller**: `app/Http/Controllers/Admin/UserController.php`

| Operation | Route | Middleware | Access |
|-----------|-------|------------|--------|
| List Users | GET /pengguna | auth, role:admin | Admin only |
| Create User | POST /pengguna | auth, role:admin | Admin only |
| Edit User | PUT /pengguna/{id} | auth, role:admin | Admin only |
| Delete User | DELETE /pengguna/{id} | auth, role:admin | Admin only |

#### 7.2 Deletion Safeguards
✅ **Comprehensive Protection** (lines 83-117):

1. **Cannot Delete Self**
   ```php
   if ($pengguna->id === Auth::id()) {
       return redirect()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
   }
   ```

2. **Cannot Delete Last Admin**
   ```php
   if ($pengguna->isAdmin() && User::where('role', 'admin')->count() <= 1) {
       return redirect()->with('error', 'Tidak dapat menghapus admin terakhir.');
   }
   ```

3. **Audit Trail Protection** (prevents data orphaning)
   ```php
   if ($pengguna->waterMonitorings()->exists()) {
       return redirect()->with('error', 'Pengguna tidak dapat dihapus karena memiliki data pemeriksaan.');
   }
   
   if ($pengguna->financeReports()->exists()) {
       return redirect()->with('error', 'Pengguna tidak dapat dihapus karena memiliki data laporan bulanan.');
   }
   
   if (DB::table('stock_adjustments')->where('user_id', $pengguna->id)->exists()) {
       return redirect()->with('error', 'Pengguna tidak dapat dihapus karena memiliki riwayat penyesuaian stok.');
   }
   ```

#### 7.3 Role Immutability
✅ **Role cannot be changed after creation** (line 67):
```php
// UpdateUserRequest only validates name, email, password
// Role field excluded from update
```

**Security Benefit**: Prevents privilege escalation by modifying role after creation.

---

## 8. Authentication Middleware

### ✅ CORRECTLY APPLIED

#### 8.1 Middleware Registration
**File**: `app/Http/Kernel.php`
```php
protected $middlewareAliases = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
    'role' => \App\Http\Middleware\RoleMiddleware::class,
    // ...
];
```

#### 8.2 Guest Middleware
**Behavior**: Authenticated users redirected to dashboard
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm']);
    Route::post('/login', [AuthController::class, 'login']);
});
```
✅ Prevents authenticated users from seeing login page

#### 8.3 Auth Middleware
**Behavior**: Guests redirected to login
```php
// app/Http/Middleware/Authenticate.php
protected function redirectTo(Request $request): ?string
{
    return $request->expectsJson() ? null : route('login');
}
```
✅ Proper redirect handling for web vs API requests

---

## 9. Missing Features (Critical Gaps)

### ❌ 9.1 Password Reset Functionality
**Status**: **NOT IMPLEMENTED** (despite infrastructure existing)

**Evidence**:
- ✅ Migration exists: `2014_10_12_100000_create_password_reset_tokens_table.php`
- ✅ Config exists: `config/auth.php` (lines 93-100)
- ❌ No password reset routes
- ❌ No ForgotPasswordController
- ❌ No ResetPasswordController
- ❌ No password reset views

**Configuration Found**:
```php
// config/auth.php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,  // Reset link expires in 60 minutes
        'throttle' => 60,  // Can request new token every 60 seconds
    ],
],
```

**Impact**: 
- Users **cannot recover accounts** if they forget passwords
- Requires admin intervention for password resets
- Poor user experience
- Higher support burden

**Recommendation**: Implement Laravel's built-in password reset:
```bash
# Generate password reset controllers
php artisan make:controller Auth/ForgotPasswordController
php artisan make:controller Auth/ResetPasswordController

# Add routes (routes/web.php)
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
```

### ❌ 9.2 User Registration
**Status**: **NOT IMPLEMENTED** (Intentional Design)

**Evidence**:
- ❌ No registration routes
- ❌ No RegisterController
- ⚠️ Welcome blade references non-existent `route('register')` (line 27)

**Design Decision**: Admin creates all users (secure for internal systems)

**Issue**: Dead link in welcome view should be removed or hidden.

### ❌ 9.3 Two-Factor Authentication (2FA)
**Status**: **NOT IMPLEMENTED**

**Impact**:
- Single factor authentication only
- Higher risk if passwords compromised
- No compliance with modern security standards

**Recommendation**: Consider Laravel Fortify or custom TOTP implementation.

### ❌ 9.4 Audit Logging
**Status**: **NOT IMPLEMENTED**

**Missing Logs**:
- Login events (successful/failed)
- Logout events
- Password changes
- Role assignments
- Failed authorization attempts
- IP address tracking

**Recommendation**: Implement logging for security monitoring and compliance.

---

## 10. Security Vulnerabilities

### 🔴 10.1 Email Enumeration (Medium Risk)
**Vulnerability**: Attacker can determine if email exists in system

**Evidence**:
```php
// Different error messages reveal email existence
if ($user && !in_array($user->role, ['admin', 'petugas'])) {
    return back()->withErrors([
        'email' => 'Akun Anda tidak memiliki akses ke sistem.',  // Email exists
    ]);
}

if (Auth::attempt($credentials, $request->boolean('remember'))) {
    // ...
}

return back()->withErrors([
    'email' => 'Email atau password salah.',  // Could be non-existent email
]);
```

**Attack Scenario**:
1. Try login with `attacker@example.com` / `random123`
   - Response: "Email atau password salah" → email doesn't exist
2. Try login with `known_user@example.com` / `random123`
   - Response: "Email atau password salah" → email exists
3. Try login with `invalid_role_user@example.com` / `correct_password`
   - Response: "Akun Anda tidak memiliki akses" → email exists, valid password

**Recommendation**: Use same error message for all scenarios:
```php
'email' => 'Email atau password salah.'
```

### 🟡 10.2 Session Fixation Risk (Low - Mitigated)
✅ **Already Mitigated** by session regeneration on login

### 🟡 10.3 Concurrent Session Abuse (Low)
**Issue**: No limit on concurrent sessions per user

**Risk**: Stolen credentials used on multiple devices simultaneously

**Recommendation**: Track active sessions and provide "logout everywhere" feature.

---

## 11. Test Coverage Summary

### Test Results
**Total Tests**: 27  
**Passed**: 25 ✅  
**Failed**: 1 ❌  
**Skipped**: 2 ⚠️  

### Passing Tests
**AuthenticationFlowTest** (9/10 passed):
- ✅ Login page accessible
- ✅ CSRF protection active
- ✅ Required field validation
- ✅ Email format validation
- ✅ Invalid credentials handling
- ✅ Invalid role rejection
- ✅ Successful login redirect
- ✅ Session regeneration
- ✅ Remember me functionality
- ❌ **Rate limiting** (FAILED - see below)

**CsrfProtectionTest** (6/8 passed):
- ✅ Login route uses web middleware
- ✅ Inventory routes use web middleware
- ✅ No routes exclude CSRF
- ✅ Valid CSRF token succeeds
- ✅ Export routes have throttle
- ✅ Inventory routes require auth
- ⚠️ CSRF middleware registration (SKIPPED)
- ⚠️ Login throttle middleware (SKIPPED)

### Failed Test Analysis
**Test**: `AuthenticationFlowTest::test_rate_limiting_tracks_failed_login_attempts`  
**Status**: ❌ FAILED

**Expected**: 302 redirect with error message  
**Actual**: 429 Too Many Requests

**Root Cause**: Conflict between two rate limiting mechanisms:
1. Route-level: `->middleware('throttle:login')` (returns 429)
2. Controller-level: Custom `RateLimiter::hit()` (returns redirect with message)

**Impact**: Rate limiting **works** but test expectations don't match implementation.

**Recommendation**: Choose one approach:
- **Option A**: Keep route-level throttle, remove controller implementation
- **Option B**: Keep controller implementation, remove route-level middleware (better UX)

---

## 12. Configuration Review

### Database Users
**File**: `database/seeders/UserSeeder.php`

| Email | Password | Role | Issue |
|-------|----------|------|-------|
| admin@example.com | password | admin | ⚠️ Weak password |
| petugas@example.com | password | petugas | ⚠️ Weak password |

**Note**: Task requires testing with `petugas@sarpras.com` / `password123` but this user doesn't exist in seeder.

**Actual Test Credentials** (from `AuthenticationFlowTest.php`):
- admin@sarpras.com / password123
- petugas@sarpras.com / password123

### Environment Configuration
**File**: `.env.example`
```ini
SESSION_DRIVER=file
SESSION_LIFETIME=120
# ⚠️ SESSION_SECURE_COOKIE not set (should be true in production)
```

---

## 13. Recommendations

### 🔴 Critical Priority (Implement Immediately)
1. **Implement Password Reset**
   - Use Laravel's built-in password reset
   - Estimated effort: 4-6 hours
   - Impact: Enables self-service account recovery

2. **Strengthen Password Policy**
   ```php
   'password' => [
       'required', 
       'string', 
       'min:8',
       'max:128', 
       'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
       'confirmed'
   ],
   ```
   - Require: uppercase, lowercase, number, special character
   - Add common password blacklist
   - Estimated effort: 2-3 hours

### 🟠 High Priority (Implement Soon)
3. **Fix Email Enumeration**
   - Use generic error message for all auth failures
   - Estimated effort: 30 minutes

4. **Enforce HTTPS in Production**
   ```php
   // .env (production)
   SESSION_SECURE_COOKIE=true
   SESSION_DOMAIN=yourdomain.com
   ```

5. **Resolve Rate Limiting Conflict**
   - Remove route-level throttle, keep controller implementation
   - Update test expectations
   - Estimated effort: 1 hour

6. **Add Session Encryption**
   ```php
   // config/session.php
   'encrypt' => true,
   ```

### 🟡 Medium Priority (Plan for Next Sprint)
7. **Implement Audit Logging**
   - Log all authentication events
   - Track IP addresses and user agents
   - Estimated effort: 8-12 hours

8. **Add Password Change Feature**
   - Allow users to change their own passwords
   - Force change on first login
   - Estimated effort: 4 hours

9. **Concurrent Session Management**
   - Track active sessions per user
   - Add "logout everywhere" feature
   - Estimated effort: 6-8 hours

10. **Add 2FA Support (Optional)**
    - TOTP-based (Google Authenticator compatible)
    - Estimated effort: 16-20 hours

### 🟢 Low Priority (Nice to Have)
11. **Add Idle Timeout Warning**
    - JavaScript countdown before session expires
    - Auto-save form data

12. **Improve Test Credentials**
    - Use stronger passwords in seeder
    - Add production safety warnings

13. **Remove Dead Links**
    - Fix `route('register')` reference in welcome.blade.php

---

## 14. Compliance Considerations

### OWASP Top 10 (2021)
| Risk | Status | Notes |
|------|--------|-------|
| A01: Broken Access Control | ✅ Good | RBAC properly implemented |
| A02: Cryptographic Failures | ⚠️ Partial | Passwords hashed, but sessions not encrypted |
| A03: Injection | ✅ Good | Parameterized queries, input validation |
| A04: Insecure Design | ⚠️ Partial | Missing password reset, weak policy |
| A05: Security Misconfiguration | ⚠️ Partial | SESSION_SECURE_COOKIE not enforced |
| A07: Auth Failures | ⚠️ Partial | Rate limiting works, but email enumeration exists |

### General Data Protection Regulation (GDPR)
- ⚠️ No explicit user consent management
- ⚠️ No audit trail for data access
- ✅ Passwords properly hashed

---

## 15. Testing Checklist

### Manual Testing Required
Due to investigation-only constraint, the following should be tested in a live environment:

- [ ] Login with valid admin credentials
- [ ] Login with valid petugas credentials  
- [ ] Login with invalid password (verify rate limiting after 5 attempts)
- [ ] Login with non-existent email
- [ ] Login with invalid role user (if exists in DB)
- [ ] Remember me functionality (close browser, reopen)
- [ ] Logout clears session
- [ ] Session expires after 120 minutes of inactivity
- [ ] Access protected route after logout (should redirect to login)
- [ ] CSRF token validation (remove token from form, submit should fail)
- [ ] Role-based access:
  - [ ] Admin can access /pengguna
  - [ ] Petugas cannot access /pengguna (403)
  - [ ] Admin can create users
  - [ ] Petugas can create monitoring records
  - [ ] Petugas cannot access /keuangan index
- [ ] User management:
  - [ ] Admin cannot delete self
  - [ ] Cannot delete last admin
  - [ ] Cannot delete user with data

---

## 16. Conclusion

### Strengths
✅ Solid core authentication implementation  
✅ Comprehensive CSRF protection  
✅ Effective role-based access control  
✅ Proper session handling with regeneration  
✅ Rate limiting prevents brute force  
✅ Audit trail protection for user deletions  
✅ Good test coverage (92% passing)

### Critical Gaps
❌ No password reset functionality  
❌ Weak password policy (no complexity requirements)  
❌ Email enumeration vulnerability  
❌ Session security not hardened for production  
❌ No authentication event logging

### Recommendation Summary
The authentication system is **production-ready for internal use** with the current feature set, but **requires immediate attention** for:
1. Password reset implementation (user self-service)
2. Stronger password policy (complexity requirements)
3. Email enumeration fix (security)
4. Production session hardening (HTTPS enforcement)

**Estimated Total Effort for Critical Issues**: 8-12 hours

---

## Appendix A: Route Security Matrix

### Public Routes (Guest Middleware)
| Method | URI | Controller | CSRF | Throttle |
|--------|-----|------------|------|----------|
| GET | /login | AuthController@showLoginForm | N/A | - |
| POST | /login | AuthController@login | ✅ | ✅ (5/min) |

### Authenticated Routes (Auth Middleware)
| Method | URI | Role | CSRF | Throttle |
|--------|-----|------|------|----------|
| POST | /logout | All | ✅ | - |
| GET | /dashboard | All | N/A | - |
| GET | /monitoring | admin,petugas | N/A | - |
| GET | /monitoring/create | petugas | N/A | - |
| POST | /monitoring | petugas | ✅ | - |
| GET | /keuangan | admin | N/A | - |
| POST | /keuangan | petugas | ✅ | - |
| GET | /pengguna | admin | N/A | - |
| POST | /pengguna | admin | ✅ | - |
| PUT | /pengguna/{id} | admin | ✅ | - |
| DELETE | /pengguna/{id} | admin | ✅ | - |
| POST | /inventory/reports/export/pdf | admin,petugas | ✅ | ✅ (role-based) |

---

## Appendix B: Password Policy Recommendation

### Proposed Validation Rule
```php
// app/Http/Requests/StoreUserRequest.php & UpdateUserRequest.php
'password' => [
    'required',
    'string',
    'min:8',
    'max:128',
    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
    'confirmed',
    function ($attribute, $value, $fail) {
        $commonPasswords = ['password', 'password123', '12345678', 'admin123'];
        if (in_array(strtolower($value), $commonPasswords)) {
            $fail('Password terlalu umum. Silakan gunakan password yang lebih kuat.');
        }
    },
],
```

### Custom Error Messages
```php
'password.regex' => 'Password harus mengandung minimal 1 huruf besar, 1 huruf kecil, 1 angka, dan 1 karakter spesial (@$!%*?&).',
'password.min' => 'Password minimal 8 karakter.',
'password.max' => 'Password maksimal 128 karakter.',
```

---

**End of Audit Report**
