<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaterMonitoringRequest;
use App\Http\Requests\UpdateWaterMonitoringRequest;
use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class MonitoringController extends Controller
{
    /**
     * Pilihan lokasi untuk form.
     *
     * Lokasi aktif selalu tersedia. Lokasi non-aktif hanya dimuat bila lokasi
     * tersebut sedang dipakai oleh record yang sedang diedit, agar edit tidak
     * kehilangan pilihan yang sudah tersimpan.
     */
    protected function locationOptions(?WaterMonitoring $monitoring = null): Collection
    {
        return MonitoringLocation::query()
            ->where(function ($query) use ($monitoring) {
                $query->where('status', 'aktif');

                if ($monitoring?->location_id) {
                    $query->orWhere('id', $monitoring->location_id);
                }
            })
            ->orderBy('nama_lokasi')
            ->get();
    }
    /**
     * Tampilkan daftar hasil pemeriksaan.
     *
     * Admin melihat seluruh data, petugas hanya melihat data miliknya.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $filterTanggal = $request->input('tanggal');
        $filterLokasi  = $request->input('lokasi');

        $monitorings = WaterMonitoring::with(['user', 'location'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->when($filterTanggal, fn ($query) => $query->whereDate('tanggal', $filterTanggal))
            ->when($filterLokasi, fn ($query) => $query->where('location_id', $filterLokasi))
            ->latest('tanggal')
            ->latest('waktu')
            ->paginate(10)
            ->withQueryString();

        return view('monitoring.index', [
            'monitorings'   => $monitorings,
            'isAdmin'       => $user->isAdmin(),
            'filterTanggal' => $filterTanggal,
            'filterLokasi'  => $filterLokasi,
            'locations'     => MonitoringLocation::orderBy('nama_lokasi')->get(),
        ]);
    }

    /**
     * Tampilkan form input pemeriksaan.
     */
    public function create(): View
    {
        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        return view('monitoring.create', [
            'locations' => $locations,
            'sesiOptions' => WaterMonitoring::SESI,
            'kondisiOptions' => WaterMonitoring::KONDISI,
        ]);
    }

    /**
     * Simpan hasil pemeriksaan.
     */
    public function store(StoreWaterMonitoringRequest $request): RedirectResponse
    {
        // Kegagalan menulis file ditangani di dalam storeUploaded() dan
        // diterjemahkan menjadi respons ramah. Penting untuk pengecekan
        // dilakukan SEBELUM menyentuh database: kalau file tidak bisa
        // ditulis, tidak ada record yang boleh dibuat sama sekali.
        $fotoPath = PhotoStorage::storeUploaded($request->file('foto'), PhotoStorage::DIR_MONITORING);

        if ($fotoPath === null) {
            return back()
                ->withInput()
                ->with('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        }

        try {
            WaterMonitoring::create([
                'user_id' => Auth::id(),
                'location_id' => $request->location_id,
                'tanggal' => $request->tanggal,
                'sesi' => $request->sesi,
                'waktu' => WaterMonitoring::SESI_WAKTU[$request->sesi],
                'kondisi' => $request->kondisi,
                'keterangan' => $request->keterangan,
                'foto' => $fotoPath,
                'foto_disk' => PhotoStorage::EVIDENCE,
            ]);
        } catch (Throwable $exception) {
            PhotoStorage::deleteQuietly(Storage::disk(PhotoStorage::EVIDENCE), $fotoPath);

            throw $exception;
        }

        if ($request->kondisi !== 'normal') {
            $label = WaterMonitoring::KONDISI[$request->kondisi];

            return redirect()->route('dashboard')
                ->with('error', "Pemeriksaan tersimpan. Kondisi \"{$label}\" terdeteksi — PERLU PERHATIAN.");
        }

        return redirect()->route('dashboard')
            ->with('success', 'Pemeriksaan berhasil disimpan.');
    }

    /**
     * Tampilkan form edit pemeriksaan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     */
    public function edit(WaterMonitoring $monitoring): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $locations = $this->locationOptions($monitoring);

        return view('monitoring.edit', [
            'monitoring' => $monitoring,
            'locations' => $locations,
            'sesiOptions' => WaterMonitoring::SESI,
            'kondisiOptions' => WaterMonitoring::KONDISI,
        ]);
    }

    /**
     * Perbarui hasil pemeriksaan.
     *
     * Petugas hanya dapat mengubah data miliknya sendiri.
     * Admin dapat mengubah data siapa saja.
     * Foto bersifat opsional: bila tidak diganti, foto lama tetap dipakai.
     */
    public function update(UpdateWaterMonitoringRequest $request, WaterMonitoring $monitoring): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data ini.');
        }

        $data = [
            'location_id' => $request->location_id,
            'tanggal' => $request->tanggal,
            'sesi' => $request->sesi,
            'waktu' => WaterMonitoring::SESI_WAKTU[$request->sesi],
            'kondisi' => $request->kondisi,
            'keterangan' => $request->keterangan,
        ];

        $fotoLama = $monitoring->foto;
        $diskLama = $monitoring->fotoDiskName();
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            // Kalau foto baru gagal ditulis, kembalikan respons lebih dulu
            // tanpa menyentuh record. Foto lama pada database maupun di
            // disk karena itu tetap utuh dan tidak ada file baru yang
            // menggantung.
            $fotoBaru = PhotoStorage::storeUploaded($request->file('foto'), PhotoStorage::DIR_MONITORING);

            if ($fotoBaru === null) {
                return back()
                    ->withInput()
                    ->with('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
            }

            $data['foto'] = $fotoBaru;
            $data['foto_disk'] = PhotoStorage::EVIDENCE;
        }

        try {
            $monitoring->update($data);
        } catch (Throwable $exception) {
            if ($fotoBaru !== null) {
                PhotoStorage::deleteQuietly(Storage::disk(PhotoStorage::EVIDENCE), $fotoBaru);
            }

            throw $exception;
        }

        if ($fotoBaru !== null && $fotoLama !== null && $fotoLama !== $fotoBaru) {
            PhotoStorage::deleteQuietly(Storage::disk($diskLama), $fotoLama);
        }

        if ($monitoring->kondisi !== 'normal') {
            $label = WaterMonitoring::KONDISI[$monitoring->kondisi];

            return redirect()->route('dashboard')
                ->with('error', "Pemeriksaan diperbarui. Kondisi \"{$label}\" terdeteksi — PERLU PERHATIAN.");
        }

        return redirect()->route('dashboard')
            ->with('success', 'Pemeriksaan berhasil diperbarui.');
    }

    /**
     * Tampilkan detail satu pemeriksaan (URL dapat dibagikan sesuai PRD pasal 23).
     *
     * Petugas hanya dapat melihat data miliknya sendiri, admin dapat
     * melihat semua data (dicek di sini -> 403 bila bukan miliknya).
     */
    public function show(WaterMonitoring $monitoring): View
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data ini.');
        }

        $monitoring->load(['user', 'location']);

        return view('monitoring.show', [
            'monitoring' => $monitoring,
            'isAdmin' => $user->isAdmin(),
        ]);
    }
    /**
     * Hapus data pemeriksaan.
     *
     * Petugas hanya dapat menghapus data miliknya sendiri.
     * Admin dapat menghapus data siapa saja.
     * File foto ikut dihapus dari storage.
     */
    public function destroy(WaterMonitoring $monitoring): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isAdmin() && $monitoring->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus data ini.');
        }

        // Baris database dihapus lebih dulu, baru file-nya. Urutan ini
        // disengaja: bila penghapusan file gagal, pengguna tetap melihat
        // datanya sudah hilang, bukan halaman error. Pola yang sama
        // berlaku di FinanceReportController::destroy(). Foto dihapus
        // lewat deleteFotoFile() supaya penentuan disk dan peniadaan
        // path traversal tidak ditulis ulang di sini.
        $monitoring->delete();
        $monitoring->deleteFotoFile();

        return back()->with('success', 'Data pemeriksaan berhasil dihapus.');
    }
}
