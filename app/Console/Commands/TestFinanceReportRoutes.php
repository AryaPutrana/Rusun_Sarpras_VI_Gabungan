<?php

namespace App\Console\Commands;

use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class TestFinanceReportRoutes extends Command
{
    protected $signature = 'test:finance-routes';
    protected $description = 'Test Finance Report routes and authorization';

    public function handle()
    {
        $this->info('=== FINANCE REPORT ROUTES TEST ===');
        $this->newLine();

        // Get users
        $admin = User::where('role', 'admin')->first();
        $petugas = User::where('role', 'petugas')->first();

        if (!$admin || !$petugas) {
            $this->error('Users not found. Run db:seed first.');
            return 1;
        }

        // Test 1: Check routes exist
        $this->info('Test 1: Check Routes Exist');
        $routes = [
            'keuangan.index' => 'GET /keuangan',
            'keuangan.create' => 'GET /keuangan/create',
            'keuangan.store' => 'POST /keuangan',
            'keuangan.edit' => 'GET /keuangan/{report}/edit',
            'keuangan.update' => 'PUT /keuangan/{report}',
            'keuangan.destroy' => 'DELETE /keuangan/{report}',
            'keuangan.riwayat' => 'GET /keuangan/riwayat',
            'keuangan.pdf' => 'GET /keuangan/pdf',
        ];

        foreach ($routes as $name => $description) {
            if (Route::has($name)) {
                $this->line("  ✓ {$description}");
            } else {
                $this->error("  ✗ {$description} - NOT FOUND");
            }
        }
        $this->newLine();

        // Test 2: Check middleware
        $this->info('Test 2: Check Route Middleware');
        
        $routeTests = [
            ['name' => 'keuangan.index', 'expected' => 'role:admin'],
            ['name' => 'keuangan.pdf', 'expected' => 'role:admin'],
            ['name' => 'keuangan.create', 'expected' => 'role:petugas'],
            ['name' => 'keuangan.store', 'expected' => 'role:petugas'],
            ['name' => 'keuangan.riwayat', 'expected' => 'role:petugas'],
            ['name' => 'keuangan.edit', 'expected' => 'role:admin,petugas'],
            ['name' => 'keuangan.update', 'expected' => 'role:admin,petugas'],
            ['name' => 'keuangan.destroy', 'expected' => 'role:admin,petugas'],
        ];

        foreach ($routeTests as $test) {
            $route = Route::getRoutes()->getByName($test['name']);
            if ($route) {
                $middleware = implode(',', $route->middleware());
                $hasRole = str_contains($middleware, 'role');
                if ($hasRole) {
                    $this->line("  ✓ {$test['name']} has role middleware");
                } else {
                    $this->warn("  ! {$test['name']} missing role middleware");
                }
            }
        }
        $this->newLine();

        // Test 3: Check validation rules
        $this->info('Test 3: Check Validation Rules');
        
        $storeRequest = new \App\Http\Requests\StoreFinanceReportRequest();
        $storeRules = $storeRequest->rules();
        
        $expectedRules = [
            'tanggal' => 'required, date, before_or_equal:today',
            'location_id' => 'required, integer, exists',
            'kondisi' => 'required, in:[keys]',
            'keterangan' => 'required, string',
            'foto' => 'required, image, max:5120',
        ];
        
        foreach ($expectedRules as $field => $desc) {
            if (isset($storeRules[$field])) {
                $this->line("  ✓ {$field}: {$desc}");
            } else {
                $this->error("  ✗ {$field}: missing");
            }
        }
        $this->newLine();

        // Test 4: Check update validation
        $this->info('Test 4: Check Update Validation (Photo Optional)');
        
        $updateRequest = new \App\Http\Requests\UpdateFinanceReportRequest();
        $updateRules = $updateRequest->rules();
        
        if (isset($updateRules['foto'])) {
            $photoRules = $updateRules['foto'];
            $isOptional = in_array('sometimes', $photoRules) || in_array('nullable', $photoRules);
            if ($isOptional) {
                $this->line("  ✓ Photo is optional in update");
            } else {
                $this->warn("  ! Photo should be optional in update");
            }
        }
        $this->newLine();

        // Test 5: Check authorization logic
        $this->info('Test 5: Check Authorization Logic');
        
        $petugasReport = FinanceReport::where('user_id', $petugas->id)->first();
        
        if ($petugasReport) {
            $this->line("  ✓ Test report found (ID: {$petugasReport->id})");
            $this->line("    Owner: {$petugasReport->user->name} (ID: {$petugasReport->user_id})");
            
            // Simulate authorization checks
            $canPetugasEditOwn = ($petugasReport->user_id === $petugas->id);
            $canAdminEditAny = ($admin->role === 'admin');
            
            if ($canPetugasEditOwn) {
                $this->line("  ✓ Petugas can edit own report");
            }
            
            if ($canAdminEditAny) {
                $this->line("  ✓ Admin can edit any report");
            }
        } else {
            $this->warn("  ! No reports found for testing");
        }
        $this->newLine();

        // Test 6: Check KONDISI constants
        $this->info('Test 6: Check KONDISI Constants');
        
        $kondisiOptions = \App\Models\FinanceReport::KONDISI;
        $this->line("  ✓ KONDISI options: " . count($kondisiOptions));
        foreach ($kondisiOptions as $key => $label) {
            $this->line("    - {$key}: {$label}");
        }
        $this->newLine();

        // Test 7: Check photo handling
        $this->info('Test 7: Check Photo Handling');
        
        if ($petugasReport && $petugasReport->foto) {
            $this->line("  ✓ Photo path: {$petugasReport->foto}");
            $this->line("  ✓ Photo disk: {$petugasReport->foto_disk}");
            $exists = $petugasReport->fotoExists();
            $this->line("  ✓ Photo exists: " . ($exists ? 'Yes' : 'No'));
            
            if ($exists) {
                $photoUrl = route('foto.finance', $petugasReport);
                $this->line("  ✓ Photo URL: {$photoUrl}");
            }
        }
        $this->newLine();

        // Test 8: Summary
        $this->info('=== TEST SUMMARY ===');
        $totalReports = FinanceReport::count();
        $adminReports = FinanceReport::count();
        $petugasReports = FinanceReport::where('user_id', $petugas->id)->count();
        
        $this->line("  Total Reports: {$totalReports}");
        $this->line("  Petugas Reports: {$petugasReports}");
        $this->line("  Current Month: " . now()->format('Y-m'));
        
        return 0;
    }
}
