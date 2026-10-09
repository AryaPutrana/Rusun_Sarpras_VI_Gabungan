# INVENTORY MATERIAL MODULE - QUICK FIX PATCHES

This file contains ready-to-apply fixes for the 3 critical bugs blocking the Inventory module.

## PATCH 1: Fix Relationship Path in WithdrawalController (BUG-INV-001)

**File:** `app/Http/Controllers/Inventory/WithdrawalController.php`

### Change 1 - Line 53 (index method):
```php
// BEFORE:
$withdrawals = Withdrawal::with(['rusun', 'inventory.items.item'])

// AFTER:
$withdrawals = Withdrawal::with(['rusun', 'items.item'])
```

### Change 2 - Line 56-60 (search whereHas):
```php
// BEFORE:
->where(function ($q) use ($search) {
    $q->whereHas('inventory.items.item', function ($q2) use ($search) {

// AFTER:
->where(function ($q) use ($search) {
    $q->whereHas('items.item', function ($q2) use ($search) {
```

### Change 3 - Line 236 (show method):
```php
// BEFORE:
$withdrawal = Withdrawal::with(['rusun', 'inventory.items.item'])->findOrFail($id);

// AFTER:
$withdrawal = Withdrawal::with(['rusun', 'items.item'])->findOrFail($id);
```

### Change 4 - Line 246 (edit method):
```php
// BEFORE:
$withdrawal = Withdrawal::with(['rusun', 'inventory.items.item'])->findOrFail($id);

// AFTER:
$withdrawal = Withdrawal::with(['rusun', 'items.item'])->findOrFail($id);
```

### Change 5 - Line 432 (destroy method):
```php
// BEFORE:
$withdrawal = Withdrawal::with('inventory.items.item')->findOrFail($id);

// AFTER:
$withdrawal = Withdrawal::with('items.item')->findOrFail($id);
```

---

## PATCH 2: Fix Validation Keys in WithdrawalController (BUG-INV-002)

**File:** `app/Http/Controllers/Inventory/WithdrawalController.php`

### Change 1 - Lines 128-142 (store validation):
```php
// BEFORE:
$validated = $request->validate([
    'items' => ['required', 'array', 'min:1', 'max:'.Withdrawal::MAX_ITEMS_PER_TRANSACTION],
    'inventory.items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
    'inventory.items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Withdrawal::MAX_QUANTITY],
    'taken_by' => 'required|string|max:255',
    'rusun_id' => 'required|exists:rusun,id',
    'taken_at' => 'required|date|before_or_equal:now',
    'description' => 'nullable|string|max:60000',
], [
    'inventory.items.required' => 'Minimal satu barang harus dipilih.',
    'inventory.items.min' => 'Minimal satu barang harus dipilih.',
    'inventory.items.max' => 'Maksimal '.Withdrawal::MAX_ITEMS_PER_TRANSACTION.' jenis barang per transaksi.',
    'inventory.items.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
    'inventory.items.*.quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
    'quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
]);

// AFTER:
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
]);
```

### Change 2 - Lines 288-303 (update validation):
```php
// BEFORE:
$validated = $request->validate([
    'items' => ['required', 'array', 'min:1', 'max:'.Withdrawal::MAX_ITEMS_PER_TRANSACTION],
    'inventory.items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
    'inventory.items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.Withdrawal::MAX_QUANTITY],
    'taken_by' => 'required|string|max:255',
    'rusun_id' => 'required|exists:rusun,id',
    'taken_at' => 'required|date|before_or_equal:now',
    'description' => 'nullable|string|max:60000',
], [
    'inventory.items.required' => 'Minimal satu barang harus dipilih.',
    'inventory.items.min' => 'Minimal satu barang harus dipilih.',
    'inventory.items.max' => 'Maksimal '.Withdrawal::MAX_ITEMS_PER_TRANSACTION.' jenis barang per transaksi.',
    'inventory.items.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
    'inventory.items.*.quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
    'quantity.max' => 'Jumlah pengambilan maksimal 1.000.000 unit per barang.',
]);

// AFTER:
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
]);
```

---

## PATCH 3: Fix SQL Table Names in ReportController (BUG-INV-003)

**File:** `app/Http/Controllers/Inventory/ReportController.php`

### Change 1 - Lines 35-39 (withdrawalDetailQuery method):
```php
// BEFORE:
return $query
    ->join('withdrawals', 'inventory.withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
    ->orderBy('inventory.withdrawals.taken_at', 'desc')
    ->orderBy('withdrawal_items.id', 'asc')
    ->select('withdrawal_items.*');

// AFTER:
return $query
    ->join('withdrawals', 'withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
    ->orderBy('withdrawals.taken_at', 'desc')
    ->orderBy('withdrawal_items.id', 'asc')
    ->select('withdrawal_items.*');
```

### Change 2 - Lines 50-56 (detailTotals method):
```php
// BEFORE:
$query = DB::table('withdrawal_items')
    ->join('withdrawals', 'inventory.withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
    ->whereBetween('inventory.withdrawals.taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

if ($rusunId && $rusunId != 'all') {
    $query->where('inventory.withdrawals.rusun_id', $rusunId);
}

// AFTER:
$query = DB::table('withdrawal_items')
    ->join('withdrawals', 'withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
    ->whereBetween('withdrawals.taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

if ($rusunId && $rusunId != 'all') {
    $query->where('withdrawals.rusun_id', $rusunId);
}
```

---

## VERIFICATION AFTER APPLYING PATCHES

After applying all 3 patches, test the following:

### Test 1: Withdrawals Index
```bash
# Navigate to: http://127.0.0.1:8000/inventory/withdrawals
# Should load without errors
```

### Test 2: Create Withdrawal
```bash
# Navigate to: http://127.0.0.1:8000/inventory/withdrawals/create
# Fill form and submit
# Should create withdrawal and reduce stock
```

### Test 3: Reports
```bash
# Navigate to: http://127.0.0.1:8000/inventory/reports
# Select date range and click "Tampilkan Laporan"
# Should display transactions
# Click "Export Excel" - should download CSV
# Click "Export PDF" - should download PDF
```

### Test 4: Validation
```bash
# Try to create withdrawal with quantity > stock
# Should show error: "Stok tidak mencukupi"

# Try to select same item twice
# Should merge quantities automatically
```

---

## AUTOMATED PATCH APPLICATION

To apply all patches automatically, run:

```bash
cd C:/Laravel/Gabungan_Sistem_Sarpras
```

Then use the patch tool or manually edit the files with the changes above.

---

## ROLLBACK PLAN

If patches cause issues, revert using Git:

```bash
git checkout app/Http/Controllers/Inventory/WithdrawalController.php
git checkout app/Http/Controllers/Inventory/ReportController.php
```

Or restore from backup before applying patches.
