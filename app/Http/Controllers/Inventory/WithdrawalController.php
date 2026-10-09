<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Concerns\SanitizesQueryInput;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Rusun;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    use SanitizesQueryInput;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $this->scalarQuery($request, 'search');
        $startDate = $this->scalarQuery($request, 'start_date');
        $endDate = $this->scalarQuery($request, 'end_date');

        // Sanitasi filter tanggal. Input GET yang tidak valid dibuang (bukan di-redirect)
        // supaya halaman daftar withdrawal tidak terjadi redirect berulang.
        $filterWarning = null;

        $dateValidator = Validator::make(
            compact('startDate', 'endDate'),
            [
                'startDate' => 'nullable|date_format:Y-m-d',
                'endDate' => 'nullable|date_format:Y-m-d',
            ],
            [
                'startDate.date_format' => 'Format tanggal awal tidak valid. Gunakan format YYYY-MM-DD.',
                'endDate.date_format' => 'Format tanggal akhir tidak valid. Gunakan format YYYY-MM-DD.',
            ]
        );

        if ($dateValidator->fails()) {
            $filterWarning = $dateValidator->errors()->first().' Filter tanggal diabaikan.';
            $startDate = null;
            $endDate = null;
        } elseif ($startDate && $endDate && $endDate < $startDate) {
            $filterWarning = 'Tanggal akhir lebih kecil dari tanggal awal. Filter tanggal diabaikan.';
            $endDate = null;
        }

        $withdrawals = Withdrawal::with(['rusun', 'items.item'])
            ->withCount('items')
            ->when(filled($search), function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->whereHas('items.item', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('item_code', 'like', "%{$search}%");
                    })
                        ->orWhere('taken_by', 'like', "%{$search}%");
                });
            })
            ->when($startDate, function ($query) use ($startDate) {
                return $query->whereDate('taken_at', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                return $query->whereDate('taken_at', '<=', $endDate);
            })
            ->orderBy('taken_at', 'desc')
            ->paginate(10)
            ->appends([
                'search' => $search,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        return view('inventory.withdrawals.index', compact(
            'withdrawals',
            'search',
            'startDate',
            'endDate',
            'filterWarning'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $items = Item::orderBy('item_code', 'asc')->get(); // Urut dari terkecil (BRG-001, BRG-002...)
        $rusuns = Rusun::orderBy('name')->get();

        // Pre-select barang dari tautan "Pengambilan Barang" di halaman detail (?item=id)
        $selectedItemId = request()->integer('item') ?: null;

        // Hanya barang yang masih punya stok boleh dipilih.
        $availableItems = $items->filter(fn ($item) => $item->stock > 0)->values();

        // JSON disusun di controller, bukan di Blade: helper @json() memotong
        // ekspresi pada tanda kurung pertama sehingga array multi-baris
        // di dalam closure akan terpotong dan membuat error parse.
        $itemsCatalogJson = json_encode(
            $availableItems->mapWithKeys(fn ($item) => [
                $item->id => [
                    'code' => $item->item_code,
                    'name' => $item->name,
                    'price' => (float) $item->unit_price,
                    'stock' => (int) $item->stock,
                    'unit' => $item->unit,
                ],
            ])->all(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $noStockCountJson = json_encode($items->count() - $availableItems->count());

        return view('inventory.withdrawals.create', compact('rusuns', 'selectedItemId', 'itemsCatalogJson', 'noStockCountJson'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.Withdrawal::MAX_ITEMS_PER_TRANSACTION],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Withdrawal::MAX_QUANTITY],
            'taken_by' => 'required|string|max:255',
            'rusun_id' => 'required|exists:rusun,id',
            'taken_at' => 'required|date|before_or_equal:now',
            'description' => 'nullable|string|max:60000',
        ], [
            'items.required' => 'Minimal satu barang harus dipilih.',
            'items.min' => 'Minimal satu barang harus dipilih.',
            'items.max' => 'Maksimal '.Withdrawal::MAX_ITEMS_PER_TRANSACTION.' jenis barang per transaksi.',
            'items.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
            'items.*.quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
            'quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
        ]);

        // Urutkan id barang sebelum locking: tanpa ini dua transaksi yang
        // mengunci barang sama dengan urutan berbeda bisa saling menunggu
        // (deadlock) sampai MySQL/MySQL-compatible membatalkan salah satunya.
        $itemIds = collect($validated['items'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        try {
            DB::transaction(function () use ($validated, $itemIds) {
                // Kunci semua baris item sekaligus, urut ascending.
                $items = Item::whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($items->count() !== $itemIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada barang yang tidak ditemukan. Muat ulang halaman dan coba lagi.',
                    ]);
                }

                $detailRows = [];
                $totalQuantity = 0;
                $totalValue = 0.0;

                foreach ($validated['items'] as $line) {
                    $item = $items->get((int) $line['item_id']);
                    $quantity = (int) $line['quantity'];

                    // Validasi ketersediaan stok (BR-10) di dalam transaksi
                    if ($quantity > $item->stock) {
                        throw ValidationException::withMessages([
                            'items' => "Stok {$item->name} tidak mencukupi. Stok tersedia hanya {$item->stock} {$item->unit}.",
                        ]);
                    }

                    $subtotal = $quantity * $item->unit_price;

                    $totalQuantity += $quantity;
                    $totalValue += $subtotal;

                    $detailRows[] = [
                        'item_id' => $item->id,
                        'quantity' => $quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $subtotal,
                    ];
                }

                // Cegah overflow kolom DECIMAL(15,2) pada total nilai.
                // Tanpa guard ini MySQL strict mode akan melempar QueryException (HTTP 500).
                if ($totalValue > Withdrawal::MAX_SUBTOTAL) {
                    throw ValidationException::withMessages([
                        'items' => 'Total nilai pengambilan melebihi batas maksimum yang bisa disimpan. '
                            .'Kurangi jumlah atau harga satuan barang.',
                    ]);
                }

                $withdrawal = Withdrawal::create([
                    'taken_by' => $validated['taken_by'],
                    'rusun_id' => $validated['rusun_id'],
                    'total_quantity' => $totalQuantity,
                    'total_value' => $totalValue,
                    'taken_at' => $validated['taken_at'],
                    'description' => $validated['description'] ?? null,
                ]);

                $withdrawal->items()->createMany($detailRows);

                // Reduce stock (BR-12) untuk semua barang dalam transaksi ini
                foreach ($detailRows as $row) {
                    $items->get($row['item_id'])->decrement('stock', $row['quantity']);
                }
            });
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()->route('inventory.withdrawals.index')
            ->with('success', 'Pengambilan barang berhasil disimpan dan stok telah dikurangi.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $withdrawal = Withdrawal::with(['rusun', 'items.item'])->findOrFail($id);

        return view('inventory.withdrawals.show', compact('withdrawal'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $withdrawal = Withdrawal::with(['rusun', 'items.item'])->findOrFail($id);
        
        $items = Item::orderBy('item_code', 'asc')->get();
        $rusuns = Rusun::orderBy('name')->get();

        // Untuk edit, barang yang sudah dipilih tetap bisa dipilih lagi meskipun stoknya 0
        // karena kita bisa mengurangi quantity atau menghapusnya
        $availableItems = $items->filter(function ($item) use ($withdrawal) {
            // Item tersedia jika: stok > 0 ATAU sudah ada di transaksi ini
            return $item->stock > 0 || $withdrawal->items->contains('item_id', $item->id);
        })->values();

        $itemsCatalogJson = json_encode(
            $availableItems->mapWithKeys(fn ($item) => [
                $item->id => [
                    'code' => $item->item_code,
                    'name' => $item->name,
                    'price' => (float) $item->unit_price,
                    'stock' => (int) $item->stock,
                    'unit' => $item->unit,
                ],
            ])->all(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $noStockCountJson = json_encode($items->count() - $availableItems->count());

        return view('inventory.withdrawals.edit', compact(
            'withdrawal',
            'rusuns',
            'itemsCatalogJson',
            'noStockCountJson'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $withdrawal = Withdrawal::with('items')->findOrFail($id);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.Withdrawal::MAX_ITEMS_PER_TRANSACTION],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Withdrawal::MAX_QUANTITY],
            'taken_by' => 'required|string|max:255',
            'rusun_id' => 'required|exists:rusun,id',
            'taken_at' => 'required|date|before_or_equal:now',
            'description' => 'nullable|string|max:60000',
        ], [
            'items.required' => 'Minimal satu barang harus dipilih.',
            'items.min' => 'Minimal satu barang harus dipilih.',
            'items.max' => 'Maksimal '.Withdrawal::MAX_ITEMS_PER_TRANSACTION.' jenis barang per transaksi.',
            'items.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
            'items.*.quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
            'quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
        ]);

        // Urutkan id barang untuk mencegah deadlock
        $itemIds = collect($validated['items'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        try {
            DB::transaction(function () use ($validated, $itemIds, $withdrawal) {
                // Kunci semua baris item sekaligus, urut ascending
                $items = Item::whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($items->count() !== $itemIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada barang yang tidak ditemukan. Muat ulang halaman dan coba lagi.',
                    ]);
                }

                // Ambil data lama untuk perbandingan stok
                $oldItems = $withdrawal->items->keyBy('item_id');

                $detailRows = [];
                $totalQuantity = 0;
                $totalValue = 0.0;

                // Map item_id => quantity baru untuk kemudahan lookup
                $newItemQuantities = collect($validated['items'])
                    ->mapWithKeys(fn ($line) => [(int) $line['item_id'] => (int) $line['quantity']]);

                // 1. Proses barang yang ada di data baru
                foreach ($validated['items'] as $line) {
                    $item = $items->get((int) $line['item_id']);
                    $newQuantity = (int) $line['quantity'];
                    $oldItem = $oldItems->get((int) $line['item_id']);

                    if ($oldItem) {
                        // Barang sudah ada di transaksi lama, hitung selisihnya
                        $oldQuantity = $oldItem->quantity;
                        $difference = $newQuantity - $oldQuantity;

                        if ($difference > 0) {
                            // Quantity bertambah → kurangi stok
                            if ($difference > $item->stock) {
                                throw ValidationException::withMessages([
                                    'items' => "Stok {$item->name} tidak mencukupi. Stok tersedia hanya {$item->stock} {$item->unit}.",
                                ]);
                            }
                            $item->decrement('stock', $difference);
                        } elseif ($difference < 0) {
                            // Quantity berkurang → kembalikan stok
                            $item->increment('stock', abs($difference));
                        }
                        // Jika difference == 0, tidak ada perubahan stok
                    } else {
                        // Barang baru ditambahkan ke transaksi
                        if ($newQuantity > $item->stock) {
                            throw ValidationException::withMessages([
                                'items' => "Stok {$item->name} tidak mencukupi. Stok tersedia hanya {$item->stock} {$item->unit}.",
                            ]);
                        }
                        $item->decrement('stock', $newQuantity);
                    }

                    $subtotal = $newQuantity * $item->unit_price;
                    $totalQuantity += $newQuantity;
                    $totalValue += $subtotal;

                    $detailRows[] = [
                        'item_id' => $item->id,
                        'quantity' => $newQuantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $subtotal,
                    ];
                }

                // 2. Barang yang dihapus dari transaksi → kembalikan stoknya
                foreach ($oldItems as $oldItem) {
                    if (! $newItemQuantities->has($oldItem->item_id)) {
                        // Barang ini ada di transaksi lama tapi tidak ada di data baru
                        $item = Item::lockForUpdate()->find($oldItem->item_id);
                        if ($item) {
                            $item->increment('stock', $oldItem->quantity);
                        }
                    }
                }

                // Cegah overflow kolom DECIMAL(15,2)
                if ($totalValue > Withdrawal::MAX_SUBTOTAL) {
                    throw ValidationException::withMessages([
                        'items' => 'Total nilai pengambilan melebihi batas maksimum yang bisa disimpan. '
                            .'Kurangi jumlah atau harga satuan barang.',
                    ]);
                }

                // Update data withdrawal
                $withdrawal->update([
                    'taken_by' => $validated['taken_by'],
                    'rusun_id' => $validated['rusun_id'],
                    'total_quantity' => $totalQuantity,
                    'total_value' => $totalValue,
                    'taken_at' => $validated['taken_at'],
                    'description' => $validated['description'] ?? null,
                ]);

                // Hapus semua withdrawal_items lama dan buat yang baru
                $withdrawal->items()->delete();
                $withdrawal->items()->createMany($detailRows);
            });
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->withErrors($e->errors());
        }

        return redirect()->route('inventory.withdrawals.index')
            ->with('success', 'Pengambilan barang berhasil diperbarui dan stok telah disesuaikan.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $withdrawal = Withdrawal::with('items.item')->findOrFail($id);

        try {
            DB::transaction(function () use ($withdrawal) {
                // Kembalikan semua stok barang yang diambil
                foreach ($withdrawal->items as $withdrawalItem) {
                    $item = Item::lockForUpdate()->find($withdrawalItem->item_id);
                    
                    if ($item) {
                        $item->increment('stock', $withdrawalItem->quantity);
                    }
                }

                // Hapus withdrawal items (atau bisa pakai cascade delete di migration)
                $withdrawal->items()->delete();
                
                // Hapus withdrawal
                $withdrawal->delete();
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus data pengambilan.');
        }

        return redirect()->route('inventory.withdrawals.index')
            ->with('success', 'Pengambilan barang berhasil dihapus dan stok telah dikembalikan.');
    }
}
