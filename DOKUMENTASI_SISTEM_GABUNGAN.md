# Sistem Informasi Sarpras UPRS VI - DOKUMENTASI LENGKAP

Sistem informasi terpadu untuk Divisi Sarana dan Prasarana UPRS VI yang menggabungkan:
- **Monitoring GWT & Kolam Air Bersih** - Pemeriksaan harian dan laporan bulanan kondisi air
- **Inventory Material** - Manajemen inventory barang dan pengambilan material untuk 6 rusun

## 🎯 Fitur Utama

### 🔵 Monitoring Air (GWT)
- **Pemeriksaan Harian**: Input kondisi air 3x sehari (pagi/siang/sore)
- **Laporan Bulanan**: Input kondisi keuangan/operasional bulanan
- **Dashboard Real-time**: Statistik dan chart kondisi air per lokasi
- **Role-based Access**: Admin (kelola semua) & Petugas (input & lihat data sendiri)

### 📦 Inventory Material
- **Data Barang**: CRUD inventory dengan foto, stok, harga satuan
- **Pengambilan Barang**: Transaksi pengambilan multi-item per rusun
- **Laporan Pengambilan**: Filter tanggal & rusun, export PDF/Excel
- **Stock Adjustments**: Audit trail perubahan stok manual
- **Alert Stok Menipis**: Monitoring barang yang perlu restock

## 💻 Tech Stack

- Laravel 10.50.3
- PHP 8.1+
- MySQL/MariaDB
- Bootstrap 5 + Chart.js
- DOMPDF untuk export PDF

## 📁 Struktur Database

**Total: 13 tabel aktif**

### GWT (8 tabel)
- users, water_monitorings, finance_reports
- monitoring_locations, finance_locations

### Inventory (5 tabel)
- items, withdrawals, withdrawal_items
- rusuns, stock_adjustments

## 🚀 Instalasi Cepat

```bash
# 1. Install dependencies
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi .env
DB_DATABASE=gabungan_sistem_sarpras

# 4. Migrate & seed
php artisan migrate
php artisan db:seed

# 5. Storage link
php artisan storage:link

# 6. Run server
php artisan serve
```

## 🔐 Login Credentials

**Admin**: admin@sarpras.com / password123  
**Petugas**: petugas@sarpras.com / password123

⚠️ Ganti password setelah login pertama!

## 📂 Struktur Menu

### Admin
- Dashboard (GWT + Inventory stats)
- **Monitoring Harian** ▼
  - Lokasi Monitoring
  - Histori Monitoring
- **Laporan Bulanan** ▼
  - Lokasi Bulanan
  - Laporan Bulanan
- **Inventory Material** ▼
  - Data Barang
  - Pengambilan Barang
  - Laporan Pengambilan
- Kelola Pengguna

### Petugas
- Dashboard
- **Monitoring Harian** ▼
  - Input Monitoring
  - Histori Monitoring
- **Laporan Bulanan** ▼
  - Input Bulanan
  - Riwayat Bulanan
- **Inventory Material** ▼
  - Data Barang
  - Pengambilan Barang
  - Laporan Pengambilan

## 📸 Storage Folders

```
storage/app/public/
├── monitoring/    # Foto pemeriksaan air
├── keuangan/      # Foto laporan bulanan
└── items/         # Foto barang inventory
```

## 🛡️ Keamanan

✅ Role-based authorization (Admin/Petugas)  
✅ CSRF protection  
✅ File upload validation  
✅ Rate limiting (login & export)  
✅ Foreign key constraints  
✅ Private photo storage  

## 🔧 Troubleshooting

```bash
# Reset database
php artisan migrate:fresh --seed

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Autoload
composer dump-autoload
```

## 📊 Routes Summary

- **GWT**: `/monitoring`, `/keuangan`, `/lokasi`
- **Inventory**: `/inventory/items`, `/inventory/withdrawals`, `/inventory/reports`
- **Admin**: `/pengguna`, `/lokasi-bulanan`

---

**Version**: 1.0.0 (Integrated System)  
**Date**: October 9, 2026  
**Developer**: Sistem Sarpras UPRS VI
