# Test Manual Login - Fix Error 419

## Root Cause Analysis
Error 419 "Page Expired" terjadi karena:
1. ✅ Route cache lama masih aktif (sudah di-clear)
2. ✅ Config cache lama (sudah di-rebuild)
3. ✅ Session directory permissions (sudah diperbaiki)

## Langkah Yang Sudah Dilakukan

### 1. Clear ALL Caches
```bash
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 2. Remove Old Route Cache
```bash
rm -f bootstrap/cache/routes-v7.php
```

### 3. Fix Session Permissions
```bash
chmod -R 777 storage/framework/sessions
```

### 4. Rebuild Config Cache
```bash
php artisan config:cache
```

## Status Sekarang
- ✅ Config: CACHED
- ✅ Routes: NOT CACHED (fresh)
- ✅ Views: NOT CACHED (fresh)
- ✅ Session directory: Writable (413 files)
- ✅ APP_KEY: Present
- ✅ CSRF Middleware: Active

## Cara Test Login

### PENTING: Restart Web Server!
Setelah perubahan ini, **WAJIB restart web server** Anda:

**Jika menggunakan Laragon:**
1. Stop Laragon
2. Start Laragon lagi

**Jika menggunakan php artisan serve:**
```bash
# Tekan CTRL+C untuk stop
php artisan serve
```

**Jika menggunakan Apache/Nginx:**
```bash
# Restart service
```

### Test Login
1. Buka browser **BARU** atau **Incognito Mode**
2. Clear browser cache: CTRL+SHIFT+DELETE
3. Buka: http://localhost/login
4. Masukkan kredensial:
   - Email: petugas@sarpras.com
   - Password: password123
5. Klik Login

## Expected Result
✅ Login berhasil redirect ke dashboard
✅ Tidak ada error 419

## If Still Error 419
Jalankan command ini sekali lagi:
```bash
php artisan config:clear
php artisan cache:clear
```

Kemudian **RESTART BROWSER** sepenuhnya (tutup semua window).

---

**Note**: Error 419 biasanya terjadi karena:
- Browser masih menyimpan form token lama
- Session expired (default 120 menit)
- Server cache tidak ter-refresh

**Solusi terbaik**: 
1. Restart web server
2. Open new incognito window
3. Try login again
