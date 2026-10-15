# RENCANA PERBAIKAN BUG #3
## Fix Photo Check Logic di destroy() Method

### MASALAH SAAT INI:
```php
DB::transaction(function () use ($item, $photoPath) {
    $item->delete();  // Line 310
    
    if ($photoPath) {
        $stillUsed = Item::where('photo', $photoPath)
            ->where('id', '!=', $item->id)  // Confusing: item sudah deleted
            ->exists();
```

**Kenapa ini masalah:**
1. Query check `where('id', '!=', $item->id)` SETELAH item deleted membingungkan
2. Meskipun masih berfungsi, logic-nya tidak jelas
3. Item sudah tidak ada di database, tapi kita masih exclude-nya

### SOLUSI YANG BENAR:

**Opsi 1: Check SEBELUM delete (Recommended)**
```php
$itemId = $item->id;  // Capture ID dulu

DB::transaction(function () use ($item, $photoPath, $itemId) {
    // Check SEBELUM delete: cari item lain yang pakai foto sama
    $otherItemsUsingPhoto = 0;
    if ($photoPath) {
        $otherItemsUsingPhoto = Item::where('photo', $photoPath)
            ->where('id', '!=', $itemId)
            ->count();
    }
    
    $item->delete();
    
    // Hapus foto hanya jika tidak ada item lain yang pakai
    if ($photoPath && $otherItemsUsingPhoto === 0) {
        Storage::delete('public/items/'.$photoPath);
        Cache::forget('item-thumb:'.sha1($photoPath.'|44|v1'));
    }
});
```

**Keuntungan:**
- Logic jelas: check dulu, baru delete
- Tidak ada query ke deleted record
- Cache cleanup di dalam transaction (aman karena Cache::forget idempotent)

**Opsi 2: Check SETELAH delete tanpa exclude**
```php
DB::transaction(function () use ($item, $photoPath) {
    $item->delete();
    
    if ($photoPath) {
        // Karena item sudah deleted, cukup cek apakah ada item dengan foto ini
        $stillUsed = Item::where('photo', $photoPath)->exists();
        
        if (!$stillUsed) {
            Storage::delete('public/items/'.$photoPath);
            Cache::forget('item-thumb:'.sha1($photoPath.'|44|v1'));
        }
    }
});
```

**Saya pilih Opsi 2** karena:
1. Lebih simple (tidak perlu capture ID)
2. Query lebih efisien (tidak perlu where id !=)
3. Logic jelas: "setelah delete, apakah masih ada item dengan foto ini?"

### IMPLEMENTASI:
File: `app/Http/Controllers/Inventory/ItemController.php`
Method: `destroy()` (line 296-329)
