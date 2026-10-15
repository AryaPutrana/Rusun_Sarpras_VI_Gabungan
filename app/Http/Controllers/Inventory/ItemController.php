<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Concerns\SanitizesQueryInput;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Rules\ValidImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ItemController extends Controller
{
    use SanitizesQueryInput;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $this->scalarQuery($request, 'search');

        $items = Item::query()
            ->when(filled($search), function ($query) use ($search) {
                return $query->where('item_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('item_code', 'asc') // Urutan dari terkecil ke terbesar (BRG-001, BRG-002...)
            ->paginate(10); // 10 item per halaman

        return view('inventory.items.index', compact('items', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('inventory.items.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => 'required|unique:items,item_code|max:50',
            'name' => 'required|max:255',
            'photo' => ['nullable', 'file', new ValidImage(), 'max:5120'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:'.Item::MAX_UNIT_PRICE],
            'stock' => ['required', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
            'unit' => 'required|max:50',
            'description' => 'nullable|string|max:60000',
        ], [
            'photo.max' => 'Ukuran foto maksimal 5MB.',
            'unit_price.max' => 'Harga satuan maksimal Rp 1.000.000.000.',
            'stock.max' => 'Jumlah stok terlalu besar.',
            'min_stock.max' => 'Batas minimum stok terlalu besar.',
        ]);

        $validated['min_stock'] = $validated['min_stock'] ?? 0;
        $validated['description'] = $validated['description'] ?? null;

        $photo = $request->file('photo');
        $filename = $photo ? Str::uuid().'.'.$photo->extension() : null;

        try {
            // Simpan DB + file dalam satu transaksi; file dibersihkan bila simpan gagal
            DB::transaction(function () use ($validated, $photo, $filename) {
                $validated['photo'] = $filename;
                Item::create($validated);
                if ($photo && $filename) {
                    $photo->storeAs('public/items', $filename);
                }
            });
        } catch (Throwable $e) {
            if ($filename) {
                Storage::delete('public/items/'.$filename);
            }
            throw $e;
        }

        return redirect()->route('inventory.items.index')
            ->with('success', 'Barang berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Item::findOrFail($id);

        return view('inventory.items.show', compact('item'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $item = Item::findOrFail($id);

        return view('inventory.items.edit', compact('item'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $item = Item::findOrFail($id);

        $validated = $request->validate([
            'item_code' => 'required|max:50|unique:items,item_code,'.$id,
            'name' => 'required|max:255',
            'photo' => ['nullable', 'file', new ValidImage(), 'max:5120'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:'.Item::MAX_UNIT_PRICE],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
            'unit' => 'required|max:50',
            'description' => 'nullable|string|max:60000',
            // Mode pengelolaan stok yang dipilih pengguna (REQUIRED untuk kejelasan)
            'stock_mode' => ['required', 'in:adjustment,direct'],
            // Mode Adjustment
            'add_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_ADD_STOCK],
            'subtract_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
            'subtract_reason' => ['nullable', 'string', 'max:255'],
            // Mode Direct Edit
            'new_stock' => ['nullable', 'integer', 'min:0', 'max:'.Item::MAX_STOCK],
            'stock_change_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'photo.max' => 'Ukuran foto maksimal 5MB.',
            'unit_price.max' => 'Harga satuan maksimal Rp 1.000.000.000.',
            'min_stock.max' => 'Batas minimum stok terlalu besar.',
            'add_stock.max' => 'Penambahan stok maksimal 1.000.000 Unit per kali.',
            'subtract_stock.max' => 'Pengurangan stok tidak boleh melebihi stok maksimum.',
            'new_stock.max' => 'Jumlah stok terlalu besar.',
        ]);

        // Mode pengelolaan stok HARUS dipilih eksplisit oleh user
        // Tidak ada auto-detect untuk mencegah salah interpretasi
        $stockMode = $validated['stock_mode'] ?? 'adjustment';  // Default aman untuk backward compatibility

        // Alasan pengurangan wajib hanya saat stok benar-benar dikurangi pada
        // mode adjustment. Sebelumnya memakai required_with:subtract_stock,
        // padahal form selalu mengirim subtract_stock=0 sehingga memblokir
        // seluruh update — termasuk update yang tidak mengubah stok sama sekali.
        if ($stockMode === 'adjustment'
            && isset($validated['subtract_stock'])
            && $validated['subtract_stock'] > 0
            && empty($validated['subtract_reason'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'subtract_reason' => 'Alasan pengurangan stok wajib diisi.',
            ]);
        }

        // Validasi custom: stock_change_reason wajib hanya jika stok benar-benar
        // berubah pada mode direct edit.
        if ($stockMode === 'direct'
            && isset($validated['new_stock'])
            && $validated['new_stock'] != $item->stock
            && empty($validated['stock_change_reason'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stock_change_reason' => 'Alasan perubahan stok wajib diisi saat mengubah stok.',
            ]);
        }

        $validated['min_stock'] = $validated['min_stock'] ?? 0;
        $validated['description'] = $validated['description'] ?? null;

        $oldPhoto = null;
        $newFilename = null;

        try {
            DB::transaction(function () use ($request, $id, &$validated, &$oldPhoto, &$newFilename, $stockMode) {
                // Lock baris untuk mencegah race condition
                $locked = Item::whereKey($id)->lockForUpdate()->firstOrFail();

                // MODE 1: ADJUSTMENT - Tambah/kurangi dari stok saat ini.
                // Hanya dijalankan pada mode adjustment supaya perubahan tidak
                // ditimpa oleh nilai new_stock dari section mode direct yang
                // selalu ikut terkirim oleh form.
                if ($stockMode === 'adjustment') {
                    if (! empty($validated['add_stock']) && $validated['add_stock'] > 0) {
                        $locked->increment('stock', $validated['add_stock']);

                        // Audit trail untuk penambahan stok
                        \App\Models\StockAdjustment::create([
                            'item_id' => $locked->id,
                            'user_id' => auth()->id(),
                            'type' => 'add',
                            'quantity' => $validated['add_stock'],
                            'reason' => 'Penambahan stok melalui form edit barang',
                        ]);
                    }

                    if (! empty($validated['subtract_stock']) && $validated['subtract_stock'] > 0) {
                        // Re-check stok di dalam lock (race condition guard)
                        if ($locked->stock < $validated['subtract_stock']) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'subtract_stock' => 'Stok tidak mencukupi. Stok saat ini: '.$locked->stock.' unit. Kemungkinan ada pengambilan bersamaan.',
                            ]);
                        }

                        $locked->decrement('stock', $validated['subtract_stock']);

                        // Audit trail untuk pengurangan stok
                        \App\Models\StockAdjustment::create([
                            'item_id' => $locked->id,
                            'user_id' => auth()->id(),
                            'type' => 'subtract',
                            'quantity' => $validated['subtract_stock'],
                            'reason' => $validated['subtract_reason'] ?? 'Koreksi stok',
                        ]);
                    }
                }
                unset($validated['add_stock']);
                unset($validated['subtract_stock']);
                unset($validated['subtract_reason']);

                // MODE 2: DIRECT EDIT - Ganti stok langsung dengan angka baru.
                // Hanya dijalankan pada mode direct agar tidak menimpa hasil
                // adjustment.
                if ($stockMode === 'direct'
                    && isset($validated['new_stock'])
                    && $validated['new_stock'] != $locked->stock) {
                    $oldStock = $locked->stock;
                    $newStock = $validated['new_stock'];
                    $difference = $newStock - $oldStock;

                    // Update stok langsung
                    $locked->stock = $newStock;
                    $locked->save();

                    // Audit trail untuk perubahan stok langsung
                    \App\Models\StockAdjustment::create([
                        'item_id' => $locked->id,
                        'user_id' => auth()->id(),
                        'type' => $difference >= 0 ? 'add' : 'subtract',
                        'quantity' => abs($difference),
                        'reason' => $validated['stock_change_reason'] ?? 'Edit stok langsung dari '.$oldStock.' ke '.$newStock,
                    ]);
                }
                unset($validated['new_stock']);
                unset($validated['stock_change_reason']);
                unset($validated['stock_mode']);

                // Ganti foto bila ada upload baru
                if ($request->hasFile('photo')) {
                    $photo = $request->file('photo');
                    $newFilename = Str::uuid().'.'.$photo->extension();
                    $photo->storeAs('public/items', $newFilename);
                    $validated['photo'] = $newFilename;
                    $oldPhoto = $locked->photo;
                } else {
                    // Hapus field photo dari validated untuk mencegah overwrite dengan null
                    unset($validated['photo']);
                }

                $locked->update($validated);
            });
        } catch (Throwable $e) {
            if ($newFilename) {
                Storage::delete('public/items/'.$newFilename);
            }
            throw $e;
        }

        // Hapus foto lama hanya setelah transaksi berhasil, dan hanya jika tidak dipakai item lain
        if ($oldPhoto) {
            $stillUsed = Item::where('photo', $oldPhoto)
                ->where('id', '!=', $item->id)
                ->exists();
            if (! $stillUsed) {
                Storage::delete('public/items/'.$oldPhoto);

                // Invalidate cache thumbnail foto lama
                $oldKey = 'item-thumb:'.sha1($oldPhoto.'|44|v1');
                Cache::forget($oldKey);
            }
        }

        return redirect()->route('inventory.items.index')
            ->with('success', 'Barang berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Item::findOrFail($id);

        // Cegah penghapusan bila barang punya riwayat pengambilan (histori laporan harus terjaga)
        if ($item->withdrawals()->exists()) {
            return redirect()->back()
                ->with('error', 'Barang tidak dapat dihapus karena memiliki riwayat pengambilan.');
        }

        $photoPath = $item->photo;

        // Wrap dalam transaction untuk data integrity
        DB::transaction(function () use ($item) {
            $item->delete();
        });
        
        // Hapus foto dan invalidate cache SETELAH transaction berhasil
        if ($photoPath) {
            // Cek apakah masih ada item lain yang pakai foto ini
            $stillUsed = Item::where('photo', $photoPath)->exists();
            
            if (! $stillUsed) {
                Storage::delete('public/items/'.$photoPath);

                // Invalidate cache thumbnail (di luar transaction!)
                $cacheKey = 'item-thumb:'.sha1($photoPath.'|44|v1');
                Cache::forget($cacheKey);
            }
        }

        return redirect()->route('inventory.items.index')
            ->with('success', 'Barang berhasil dihapus.');
    }
}
