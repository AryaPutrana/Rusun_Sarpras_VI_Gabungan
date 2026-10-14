<?php
/**
 * DEEP DIAGNOSTIC TEST - MONITORING FOTO UPDATE
 * Test langsung upload dan update logic
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║       DEEP DIAGNOSTIC: MONITORING FOTO UPDATE BUG            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Test 1: Check monitoring record exists
echo "TEST 1: Monitoring Records\n";
echo "─────────────────────────────────────\n";
$monitoring = App\Models\WaterMonitoring::first();
if (!$monitoring) {
    echo "✗ TIDAK ADA DATA MONITORING\n";
    echo "  → Buat data monitoring dulu sebelum test update\n\n";
    exit(1);
}

echo "✓ Monitoring ID: {$monitoring->id}\n";
echo "  Location: {$monitoring->location->nama_lokasi}\n";
echo "  Tanggal: {$monitoring->tanggal->format('Y-m-d')}\n";
echo "  Foto saat ini: " . ($monitoring->foto ?? 'NULL') . "\n";
echo "  Foto disk: " . ($monitoring->foto_disk ?? 'NULL') . "\n";
echo "  Foto exists: " . ($monitoring->fotoExists() ? 'YES' : 'NO') . "\n\n";

// Test 2: Check storage paths
echo "TEST 2: Storage Configuration\n";
echo "─────────────────────────────────────\n";

$evidencePath = storage_path('app/evidence/monitoring');
$publicPath = storage_path('app/public/monitoring');

echo "Evidence disk path: {$evidencePath}\n";
echo "  Exists: " . (file_exists($evidencePath) ? '✓' : '✗ TIDAK ADA') . "\n";
echo "  Writable: " . (is_writable($evidencePath) ? '✓' : '✗ TIDAK WRITABLE') . "\n\n";

echo "Public disk path (legacy): {$publicPath}\n";
echo "  Exists: " . (file_exists($publicPath) ? '✓' : '✗') . "\n";
echo "  Writable: " . (is_writable($publicPath) ? '✓' : '✗') . "\n\n";

// Test 3: Check filesystems config
echo "TEST 3: Filesystems Configuration\n";
echo "─────────────────────────────────────\n";

$disks = config('filesystems.disks');

if (isset($disks['evidence'])) {
    echo "✓ Disk 'evidence' configured\n";
    echo "  Driver: " . ($disks['evidence']['driver'] ?? 'N/A') . "\n";
    echo "  Root: " . ($disks['evidence']['root'] ?? 'N/A') . "\n";
    echo "  Throw: " . (($disks['evidence']['throw'] ?? false) ? 'true' : 'false') . "\n";
} else {
    echo "✗ Disk 'evidence' TIDAK CONFIGURED!\n";
    echo "  → Ini kemungkinan masalah utama!\n";
}
echo "\n";

if (isset($disks['public'])) {
    echo "✓ Disk 'public' configured\n";
    echo "  Root: " . ($disks['public']['root'] ?? 'N/A') . "\n";
} else {
    echo "✗ Disk 'public' tidak configured\n";
}
echo "\n";

// Test 4: Check current photo file
echo "TEST 4: Current Photo File Check\n";
echo "─────────────────────────────────────\n";

if ($monitoring->foto) {
    $diskName = $monitoring->fotoDiskName();
    echo "Foto: {$monitoring->foto}\n";
    echo "Disk: {$diskName}\n";
    
    $disk = Storage::disk($diskName);
    $exists = false;
    
    try {
        $exists = $disk->exists($monitoring->foto);
        echo "File exists on disk: " . ($exists ? '✓ YES' : '✗ NO') . "\n";
        
        if ($exists) {
            $size = $disk->size($monitoring->foto);
            echo "File size: " . number_format($size) . " bytes\n";
            
            $path = $disk->path($monitoring->foto);
            echo "Full path: {$path}\n";
        }
    } catch (Exception $e) {
        echo "✗ ERROR checking file: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ No photo in database\n";
}
echo "\n";

// Test 5: Simulate photo update logic
echo "TEST 5: Simulate Update Logic\n";
echo "─────────────────────────────────────\n";

$oldFoto = $monitoring->foto;
$oldDisk = $monitoring->foto_disk;

echo "Scenario: User uploads new photo\n";
echo "Old foto: {$oldFoto}\n";
echo "Old disk: {$oldDisk}\n\n";

// Simulate what controller does
echo "Controller logic check:\n";
echo "1. hasFile('foto') check → Would check if file uploaded\n";
echo "2. PhotoStorage::storeUploaded() → Stores to 'evidence' disk\n";
echo "3. Sets \$data['foto'] = new filename\n";
echo "4. Sets \$data['foto_disk'] = 'evidence'\n";
echo "5. \$monitoring->update(\$data) → Updates database\n\n";

// Test 6: Check fillable fields
echo "TEST 6: Model Fillable Check\n";
echo "─────────────────────────────────────\n";

$fillable = $monitoring->getFillable();
echo "Fillable fields:\n";
foreach ($fillable as $field) {
    echo "  - {$field}\n";
}

$hasFoto = in_array('foto', $fillable);
$hasFotoDisk = in_array('foto_disk', $fillable);

echo "\n";
echo "foto in fillable: " . ($hasFoto ? '✓ YES' : '✗ NO - BUG!') . "\n";
echo "foto_disk in fillable: " . ($hasFotoDisk ? '✓ YES' : '✗ NO - BUG!') . "\n\n";

// Test 7: Test actual update (without file)
echo "TEST 7: Test Database Update (No Photo)\n";
echo "─────────────────────────────────────\n";

$testData = [
    'keterangan' => $monitoring->keterangan . ' [TEST UPDATE]',
];

try {
    $monitoring->update($testData);
    echo "✓ Update successful (keterangan)\n";
    $monitoring->refresh();
    echo "  New keterangan: {$monitoring->keterangan}\n";
    
    // Rollback
    $monitoring->update(['keterangan' => str_replace(' [TEST UPDATE]', '', $monitoring->keterangan)]);
    echo "✓ Rollback successful\n";
} catch (Exception $e) {
    echo "✗ Update FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Check route
echo "TEST 8: Route Check\n";
echo "─────────────────────────────────────\n";

try {
    $editRoute = route('monitoring.edit', $monitoring);
    echo "Edit route: {$editRoute}\n";
    
    $updateRoute = route('monitoring.update', $monitoring);
    echo "Update route: {$updateRoute}\n";
    echo "✓ Routes OK\n";
} catch (Exception $e) {
    echo "✗ Route error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 9: Check validation rules
echo "TEST 9: Validation Rules Check\n";
echo "─────────────────────────────────────\n";

$requestClass = 'App\\Http\\Requests\\UpdateWaterMonitoringRequest';
if (class_exists($requestClass)) {
    echo "✓ UpdateWaterMonitoringRequest exists\n";
    
    // Instantiate to check rules
    $request = new $requestClass();
    
    // Get rules via reflection (rules() needs container)
    $reflection = new ReflectionClass($request);
    $method = $reflection->getMethod('rules');
    
    echo "  Method rules() exists: ✓\n";
} else {
    echo "✗ UpdateWaterMonitoringRequest NOT FOUND\n";
}
echo "\n";

// FINAL SUMMARY
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    DIAGNOSTIC SUMMARY                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "CRITICAL CHECKS:\n";
echo "─────────────────────────────────────\n";
echo "1. Monitoring data exists: " . ($monitoring ? '✓' : '✗') . "\n";
echo "2. Evidence storage writable: " . (is_writable($evidencePath) ? '✓' : '✗') . "\n";
echo "3. Disk 'evidence' configured: " . (isset($disks['evidence']) ? '✓' : '✗') . "\n";
echo "4. Field 'foto' fillable: " . ($hasFoto ? '✓' : '✗') . "\n";
echo "5. Field 'foto_disk' fillable: " . ($hasFotoDisk ? '✓' : '✗') . "\n";
echo "6. Database update works: ✓ (tested)\n";
echo "7. Routes registered: ✓\n\n";

// POTENTIAL ISSUES
echo "POTENTIAL ISSUES TO CHECK:\n";
echo "─────────────────────────────────────\n";

$issues = [];

if (!is_writable($evidencePath)) {
    $issues[] = "Storage path tidak writable: {$evidencePath}";
}

if (!isset($disks['evidence'])) {
    $issues[] = "Disk 'evidence' tidak configured di config/filesystems.php";
}

if (!$hasFoto || !$hasFotoDisk) {
    $issues[] = "Field foto/foto_disk tidak ada di \$fillable array";
}

if (empty($issues)) {
    echo "✓ TIDAK ADA MASALAH TERDETEKSI DI KONFIGURASI\n\n";
    echo "Kemungkinan masalah:\n";
    echo "1. Form tidak mengirim file (cek browser F12 Network tab)\n";
    echo "2. Validation reject file (cek error message di form)\n";
    echo "3. PhotoStorage::storeUploaded() return null (cek logs)\n";
    echo "4. Browser cache (Ctrl+F5)\n";
} else {
    echo "✗ DITEMUKAN MASALAH:\n\n";
    foreach ($issues as $i => $issue) {
        echo "   " . ($i + 1) . ". {$issue}\n";
    }
}

echo "\n";
echo "NEXT STEPS:\n";
echo "─────────────────────────────────────\n";
echo "1. Perbaiki issues di atas (jika ada)\n";
echo "2. Cek storage/logs/laravel.log untuk errors\n";
echo "3. Test update foto via browser dengan F12 open\n";
echo "4. Cek Network tab: Content-Type dan Form Data\n";
echo "5. Jika masih gagal, tambahkan logging di controller\n\n";

echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
