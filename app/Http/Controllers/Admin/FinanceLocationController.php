<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceLocationRequest;
use App\Http\Requests\UpdateFinanceLocationRequest;
use App\Models\FinanceLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceLocationController extends Controller
{
    /**
     * Tampilkan daftar lokasi bulanan.
     */
    public function index(): View
    {
        $locations = FinanceLocation::withCount('financeReports')
            ->orderBy('nama_lokasi')
            ->paginate(10);

        return view('finance_locations.index', compact('locations'));
    }

    /**
     * Tampilkan form tambah lokasi bulanan.
     */
    public function create(): View
    {
        return view('finance_locations.create', $this->formData());
    }

    /**
     * Simpan lokasi bulanan baru.
     */
    public function store(StoreFinanceLocationRequest $request): RedirectResponse
    {
        FinanceLocation::create($request->validated());

        return redirect()->route('lokasiBulanan.index')
            ->with('success', 'Lokasi bulanan berhasil ditambahkan.');
    }

    /**
     * Tampilkan form ubah lokasi bulanan.
     */
    public function edit(FinanceLocation $lokasiBulanan): View
    {
        return view('finance_locations.edit', array_merge(
            ['lokasi' => $lokasiBulanan],
            $this->formData()
        ));
    }

    /**
     * Perbarui lokasi bulanan.
     */
    public function update(UpdateFinanceLocationRequest $request, FinanceLocation $lokasiBulanan): RedirectResponse
    {
        $lokasiBulanan->update($request->validated());

        return redirect()->route('lokasiBulanan.index')
            ->with('success', 'Lokasi bulanan berhasil diperbarui.');
    }

    /**
     * Hapus lokasi bulanan.
     */
    public function destroy(FinanceLocation $lokasiBulanan): RedirectResponse
    {
        if ($lokasiBulanan->financeReports()->exists()) {
            return redirect()->route('lokasiBulanan.index')
                ->with('error', 'Lokasi tidak dapat dihapus karena memiliki data laporan bulanan.');
        }

        $lokasiBulanan->delete();

        return redirect()->route('lokasiBulanan.index')
            ->with('success', 'Lokasi bulanan berhasil dihapus.');
    }

    /**
     * Data pilihan yang dibutuhkan oleh form lokasi bulanan.
     *
     * @return array<string, array<string, string>>
     */
    private function formData(): array
    {
        return [
            'statusOptions' => FinanceLocation::STATUS,
        ];
    }
}
