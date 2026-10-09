<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMonitoringLocationRequest;
use App\Http\Requests\UpdateMonitoringLocationRequest;
use App\Models\MonitoringLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MonitoringLocationController extends Controller
{
    /**
     * Tampilkan daftar lokasi monitoring.
     */
    public function index(): View
    {
        $locations = MonitoringLocation::withCount('waterMonitorings')
            ->orderBy('nama_lokasi')
            ->paginate(10);

        return view('locations.index', compact('locations'));
    }

    /**
     * Tampilkan form tambah lokasi.
     */
    public function create(): View
    {
        return view('locations.create', $this->formData());
    }

    /**
     * Simpan lokasi baru.
     */
    public function store(StoreMonitoringLocationRequest $request): RedirectResponse
    {
        MonitoringLocation::create($request->validated());

        return redirect()->route('lokasi.index')
            ->with('success', 'Lokasi monitoring berhasil ditambahkan.');
    }

    /**
     * Tampilkan form ubah lokasi.
     */
    public function edit(MonitoringLocation $lokasi): View
    {
        return view('locations.edit', array_merge(['lokasi' => $lokasi], $this->formData()));
    }

    /**
     * Perbarui lokasi.
     */
    public function update(UpdateMonitoringLocationRequest $request, MonitoringLocation $lokasi): RedirectResponse
    {
        $lokasi->update($request->validated());

        return redirect()->route('lokasi.index')
            ->with('success', 'Lokasi monitoring berhasil diperbarui.');
    }

    /**
     * Hapus lokasi.
     */
    public function destroy(MonitoringLocation $lokasi): RedirectResponse
    {
        if ($lokasi->waterMonitorings()->exists()) {
            return redirect()->route('lokasi.index')
                ->with('error', 'Lokasi tidak dapat dihapus karena memiliki data pemeriksaan.');
        }

        $lokasi->delete();

        return redirect()->route('lokasi.index')
            ->with('success', 'Lokasi monitoring berhasil dihapus.');
    }

    /**
     * Data pilihan yang dibutuhkan oleh form lokasi.
     *
     * @return array<string, array<string, string>>
     */
    private function formData(): array
    {
        return [
            'statusOptions' => MonitoringLocation::STATUS,
        ];
    }
}
