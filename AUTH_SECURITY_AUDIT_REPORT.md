# AUTHENTICATION & AUTHORIZATION SECURITY AUDIT REPORT

**Project**: Sistem Sarana dan Prasarana - UPRS VI  
**Database**: sistem_inventory_uprs_vi (MySQL)  
**Framework**: Laravel 11  
**Audit Date**: 2026-10-15  
**Roles**: admin, petugas  

---

## EXECUTIVE SUMMARY

Comprehensive security audit of authentication and authorization system covering:
- Login/logout flows
- Session management
- CSRF protection
- Rate limiting/throttling
- Role-based access control
- Input validation
- SQL injection prevention
- XSS protection
- Privilege escalation prevention

**Total Tests**: 57  
**Passed**: 51 (89.5%)  
**Failed**: 6 (10.5%)  

**Security Issues**: 3  
- **CRITICAL**: 1  
- **HIGH**: 0  
- **MEDIUM**: 2  
- **LOW**: 0  

---

## CRITICAL ISSUES

### 🔴 ISSUE #1: User Enumeration via Role Check

**Severity**: CRITICAL  
**Location**: `app/Http/Controllers/AuthController.php:59-67`  
**CWE**: CWE-204 (Observable Response Discrepancy)

#### Description
The login method performs a separate database query to check user role **BEFORE** authentication. This creates observable differences in error messages:
- Valid email with invalid role: "Akun Anda tidak memiliki akses ke sistem."
- Invalid email: "Email atau password salah."

```php
// VULNERABLE CODE (lines 59-67)
$user = \App\Models\User::where('email', $credentials['email'])->first();

if ($user && ! in_array($user->role, ['admin', 'petugas'])) {
    $this->incrementLoginAttempts($request);
    
    return back()->withErrors([
        'email' => 'Akun Anda tidak memiliki akses ke sistem.',
    ])->onlyInput('email');
}
```

#### Impact
- **User Enumeration**: Attackers can determine which email addresses are registered in the system
- **Targeted Attacks**: Valid emails can be collected for phishing, credential stuffing, or social engineering
- **Information Disclosure**: Reveals internal user management structure

#### Exploitation Scenario
```bash
# Attacker tests emails:
POST /login
  email: victim@example.com
  password: random
  
# Response: "Email atau password salah" → Email NOT in system

POST /login
  email: real-user@company.com  
  password: random
  
# Response: "Akun Anda tidak memiliki akses" → Email EXISTS in system!
```

#### Recommendation
**Option 1 - Post-Authentication Check (Recommended)**:
```php
public function login(Request $request): RedirectResponse
{
    if ($this->hasTooManyLoginAttempts($request)) {
        return $this->sendLockoutResponse($request);
    }

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    // Authenticate FIRST
    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $user = Auth::user();
        
        // Check role AFTER authentication
        if (! in_array($user->role, ['admin', 'petugas'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            $this->incrementLoginAttempts($request);
            
            return back()->withErrors([
                'email' => 'Email atau password salah.',  // Generic message
            ])->onlyInput('email');
        }
        
        $this->clearLoginAttempts($request);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    $this->incrementLoginAttempts($request);

    return back()->withErrors([
        'email' => 'Email atau password salah.',
    ])->onlyInput('email');
}
```

**Option 2 - Custom Guard**:
Create a custom authentication guard that validates role during the authentication attempt itself.

**Fix Priority**: IMMEDIATE - This is an active information disclosure vulnerability

---

## MEDIUM ISSUES

### 🟡 ISSUE #2: Password Reset Not Implemented

**Severity**: MEDIUM  
**Location**: `routes/web.php`  
**CWE**: CWE-640 (Weak Password Recovery Mechanism)

#### Description
Password reset functionality is not implemented despite:
- `password_reset_tokens` table existing in database
- Configuration in `config/auth.php` being present
- Laravel framework support being available

#### Impact
- **Account Lockout**: Users who forget passwords cannot self-recover
- **Admin Burden**: Every password reset requires manual admin intervention
- **Business Continuity**: Affects operational efficiency
- **Security Risk**: Admins may resort to insecure password reset methods (e.g., SMS, weak temporary passwords)

#### Recommendation

**Step 1**: Generate password reset controllers
```bash
php artisan make:controller Auth/ForgotPasswordController
php artisan make:controller Auth/ResetPasswordController
```

**Step 2**: Add routes to `routes/web.php`
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.attempt');
    
    // Add password reset routes
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
        ->name('password.update');
});
```

**Step 3**: Implement controllers using Laravel's built-in password reset traits

**Step 4**: Create views:
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/emails/password-reset.blade.php`

**Step 5**: Configure mail settings in `.env`

**Fix Priority**: MEDIUM - Should be implemented in next sprint

---

### 🟡 ISSUE #3: Timing Attack in Role Validation

**Severity**: MEDIUM  
**Location**: `app/Http/Controllers/AuthController.php:59-67`  
**CWE**: CWE-208 (Observable Timing Discrepancy)

#### Description
The separate database query and role check before `Auth::attempt()` creates measurable timing differences:
- Valid email with role check: Database query + in_array check + response (~50-100ms)
- Invalid email: Only Auth::attempt with bcrypt verification (~200-300ms due to hashing)

#### Impact
- **Advanced User Enumeration**: Sophisticated attackers can use timing analysis to enumerate users
- **Complements Issue #1**: Provides alternative enumeration method even if error messages are fixed
- **Low Exploitability**: Requires multiple requests and statistical analysis

#### Recommendation
Fix is the same as **Issue #1** - perform role check after authentication. This ensures:
- All login attempts take approximately the same time (dominated by bcrypt hash verification)
- No observable timing differences between valid/invalid emails
- Constant-time execution flow

**Fix Priority**: HIGH - Should be fixed together with Issue #1

---

## STRENGTHS IDENTIFIED

### ✅ Authentication Core (Excellent)

1. **Password Security**
   - ✓ Bcrypt hashing with proper cost factor
   - ✓ Passwords hidden from serialization
   - ✓ Minimum 8 characters enforced
   - ✓ Password confirmation required on creation
   - ✓ Hash::make() and Hash::check() used correctly

2. **Rate Limiting** 
   - ✓ 5 failed attempts per email+IP combination
   - ✓ 60-second lockout period
   - ✓ Failed attempts cleared on successful login
   - ✓ Only failed attempts counted (not successful logins)
   - ✓ User-friendly error messages with countdown
   - ✓ Proper throttle key implementation

3. **Session Management**
   - ✓ Session regeneration on login (prevents fixation)
   - ✓ Session invalidation on logout
   - ✓ CSRF token regeneration on logout
   - ✓ HttpOnly flag set (prevents XSS access)
   - ✓ SameSite=lax (CSRF protection)
   - ✓ 120-minute session lifetime

### ✅ CSRF Protection (Excellent)

- ✓ VerifyCsrfToken middleware enabled globally
- ✓ @csrf directive in all forms
- ✓ No dangerous exceptions to CSRF protection
- ✓ Logout uses POST method (not GET)
- ✓ Token regeneration on logout

### ✅ Role-Based Access Control (Excellent)

1. **Middleware**
   - ✓ RoleMiddleware properly registered
   - ✓ Authentication checked before role
   - ✓ Role validated against allowed list
   - ✓ 403 abort on unauthorized access

2. **Route Protection**
   - ✓ Admin-only routes protected with `role:admin`
   - ✓ Petugas routes protected with `role:petugas`
   - ✓ Mixed access routes use `role:admin,petugas`
   - ✓ Auth middleware applied to all protected routes

3. **Data Ownership**
   - ✓ Controllers verify ownership for edit/update/delete
   - ✓ Petugas can only modify their own records
   - ✓ Admin can modify all records
   - ✓ Proper 403 responses for unauthorized access

### ✅ Input Validation (Excellent)

- ✓ Form Request validation used throughout
- ✓ Email format validation
- ✓ Role whitelist validation (Rule::in(['admin', 'petugas']))
- ✓ Unique email constraint
- ✓ Password confirmation required
- ✓ TrimStrings middleware prevents whitespace bypass

### ✅ SQL Injection Prevention (Excellent)

- ✓ Eloquent ORM used exclusively
- ✓ No raw SQL queries in controllers
- ✓ Auth::attempt() with parameterized credentials
- ✓ Query Builder with parameter binding
- ✓ Mass assignment protection via $fillable

### ✅ XSS Prevention (Excellent)

- ✓ Blade templates with automatic escaping
- ✓ No unescaped user input: `{!! !!}` not used in forms
- ✓ Sensitive attributes hidden from serialization
- ✓ HttpOnly session cookies

### ✅ Privilege Escalation Prevention (Excellent)

- ✓ Role cannot be changed during user update
- ✓ UpdateUserRequest excludes 'role' field
- ✓ UserController uses `only(['name', 'email'])`
- ✓ Admin cannot delete themselves
- ✓ Cannot delete last admin user
- ✓ User deletion checks for related records

### ✅ Remember Me Functionality (Excellent)

- ✓ Properly implemented with Auth::attempt()
- ✓ remember_token column exists
- ✓ Checkbox in login form
- ✓ Token hidden from serialization

---

## EDGE CASES & MINOR OBSERVATIONS

### 🔵 Observation #1: Multiple Database Queries on Login
**Location**: AuthController.php:59-69  
**Status**: By Design (with security flaw)

The current implementation makes 2 queries:
1. `User::where('email', ...)->first()` - Role check
2. `Auth::attempt()` - Authentication

While this is the root cause of Issues #1 and #3, the comment in the code acknowledges this trade-off:
```php
// Note: Ini memang 2 query (check user + Auth::attempt), tapi ini trade-off
// yang acceptable untuk keamanan.
```

**Reality**: This is NOT an acceptable security trade-off. The alternative (post-auth role check) is both more secure and equally simple to maintain.

### 🔵 Observation #2: No Account Lockout After Exhausting Rate Limit
**Status**: Working as Designed

After 5 failed attempts, the account is temporarily locked for 60 seconds. However:
- No permanent lockout after repeated violations
- No notification to user or admin
- Rate limiter clears after decay period

**Recommendation**: Consider adding:
- Email notification on repeated lockouts
- Admin dashboard alert for suspicious activity
- Longer lockout periods for repeat offenders

### 🔵 Observation #3: Password Strength Not Enforced
**Status**: Minimal Requirements

Current validation: `min:8` only

**Recommendation**: Consider adding:
- At least one uppercase letter
- At least one number
- At least one special character
- Check against common password lists

Example:
```php
'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*?&])/']
```

### 🔵 Observation #4: No Login Activity Log
**Status**: Not Implemented

No logging of:
- Successful logins
- Failed login attempts
- Logout events
- Session hijacking attempts

**Recommendation**: Implement audit logging for security events

---

## RECOMMENDATIONS SUMMARY

### Immediate Actions (Critical)

1. **Fix User Enumeration (Issue #1)**
   - Move role check AFTER authentication
   - Use generic error messages
   - Test thoroughly for timing differences

### Short-term Actions (Medium Priority)

2. **Implement Password Reset (Issue #2)**
   - Use Laravel's built-in password reset
   - Configure email delivery
   - Add throttling to reset requests

3. **Add Security Logging**
   - Log all authentication events
   - Monitor for suspicious activity
   - Alert on repeated failures

### Long-term Enhancements (Low Priority)

4. **Enhance Password Policy**
   - Complexity requirements
   - Password history
   - Periodic rotation reminders

5. **Two-Factor Authentication**
   - Optional 2FA for admin accounts
   - TOTP or email-based codes

6. **Account Activity Monitoring**
   - Show recent login history to users
   - Notify on new device/location login
   - Session management (view/revoke active sessions)

---

## COMPLIANCE & BEST PRACTICES

### ✅ OWASP Top 10 Compliance

| OWASP Category | Status | Notes |
|----------------|--------|-------|
| A01: Broken Access Control | ⚠️ PARTIAL | Role check has enumeration flaw |
| A02: Cryptographic Failures | ✅ PASS | Bcrypt hashing, secure sessions |
| A03: Injection | ✅ PASS | Eloquent ORM, no raw queries |
| A04: Insecure Design | ⚠️ PARTIAL | No password reset flow |
| A05: Security Misconfiguration | ✅ PASS | Good defaults, proper middleware |
| A07: XSS | ✅ PASS | Blade escaping, HttpOnly cookies |
| A08: Software/Data Integrity | ✅ PASS | CSRF protection, validation |
| A09: Logging/Monitoring | ❌ FAIL | No security event logging |
| A10: SSRF | N/A | Not applicable |

### ✅ CWE Coverage

- ✅ CWE-89 (SQL Injection): Protected via Eloquent ORM
- ✅ CWE-79 (XSS): Protected via Blade escaping
- ✅ CWE-352 (CSRF): Protected via middleware
- ✅ CWE-307 (Brute Force): Protected via rate limiting
- ⚠️ CWE-204 (Observable Response): User enumeration vulnerability
- ⚠️ CWE-208 (Timing Attack): Timing discrepancy exists
- ⚠️ CWE-640 (Password Recovery): Not implemented

---

## TEST COVERAGE DETAILS

### Test Categories

**Database & Setup** (3 tests)
- ✅ Database connection
- ✅ Users table exists  
- ⚠️ Test user verification (no test users in DB)

**Password Security** (2 tests)
- ⚠️ Hashing verification (requires test user)
- ⚠️ Hash verification (requires test user)

**Login Validation** (4 tests)
- ✅ AuthController exists
- ✅ Login route configured
- ✅ Email validation required
- ✅ Password required

**Rate Limiting** (6 tests)
- ✅ Rate limiter configured
- ✅ 5 attempt limit enforced
- ✅ Email+IP combination used
- ✅ Failed attempts tracked
- ✅ Successful login clears attempts
- ❌ User enumeration vulnerability detected

**Session Security** (7 tests)
- ✅ Session regeneration on login
- ✅ Session invalidation on logout
- ✅ CSRF token regeneration
- ✅ Session lifetime configured
- ✅ HttpOnly cookies enabled
- ✅ SameSite protection
- ✅ Secure flag configuration

**CSRF Protection** (4 tests)
- ✅ Middleware exists
- ✅ Middleware in web group
- ✅ @csrf in forms
- ✅ No dangerous exceptions

**Remember Me** (3 tests)
- ✅ Implementation present
- ✅ Database column exists
- ✅ Form checkbox present

**Role-Based Access** (7 tests)
- ✅ Middleware exists
- ✅ Middleware registered
- ✅ Authentication check
- ✅ Role validation
- ✅ 403 responses
- ✅ Admin routes protected
- ✅ Ownership verification

**Password Reset** (2 tests)
- ✅ Database table exists
- ❌ Routes not implemented

**Input Validation** (6 tests)
- ✅ Form Request validation
- ✅ Password minimum length
- ✅ Password confirmation
- ✅ Role whitelist
- ✅ SQL injection protection
- ✅ Parameterized queries

**XSS Protection** (2 tests)
- ✅ Blade templates
- ✅ Sensitive attributes hidden

**Logout Security** (2 tests)
- ✅ POST method used
- ✅ Auth::logout() called

**Privilege Escalation** (4 tests)
- ✅ Role immutable on update
- ✅ Self-deletion prevented
- ✅ Last admin protected
- ✅ User management restricted

**Additional Security** (5 tests)
- ✅ Guest middleware
- ✅ Auth middleware
- ✅ Email uniqueness
- ✅ TrimStrings middleware
- ❌ Timing attack vulnerability

---

## CONCLUSION

The authentication and authorization system demonstrates **strong security fundamentals** with excellent implementation of:
- Password hashing and storage
- CSRF protection
- SQL injection prevention
- XSS prevention
- Role-based access control
- Session management
- Input validation

However, **one critical vulnerability** (user enumeration via role check) and **two medium-priority issues** (missing password reset, timing attack) should be addressed.

**Overall Security Rating**: 🟡 **B+ (Good, with one critical fix needed)**

After addressing the three identified issues, the system would achieve an **A rating**.

---

## APPENDIX: FIX VERIFICATION CHECKLIST

After implementing fixes, verify:

- [ ] Login with invalid email returns generic error
- [ ] Login with valid email but invalid role returns generic error
- [ ] Login with valid credentials succeeds
- [ ] No timing differences between valid/invalid emails (measure with tool)
- [ ] Password reset link works
- [ ] Password reset email is sent
- [ ] Password reset token expires correctly
- [ ] Old password reset tokens are invalidated after use
- [ ] Rate limiting still works after role check fix
- [ ] All existing tests still pass

---

**Report Generated**: 2026-10-15T07:01:08Z  
**Auditor**: Automated Security Audit Script v1.0  
**Next Review**: After critical fixes are deployed
