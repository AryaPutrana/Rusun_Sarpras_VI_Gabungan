# 🔴 RENCANA PERBAIKAN - HIGH PRIORITY BUGS

## BUG #2: Race Condition pada Stock Validation

### LOKASI:
`app/Http/Controllers/Inventory/ItemController.php:118, 132`

### MASALAH:
```php
// Line 118: Load item TANPA lock
$item = Item::findOrFail($id);

// Line 132: Validasi menggunakan stock dari item yang di-load tanpa lock
'subtract_stock' => ['nullable', 'integer', 'min:0', 'max:' . $item->stock],

// Line 183: Baru lock di dalam transaction
$locked = Item::whereKey($id)->lockForUpdate()->firstOrFail();
```

**Skenario Race Condition:**
1. Request A: Load item (stock: 100) → validasi max:100 PASS
2. Request B: Lock & kurangi 60 unit → stock jadi 40
3. Request A: Lock & coba kurangi 70 unit → re-check FAIL tapi error message misleading

**Dampak:**
- Error message: "max 100" padahal stok aktual 40
- User bingung kenapa validation berbeda
- Re-check di dalam transaction (line 205) menangkap ini, tapi UX jelek

### SOLUSI:
**Opsi 1: Validate Inside Transaction (Recommended)**
- Pindahkan validasi stock ke dalam transaction setelah lock
- Custom validation exception dengan stock aktual

**Opsi 2: Lock Before Validation**
- Lock item sebelum validation
- Tapi ini berisiko: validation bisa lama, lock terlalu lama

**PILIHAN: Opsi 1** - Validasi dasar dulu, re-validate di dalam transaction dengan pesan jelas

---

## BUG #5: Deadlock Risk pada Withdrawal Update

### LOKASI:
`app/Http/Controllers/Inventory/WithdrawalController.php:313-317, 386-400`

### MASALAH:
```php
// Line 313-317: Lock items DENGAN ORDER ✅
$items = Item::whereIn('id', $itemIds)
    ->orderBy('id')
    ->lockForUpdate()
    ->get();

// Line 386-400: Lock items TANPA ORDER ❌
foreach ($oldItems as $oldItem) {
    if (! $newItemQuantities->has($oldItem->item_id)) {
        $item = Item::lockForUpdate()->find($oldItem->item_id);  // No ordering!
```

**Skenario Deadlock:**
```
Transaction A: Lock items [1, 2, 3] in order
Transaction B: Lock items [3, 2, 1] in different order
→ Deadlock possible!
```

**Dalam loop, urutan lock = urutan $oldItems (tidak guaranteed sorted)**

### SOLUSI:
Collect semua item_id yang perlu di-lock, sort, then lock batch:

```php
// Collect all item IDs that need locking
$allItemIds = collect($validated['items'])->pluck('item_id')
    ->merge($oldItems->pluck('item_id'))
    ->unique()
    ->sort()
    ->values();

// Lock all at once in sorted order
$items = Item::whereIn('id', $allItemIds)
    ->orderBy('id')
    ->lockForUpdate()
    ->get()
    ->keyBy('id');

// Then use from collection, no more individual locks
```

---

## BUG #6: Stock Mode Default Tidak Aman

### LOKASI:
`app/Http/Controllers/Inventory/ItemController.php:148`

### MASALAH:
```php
$stockMode = $validated['stock_mode'] ?? 'adjustment';
```

**Skenario:**
1. User disable JavaScript
2. Form tidak kirim `stock_mode` field
3. Default ke 'adjustment'
4. Tapi user isi field `new_stock` (mode direct)
5. Field `new_stock` diabaikan karena mode = adjustment
6. Stock tidak update!

### SOLUSI:
**Opsi 1: Required validation**
```php
'stock_mode' => ['required', 'in:adjustment,direct'],
```
Tapi ini break backward compatibility.

**Opsi 2: Smart detection**
```php
// Detect mode dari field yang diisi
if (!isset($validated['stock_mode'])) {
    // Auto-detect dari field yang ada
    $hasAdjustmentFields = !empty($validated['add_stock']) || !empty($validated['subtract_stock']);
    $hasDirectFields = isset($validated['new_stock']) && $validated['new_stock'] != $item->stock;
    
    if ($hasDirectFields && !$hasAdjustmentFields) {
        $stockMode = 'direct';
    } else {
        $stockMode = 'adjustment';  // Default aman
    }
} else {
    $stockMode = $validated['stock_mode'];
}
```

**PILIHAN: Opsi 2** - Smart detection dengan fallback aman

---

## PRIORITAS EKSEKUSI:
1. **BUG #5** - Paling critical (deadlock = production down)
2. **BUG #2** - Important (UX issue)
3. **BUG #6** - Medium (edge case)
