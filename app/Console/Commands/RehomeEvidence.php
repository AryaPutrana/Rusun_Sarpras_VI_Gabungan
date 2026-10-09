<?php

namespace App\Console\Commands;

use App\Support\PhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Memindahkan foto bukti lama dari disk publik ke disk privat.
 *
 * Tanpa --apply perintah ini hanya melaporkan rencana dan tidak mengubah
 * file maupun database. Foto yang sudah berada di disk 'evidence'
 * dilewati.
 *
 * Perintah ini dirancang untuk boleh dijalankan berulang kali. Nilai
 * --apply boleh terputus kapan saja (proses dibunuh, server restart,
 * koneksi database putus), jadi setiap keadaan sisa harus bisa dikenali
 * dan diselesaikan pada eksekusi berikutnya, bukan dilaporkan sekali lalu
 * dibiarkan menggantung. Daftar ketidakkonsistenan yang ditangani ada di
 * tanganiRecord() dan lanjutkanPindah().
 */
class RehomeEvidence extends Command
{
    protected $signature = 'evidence:rehome
                            {--apply : Jalankan perpindahan file dan update database. Tanpa flag ini hanya simulasi.}';

    protected $description = 'Pindahkan foto bukti dari disk publik ke disk privat (default simulasi).';

    /**
     * Status yang dikembalikan oleh metode pemrosesan satu record.
     */
    public const STATUS_DIPINDAH = 'dipindah';

    public const STATUS_DIPULIHKAN = 'dipulihkan';

    public const STATUS_AKAN_DIPULIHKAN = 'akan-dipulihkan';

    public const STATUS_TERSISA = 'tersisa';

    public const STATUS_HILANG = 'hilang';

    public const STATUS_KONFLIK = 'konflik';

    public const STATUS_GAGAL = 'gagal';

    /**
     * Pemetaan status ke jumlah pada ringkasan.
     *
     * Dipisah dari logika cabang supaya penghitungan tidak pernah meleset
     * saat cabang baru ditambahkan.
     *
     * @var array<string, string>
     */
    protected const PETA_STATUS = [
        self::STATUS_DIPINDAH => 'pindah',
        self::STATUS_DIPULIHKAN => 'dipulihkan',
        self::STATUS_AKAN_DIPULIHKAN => 'dipulihkan',
        self::STATUS_TERSISA => 'tersisa',
        self::STATUS_HILANG => 'hilang',
        self::STATUS_KONFLIK => 'konflik',
        self::STATUS_GAGAL => 'gagal',
    ];

    /**
     * Jumlah baris yang diambil per satu kali query.
     *
     * Record diproses satu per satu karena tiap baris memicu pemeriksaan
     * keberadaan file di disk. Memuat seluruh tabel sekaligus membuat
     * pemakaian memori bertambah seiring jumlah record, padahal yang perlu
     * disimpan hanya potongan yang sedang diproses dan hitungan akumulator.
     */
    public const UKURAN_POTONGAN = 200;

    /**
     * Tabel yang menyimpan foto bukti.
     *
     * @var array<int, string>
     */
    protected array $tables = ['water_monitorings', 'finance_reports'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? '<fg=yellow>MODE: APLIKASI — file akan dipindah dan database diperbarui.</>'
            : '<fg=cyan>MODE: SIMULASI (dry-run) — tidak ada yang diubah. Tambahkan --apply untuk eksekusi.</>');
        $this->newLine();

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'foto_disk')) {
                $this->error("Kolom foto_disk belum ada di tabel {$table}.");
                $this->line('Jalankan <fg=cyan>php artisan migrate</> terlebih dahulu, lalu ulangi perintah ini.');

                return self::FAILURE;
            }
        }

        $public = Storage::disk(PhotoStorage::LEGACY_PUBLIC);
        $evidence = Storage::disk(PhotoStorage::EVIDENCE);

        $total = [
            'pindah' => 0, 'dipulihkan' => 0, 'sudah' => 0,
            'hilang' => 0, 'konflik' => 0, 'gagal' => 0, 'tersisa' => 0,
        ];

        foreach ($this->tables as $table) {
            $this->line("<fg=white>[{$table}]</>");

            $sudah = DB::table($table)
                ->where('foto_disk', PhotoStorage::EVIDENCE)
                ->count();
            $total['sudah'] += $sudah;

            if ($sudah > 0) {
                $this->line("  <fg=green>{$sudah} record sudah berada di disk privat.</>");
            }

            // Record yang kolomnya sudah menunjuk ke disk privat TETAP perlu
            // diperiksa. Eksekusi sebelumnya bisa mati setelah update database
            // tetapi sebelum file lama dihapus, sehingga fotonya masih bisa
            // diakses lewat /storage. Karena baris seperti ini tidak ikut
            // diambil pada langkah kedua di bawah, tanpa pemeriksaan eksplisit
            // file yang terekspos itu akan lolos dari laporan selamanya.
            //
            // Diambil perPotongan, bukan sekaligus. Tiap baris memicu
            // pemeriksaan keberadaan file di disk, jadi memuat seluruh tabel
            // sekaligus boros memori proportional terhadap jumlah record.
            DB::table($table)
                ->where('foto_disk', PhotoStorage::EVIDENCE)
                ->select(['id', 'foto'])
                ->chunkById(self::UKURAN_POTONGAN, function ($rows) use ($public, $evidence, $apply, &$total) {
                    foreach ($rows as $row) {
                        $path = PhotoStorage::safePath($row->foto);

                        if ($path === null) {
                            // Path rusak pada record yang sudah menunjuk ke
                            // disk privat sama berbahaya dengan path rusak
                            // pada record yang belum dipindah: operator
                            // mengira fotonya sudah aman, padahal tidak ada
                            // yang bisa dibuka. Diam-diam dilewati di sini
                            // membuat laporan undercount.
                            $total['gagal']++;
                            $this->line("  <fg=red>id {$row->id}: path foto tidak valid, dilewati.</>");

                            continue;
                        }

                        if (! PhotoStorage::existsQuietly($public, $path)) {
                            continue;
                        }

                        $status = $this->lanjutkanPindah($row->id, $path, $public, $evidence, $apply);
                        $total[self::PETA_STATUS[$status]]++;
                    }
                });

            DB::table($table)
                ->where('foto_disk', '!=', PhotoStorage::EVIDENCE)
                ->select(['id', 'foto', 'foto_disk'])
                ->chunkById(self::UKURAN_POTONGAN, function ($rows) use ($table, $public, $evidence, $apply, &$total) {
                    foreach ($rows as $row) {
                        $path = PhotoStorage::safePath($row->foto);

                        if ($path === null) {
                            $total['gagal']++;
                            $this->line("  <fg=red>id {$row->id}: path foto tidak valid, dilewati.</>");

                            continue;
                        }

                        $adaPublik = PhotoStorage::existsQuietly($public, $path);
                        $adaPrivat = PhotoStorage::existsQuietly($evidence, $path);

                        if (! $apply) {
                            $this->ringkasanRencana($row->id, $path, $adaPublik, $adaPrivat, $public, $evidence, $total);

                            continue;
                        }

                        $status = $this->tanganiRecord($table, $row->id, $path, $public, $evidence, $adaPublik, $adaPrivat);
                        $total[self::PETA_STATUS[$status]]++;
                    }
                });

            $this->newLine();
        }

        $this->line('<fg=white>Ringkasan:</>');
        $this->line("  dipindahkan                     : {$total['pindah']}");
        $this->line("  dipulihkan dari sisa eksekusi   : {$total['dipulihkan']}");
        $this->line("  sudah di disk privat            : {$total['sudah']}");
        $this->line("  file hilang                     : {$total['hilang']}");
        $this->line("  bentrok / perlu ditinjau        : {$total['konflik']}");
        $this->line("  gagal / tidak valid             : {$total['gagal']}");
        $this->line("  masih terekspos di publik       : {$total['tersisa']}");

        if (! $apply && $total['pindah'] > 0) {
            $this->newLine();
            $this->line('<fg=cyan>Simulasi selesai. Jalankan: php artisan evidence:rehome --apply</>');
        }

        if (! $apply && $total['dipulihkan'] > 0) {
            $this->newLine();
            $this->line('<fg=cyan>Ada sisa eksekusi sebelumnya yang akan diselesaikan. Jalankan: php artisan evidence:rehome --apply</>');
        }

        // File hilang, bentrok, gagal diproses, atau file lama yang masih
        // tertinggal di disk publik semuanya berarti ada yang perlu
        // ditindaklanjuti operator, jadi kode keluar ditandai gagal meski
        // perintah tidak fatal.
        $perluTindakLanjut = $total['gagal'] + $total['hilang']
            + $total['konflik'] + $total['tersisa'];

        return $perluTindakLanjut > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Tentukan tindakan untuk satu record yang kolomnya belum menunjuk ke
     * disk privat.
     *
     * Kombinasi keberadaan file di dua disk menentukan tindakan, dan semua
     * kombinasi yang mungkin harus punya cabang di sini.
     *
     * @param  bool  $adaPublik  file masih ada di disk publik
     * @param  bool  $adaPrivat  file sudah ada di disk privat
     */
    protected function tanganiRecord(string $table, int $id, string $path, $public, $evidence, bool $adaPublik, bool $adaPrivat): string
    {
        // Tidak ada di mana pun: tidak ada yang bisa dipulihkan. Bukan
        // kegagalan perintah, tapi tetap harus terlihat oleh operator.
        if (! $adaPublik && ! $adaPrivat) {
            $this->line("  <fg=yellow>id {$id}: file '{$path}' tidak ada di disk publik, dilewati.</>");

            return self::STATUS_HILANG;
        }

        // Hanya ada di disk privat. Dua kemungkinan asal-usulnya: eksekusi
        // sebelumnya mati setelah menyalin tetapi sebelum update database,
        // atau file publik ikut terhapus karena path-nya dipakai record lain.
        // Pada keduanya file sudah benar dan yang salah hanya kolomnya, jadi
        // tidak ada file yang perlu dihapus.
        if (! $adaPublik && $adaPrivat) {
            $this->line("  id {$id}: file '{$path}' sudah benar di disk privat, hanya kolom foto_disk yang tertinggal.");

            return $this->perbaikiKolom($table, $id, $path, $public, hapusPublik: false);
        }

        // Ada di kedua disk. Salinan privat baru boleh dipercaya setelah
        // isinya dipastikan sama dengan file publik yang masih utuh; kalau
        // tidak, file publik justru satu-satunya versi yang benar dan tidak
        // boleh disentuh.
        if ($adaPublik && $adaPrivat) {
            if (! PhotoStorage::isVerifiedCopy($public, $path, $evidence, $path)) {
                $this->line("  <fg=yellow>id {$id}: '{$path}' sudah ada di disk privat tetapi isinya berbeda dari disk publik, kedua file dipertahankan dan dilewati. Periksa manual.</>");

                return self::STATUS_KONFLIK;
            }

            $this->line("  id {$id}: salinan di disk privat sudah sama persis dengan '{$path}', dilanjutkan dari titik yang terputus.");

            return $this->perbaikiKolom($table, $id, $path, $public, hapusPublik: true);
        }

        return $this->moveOne($table, $id, $path, $public, $evidence);
    }

    /**
     * Selesaikan perpindahan yang sudah sebagian selesai: arahkan kolom ke
     * disk privat lalu, bila diminta, hapus duplikatnya di disk publik.
     *
     * Tidak ada penulisan file di sini, jadi kegagalan update database
     * tidak meninggalkan apa pun untuk dibatalkan — file publik tetap utuh
     * dan tetap menjadi satu-satunya sumber.
     */
    protected function perbaikiKolom(string $table, int $id, string $path, $public, bool $hapusPublik): string
    {
        try {
            DB::table($table)->where('id', $id)->update(['foto_disk' => PhotoStorage::EVIDENCE]);
        } catch (Throwable $exception) {
            $this->line("  <fg=red>id {$id}: update database gagal ({$exception->getMessage()})</>");

            return self::STATUS_GAGAL;
        }

        if (! $hapusPublik) {
            $this->line("  <fg=green>id {$id}: kolom foto_disk diperbaiki, '{$path}'</>");

            return self::STATUS_DIPULIHKAN;
        }

        if (! PhotoStorage::deleteQuietly($public, $path)) {
            $this->line("  <fg=yellow>id {$id}: database diperbarui tetapi file lama masih terekspos di disk publik.</>");

            return self::STATUS_TERSISA;
        }

        $this->line("  <fg=green>id {$id}: dipulihkan '{$path}'</>");

        return self::STATUS_DIPULIHKAN;
    }

    /**
     * Bersihkan file lama yang masih tertinggal di disk publik untuk record
     * yang kolomnya sudah menunjuk ke disk privat.
     *
     * Keadaan ini hanya terjadi kalau eksekusi sebelumnya mati tepat di
     * antara update database dan penghapusan file publik. Karena kolomnya
     * sudah benar, file publik yang tersisa adalah duplikat — tapi hanya
     * asalkan duplikat itu memang sama isinya. Kalau file privat hilang
     * atau isinya berbeda, file publik justru satu-satunya salinan dan
     * harus dipertahankan.
     */
    protected function lanjutkanPindah(int $id, string $path, $public, $evidence, bool $apply): string
    {
        if (! PhotoStorage::existsQuietly($evidence, $path)) {
            $this->line("  <fg=red>id {$id}: database menunjuk ke disk privat tapi '<{$path}>' tidak ada di sana; file yang masih ada di disk publik dipertahankan sebagai satu-satunya salinan.</>");

            return self::STATUS_KONFLIK;
        }

        if (! PhotoStorage::isVerifiedCopy($public, $path, $evidence, $path)) {
            $this->line("  <fg=red>id {$id}: '<{$path}>' masih ada di disk publik tapi salinan di disk privat tidak dapat dipastikan sama, file publik tidak dihapus. Periksa manual.</>");

            return self::STATUS_KONFLIK;
        }

        if (! $apply) {
            $this->line("  id {$id}: duplikat '<{$path}>' akan dihapus dari disk publik");

            return self::STATUS_AKAN_DIPULIHKAN;
        }

        if (! PhotoStorage::deleteQuietly($public, $path)) {
            $this->line("  <fg=yellow>id {$id}: '<{$path}>' masih terekspos di disk publik.</>");

            return self::STATUS_TERSISA;
        }

        $this->line("  <fg=green>id {$id}: duplikat di disk publik dihapus '<{$path}>'</>");

        return self::STATUS_DIPULIHKAN;
    }

    /**
     * Laporan dry-run untuk record yang kolomnya belum menunjuk ke disk
     * privat.
     *
     * Klasifikasi di sini harus memakai urutan kondisi yang sama persis
     * dengan tanganiRecord(). Bedanya hanya pada hasil: simulasi berhenti
     * setelah mencetak rencana. Test
     * test_dry_run_classifies_exactly_like_apply_menjaga keduanya tidak
     * melenceng.
     */
    protected function ringkasanRencana(int $id, string $path, bool $adaPublik, bool $adaPrivat, $public, $evidence, array &$total): void
    {
        if (! $adaPublik && ! $adaPrivat) {
            $total['hilang']++;
            $this->line("  <fg=yellow>id {$id}: file '{$path}' tidak ada di disk publik, dilewati.</>");

            return;
        }

        if (! $adaPublik && $adaPrivat) {
            $total['dipulihkan']++;
            $this->line("  id {$id}: kolom foto_disk akan diperbaiki, '{$path}'");

            return;
        }

        if ($adaPublik && $adaPrivat) {
            if (PhotoStorage::isVerifiedCopy($public, $path, $evidence, $path)) {
                $total['dipulihkan']++;
                $this->line("  id {$id}: akan dipulihkan, salinan di disk privat sudah sama persis dengan '{$path}'");
            } else {
                $total['konflik']++;
                $this->line("  <fg=yellow>id {$id}: '{$path}' sudah ada di disk privat dengan isi berbeda, dilewati.</>");
            }

            return;
        }

        $total['pindah']++;
        $this->line("  id {$id}: akan dipindah '{$path}'");
    }

    /**
     * Pindahkan satu file lalu perbarui kolom foto_disk.
     *
     * Urutan: salin ke disk privat -> pastikan salinan utuh -> update
     * database -> hapus dari disk publik. Bila update database gagal,
     * salinan di disk privat dihapus kembali sehingga file lama tetap utuh
     * di disk publik.
     *
     * Langkah "pastikan salinan utuh" itu wajib, bukan sekadar pemeriksaan
     * tambahan. exists() yang benar tidak berarti isinya berhasil disalin:
     * penulisan bisa terputus di tengah dan put() tetap mengembalikan
     * berhasil. Tanpa verifikasi, database menunjuk ke salinan terpotong
     * sementara file publik yang utuh ikut terhapus, sehingga foto yang
     * tersisa hanya cuplikan tidak berguna. isVerifiedCopy() menangkapnya
     * lewat perbandingan ukuran lalu sha256.
     *
     * Null dari get() ditangani eksplisit untuk alasan yang lebih sempit:
     * disk 'public' dikonfigurasi 'throw' => false, jadi file yang tidak
     * terbaca menghasilkan null dan bukan exception. Tanpa penanganan ini
     * operator melihat pesan TypeError dari Flysystem yang tidak
     * menjelaskan penyebabnya, dan tidak ada catatan bahwa file itu gagal
     * dibaca.
     *
     * Mengembalikan salah satu STATUS_* di atas. STATUS_TERSISA berarti
     * database sudah diperbarui tetapi file lama belum terhapus dari disk
     * publik, sehingga foto itu masih bisa diakses lewat /storage dan wajib
     * dilaporkan ke operator.
     */
    protected function moveOne(string $table, int $id, string $path, $public, $evidence): string
    {
        try {
            // Disk 'public' ber-'throw' => false, jadi file yang tidak
            // terbaca menghasilkan null dan bukan exception. Ditolak di sini
            // supaya operator mendapat penyebab yang jelas, bukan pesan
            // TypeError dari Flysystem yang tidak menyebut path fotonya.
            $isi = $public->get($path);

            if ($isi === null) {
                throw new RuntimeException("file '{$path}' tidak dapat dibaca dari disk publik");
            }

            $evidence->put($path, $isi);
        } catch (Throwable $exception) {
            $this->line("  <fg=red>id {$id}: gagal menyalin ke disk privat ({$exception->getMessage()})</>");

            return self::STATUS_GAGAL;
        }

        // Salinan sudah ada di disk privat, tapi keberadaannya belum berarti
        // isinya utuh: penulisan bisa terputus di tengah dan put() tetap
        // mengembalikan berhasil. File publik masih utuh pada titik ini, jadi
        // selama salinan privat tidak bisa dipastikan sama, file publik tidak
        // boleh dihapus.
        if (! PhotoStorage::isVerifiedCopy($public, $path, $evidence, $path)) {
            $this->line("  <fg=red>id {$id}: salinan di disk privat tidak dapat dipastikan utuh, file publik '<{$path}>' dipertahankan sebagai satu-satunya salinan.</>");

            $sisa = $this->hapusSalinanPrivat($path, $evidence);

            if ($sisa) {
                $this->line("  <fg=red>id {$id}: salinan di disk privat tidak bisa dihapus, '<{$path}>' tertinggal di sana (jalankan ulang perintah ini setelah masalahnya diperbaiki).</>");
            }

            return self::STATUS_GAGAL;
        }

        try {
            DB::table($table)->where('id', $id)->update(['foto_disk' => PhotoStorage::EVIDENCE]);
        } catch (Throwable $exception) {
            // Salinan di disk privat harus dihapus kembali supaya file lama
            // di disk publik tetap menjadi satu-satunya sumber. Kegagalan
            // delete di sini TIDAK boleh dilempar: exception kedua akan
            // menimpa pesan kegagalan database yang sebenarnya, dan sisa
            // file di disk privat akan lolos dari laporan.
            $sisa = $this->hapusSalinanPrivat($path, $evidence);

            if ($sisa) {
                $this->line("  <fg=red>id {$id}: update database gagal dan salinan di disk privat tidak bisa dihapus, '<{$path}>' tertinggal di sana (jalankan ulang perintah ini setelah masalahnya diperbaiki).</>");

                return self::STATUS_GAGAL;
            }

            $this->line("  <fg=red>id {$id}: update database gagal, file dikembalikan ({$exception->getMessage()})</>");

            return self::STATUS_GAGAL;
        }

        // Disk yang dikonfigurasi dengan 'throw' => true akan melempar
        // exception alih-alih mengembalikan false, jadi keduanya harus
        // ditangani agar file yang masih terekspos tidak lolos dari laporan.
        if (! PhotoStorage::deleteQuietly($public, $path)) {
            $this->line("  <fg=yellow>id {$id}: database diperbarui tetapi file lama masih terekspos di disk publik.</>");

            return self::STATUS_TERSISA;
        }

        $this->line("  <fg=green>id {$id}: dipindah '{$path}'</>");

        return self::STATUS_DIPINDAH;
    }

    /**
     * Hapus salinan di disk privat tanpa pernah melempar exception.
     *
     * Hanya dipakai dari dalam blok catch moveOne(). Exception apa pun
     * yang dilempar dari sini akan menutupi exception database yang
     * sedang ditangani, membuat penyebab sebenarnya hilang dari log.
     *
     * Mengembalikan true bila file dipastikan masih ada, yaitu ada sisa
     * yang wajib dilaporkan ke operator. Bila kondisi tidak bisa dipastikan
     * karena pemeriksaan ikut gagal, hasilnya dianggap sisa — lebih baik
     * operator memeriksa file yang sudah bersih daripada file yang
     * diam-diam tertinggal.
     */
    protected function hapusSalinanPrivat(string $path, $evidence): bool
    {
        try {
            if ($evidence->delete($path)) {
                return false;
            }
        } catch (Throwable $rollbackFailure) {
            report($rollbackFailure);
        }

        try {
            return $evidence->exists($path);
        } catch (Throwable) {
            return true;
        }
    }
}
