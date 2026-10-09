<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Environment variable untuk memaksa down() tetap menjatuhkan kolom.
     *
     * Hanya dibaca sekali, saat rollback benar-benar dijalankan, dan tidak
     * pernah dibaca pada request biasa — jadi tidak ada efek samping ke
     * aplikasi.
     */
    private const ENV_PAKSA = 'FORCE_DROP_FOTO_DISK';

    /**
     * Tabel yang menyimpan foto bukti.
     *
     * @var array<int, string>
     */
    private const TABEL = ['water_monitorings', 'finance_reports'];

    /**
     * Run the migrations.
     *
     * Menambahkan kolom foto_disk pada kedua tabel foto bukti.
     *
     * Default-nya 'public' dengan sengaja: seluruh baris yang sudah ada
     * tetap menunjuk disk publik sehingga aplikasi tidak langsung rusak
     * setelah migrasi ini. Foto baru disimpan ke disk privat 'evidence'.
     * Pemindahan file lama ke disk privat dilakukan terpisah melalui
     * perintah `php artisan evidence:rehome`.
     *
     * Keberadaan kolom per tabel dijaga supaya migrasi ini bisa dijalankan
     * ulang untuk menyelesaikan up() yang gagal di tengah. Tanpa itu, satu
     * kegagalan membuat migrasi tidak pernah bisa dilanjutkan.
     */
    public function up(): void
    {
        foreach (self::TABEL as $table) {
            if (Schema::hasColumn($table, 'foto_disk')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->string('foto_disk')->default('public')->after('foto');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Dijaga pada dua sisi:
     *
     * 1. Per tabel. Kalau up() berhenti di tengah, rollback tetap merapikan
     *    tabel yang sudah mendapat kolom dan melewati yang belum. Menjalankan
     *    down() dua kali pun tidak menghasilkan error.
     * 2. Terhadap data. Setelah `evidence:rehome` berjalan, file foto sudah
     *    tidak ada di disk publik sementara kolom foto_disk masih
     *    menunjuk ke sana. Menjatuhkan kolom membuat semua baris itu
     *    kembali menunjuk disk yang salah, dan karena informasinya hilang,
     *    `evidence:rehome` tidak lagi bisa memulihkannya.
     */
    public function down(): void
    {
        $adaKolom = array_values(array_filter(
            self::TABEL,
            static fn (string $table): bool => Schema::hasColumn($table, 'foto_disk')
        ));

        if ($adaKolom === []) {
            return;
        }

        $this->jagaFotoYangMasihHidup($adaKolom);

        foreach ($adaKolom as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('foto_disk');
            });
        }
    }

    /**
     * Tolak menjatuhkan kolom selama masih ada foto yang benar-benar hidup.
     *
     * Hanya tabel yang benar-benar punya kolom yang diperiksa. Melewati
     * tabel lain itu penting: bila up() gagal di tengah lalu penjaga
     * melakukan query ke tabel tanpa kolom foto_disk, MySQL melempar
     * Unknown column dan pesan aslinya hilang.
     *
     * @param  array<int, string>  $tables
     */
    private function jagaFotoYangMasihHidup(array $tables): void
    {
        if ($this->dropDipaksa()) {
            return;
        }

        foreach ($tables as $table) {
            $jumlah = DB::table($table)->where('foto_disk', '!=', 'public')->count();

            if ($jumlah === 0) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Migrasi dibatalkan: %d baris di tabel %s masih menunjuk foto ke disk privat. '
                .'Menjatuhkan kolom foto_disk akan membuat baris-baris itu kembali menunjuk disk publik, '
                .'padanya file-nya sudah dipindahkan. Foto tersebut lalu tidak bisa dibuka dan tidak '
                .'dapat dipulihkan lagi, karena informasi disknya sudah hilang. '
                .'Bila rollback memang disengaja, paksa dengan %s=1.',
                $jumlah,
                $table,
                self::ENV_PAKSA
            ));
        }
    }

    /**
     * Apakah operator memaksa kolom tetap dijatuhkan?
     *
     * env() diperiksa lebih dulu lalu getenv() sebagai cadangan. Keduanya
     * dibaca karena cara menyetel variabel berbeda antara terminal, cron,
     * dan supervisor; hanya membaca satu membuat ada cara setelan yang
     * terlihat berhasil tetapi diam-diam diabaikan.
     */
    private function dropDipaksa(): bool
    {
        return filter_var(
            env(self::ENV_PAKSA, getenv(self::ENV_PAKSA)),
            FILTER_VALIDATE_BOOLEAN
        );
    }
};
