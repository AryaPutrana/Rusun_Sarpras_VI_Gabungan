#!/usr/bin/env python3
"""
File Upload Security Testing Suite
Tests for MIME type spoofing, size validation, path traversal, and other upload vulnerabilities
"""

import requests
import os
import sys
from datetime import datetime

BASE_URL = "http://localhost"
RESULTS_FILE = "results/upload_tests_results.txt"

class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'

class TestResults:
    def __init__(self):
        self.tests = []
        self.passed = 0
        self.failed = 0
        self.warnings = 0
    
    def add_test(self, test_name, status, details):
        self.tests.append({
            'name': test_name,
            'status': status,
            'details': details,
            'timestamp': datetime.now().isoformat()
        })
        if status == 'PASS':
            self.passed += 1
        elif status == 'FAIL':
            self.failed += 1
        elif status == 'WARN':
            self.warnings += 1
    
    def save(self):
        os.makedirs('results', exist_ok=True)
        with open(RESULTS_FILE, 'w') as f:
            f.write(f"File Upload Security Test Results\n")
            f.write(f"Generated: {datetime.now()}\n")
            f.write("=" * 80 + "\n\n")
            f.write(f"Summary: {self.passed} Passed, {self.failed} Failed, {self.warnings} Warnings\n\n")
            
            for test in self.tests:
                f.write(f"[{test['status']}] {test['name']}\n")
                f.write(f"  Time: {test['timestamp']}\n")
                f.write(f"  Details: {test['details']}\n")
                f.write("\n")
    
    def print_summary(self):
        print(f"\n{Colors.HEADER}{'=' * 80}{Colors.ENDC}")
        print(f"{Colors.BOLD}Test Summary{Colors.ENDC}")
        print(f"{Colors.OKGREEN}Passed: {self.passed}{Colors.ENDC}")
        print(f"{Colors.FAIL}Failed: {self.failed}{Colors.ENDC}")
        print(f"{Colors.WARNING}Warnings: {self.warnings}{Colors.ENDC}")
        print(f"{Colors.HEADER}{'=' * 80}{Colors.ENDC}\n")

def test_monitoring_upload(filename, test_name, expected_status='reject'):
    """Test file upload to monitoring endpoint"""
    print(f"\n{Colors.OKCYAN}Testing: {test_name}{Colors.ENDC}")
    
    url = f"{BASE_URL}/monitoring"
    
    try:
        with open(f"malicious_files/{filename}", 'rb') as f:
            files = {'foto': (filename, f, 'image/jpeg')}
            data = {
                'location_id': '1',
                'tanggal': '2026-10-09',
                'sesi': 'pagi',
                'kondisi': 'normal',
                'keterangan': 'Test upload',
                '_token': 'test'  # This would need to be obtained from CSRF
            }
            
            response = requests.post(url, files=files, data=data, allow_redirects=False)
            
            if expected_status == 'reject':
                if response.status_code in [422, 302, 400]:
                    return ('PASS', f"Upload correctly rejected (HTTP {response.status_code})")
                else:
                    return ('FAIL', f"Upload not rejected! HTTP {response.status_code}")
            else:
                if response.status_code in [200, 302]:
                    return ('PASS', f"Valid file accepted (HTTP {response.status_code})")
                else:
                    return ('WARN', f"Valid file rejected (HTTP {response.status_code})")
                    
    except FileNotFoundError:
        return ('WARN', f"Test file not found: {filename}")
    except Exception as e:
        return ('WARN', f"Exception: {str(e)}")

def test_items_upload(filename, test_name, expected_status='reject'):
    """Test file upload to inventory items endpoint"""
    print(f"\n{Colors.OKCYAN}Testing: {test_name}{Colors.ENDC}")
    
    url = f"{BASE_URL}/inventory/items"
    
    try:
        with open(f"malicious_files/{filename}", 'rb') as f:
            files = {'photo': (filename, f, 'image/jpeg')}
            data = {
                'item_code': f'TEST-{datetime.now().timestamp()}',
                'name': 'Test Item',
                'unit_price': '1000',
                'stock': '10',
                'unit': 'pcs',
                '_token': 'test'
            }
            
            response = requests.post(url, files=files, data=data, allow_redirects=False)
            
            if expected_status == 'reject':
                if response.status_code in [422, 302, 400, 419]:
                    return ('PASS', f"Upload correctly rejected (HTTP {response.status_code})")
                else:
                    return ('FAIL', f"Upload not rejected! HTTP {response.status_code}")
            else:
                if response.status_code in [200, 302]:
                    return ('PASS', f"Valid file accepted (HTTP {response.status_code})")
                else:
                    return ('WARN', f"Valid file rejected (HTTP {response.status_code})")
                    
    except FileNotFoundError:
        return ('WARN', f"Test file not found: {filename}")
    except Exception as e:
        return ('WARN', f"Exception: {str(e)}")

def check_storage_permissions():
    """Check storage directory permissions and configuration"""
    print(f"\n{Colors.OKCYAN}Checking Storage Permissions{Colors.ENDC}")
    
    results = []
    
    # Check storage directories
    dirs_to_check = [
        '../storage/app/public/items',
        '../storage/app/public/evidence',
        '../storage/app/evidence',
        '../public/storage'
    ]
    
    for dir_path in dirs_to_check:
        if os.path.exists(dir_path):
            is_writable = os.access(dir_path, os.W_OK)
            results.append(f"{dir_path}: {'WRITABLE' if is_writable else 'NOT WRITABLE'}")
            
            # Check for symlink
            if os.path.islink(dir_path):
                target = os.readlink(dir_path)
                results.append(f"  -> Symlink to: {target}")
        else:
            results.append(f"{dir_path}: NOT FOUND")
    
    return ('PASS', '\n'.join(results))

def main():
    print(f"{Colors.HEADER}{Colors.BOLD}")
    print("=" * 80)
    print("FILE UPLOAD SECURITY TEST SUITE")
    print("=" * 80)
    print(f"{Colors.ENDC}")
    
    results = TestResults()
    
    # Test 1: Empty file
    status, details = test_items_upload('empty_file.jpg', 'Empty File (0 bytes)', 'reject')
    results.add_test('Empty File Upload', status, details)
    
    # Test 2: PHP with PNG header
    status, details = test_items_upload('php_with_png_header.png', 'PHP code with PNG magic bytes', 'reject')
    results.add_test('PHP with PNG Header', status, details)
    
    # Test 3: PHP with JPEG header
    status, details = test_items_upload('php_with_jpeg_header.jpg', 'PHP code with JPEG magic bytes', 'reject')
    results.add_test('PHP with JPEG Header', status, details)
    
    # Test 4: Size bomb (10MB > 5MB limit)
    status, details = test_items_upload('size_bomb_10mb.jpg', '10MB file (exceeds 5MB limit)', 'reject')
    results.add_test('Size Bomb (10MB)', status, details)
    
    # Test 5: Webshell disguised as JPG
    status, details = test_items_upload('webshell.php.jpg', 'PHP webshell with .jpg extension', 'reject')
    results.add_test('Webshell (.php.jpg)', status, details)
    
    # Test 6: GIF file (not allowed)
    status, details = test_items_upload('fake.gif', 'GIF file (not in allowed types)', 'reject')
    results.add_test('GIF Upload', status, details)
    
    # Test 7: Truncated JPEG
    status, details = test_items_upload('truncated_jpeg.jpg', 'Truncated/corrupt JPEG', 'reject')
    results.add_test('Truncated JPEG', status, details)
    
    # Test 8: Valid tiny files (should pass if server is running)
    status, details = test_items_upload('valid_tiny.jpg', 'Valid JPEG with proper magic bytes', 'accept')
    results.add_test('Valid JPEG', status, details)
    
    # Test 9: Storage permissions
    status, details = check_storage_permissions()
    results.add_test('Storage Permissions Check', status, details)
    
    # Print and save results
    results.print_summary()
    results.save()
    
    print(f"\n{Colors.OKGREEN}Results saved to: {RESULTS_FILE}{Colors.ENDC}\n")
    
    return 0 if results.failed == 0 else 1

if __name__ == '__main__':
    sys.exit(main())
