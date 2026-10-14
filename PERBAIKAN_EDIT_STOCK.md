# Perbaikan Fitur Edit Stock Barang
Tanggal: 2026-10-13

## Masalah
Form edit barang memiliki 2 mode update stok:
1. Mode Adjustment (Tambah/Kurangi) - BERFUNGSI ✓
2. Mode Edit Langsung - TIDAK BERFUNGSI ✗

User tidak bisa mengupdate stock dengan mode edit langsung, hanya bisa mengurangi stock.

## Penyebab
Controller ItemController::update() hanya memproses field `add_stock` dan `subtract_stock`, tidak memproses field `new_stock` dan `stock_change_reason` dari Mode Edit Langsung.

## Solusi
### 1. Update Validasi (Baris 120-144)
Tambahkan validasi untuk Mode Edit Langsung:
- `new_stock`: integer, min:0, max:MAX_STOCK
- `stock_change_reason`: required_with:new_stock, max:255

### 2. Update Logic Transaction (Baris 194-214)
Tambahkan logic untuk memproses Mode Edit Langsung:
- Deteksi jika `new_stock` berbeda dari stock saat ini
- Update stock langsung dengan nilai baru
- Hitung difference (new - old)
- Simpan audit trail ke stock_adjustments dengan type 'add' atau 'subtract'

## Fitur Setelah Perbaikan
### Mode 1: Adjustment (Sudah Ada)
- Tambah Stock: tambah dari stok saat ini
- Kurangi Stock: kurangi dari stok saat ini (dengan alasan)

### Mode 2: Direct Edit (BARU BERFUNGSI)
- Edit langsung: ganti stok dengan angka baru
- Wajib isi alasan perubahan
- Audit trail otomatis (add/subtract sesuai perubahan)

## Testing
✓ Syntax check: No errors
✓ Laravel boot: OK
✓ Route exists: inventory.items.update
✓ Cache cleared: config, cache, view

## Files Modified
- app/Http/Controllers/Inventory/ItemController.php
  - Method: update() baris 116-223
