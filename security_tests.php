<?php
/**
 * Comprehensive Security Testing Suite
 * Tests: Authentication, Authorization, Input Validation, XSS, CSRF, File Upload, IDOR
 */

class SecurityTester
{
    private $baseUrl = 'http://127.0.0.1:8000';
    private $results = [];
    private $cookies = [];
    private $csrfToken = null;

    public function run()
    {
        echo "=== SECURITY TESTING SUITE ===\n\n";
        
        // 1. Authentication Tests
        $this->testAuthenticationSQLInjection();
        $this->testAuthenticationXSS();
        $this->testRateLimiting();
        $this->testSessionFixation();
        
        // 2. Authorization Tests (need valid credentials)
        $this->testUnauthorizedAccess();
        $this->testRoleEscalation();
        $this->testIDOR();
        
        // 3. Input Validation Tests
        $this->testSQLInjectionInSearch();
        $this->testXSSInInputs();
        $this->testNegativeNumbers();
        $this->testOverflowAttacks();
        $this->testPathTraversal();
        
        // 4. CSRF Tests
        $this->testCSRFProtection();
        
        // 5. File Upload Tests
        $this->testMaliciousFileUpload();
        
        // Print Results
        $this->printResults();
    }

    // ==================== AUTHENTICATION TESTS ====================
    
    private function testAuthenticationSQLInjection()
    {
        echo "[TEST] SQL Injection in Login...\n";
        
        $payloads = [
            "admin' OR '1'='1",
            "admin'--",
            "admin' OR 1=1--",
            "' OR '1'='1' --",
            "admin'; DROP TABLE users--",
            "' UNION SELECT NULL, NULL, NULL--",
        ];
        
        foreach ($payloads as $payload) {
            $response = $this->post('/login', [
                'email' => $payload,
                'password' => 'password'
            ]);
            
            // Should NOT allow login with SQL injection
            if (strpos($response['body'], 'dashboard') !== false || 
                strpos($response['body'], 'Dashboard') !== false) {
                $this->addResult('CRITICAL', 'SQL Injection', 
                    "Login vulnerable to SQL injection with payload: $payload");
                return;
            }
        }
        
        $this->addResult('PASS', 'SQL Injection', 
            'Login form protected against SQL injection');
    }
    
    private function testAuthenticationXSS()
    {
        echo "[TEST] XSS in Login...\n";
        
        $payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<svg/onload=alert("XSS")>',
        ];
        
        foreach ($payloads as $payload) {
            $response = $this->post('/login', [
                'email' => $payload,
                'password' => 'password'
            ]);
            
            // Check if payload is reflected unescaped
            if (strpos($response['body'], $payload) !== false && 
                strpos($response['body'], htmlspecialchars($payload)) === false) {
                $this->addResult('HIGH', 'XSS', 
                    "Login form vulnerable to XSS with payload: $payload");
                return;
            }
        }
        
        $this->addResult('PASS', 'XSS', 
            'Login form properly escapes XSS payloads');
    }
    
    private function testRateLimiting()
    {
        echo "[TEST] Rate Limiting on Login...\n";
        
        $attempts = 0;
        for ($i = 0; $i < 7; $i++) {
            $response = $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
            
            $attempts++;
            
            if (strpos($response['body'], 'Terlalu banyak') !== false || 
                $response['status'] == 429) {
                $this->addResult('PASS', 'Rate Limiting', 
                    "Rate limiting activated after $attempts failed attempts");
                return;
            }
        }
        
        $this->addResult('WARN', 'Rate Limiting', 
            'No rate limiting detected after 7 failed attempts');
    }
    
    private function testSessionFixation()
    {
        echo "[TEST] Session Fixation...\n";
        
        // Get initial session
        $response1 = $this->get('/login');
        $session1 = $this->extractSessionCookie($response1);
        
        // Attempt login with fixed session
        $response2 = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password'
        ], ['Cookie' => $session1]);
        
        $session2 = $this->extractSessionCookie($response2);
        
        if ($session1 && $session2 && $session1 === $session2) {
            $this->addResult('HIGH', 'Session Fixation', 
                'Session ID not regenerated after login');
        } else {
            $this->addResult('PASS', 'Session Fixation', 
                'Session regenerated properly after login');
        }
    }
    
    // ==================== AUTHORIZATION TESTS ====================
    
    private function testUnauthorizedAccess()
    {
        echo "[TEST] Unauthorized Access to Protected Routes...\n";
        
        $protectedRoutes = [
            '/dashboard',
            '/monitoring',
            '/keuangan',
            '/lokasi',
            '/pengguna',
            '/inventory/items',
        ];
        
        $vulnerableRoutes = [];
        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            
            // Should redirect to login
            if ($response['status'] != 302 && 
                strpos($response['body'], 'login') === false &&
                strpos($response['body'], 'Login') === false) {
                $vulnerableRoutes[] = $route;
            }
        }
        
        if (count($vulnerableRoutes) > 0) {
            $this->addResult('CRITICAL', 'Authorization', 
                'Unauthenticated access allowed to: ' . implode(', ', $vulnerableRoutes));
        } else {
            $this->addResult('PASS', 'Authorization', 
                'Protected routes require authentication');
        }
    }
    
    private function testRoleEscalation()
    {
        echo "[TEST] Role Escalation (Petugas accessing Admin routes)...\n";
        
        // This would require actual valid credentials
        $this->addResult('MANUAL', 'Role Escalation', 
            'Requires valid petugas credentials - manual test needed');
    }
    
    private function testIDOR()
    {
        echo "[TEST] Insecure Direct Object Reference (IDOR)...\n";
        
        // Test accessing other users' data by manipulating IDs
        $this->addResult('MANUAL', 'IDOR', 
            'Requires authenticated session - manual test needed');
    }
    
    // ==================== INPUT VALIDATION TESTS ====================
    
    private function testSQLInjectionInSearch()
    {
        echo "[TEST] SQL Injection in Search Fields...\n";
        
        $searchPayloads = [
            "' OR '1'='1",
            "1' UNION SELECT NULL--",
            "'; DROP TABLE items--",
        ];
        
        foreach ($searchPayloads as $payload) {
            $response = $this->get('/monitoring?search=' . urlencode($payload));
            
            // Check for SQL errors
            if (preg_match('/SQL|syntax|mysql|query/i', $response['body'])) {
                $this->addResult('HIGH', 'SQL Injection', 
                    "Search vulnerable to SQL injection: $payload");
                return;
            }
        }
        
        $this->addResult('PASS', 'SQL Injection', 
            'Search fields protected against SQL injection');
    }
    
    private function testXSSInInputs()
    {
        echo "[TEST] XSS in Input Fields...\n";
        
        // Test reflected XSS in search parameters
        $xssPayloads = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '"><script>alert(1)</script>',
        ];
        
        foreach ($xssPayloads as $payload) {
            $response = $this->get('/monitoring?search=' . urlencode($payload));
            
            // Check if unescaped
            if (strpos($response['body'], $payload) !== false) {
                $this->addResult('HIGH', 'XSS', 
                    "Reflected XSS in search: $payload");
                return;
            }
        }
        
        $this->addResult('PASS', 'XSS', 
            'Input fields properly escaped');
    }
    
    private function testNegativeNumbers()
    {
        echo "[TEST] Negative Numbers in Quantity...\n";
        
        // Would need authenticated session to test POST
        $this->addResult('MANUAL', 'Input Validation', 
            'Negative number validation requires authenticated POST - manual test needed');
    }
    
    private function testOverflowAttacks()
    {
        echo "[TEST] Numeric Overflow Attacks...\n";
        
        $this->addResult('MANUAL', 'Overflow', 
            'Overflow testing requires authenticated POST - manual test needed');
    }
    
    private function testPathTraversal()
    {
        echo "[TEST] Path Traversal...\n";
        
        $traversalPayloads = [
            '../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//etc/passwd',
        ];
        
        foreach ($traversalPayloads as $payload) {
            // Test in various parameters
            $response = $this->get('/foto/monitoring/' . urlencode($payload));
            
            if (preg_match('/root:|administrator:|passwd/i', $response['body'])) {
                $this->addResult('CRITICAL', 'Path Traversal', 
                    "File system exposed via: $payload");
                return;
            }
        }
        
        $this->addResult('PASS', 'Path Traversal', 
            'No path traversal vulnerabilities detected');
    }
    
    // ==================== CSRF TESTS ====================
    
    private function testCSRFProtection()
    {
        echo "[TEST] CSRF Protection...\n";
        
        // Try POST without CSRF token
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ], ['X-CSRF-TOKEN' => '']);
        
        if ($response['status'] != 419 && 
            strpos($response['body'], 'CSRF') === false &&
            strpos($response['body'], 'token') === false) {
            $this->addResult('HIGH', 'CSRF', 
                'POST request allowed without CSRF token');
        } else {
            $this->addResult('PASS', 'CSRF', 
                'CSRF protection active on POST requests');
        }
    }
    
    // ==================== FILE UPLOAD TESTS ====================
    
    private function testMaliciousFileUpload()
    {
        echo "[TEST] Malicious File Upload...\n";
        
        // Would need authenticated session and proper multipart form
        $this->addResult('MANUAL', 'File Upload', 
            'File upload testing requires authenticated session - manual test needed');
    }
    
    // ==================== HELPER METHODS ====================
    
    private function get($path, $headers = [])
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headers));
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        return [
            'status' => $status,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize)
        ];
    }
    
    private function post($path, $data, $headers = [])
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($headers));
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        return [
            'status' => $status,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize)
        ];
    }
    
    private function buildHeaders($headers)
    {
        $result = ['Accept: text/html'];
        foreach ($headers as $key => $value) {
            $result[] = "$key: $value";
        }
        return $result;
    }
    
    private function extractSessionCookie($response)
    {
        if (preg_match('/Set-Cookie: ([^;]+)/i', $response['headers'], $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function addResult($severity, $category, $message)
    {
        $this->results[] = [
            'severity' => $severity,
            'category' => $category,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function printResults()
    {
        echo "\n\n=== SECURITY TEST RESULTS ===\n\n";
        
        $counts = ['CRITICAL' => 0, 'HIGH' => 0, 'WARN' => 0, 'PASS' => 0, 'MANUAL' => 0];
        
        foreach ($this->results as $result) {
            $counts[$result['severity']]++;
            
            $color = [
                'CRITICAL' => "\033[1;31m",
                'HIGH' => "\033[0;31m",
                'WARN' => "\033[0;33m",
                'PASS' => "\033[0;32m",
                'MANUAL' => "\033[0;36m",
            ][$result['severity']];
            
            echo sprintf(
                "%s[%s] %s: %s\033[0m\n",
                $color,
                $result['severity'],
                $result['category'],
                $result['message']
            );
        }
        
        echo "\n=== SUMMARY ===\n";
        echo "CRITICAL: {$counts['CRITICAL']}\n";
        echo "HIGH: {$counts['HIGH']}\n";
        echo "WARN: {$counts['WARN']}\n";
        echo "PASS: {$counts['PASS']}\n";
        echo "MANUAL: {$counts['MANUAL']}\n";
        
        // Save to file
        $this->saveReport();
    }
    
    private function saveReport()
    {
        $report = "# Security Testing Report\n\n";
        $report .= "**Date:** " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "## Results\n\n";
        
        foreach ($this->results as $result) {
            $report .= sprintf(
                "- **[%s]** %s: %s\n",
                $result['severity'],
                $result['category'],
                $result['message']
            );
        }
        
        file_put_contents(__DIR__ . '/security_test_results.txt', $report);
        echo "\nReport saved to security_test_results.txt\n";
    }
}

// Run tests
$tester = new SecurityTester();
$tester->run();
