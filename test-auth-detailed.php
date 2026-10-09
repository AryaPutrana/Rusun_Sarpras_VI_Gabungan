<?php

/**
 * Detailed Authentication Flow Diagnostic
 */

$baseUrl = 'http://127.0.0.1:8000';
$cookieJar = sys_get_temp_dir() . '/auth_test_detailed.txt';

function httpRequest($url, $method = 'GET', $data = [], $cookieJar = null, $verbose = false) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects
    
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    if ($verbose) {
        echo "=== REQUEST ===\n";
        echo "$method $url\n";
        if (!empty($data)) {
            echo "Data: " . json_encode($data) . "\n";
        }
        echo "\n=== RESPONSE ===\n";
        echo "Status: $httpCode\n";
        echo "Headers:\n$header\n";
        echo "Body (first 500 chars): " . substr($body, 0, 500) . "\n\n";
    }
    
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'headers' => $header,
        'body' => $body,
    ];
}

function extractCsrfToken($html) {
    if (preg_match('/<input[^>]+name="_token"[^>]+value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "DETAILED AUTHENTICATION FLOW DIAGNOSTIC\n";
echo str_repeat('=', 80) . "\n\n";

// Clean up
if (file_exists($cookieJar)) {
    unlink($cookieJar);
}

// Step 1: Get login page
echo "STEP 1: GET LOGIN PAGE\n";
echo str_repeat('-', 80) . "\n";
$response = httpRequest("$baseUrl/login", 'GET', [], $cookieJar, true);
$csrfToken = extractCsrfToken($response['body']);
echo "CSRF Token: $csrfToken\n\n";

// Step 2: Attempt login
echo "STEP 2: POST LOGIN WITH VALID CREDENTIALS\n";
echo str_repeat('-', 80) . "\n";
$response = httpRequest("$baseUrl/login", 'POST', [
    '_token' => $csrfToken,
    'email' => 'petugas@sarpras.com',
    'password' => 'password123',
], $cookieJar, true);

// Extract redirect location
if (preg_match('/Location:\s*(.+?)[\r\n]/i', $response['headers'], $matches)) {
    $redirectUrl = trim($matches[1]);
    echo "Redirect URL: $redirectUrl\n\n";
    
    // Step 3: Follow redirect
    echo "STEP 3: FOLLOW REDIRECT TO DASHBOARD\n";
    echo str_repeat('-', 80) . "\n";
    $response = httpRequest($redirectUrl, 'GET', [], $cookieJar, true);
}

// Step 4: Check cookie file
echo "STEP 4: COOKIE FILE CONTENTS\n";
echo str_repeat('-', 80) . "\n";
if (file_exists($cookieJar)) {
    echo file_get_contents($cookieJar) . "\n";
} else {
    echo "Cookie file not found\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "DIAGNOSTIC COMPLETE\n";
echo str_repeat('=', 80) . "\n";
