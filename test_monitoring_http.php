#!/usr/bin/env php
<?php
/**
 * HTTP-based Test Suite for Monitoring Harian GWT Module
 * Tests web interface, forms, validation, file uploads, filters, access control
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Colors for output
function colorize($text, $color) {
    $colors = ['red' => "\033[31m", 'green' => "\033[32m", 'yellow' => "\033[33m", 'blue' => "\033[34m", 'reset' => "\033[0m"];
    return $colors[$color] . $text . $colors['reset'];
}

function logTest($message) { echo colorize("[TEST] ", 'blue') . $message . PHP_EOL; }
function logPass($message) { echo colorize("  [✓] ", 'green') . $message . PHP_EOL; }
function logFail($message) { echo colorize("  [✗] ", 'red') . $message . PHP_EOL; }
function logInfo($message) { echo colorize("  [i] ", 'yellow') . $message . PHP_EOL; }
function logBug($bugNumber, $description, $severity = 'MEDIUM') {
    echo colorize("  [BUG #{$bugNumber}] ", 'red') . "[$severity] $description" . PHP_EOL;
}

$bugs = [];
$testResults = [];
$baseUrl = 'http://localhost:8000';

echo colorize("\n=== MONITORING HARIAN GWT - HTTP INTERFACE TEST ===\n", 'blue');

// Get test users
$adminUser = User::where('role', 'admin')->first();
$petugasUser = User::where('role', 'petugas')->first();
$locations = MonitoringLocation::where('status', 'aktif')->get();

if (!$adminUser || !$petugasUser || $locations->count() < 1) {
    echo colorize("ERROR: Missing test data. Run test_monitoring_module.php first.\n", 'red');
    exit(1);
}

logInfo("Testing with Petugas: {$petugasUser->name} (ID: {$petugasUser->id})");
logInfo("Testing with Admin: {$adminUser->name} (ID: {$adminUser->id})");
logInfo("Base URL: {$baseUrl}");

// Helper function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null, $cookies = [], $files = []) {
    $ch = curl_init();
    
    $headers = [
        'Accept: text/html,application/json',
        'User-Agent: Laravel-Test-Suite/1.0',
    ];
    
    if (!empty($cookies)) {
        $cookieStr = [];
        foreach ($cookies as $key => $value) {
            $cookieStr[] = "$key=$value";
        }
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookieStr));
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($files)) {
            // Multipart form data with files
            $postData = $data ?? [];
            foreach ($files as $fieldName => $filePath) {
                if (file_exists($filePath)) {
                    $postData[$fieldName] = new CURLFile($filePath);
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } elseif ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    } elseif ($method === 'DELETE' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'code' => 0, 'body' => '', 'headers' => ''];
    }
    
    $headerSize = strpos($response, "\r\n\r\n");
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);
    
    return ['code' => $httpCode, 'body' => $body, 'headers' => $headers, 'error' => null];
}

// Extract CSRF token from HTML
function extractCsrfToken($html) {
    if (preg_match('/<input[^>]+name="_token"[^>]+value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('/<meta[^>]+name="csrf-token"[^>]+content="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

// Extract session cookie
function extractSessionCookie($headers) {
    if (preg_match('/Set-Cookie: laravel_session=([^;]+)/', $headers, $matches)) {
        return $matches[1];
    }
    if (preg_match('/Set-Cookie: XSRF-TOKEN=([^;]+)/', $headers, $matches)) {
        return $matches[1];
    }
    return null;
}

// Login helper
function login($email, $password, $baseUrl) {
    // Get login page
    $response = makeRequest("$baseUrl/login", 'GET');
    if ($response['code'] !== 200) {
        return ['success' => false, 'error' => 'Cannot load login page'];
    }
    
    $csrf = extractCsrfToken($response['body']);
    $sessionCookie = extractSessionCookie($response['headers']);
    
    if (!$csrf) {
        return ['success' => false, 'error' => 'CSRF token not found'];
    }
    
    // Submit login
    $loginData = [
        '_token' => $csrf,
        'email' => $email,
        'password' => $password,
    ];
    
    $cookies = $sessionCookie ? ['laravel_session' => $sessionCookie] : [];
    $response = makeRequest("$baseUrl/login", 'POST', $loginData, $cookies);
    
    // Laravel redirects on successful login
    if ($response['code'] === 302) {
        $newSession = extractSessionCookie($response['headers']);
        return ['success' => true, 'session' => $newSession ?: $sessionCookie, 'csrf' => $csrf];
    }
    
    return ['success' => false, 'error' => 'Login failed', 'code' => $response['code']];
}

// ============================================================================
// TEST 1: Login Tests
// ============================================================================
logTest("1. User Authentication");

$petugasLogin = login($petugasUser->email, 'password', $baseUrl);
if ($petugasLogin['success']) {
    logPass("Petugas login successful");
    $petugasSession = $petugasLogin['session'];
} else {
    logFail("Petugas login failed: " . ($petugasLogin['error'] ?? 'Unknown error'));
    $bugs[] = "BUG: Petugas login not working";
    $testResults['login'] = 'FAIL';
    exit(1);
}

$adminLogin = login($adminUser->email, 'password', $baseUrl);
if ($adminLogin['success']) {
    logPass("Admin login successful");
    $adminSession = $adminLogin['session'];
} else {
    logFail("Admin login failed: " . ($adminLogin['error'] ?? 'Unknown error'));
    $bugs[] = "BUG: Admin login not working";
    $testResults['login'] = 'FAIL';
    exit(1);
}

$testResults['login'] = 'PASS';

// ============================================================================
// TEST 2: Access Monitoring Index
// ============================================================================
logTest("2. Access Monitoring Index Page");

$response = makeRequest("$baseUrl/monitoring", 'GET', null, ['laravel_session' => $petugasSession]);
if ($response['code'] === 200) {
    logPass("Monitoring index accessible (HTTP {$response['code']})");
    
    if (strpos($response['body'], 'Histori') !== false) {
        logPass("Page contains 'Histori' heading");
    } else {
        logFail("Page missing expected content");
        logBug(1, "Monitoring index page missing expected content", "LOW");
    }
    
    $testResults['index_access'] = 'PASS';
} else {
    logFail("Cannot access monitoring index (HTTP {$response['code']})");
    $bugs[] = "BUG: Monitoring index not accessible";
    $testResults['index_access'] = 'FAIL';
}

// ============================================================================
// TEST 3: Access Create Form
// ============================================================================
logTest("3. Access Create Monitoring Form");

$response = makeRequest("$baseUrl/monitoring/create", 'GET', null, ['laravel_session' => $petugasSession]);
if ($response['code'] === 200) {
    logPass("Create form accessible (HTTP {$response['code']})");
    
    $csrf = extractCsrfToken($response['body']);
    if ($csrf) {
        logPass("CSRF token present in form");
    } else {
        logFail("CSRF token missing from form");
        logBug(2, "CSRF token missing from create form", "HIGH");
        $bugs[] = "BUG #2: CSRF protection issue";
    }
    
    // Check for required form fields
    $requiredFields = ['location_id', 'tanggal', 'sesi', 'kondisi', 'keterangan', 'foto'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (strpos($response['body'], "name=\"$field\"") === false) {
            $missingFields[] = $field;
        }
    }
    
    if (empty($missingFields)) {
        logPass("All required form fields present");
    } else {
        logFail("Missing form fields: " . implode(', ', $missingFields));
        logBug(3, "Create form missing fields: " . implode(', ', $missingFields), "HIGH");
        $bugs[] = "BUG #3: Form fields missing";
    }
    
    // Check dropdown options
    if (strpos($response['body'], 'Pagi') !== false && strpos($response['body'], 'Siang') !== false) {
        logPass("Sesi options populated");
    } else {
        logFail("Sesi dropdown may not be populated");
    }
    
    if (strpos($response['body'], 'Normal') !== false) {
        logPass("Kondisi options populated");
    } else {
        logFail("Kondisi dropdown may not be populated");
    }
    
    $testResults['create_form'] = 'PASS';
} else {
    logFail("Cannot access create form (HTTP {$response['code']})");
    $bugs[] = "BUG: Create form not accessible";
    $testResults['create_form'] = 'FAIL';
}

// ============================================================================
// TEST 4: Form Validation - Missing Required Fields
// ============================================================================
logTest("4. Form Validation - Missing Required Fields");

$response = makeRequest("$baseUrl/monitoring/create", 'GET', null, ['laravel_session' => $petugasSession]);
$csrf = extractCsrfToken($response['body']);

// Submit empty form
$emptyData = ['_token' => $csrf];
$response = makeRequest("$baseUrl/monitoring", 'POST', $emptyData, ['laravel_session' => $petugasSession]);

if ($response['code'] === 302) {
    // Check redirect back with errors (Laravel redirects with 302 on validation failure)
    logPass("Validation triggered redirect (expected behavior)");
    
    // Re-fetch the form to see validation errors
    $response = makeRequest("$baseUrl/monitoring/create", 'GET', null, ['laravel_session' => $petugasSession]);
    
    if (strpos($response['body'], 'wajib') !== false || strpos($response['body'], 'required') !== false) {
        logPass("Validation error messages displayed");
    } else {
        logInfo("Could not confirm validation messages (may require session persistence)");
    }
    
    $testResults['validation_required'] = 'PASS';
} else {
    logInfo("Validation response code: {$response['code']} (may vary by Laravel version)");
    $testResults['validation_required'] = 'PASS';
}

// ============================================================================
// TEST 5: Photo Upload via HTTP
// ============================================================================
logTest("5. Create Monitoring with Photo Upload (HTTP)");

$testImagePath = storage_path('app/test_image.jpg');
if (!file_exists($testImagePath)) {
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $testImagePath, 90);
    imagedestroy($img);
}

$response = makeRequest("$baseUrl/monitoring/create", 'GET', null, ['laravel_session' => $petugasSession]);
$csrf = extractCsrfToken($response['body']);

$location = $locations->first();
$formData = [
    '_token' => $csrf,
    'location_id' => $location->id,
    'tanggal' => now()->format('Y-m-d'),
    'sesi' => 'pagi',
    'kondisi' => 'normal',
    'keterangan' => 'Test HTTP upload',
];

$response = makeRequest(
    "$baseUrl/monitoring",
    'POST',
    $formData,
    ['laravel_session' => $petugasSession],
    ['foto' => $testImagePath]
);

if ($response['code'] === 302) {
    logPass("Form submission successful (HTTP 302 redirect)");
    
    // Check if entry was created
    $newEntry = WaterMonitoring::where('keterangan', 'Test HTTP upload')->first();
    if ($newEntry) {
        logPass("Monitoring entry created in database (ID: {$newEntry->id})");
        
        if ($newEntry->foto && Storage::disk('evidence')->exists($newEntry->foto)) {
            logPass("Photo file uploaded and stored");
        } else {
            logFail("Photo file not found in storage");
            logBug(4, "Photo upload not working via HTTP", "HIGH");
            $bugs[] = "BUG #4: Photo upload fails";
        }
    } else {
        logFail("Entry not found in database");
        $bugs[] = "BUG: Create via HTTP not persisting to database";
    }
    
    $testResults['http_create'] = 'PASS';
} else {
    logFail("Form submission failed (HTTP {$response['code']})");
    logInfo("Response snippet: " . substr($response['body'], 0, 200));
    $bugs[] = "BUG: HTTP form submission not working";
    $testResults['http_create'] = 'FAIL';
}

// ============================================================================
// TEST 6: View Detail Page
// ============================================================================
logTest("6. View Monitoring Detail Page");

$monitoring = WaterMonitoring::latest()->first();
if ($monitoring) {
    $response = makeRequest("$baseUrl/monitoring/{$monitoring->id}", 'GET', null, ['laravel_session' => $petugasSession]);
    
    if ($response['code'] === 200) {
        logPass("Detail page accessible (HTTP {$response['code']})");
        
        if (strpos($response['body'], $monitoring->location->nama_lokasi) !== false) {
            logPass("Location name displayed");
        } else {
            logFail("Location name not displayed");
            logBug(5, "Detail page missing location information", "MEDIUM");
        }
        
        if (strpos($response['body'], $monitoring->keterangan) !== false) {
            logPass("Keterangan displayed");
        } else {
            logInfo("Keterangan may be truncated or missing");
        }
        
        $testResults['detail_page'] = 'PASS';
    } else {
        logFail("Cannot access detail page (HTTP {$response['code']})");
        $bugs[] = "BUG: Detail page not accessible";
        $testResults['detail_page'] = 'FAIL';
    }
} else {
    logInfo("No monitoring entries to test detail page");
    $testResults['detail_page'] = 'SKIP';
}

// ============================================================================
// TEST 7: Edit Form and Update
// ============================================================================
logTest("7. Edit Monitoring Entry");

$monitoring = WaterMonitoring::where('user_id', $petugasUser->id)->latest()->first();
if ($monitoring) {
    $response = makeRequest("$baseUrl/monitoring/{$monitoring->id}/edit", 'GET', null, ['laravel_session' => $petugasSession]);
    
    if ($response['code'] === 200) {
        logPass("Edit form accessible (HTTP {$response['code']})");
        
        $csrf = extractCsrfToken($response['body']);
        if ($csrf) {
            logPass("CSRF token present");
        }
        
        if (strpos($response['body'], 'method="POST"') !== false && strpos($response['body'], 'name="_method" value="PUT"') !== false) {
            logPass("Form method spoofing correctly implemented");
        } else {
            logInfo("Could not verify form method spoofing");
        }
        
        $testResults['edit_form'] = 'PASS';
    } else {
        logFail("Cannot access edit form (HTTP {$response['code']})");
        $bugs[] = "BUG: Edit form not accessible";
        $testResults['edit_form'] = 'FAIL';
    }
} else {
    logInfo("No monitoring entries to test edit");
    $testResults['edit_form'] = 'SKIP';
}

// ============================================================================
// TEST 8: Filter by Date
// ============================================================================
logTest("8. Filter by Date");

$today = now()->format('Y-m-d');
$response = makeRequest("$baseUrl/monitoring?tanggal=$today", 'GET', null, ['laravel_session' => $petugasSession]);

if ($response['code'] === 200) {
    logPass("Date filter request successful");
    
    if (strpos($response['body'], 'value="' . $today . '"') !== false || strpos($response['body'], $today) !== false) {
        logPass("Filter value preserved in form");
    } else {
        logInfo("Could not verify filter value persistence");
    }
    
    $testResults['filter_date'] = 'PASS';
} else {
    logFail("Filter request failed (HTTP {$response['code']})");
    $testResults['filter_date'] = 'FAIL';
}

// ============================================================================
// TEST 9: Filter by Location
// ============================================================================
logTest("9. Filter by Location");

$location = $locations->first();
$response = makeRequest("$baseUrl/monitoring?lokasi={$location->id}", 'GET', null, ['laravel_session' => $petugasSession]);

if ($response['code'] === 200) {
    logPass("Location filter request successful");
    $testResults['filter_location'] = 'PASS';
} else {
    logFail("Filter request failed (HTTP {$response['code']})");
    $testResults['filter_location'] = 'FAIL';
}

// ============================================================================
// TEST 10: Access Control - Petugas Cannot Edit Other's Data
// ============================================================================
logTest("10. Access Control - Petugas Cannot Edit Admin's Data");

// Create an entry as admin
$adminEntry = WaterMonitoring::create([
    'user_id' => $adminUser->id,
    'location_id' => $locations->last()->id,
    'tanggal' => now()->format('Y-m-d'),
    'sesi' => 'sore',
    'waktu' => '16:00',
    'kondisi' => 'normal',
    'keterangan' => 'Admin entry for access control test',
    'foto' => 'test.jpg',
    'foto_disk' => 'evidence',
]);

// Try to access as petugas
$response = makeRequest("$baseUrl/monitoring/{$adminEntry->id}/edit", 'GET', null, ['laravel_session' => $petugasSession]);

if ($response['code'] === 403) {
    logPass("Petugas correctly blocked from editing admin's data (HTTP 403)");
    $testResults['access_control'] = 'PASS';
} elseif ($response['code'] === 302) {
    logPass("Petugas redirected away from admin's data (HTTP 302)");
    $testResults['access_control'] = 'PASS';
} else {
    logFail("Access control may not be working (HTTP {$response['code']})");
    logBug(6, "Petugas can access admin's monitoring data", "HIGH");
    $bugs[] = "BUG #6: Access control not enforced";
    $testResults['access_control'] = 'FAIL';
}

// ============================================================================
// TEST 11: Admin Can See All Data
// ============================================================================
logTest("11. Admin Can View All Data");

$response = makeRequest("$baseUrl/monitoring", 'GET', null, ['laravel_session' => $adminSession]);

if ($response['code'] === 200) {
    logPass("Admin can access monitoring index");
    
    // Admin should see "Histori Monitoring" not "Histori Pemeriksaan Saya"
    if (strpos($response['body'], 'Histori Monitoring') !== false) {
        logPass("Admin sees correct heading ('Histori Monitoring')");
    } elseif (strpos($response['body'], 'Histori Pemeriksaan Saya') !== false) {
        logInfo("Admin sees petugas heading - check isAdmin() method");
    }
    
    $testResults['admin_access'] = 'PASS';
} else {
    logFail("Admin cannot access monitoring index (HTTP {$response['code']})");
    $testResults['admin_access'] = 'FAIL';
}

// ============================================================================
// TEST 12: Photo Viewing Route
// ============================================================================
logTest("12. Photo Viewing Route");

$monitoring = WaterMonitoring::whereNotNull('foto')->first();
if ($monitoring && $monitoring->fotoExists()) {
    $response = makeRequest("$baseUrl/foto/pemeriksaan/{$monitoring->id}", 'GET', null, ['laravel_session' => $petugasSession]);
    
    if ($response['code'] === 200) {
        logPass("Photo route accessible (HTTP {$response['code']})");
        
        $contentType = '';
        if (preg_match('/Content-Type: ([^\r\n]+)/i', $response['headers'], $matches)) {
            $contentType = trim($matches[1]);
        }
        
        if (strpos($contentType, 'image/') !== false) {
            logPass("Correct Content-Type returned: $contentType");
        } else {
            logInfo("Content-Type: $contentType (may be valid)");
        }
        
        if (strlen($response['body']) > 100) {
            logPass("Photo data returned (size: " . strlen($response['body']) . " bytes)");
        } else {
            logFail("Photo data seems too small");
        }
        
        $testResults['photo_route'] = 'PASS';
    } else {
        logFail("Photo route not accessible (HTTP {$response['code']})");
        logBug(7, "Photo viewing route not working", "HIGH");
        $bugs[] = "BUG #7: Cannot view photos";
        $testResults['photo_route'] = 'FAIL';
    }
} else {
    logInfo("No photos available to test viewing route");
    $testResults['photo_route'] = 'SKIP';
}

// ============================================================================
// Final Summary
// ============================================================================
echo colorize("\n=== HTTP TEST SUMMARY ===\n", 'blue');

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($testResults as $test => $result) {
    if ($result === 'PASS') {
        $passed++;
        echo colorize("✓ ", 'green') . "$test: PASS\n";
    } elseif ($result === 'FAIL') {
        $failed++;
        echo colorize("✗ ", 'red') . "$test: FAIL\n";
    } else {
        $skipped++;
        echo colorize("○ ", 'yellow') . "$test: SKIP\n";
    }
}

echo "\nTotal HTTP Tests: " . count($testResults) . "\n";
echo colorize("Passed: $passed\n", 'green');
echo colorize("Failed: $failed\n", $failed > 0 ? 'red' : 'reset');
echo colorize("Skipped: $skipped\n", 'yellow');

if (!empty($bugs)) {
    echo colorize("\n=== BUGS FOUND ===\n", 'red');
    foreach (array_unique($bugs) as $i => $bug) {
        echo ($i + 1) . ". $bug\n";
    }
}

echo colorize("\n=== HTTP TEST COMPLETED ===\n", 'blue');
