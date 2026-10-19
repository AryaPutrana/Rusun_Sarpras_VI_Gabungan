<?php

use App\Http\Controllers\Admin\FinanceLocationController;
use App\Http\Controllers\Admin\MonitoringLocationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\WithdrawalController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    // Rate limiting ditangani langsung di AuthController dengan logic custom
    // (increment hanya pada failed attempts, clear on success)
    // Tidak menggunakan middleware throttle:login untuk menghindari double limiting
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/laporan-bulanan', [DashboardController::class, 'financeSection'])
        ->name('dashboard.finance-section');

    // Admin & petugas: melihat, mengubah, dan menghapus data pemeriksaan
    // serta data keuangan (petugas hanya dapat mengelola data miliknya sendiri)
    Route::middleware('role:admin,petugas')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/{monitoring}/edit', [MonitoringController::class, 'edit'])->name('monitoring.edit');
        Route::put('/monitoring/{monitoring}', [MonitoringController::class, 'update'])->name('monitoring.update');
        Route::delete('/monitoring/{monitoring}', [MonitoringController::class, 'destroy'])->name('monitoring.destroy');
        Route::get('/keuangan/{report}/edit', [FinanceReportController::class, 'edit'])->name('keuangan.edit');
        Route::put('/keuangan/{report}', [FinanceReportController::class, 'update'])->name('keuangan.update');
        Route::delete('/keuangan/{report}', [FinanceReportController::class, 'destroy'])->name('keuangan.destroy');
    });

    // Hanya petugas: input pemeriksaan & input/riwayat keuangan
    Route::middleware('role:petugas')->group(function () {
        Route::get('/monitoring/create', [MonitoringController::class, 'create'])->name('monitoring.create');
        Route::post('/monitoring', [MonitoringController::class, 'store'])->name('monitoring.store');
        Route::get('/keuangan/create', [FinanceReportController::class, 'create'])->name('keuangan.create');
        Route::post('/keuangan', [FinanceReportController::class, 'store'])->name('keuangan.store');
        Route::get('/keuangan/riwayat', [FinanceReportController::class, 'riwayat'])->name('keuangan.riwayat');
    });

    // Hanya admin: laporan bulanan keuangan & kelola data master
    Route::middleware('role:admin')->group(function () {
        Route::get('/keuangan', [FinanceReportController::class, 'index'])->name('keuangan.index');
        Route::get('/keuangan/pdf', [FinanceReportController::class, 'exportPdf'])->name('keuangan.pdf');
        Route::resource('lokasi', MonitoringLocationController::class)->except('show');
        Route::resource('lokasi-bulanan', FinanceLocationController::class)
            ->parameters(['lokasi-bulanan' => 'lokasiBulanan'])
            ->names([
                'index' => 'lokasiBulanan.index',
                'create' => 'lokasiBulanan.create',
                'store' => 'lokasiBulanan.store',
                'edit' => 'lokasiBulanan.edit',
                'update' => 'lokasiBulanan.update',
                'destroy' => 'lokasiBulanan.destroy',
            ])
            ->except('show');
        Route::resource('pengguna', UserController::class)->except('show');
    });

    // Detail pemeriksaan — URL dapat dibagikan (shareable) sesuai PRD pasal 23.
    // Semua role login: petugas hanya dapat membuka data miliknya sendiri
    // (dicek pada MonitoringController::show -> 403 bila bukan miliknya),
    // admin dapat membuka semua data.
    Route::get('/monitoring/{monitoring}', [MonitoringController::class, 'show'])
        ->name('monitoring.show');

    // Foto bukti tidak dilayani sebagai file statis. File dibaca dari disk
    // privat oleh PhotoController yang memeriksa hak akses sebelum mengirim.
    Route::get('/foto/pemeriksaan/{monitoring}', [PhotoController::class, 'monitoring'])
        ->name('foto.monitoring');
    Route::get('/foto/keuangan/{report}', [PhotoController::class, 'finance'])
        ->name('foto.finance');

    // ========== INVENTORY MATERIAL ==========
    // Semua role dapat akses inventory (admin & petugas)
    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Items (Inventory Barang)
        Route::resource('items', ItemController::class);
        
        // Withdrawals (Pengambilan Barang)
        Route::resource('withdrawals', WithdrawalController::class);
        
        // Reports (Laporan Pengambilan)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        
        // Export routes dengan rate limiting dan CSRF protection
        // Menggunakan POST untuk mencegah CSRF attacks dan DoS abuse
        Route::middleware('throttle:exports')->group(function () {
            Route::post('/reports/export/pdf', [ReportController::class, 'exportPdf'])
                ->name('reports.pdf');
            Route::post('/reports/export/excel', [ReportController::class, 'exportExcel'])
                ->name('reports.excel');
        });
    });
});
