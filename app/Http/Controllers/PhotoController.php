<?php

namespace App\Http\Controllers;

use App\Models\FinanceReport;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PhotoController extends Controller
{
    /**
     * Tampilkan foto bukti pemeriksaan.
     *
     * Foto tidak lagi dilayani sebagai file statis. File dibaca dari disk
     * privat lalu dikirim ke pengguna yang sudah login dan berhak
     * melihat record tersebut.
     */
    public function monitoring(Request $request, WaterMonitoring $monitoring): BinaryFileResponse
    {
        $this->authorizePhoto($request, $monitoring->user_id);

        return $this->stream($monitoring);
    }

    /**
     * Tampilkan foto bukti input keuangan.
     */
    public function finance(Request $request, FinanceReport $report): BinaryFileResponse
    {
        $this->authorizePhoto($request, $report->user_id);

        return $this->stream($report);
    }

    /**
     * Admin dapat melihat semua foto, petugas hanya miliknya sendiri.
     */
    protected function authorizePhoto(Request $request, ?int $ownerId): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($ownerId === null || (int) $ownerId !== (int) $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat foto ini.');
        }
    }

    /**
     * Kirim file foto ke browser dengan Content-Type yang dikunci.
     *
     * Pemeriksaan keberadaan file dan pengiriman file tidak bisa dilakukan
     * sebagai satu operasi atomik: foto bisa terhapus di antara keduanya,
     * misalnya karena petugas menghapus recordnya sendiri di tab lain
     * pada saat yang sama. BinaryFileResponse melempar FileNotFoundException
     * untuk kondisi itu, jadi tanpa penanganan tambahan pengguna akan
     * melihat halaman error 500 padahal penyebab sebenarnya cuma file
     * yang memang sudah tidak ada.
     */
    protected function stream(WaterMonitoring|FinanceReport $record): BinaryFileResponse
    {
        $path = $record->fotoPath();

        if ($path === null) {
            abort(404, 'Foto tidak ditemukan.');
        }

        $disk = $record->fotoDisk();

        if (! PhotoStorage::existsQuietly($disk, $path)) {
            abort(404, 'Foto tidak ditemukan.');
        }

        $localPath = PhotoStorage::localPath($disk, $path);

        if ($localPath === null) {
            abort(404, 'Foto tidak ditemukan.');
        }

        try {
            return response()->file($localPath, [
                'Content-Type' => PhotoStorage::mimeFor($path),
                'Content-Disposition' => 'inline; filename="'.addslashes(basename($path)).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        } catch (FileException) {
            abort(404, 'Foto tidak ditemukan.');
        }
    }
}
