<?php

namespace App\Console\Commands;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestFinanceReport extends Command
{
    protected $signature = 'test:finance-report';
    protected $description = 'Test Finance Report functionality';

    public function handle()
    {
        $this->info('=== FINANCE REPORT TEST SUITE ===');
        $this->newLine();

        // Test 1: Check users
        $this->info('Test 1: Check Users');
        $admin = User::where('email', 'admin@example.com')->first();
        $petugas = User::where('email', 'petugas@example.com')->first();
        
        if ($admin && $petugas) {
            $this->line("  ✓ Admin: {$admin->name} (ID: {$admin->id}, Role: {$admin->role})");
            $this->line("  ✓ Petugas: {$petugas->name} (ID: {$petugas->id}, Role: {$petugas->role})");
        } else {
            $this->error('  ✗ Users not found');
            return 1;
        }
        $this->newLine();

        // Test 2: Check locations
        $this->info('Test 2: Check Finance Locations');
        $locations = FinanceLocation::where('status', 'aktif')->get();
        $this->line("  ✓ Active locations: {$locations->count()}");
        foreach ($locations as $loc) {
            $this->line("    - {$loc->nama_lokasi}");
        }
        $this->newLine();

        // Test 3: Check existing reports
        $this->info('Test 3: Check Existing Reports');
        $totalReports = FinanceReport::count();
        $this->line("  ✓ Total reports: {$totalReports}");
        
        if ($totalReports > 0) {
            $recent = FinanceReport::with('user')->latest()->take(3)->get();
            $this->line("  Recent reports:");
            foreach ($recent as $r) {
                $this->line("    - ID:{$r->id} User:{$r->user->name} Date:{$r->tanggal->format('Y-m-d')} Kondisi:{$r->kondisi}");
            }
        }
        $this->newLine();

        // Test 4: Create test data
        $this->info('Test 4: Create Test Data');
        
        if ($locations->isEmpty()) {
            $this->warn('  ! No locations found, creating test location...');
            $location = FinanceLocation::create([
                'nama_lokasi' => 'Gedung Utama - Test',
                'status' => 'aktif'
            ]);
            $this->line("  ✓ Created location: {$location->nama_lokasi}");
        } else {
            $location = $locations->first();
            $this->line("  ✓ Using location: {$location->nama_lokasi}");
        }

        // Create test photo
        $disk = Storage::disk('evidence');
        $testPhotoPath = 'finance/test_' . time() . '.jpg';
        
        $img = imagecreatetruecolor(800, 600);
        $blue = imagecolorallocate($img, 50, 100, 200);
        imagefill($img, 0, 0, $blue);
        imagestring($img, 5, 250, 280, 'TEST PHOTO', imagecolorallocate($img, 255, 255, 255));
        
        ob_start();
        imagejpeg($img, null, 85);
        $imageData = ob_get_clean();
        imagedestroy($img);
        
        $disk->put($testPhotoPath, $imageData);
        $this->line("  ✓ Created test photo: {$testPhotoPath}");
        $this->newLine();

        // Test 5: Create finance reports
        $this->info('Test 5: Create Finance Reports');
        
        $testReports = [
            ['kondisi' => 'normal', 'days_ago' => 5],
            ['kondisi' => 'debit_air_kurang', 'days_ago' => 3],
            ['kondisi' => 'tekanan_pdam_kurang', 'days_ago' => 1],
        ];

        $created = [];
        foreach ($testReports as $data) {
            $report = FinanceReport::create([
                'user_id' => $petugas->id,
                'location_id' => $location->id,
                'tanggal' => now()->subDays($data['days_ago']),
                'kondisi' => $data['kondisi'],
                'keterangan' => 'Test: ' . FinanceReport::KONDISI[$data['kondisi']],
                'foto' => $testPhotoPath,
                'foto_disk' => 'evidence',
            ]);
            $created[] = $report;
            $this->line("  ✓ ID {$report->id} - {$report->kondisi} ({$report->tanggal->format('Y-m-d')})");
        }
        $this->newLine();

        // Test 6: Test relationships
        $this->info('Test 6: Model Relationships');
        $report = $created[0];
        $this->line("  ✓ User: {$report->user->name}");
        $this->line("  ✓ Location: {$report->location->nama_lokasi}");
        $this->line("  ✓ Photo exists: " . ($report->fotoExists() ? 'Yes' : 'No'));
        $this->newLine();

        // Test 7: Month filtering
        $this->info('Test 7: Month Filtering');
        $currentMonth = now()->format('Y-m');
        $reportsThisMonth = FinanceReport::whereBetween('tanggal', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ])->count();
        $this->line("  ✓ Reports in {$currentMonth}: {$reportsThisMonth}");
        $this->newLine();

        // Test 8: Kondisi summary
        $this->info('Test 8: Kondisi Summary');
        $summary = FinanceReport::whereBetween('tanggal', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ])
        ->selectRaw('kondisi, count(*) as jumlah')
        ->groupBy('kondisi')
        ->pluck('jumlah', 'kondisi')
        ->toArray();
        
        foreach (FinanceReport::KONDISI as $key => $label) {
            $count = $summary[$key] ?? 0;
            $this->line("  ✓ {$label}: {$count}");
        }
        $this->newLine();

        $this->info('=== ALL TESTS COMPLETED ===');
        $this->info("Total Finance Reports: " . FinanceReport::count());
        
        return 0;
    }
}
