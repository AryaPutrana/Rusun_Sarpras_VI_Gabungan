<?php

/**
 * AUTHENTICATION & AUTHORIZATION SECURITY AUDIT
 * 
 * Tests: Login, logout, session, CSRF, throttling, role-based access,
 * SQL injection, XSS, validation, privilege escalation
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

echo "═══════════════════════════════════════════════════════════════\n";
echo "   AUTHENTICATION & AUTHORIZATION SECURITY AUDIT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Test counters
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$issues = [];
$criticalIssues = 0;
$highIssues = 0;
$mediumIssues = 0;
$lowIssues = 0;

function test($description, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $result = $callback();
        if ($result === true) {
            $passedTests++;
            echo "✓ PASS: $description\n";
            return true;
        } else {
            $failedTests++;
            echo "✗ FAIL: $description\n";
            if (is_string($result)) {
                echo "  → $result\n";
            }
            return false;
        }
    } catch (Exception $e) {
        $failedTests++;
        echo "✗ ERROR: $description\n";
        echo "  → Exception: " . $e->getMessage() . "\n";
        return false;
    }
}

function addIssue($severity, $title, $location, $description, $impact, $fix) {
    global $issues, $criticalIssues, $highIssues, $mediumIssues, $lowIssues;
    
    $issues[] = [
        'severity' => $severity,
        'title' => $title,
        'location' => $location,
        'description' => $description,
        'impact' => $impact,
        'fix' => $fix,
    ];
    
    if ($severity === 'CRITICAL') $criticalIssues++;
    elseif ($severity === 'HIGH') $highIssues++;
    elseif ($severity === 'MEDIUM') $mediumIssues++;
    else $lowIssues++;
}

// ═══════════════════════════════════════════════════════════════
// 1. DATABASE & USER SETUP VERIFICATION
// ═══════════════════════════════════════════════════════════════
echo "\n[1] DATABASE & USER SETUP\n";
echo str_repeat("─", 63) . "\n";

test("Database connection", function() {
    DB::connection()->getPdo();
    return true;
});

test("Users table exists", function() {
    return DB::getSchemaBuilder()->hasTable('users');
});

test("Test user exists (petugas@sarpras.com)", function() {
    $user = DB::table('users')->where('email', 'petugas@sarpras.com')->first();
    return $user !== null;
});

$testUser = DB::table('users')->where('email', 'petugas@sarpras.com')->first();
$adminUser = DB::table('users')->where('role', 'admin')->first();

// ═══════════════════════════════════════════════════════════════
// 2. PASSWORD HASHING & STORAGE
// ═══════════════════════════════════════════════════════════════
echo "\n[2] PASSWORD HASHING & STORAGE\n";
echo str_repeat("─", 63) . "\n";

test("Passwords are hashed (not plaintext)", function() use ($testUser) {
    if (!$testUser) return "User not found";
    // Bcrypt hashes start with $2y$ and are 60 chars
    $isHashed = strlen($testUser->password) === 60 && str_starts_with($testUser->password, '$2y$');
    if (!$isHashed) {
        addIssue('CRITICAL', 'Password stored in plaintext', 
            'users table', 
            'User passwords are not properly hashed in the database.',
            'Passwords are exposed if database is compromised. Complete authentication bypass.',
            'Ensure all passwords are hashed using Hash::make() before storage.');
    }
    return $isHashed;
});

test("Password can be verified with Hash::check()", function() use ($testUser) {
    if (!$testUser) return "User not found";
    // Try to verify with known password
    return Hash::check('password123', $testUser->password);
});

// ═══════════════════════════════════════════════════════════════
// 3. LOGIN VALIDATION
// ═══════════════════════════════════════════════════════════════
echo "\n[3] LOGIN VALIDATION\n";
echo str_repeat("─", 63) . "\n";

test("AuthController exists", function() {
    return class_exists('App\Http\Controllers\AuthController');
});

test("Login route exists", function() {
    $routes = app('router')->getRoutes();
    foreach ($routes as $route) {
        if ($route->getName() === 'login.attempt') {
            return true;
        }
    }
    return false;
});

test("Login requires email validation", function() {
    // Check AuthController validation rules
    $reflection = new ReflectionMethod('App\Http\Controllers\AuthController', 'login');
    $code = file_get_contents($reflection->getFileName());
    return str_contains($code, "['required', 'email']") || str_contains($code, "'email' => 'required");
});

test("Login requires password", function() {
    $reflection = new ReflectionMethod('App\Http\Controllers\AuthController', 'login');
    $code = file_get_contents($reflection->getFileName());
    return str_contains($code, "'password' => ['required']") || str_contains($code, "'password' => 'required");
});

// ═══════════════════════════════════════════════════════════════
// 4. RATE LIMITING / THROTTLING
// ═══════════════════════════════════════════════════════════════
echo "\n[4] RATE LIMITING / THROTTLING\n";
echo str_repeat("─", 63) . "\n";

test("Rate limiting is configured", function() {
    $routeServiceProvider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));
    return str_contains($routeServiceProvider, "RateLimiter::for('login'");
});

test("Rate limit is set to 5 attempts", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, 'MAX_LOGIN_ATTEMPTS = 5');
});

test("Rate limit uses email+IP combination", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, '$request->ip()') && 
           str_contains($authController, "input('email')");
});

test("Failed attempts are tracked", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, 'incrementLoginAttempts');
});

test("Successful login clears attempts", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, 'clearLoginAttempts');
});

// Check for user enumeration vulnerability
test("Login does NOT have user enumeration via role check", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    
    // Check if there's a separate user query before Auth::attempt
    if (preg_match('/User::where.*->first\(\).*Auth::attempt/s', $authController)) {
        addIssue('CRITICAL', 'User Enumeration via Role Check',
            'AuthController.php:59-67',
            'The login method performs a separate database query to check user role BEFORE authentication. This allows attackers to enumerate valid email addresses by observing different error messages: "Akun Anda tidak memiliki akses" for valid emails with invalid roles vs "Email atau password salah" for invalid emails.',
            'Attackers can determine which email addresses are registered in the system, facilitating targeted attacks.',
            'Remove the pre-authentication role check. Instead, validate role AFTER successful authentication, or use a custom guard that handles role validation during Auth::attempt().');
        return false;
    }
    return true;
});

// ═══════════════════════════════════════════════════════════════
// 5. SESSION SECURITY
// ═══════════════════════════════════════════════════════════════
echo "\n[5] SESSION SECURITY\n";
echo str_repeat("─", 63) . "\n";

test("Session regeneration on login", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, '$request->session()->regenerate()');
});

test("Session invalidation on logout", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, '$request->session()->invalidate()');
});

test("CSRF token regeneration on logout", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, '$request->session()->regenerateToken()');
});

test("Session lifetime is configured", function() {
    $config = config('session.lifetime');
    return $config > 0;
});

test("Session uses httponly cookies", function() {
    $httpOnly = config('session.http_only');
    if (!$httpOnly) {
        addIssue('MEDIUM', 'Session Cookie Not HttpOnly',
            'config/session.php:184',
            'Session cookies do not have the HttpOnly flag set.',
            'Session tokens can be accessed via JavaScript, making the application vulnerable to XSS-based session hijacking.',
            'Set http_only to true in config/session.php');
        return false;
    }
    return $httpOnly === true;
});

test("Session uses SameSite protection", function() {
    $sameSite = config('session.same_site');
    return in_array($sameSite, ['lax', 'strict']);
});

test("Session secure flag for production", function() {
    $secure = config('session.secure');
    if ($secure === null && app()->environment('production')) {
        addIssue('MEDIUM', 'Session Secure Flag Not Set for Production',
            'config/session.php:171',
            'SESSION_SECURE_COOKIE is not configured. In production with HTTPS, session cookies should have the Secure flag.',
            'Session cookies can be transmitted over unencrypted connections if misconfigured.',
            'Set SESSION_SECURE_COOKIE=true in production .env file');
        return "Warning: Should be true in production";
    }
    return true;
});

// ═══════════════════════════════════════════════════════════════
// 6. CSRF PROTECTION
// ═══════════════════════════════════════════════════════════════
echo "\n[6] CSRF PROTECTION\n";
echo str_repeat("─", 63) . "\n";

test("VerifyCsrfToken middleware exists", function() {
    return class_exists('App\Http\Middleware\VerifyCsrfToken');
});

test("CSRF middleware is in web group", function() {
    $kernel = file_get_contents(app_path('Http/Kernel.php'));
    return str_contains($kernel, 'VerifyCsrfToken::class');
});

test("Login form has @csrf directive", function() {
    $loginView = file_get_contents(resource_path('views/auth/login.blade.php'));
    return str_contains($loginView, '@csrf');
});

test("No CSRF exceptions for critical routes", function() {
    $csrfMiddleware = file_get_contents(app_path('Http/Middleware/VerifyCsrfToken.php'));
    preg_match('/\$except\s*=\s*\[(.*?)\]/s', $csrfMiddleware, $matches);
    
    if (isset($matches[1])) {
        $except = trim($matches[1]);
        if (!empty($except) && $except !== '//') {
            addIssue('HIGH', 'CSRF Protection Disabled for Routes',
                'VerifyCsrfToken.php:14',
                'Some routes are excluded from CSRF protection.',
                'These routes are vulnerable to Cross-Site Request Forgery attacks.',
                'Remove unnecessary CSRF exceptions. Only API endpoints should be excluded if using token-based auth.');
            return false;
        }
    }
    return true;
});

// ═══════════════════════════════════════════════════════════════
// 7. REMEMBER ME FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════
echo "\n[7] REMEMBER ME\n";
echo str_repeat("─", 63) . "\n";

test("Remember me is implemented", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, "boolean('remember')") || 
           str_contains($authController, "filled('remember')");
});

test("Remember token column exists", function() {
    return DB::getSchemaBuilder()->hasColumn('users', 'remember_token');
});

test("Login form has remember checkbox", function() {
    $loginView = file_get_contents(resource_path('views/auth/login.blade.php'));
    return str_contains($loginView, 'name="remember"');
});

// ═══════════════════════════════════════════════════════════════
// 8. ROLE-BASED ACCESS CONTROL
// ═══════════════════════════════════════════════════════════════
echo "\n[8] ROLE-BASED ACCESS CONTROL\n";
echo str_repeat("─", 63) . "\n";

test("RoleMiddleware exists", function() {
    return class_exists('App\Http\Middleware\RoleMiddleware');
});

test("RoleMiddleware is registered", function() {
    $kernel = file_get_contents(app_path('Http/Kernel.php'));
    return str_contains($kernel, "'role' => ");
});

test("RoleMiddleware checks authentication", function() {
    $middleware = file_get_contents(app_path('Http/Middleware/RoleMiddleware.php'));
    $hasCheck = str_contains($middleware, 'if (! $user)') || str_contains($middleware, 'if (!$user)');
    
    if (!$hasCheck) {
        addIssue('HIGH', 'RoleMiddleware Missing Auth Check',
            'RoleMiddleware.php:21',
            'RoleMiddleware checks if user exists but should rely on auth middleware ordering.',
            'If RoleMiddleware runs before auth middleware, unauthenticated users might not be properly blocked.',
            'Ensure auth middleware runs before role middleware in route definitions.');
    }
    
    return $hasCheck;
});

test("RoleMiddleware validates against allowed roles", function() {
    $middleware = file_get_contents(app_path('Http/Middleware/RoleMiddleware.php'));
    return str_contains($middleware, 'in_array($user->role, $roles)');
});

test("RoleMiddleware returns 403 for unauthorized", function() {
    $middleware = file_get_contents(app_path('Http/Middleware/RoleMiddleware.php'));
    return str_contains($middleware, 'abort(403');
});

test("Admin routes are protected", function() {
    $routes = file_get_contents(base_path('routes/web.php'));
    // Check that admin-only routes have role:admin middleware
    if (str_contains($routes, "middleware('role:admin')")) {
        return true;
    }
    return false;
});

test("Controllers verify ownership for edit/delete", function() {
    $monitoringController = file_get_contents(app_path('Http/Controllers/MonitoringController.php'));
    $financeController = file_get_contents(app_path('Http/Controllers/FinanceReportController.php'));
    
    $monitoringChecks = str_contains($monitoringController, '$monitoring->user_id !== $user->id') &&
                        str_contains($monitoringController, 'abort(403');
    
    $financeChecks = str_contains($financeController, '$report->user_id !== $user->id') &&
                     str_contains($financeController, 'abort(403');
    
    return $monitoringChecks && $financeChecks;
});

// ═══════════════════════════════════════════════════════════════
// 9. PASSWORD RESET
// ═══════════════════════════════════════════════════════════════
echo "\n[9] PASSWORD RESET\n";
echo str_repeat("─", 63) . "\n";

test("Password reset tokens table exists", function() {
    return DB::getSchemaBuilder()->hasTable('password_reset_tokens');
});

test("Password reset routes exist", function() {
    $routes = app('router')->getRoutes();
    $hasResetRoutes = false;
    
    foreach ($routes as $route) {
        $name = $route->getName();
        if ($name && (str_contains($name, 'password.') || str_contains($name, 'reset'))) {
            $hasResetRoutes = true;
            break;
        }
    }
    
    if (!$hasResetRoutes) {
        addIssue('MEDIUM', 'Password Reset Not Implemented',
            'routes/web.php',
            'Password reset functionality is not implemented despite password_reset_tokens table existing.',
            'Users cannot recover their accounts if they forget passwords. Requires admin intervention for password resets.',
            'Implement Laravel password reset: php artisan make:auth or manually create ForgotPasswordController, ResetPasswordController with appropriate routes and views.');
    }
    
    return $hasResetRoutes;
});

// ═══════════════════════════════════════════════════════════════
// 10. INPUT VALIDATION & INJECTION PROTECTION
// ═══════════════════════════════════════════════════════════════
echo "\n[10] INPUT VALIDATION & SQL INJECTION\n";
echo str_repeat("─", 63) . "\n";

test("User creation uses Form Request validation", function() {
    return class_exists('App\Http\Requests\StoreUserRequest');
});

test("Password has minimum length requirement", function() {
    $request = file_get_contents(app_path('Http/Requests/StoreUserRequest.php'));
    return str_contains($request, "'password' => ['required', 'string', 'min:8'");
});

test("Password requires confirmation", function() {
    $request = file_get_contents(app_path('Http/Requests/StoreUserRequest.php'));
    return str_contains($request, "'confirmed'");
});

test("Role is validated against whitelist", function() {
    $request = file_get_contents(app_path('Http/Requests/StoreUserRequest.php'));
    $hasRoleValidation = str_contains($request, "Rule::in(['admin', 'petugas'])");
    
    if (!$hasRoleValidation) {
        addIssue('HIGH', 'Role Not Validated Against Whitelist',
            'StoreUserRequest.php',
            'User role input is not validated against allowed values.',
            'Attackers could potentially inject invalid roles or escalate privileges.',
            'Add Rule::in([\'admin\', \'petugas\']) to role validation rules.');
    }
    
    return $hasRoleValidation;
});

test("Eloquent ORM used (prevents SQL injection)", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    // Check that raw queries are not used
    $hasRawQuery = str_contains($authController, 'DB::raw') || 
                   str_contains($authController, '->raw(') ||
                   preg_match('/DB::select\s*\(.*\$/', $authController);
    
    if ($hasRawQuery) {
        addIssue('CRITICAL', 'Raw SQL Query in AuthController',
            'AuthController.php',
            'Raw SQL queries detected in authentication controller.',
            'Potential SQL injection vulnerability if user input is not properly escaped.',
            'Use Eloquent ORM or Query Builder with parameter binding.');
        return false;
    }
    
    return !$hasRawQuery;
});

test("Login uses parameterized Auth::attempt", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, 'Auth::attempt($credentials');
});

// ═══════════════════════════════════════════════════════════════
// 11. XSS PROTECTION
// ═══════════════════════════════════════════════════════════════
echo "\n[11] XSS PROTECTION\n";
echo str_repeat("─", 63) . "\n";

test("Blade templates used (auto-escaping)", function() {
    $loginView = resource_path('views/auth/login.blade.php');
    return file_exists($loginView);
});

test("User model hides sensitive attributes", function() {
    $userModel = file_get_contents(app_path('Models/User.php'));
    $hasHidden = str_contains($userModel, "protected \$hidden") &&
                 str_contains($userModel, "'password'") &&
                 str_contains($userModel, "'remember_token'");
    
    if (!$hasHidden) {
        addIssue('HIGH', 'Sensitive Attributes Not Hidden',
            'User.php',
            'Password and remember_token are not hidden from serialization.',
            'Sensitive data could be exposed in API responses or logs.',
            'Add protected $hidden = [\'password\', \'remember_token\'] to User model.');
    }
    
    return $hasHidden;
});

// ═══════════════════════════════════════════════════════════════
// 12. LOGOUT SECURITY
// ═══════════════════════════════════════════════════════════════
echo "\n[12] LOGOUT SECURITY\n";
echo str_repeat("─", 63) . "\n";

test("Logout route uses POST method", function() {
    $routes = app('router')->getRoutes();
    foreach ($routes as $route) {
        if ($route->getName() === 'logout') {
            $methods = $route->methods();
            return in_array('POST', $methods);
        }
    }
    return false;
});

test("Logout calls Auth::logout()", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    return str_contains($authController, 'Auth::logout()');
});

// ═══════════════════════════════════════════════════════════════
// 13. PRIVILEGE ESCALATION CHECKS
// ═══════════════════════════════════════════════════════════════
echo "\n[13] PRIVILEGE ESCALATION PREVENTION\n";
echo str_repeat("─", 63) . "\n";

test("Users cannot change their own role", function() {
    $updateRequest = file_get_contents(app_path('Http/Requests/UpdateUserRequest.php'));
    $userController = file_get_contents(app_path('Http/Controllers/Admin/UserController.php'));
    
    // Role should not be in UpdateUserRequest or should be ignored
    $roleNotUpdatable = !str_contains($updateRequest, "'role' =>") ||
                        str_contains($userController, "only(['name', 'email'])");
    
    if (!$roleNotUpdatable) {
        addIssue('CRITICAL', 'Role Can Be Modified During Update',
            'UpdateUserRequest.php / UserController.php:68',
            'User role might be modifiable during profile update.',
            'Users could escalate their privileges from petugas to admin.',
            'Ensure role is not in UpdateUserRequest validation or exclude it from mass assignment in update method.');
    }
    
    return $roleNotUpdatable;
});

test("Admin cannot delete themselves", function() {
    $userController = file_get_contents(app_path('Http/Controllers/Admin/UserController.php'));
    $hasCheck = str_contains($userController, '$pengguna->id === Auth::id()') &&
                str_contains($userController, 'tidak dapat menghapus akun sendiri');
    
    return $hasCheck;
});

test("Cannot delete last admin", function() {
    $userController = file_get_contents(app_path('Http/Controllers/Admin/UserController.php'));
    $hasCheck = str_contains($userController, "where('role', 'admin')->count()") &&
                str_contains($userController, 'admin terakhir');
    
    return $hasCheck;
});

test("User management requires admin role", function() {
    $routes = file_get_contents(base_path('routes/web.php'));
    // Check that pengguna routes have role:admin
    return str_contains($routes, "'role:admin'") && str_contains($routes, "'pengguna'");
});

// ═══════════════════════════════════════════════════════════════
// 14. ADDITIONAL SECURITY CHECKS
// ═══════════════════════════════════════════════════════════════
echo "\n[14] ADDITIONAL SECURITY\n";
echo str_repeat("─", 63) . "\n";

test("Guest middleware redirects authenticated users", function() {
    $middleware = file_get_contents(app_path('Http/Middleware/RedirectIfAuthenticated.php'));
    return str_contains($middleware, 'Auth::guard($guard)->check()') &&
           str_contains($middleware, 'redirect(');
});

test("Auth middleware redirects unauthenticated users", function() {
    $middleware = file_get_contents(app_path('Http/Middleware/Authenticate.php'));
    return str_contains($middleware, "route('login')");
});

test("Email is unique in database", function() {
    $request = file_get_contents(app_path('Http/Requests/StoreUserRequest.php'));
    return str_contains($request, "'unique:users,email'");
});

test("TrimStrings middleware prevents whitespace bypass", function() {
    $kernel = file_get_contents(app_path('Http/Kernel.php'));
    return str_contains($kernel, 'TrimStrings::class');
});

// Check for timing attacks
test("No obvious timing attacks in auth", function() {
    $authController = file_get_contents(app_path('Http/Controllers/AuthController.php'));
    
    // The current implementation has a timing issue with the role check
    // because it queries the DB separately before Auth::attempt
    $hasSeparateQuery = preg_match('/User::where.*->first\(\).*in_array.*role/s', $authController);
    
    if ($hasSeparateQuery) {
        addIssue('MEDIUM', 'Potential Timing Attack in Role Validation',
            'AuthController.php:59-67',
            'Separate database query and role check before Auth::attempt() creates timing differences.',
            'Attackers may be able to enumerate valid emails by measuring response times.',
            'Perform all validation in a single constant-time operation or use early returns that take the same amount of time.');
    }
    
    return !$hasSeparateQuery;
});

// ═══════════════════════════════════════════════════════════════
// SUMMARY REPORT
// ═══════════════════════════════════════════════════════════════
echo "\n\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "   AUDIT SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Total Tests Run: $totalTests\n";
echo "✓ Passed: $passedTests\n";
echo "✗ Failed: $failedTests\n";
echo "\nSecurity Issues Found: " . count($issues) . "\n";
echo "  • CRITICAL: $criticalIssues\n";
echo "  • HIGH: $highIssues\n";
echo "  • MEDIUM: $mediumIssues\n";
echo "  • LOW: $lowIssues\n";

if (count($issues) > 0) {
    echo "\n\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "   DETAILED SECURITY ISSUES\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
    foreach ($issues as $i => $issue) {
        echo "\n";
        echo "## " . ($i + 1) . ". " . $issue['title'] . "\n";
        echo str_repeat("─", 63) . "\n";
        echo "SEVERITY: " . $issue['severity'] . "\n";
        echo "LOCATION: " . $issue['location'] . "\n";
        echo "\nDESCRIPTION:\n" . $issue['description'] . "\n";
        echo "\nIMPACT:\n" . $issue['impact'] . "\n";
        echo "\nRECOMMENDATION:\n" . $issue['fix'] . "\n";
    }
}

echo "\n\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "   AUDIT COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════\n";
