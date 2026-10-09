<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceReportRequest;
use App\Http\Requests\UpdateFinanceReportRequest;
use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Support\PhotoStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class FinanceReportController extends Controller
{
    /**
     * Tampilkan laporan bulanan input keuangan.
     *
     * Khusus admin (dibatasi di rute). Data difilter berdasarkan bulan
     * (format Y-m), default bulan berjalan.
     */
    public function index(Request $request): View
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->whereBetween('tanggal', [$from, $to])
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('finance_reports.index', [
            'reports' => $reports,
            'bulan' => $bulan,
            'bulanLabel' => $this->bulanLabel($bulan),
            'conditionLabels' => FinanceReport::KONDISI,
            'total' => FinanceReport::whereBetween('tanggal', [$from, $to])->count(),
            'summary' => $this->summaryKondisi($from, $to),
        ]);
    }

    /**
     * Unduh laporan bulanan dalam bentuk PDF.
     *
     * Khusus admin (dibatasi di rute). Bulan mengikuti filter yang
     * dipilih di halaman laporan (default bulan berjalan).
     */
    public function exportPdf(Request $request): Response
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->whereBetween('tanggal', [$from, $to])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $rows = $reports->values()->map(fn (FinanceReport $report, int $i) => [
            'no' => $i + 1,
            'tanggal' => $report->tanggal->format('d-m-Y'),
            'kondisi' => FinanceReport::KONDISI[$report->kondisi] ?? ucfirst($report->kondisi),
            'lokasi' => $report->location?->nama_lokasi ?? '—',
            'keterangan' => $report->keterangan,
            'foto_src' => $this->fotoToDataUri($report),
        ]);

        $pdf = Pdf::loadView('finance_reports.pdf', [
            'rows' => $rows,
            'bulanLabel' => mb_strtoupper($this->bulanLabel($bulan)),
            'total' => $rows->count(),
            'summary' => $this->summaryKondisi($from, $to),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-kondisi-air-'.$bulan.'.pdf');
    }

    /**
     * Tampilkan form input keuangan.
     */
    public function create(): View
    {
        return view('finance_reports.create', [
            'kondisiOptions' => FinanceReport::KONDISI,
            'locationOptions' => $this->locationOptions(),
        ]);
    }

    /**
     * Simpan data input keuangan.
     */
    public function store(StoreFinanceReportRequest $request): RedirectResponse
    {
        // Kegagalan menulis file ditangani di dalam storeUploaded() dan
        // diterjemahkan menjadi respons ramah. Penting untuk pengecekan
        // dilakukan SEBELUM menyentuh database: kalau file tidak bisa
        // ditulis, tidak ada record yang boleh dibuat sama sekali.
        $fotoPath = PhotoStorage::storeUploaded($request->file('foto'), PhotoStorage::DIR_FINANCE);

        if ($fotoPath === null) {
            return back()
                ->withInput()
                ->with('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        }

        try {
            FinanceReport::create([
                'user_id' => Auth::id(),
                'location_id' => $request->location_id,
                'tanggal' => $request->tanggal,
                'kondisi' => $request->kondisi,
                'keterangan' => $request->keterangan,
                'foto' => $fotoPath,
                'foto_disk' => PhotoStorage::EVIDENCE,
            ]);
        } catch (Throwable $exception) {
            PhotoStorage::deleteQuietly(Storage::disk(PhotoStorage::EVIDENCE), $fotoPath);

            throw $exception;
        }

        return redirect()->route('keuangan.riwayat', ['bulan' => Carbon::parse($request->tanggal)->format('Y-m')])
            ->with('success', 'Input bulanan berhasil disimpan.');
    }

    /**
     * Tampilkan form edit input keuangan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     */
    public function edit(FinanceReport $report): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        return view('finance_reports.edit', [
            'report' => $report,
            'kondisiOptions' => FinanceReport::KONDISI,
            'locationOptions' => $this->locationOptions($report),
        ]);
    }

    /**
     * Perbarui data input keuangan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     * Foto bersifat opsional: bila tidak diganti, foto lama tetap dipakai.
     */
    public function update(UpdateFinanceReportRequest $request, FinanceReport $report): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $data = [
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
        ];

        $fotoLama = $report->foto;
        $diskLama = $report->fotoDiskName();
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            // Kalau foto baru gagal ditulis, kembalikan respons lebih dulu
            // tanpa menyentuh record. Foto lama pada database maupun di
            // disk karena itu tetap utuh dan tidak ada file baru yang
            // menggantung.
            $fotoBaru = PhotoStorage::storeUploaded($request->file('foto'), PhotoStorage::DIR_FINANCE);

            if ($fotoBaru === null) {
                return back()
                    ->withInput()
                    ->with('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
            }

            $data['foto'] = $fotoBaru;
            $data['foto_disk'] = PhotoStorage::EVIDENCE;
        }

        try {
            $report->update($data);
        } catch (Throwable $exception) {
            if ($fotoBaru !== null) {
                PhotoStorage::deleteQuietly(Storage::disk(PhotoStorage::EVIDENCE), $fotoBaru);
            }

            throw $exception;
        }

        if ($fotoBaru !== null && $fotoLama !== null && $fotoLama !== $fotoBaru) {
            PhotoStorage::deleteQuietly(Storage::disk($diskLama), $fotoLama);
        }

        $bulan = Carbon::parse($request->tanggal)->format('Y-m');

        if (Auth::user()->isAdmin()) {
            return redirect()->route('keuangan.index', ['bulan' => $bulan])
                ->with('success', 'Input keuangan berhasil diperbarui.');
        }

        return redirect()->route('keuangan.riwayat', ['bulan' => $bulan])
            ->with('success', 'Input keuangan berhasil diperbarui.');
    }

    /**
     * Hapus data input keuangan.
     *
     * Petugas hanya dapat menghapus data miliknya sendiri.
     * Admin dapat menghapus data siapa saja.
     * File foto ikut dihapus dari storage.
     */
    public function destroy(FinanceReport $report): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $report->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus data ini.');
        }

        // Baris database sudah terhapus, jadi deleteFotoFile() hanya
        // operasi pembersihan sisa file. Kegagalan tidak dilempar agar
        // pengguna tidak melihat halaman 500 padahal hapus datanya
        // sendiri sudah berhasil. Pola ini sama dengan
        // MonitoringController::destroy(). Foto dihapus lewat
        // deleteFotoFile() supaya penentuan disk dan peniadaan path
        // traversal tidak ditulis ulang di sini.
        $report->delete();
        $report->deleteFotoFile();

        return back()->with('success', 'Data input keuangan berhasil dihapus.');
    }

    /**
     * Tampilkan riwayat input keuangan milik petugas yang sedang login.
     *
     * Khusus petugas (dibatasi di rute). Data difilter berdasarkan bulan
     * (format Y-m), default bulan berjalan, diurutkan dari yang terbaru.
     */
    public function riwayat(Request $request): View
    {
        $bulan = $this->resolveBulan($request);

        $from = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $to = Carbon::createFromFormat('Y-m', $bulan)->endOfMonth();

        $reports = FinanceReport::with(['user', 'location'])
            ->where('user_id', Auth::id())
            ->whereBetween('tanggal', [$from, $to])
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('finance_reports.riwayat', [
            'reports' => $reports,
            'bulan' => $bulan,
            'bulanLabel' => $this->bulanLabel($bulan),
            'conditionLabels' => FinanceReport::KONDISI,
            'total' => $reports->total(),
            'summary' => $this->summaryKondisi($from, $to, Auth::id()),
        ]);
    }

    /**
     * Daftar lokasi bulanan untuk pilihan pada form input/edit.
     *
     * Menampilkan lokasi berstatus aktif. Saat mengedit, lokasi milik
     * laporan tetap disertakan meskipun statusnya nonaktif.
     */
    protected function locationOptions(?FinanceReport $report = null): Collection
    {
        return FinanceLocation::query()
            ->where(function ($query) use ($report) {
                $query->where('status', 'aktif');

                if ($report && $report->location_id) {
                    $query->orWhere('id', $report->location_id);
                }
            })
            ->orderBy('nama_lokasi')
            ->get();
    }

    /**
     * Tentukan bulan (format Y-m) berdasarkan request.
     *
     * Prioritas: parameter bulan -> bulan berjalan.
     */
    protected function resolveBulan(Request $request): string
    {
        $bulan = $request->string('bulan')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return $bulan;
        }

        return now()->format('Y-m');
    }

    /**
     * Label bulan untuk tampilan (contoh: September 2026).
     */
    protected function bulanLabel(string $bulan): string
    {
        return Carbon::createFromFormat('Y-m', $bulan)->locale('id')->translatedFormat('F Y');
    }

    /**
     * Ringkasan jumlah data per kondisi dalam rentang tanggal tertentu.
     *
     * Bila $userId diberikan, hanya menghitung data milik user tersebut.
     * Mengembalikan array ber-key kondisi dengan nilai 0 bila tidak ada data.
     */
    protected function summaryKondisi(Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $query = FinanceReport::whereBetween('tanggal', [$from, $to]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $counts = $query->selectRaw('kondisi, count(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi')
            ->toArray();

        $summary = [];

        foreach (array_keys(FinanceReport::KONDISI) as $kondisi) {
            $summary[$kondisi] = $counts[$kondisi] ?? 0;
        }

        return $summary;
    }

    /**
     * Ubah file foto di storage menjadi data URI untuk di-embed di PDF.
     *
     * File dibaca dari disk sesuai kolom foto_disk, sehingga foto lama di
     * disk publik maupun foto baru di disk privat sama-sama terbaca.
     * Mengembalikan null bila path kosong, tidak aman, atau file hilang.
     *
     * Isi file diambil dengan satu panggilan getQuietly(), bukan dengan
     * exists() diikuti get(). Pasangan itu menyentuh disk dua kali dan
     * menyisakan celah: file yang hilang di antara keduanya membuat get()
     * melempar dan menggagalkan seluruh unduhan PDF bulan itu, bukan
     * hanya satu foto. Foto yang tidak terbaca karena permission pun
     * hanya menghilangkan satu baris foto, bukan seluruh laporan.
     */
    protected function fotoToDataUri(?FinanceReport $report): ?string
    {
        if ($report === null) {
            return null;
        }

        $path = $report->fotoPath();

        if ($path === null) {
            return null;
        }

        $isi = PhotoStorage::getQuietly($report->fotoDisk(), $path);

        if ($isi === null || $isi === '') {
            return null;
        }

        return 'data:'.PhotoStorage::mimeFor($path, 'image/jpeg').';base64,'.base64_encode($isi);
    }
}
