<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Helper tunggal untuk seluruh akses file foto bukti.
 *
 * Foto baru disimpan pada disk privat 'evidence' yang tidak tershuaikan
 * lewat symlink public/storage. Foto lama masih berada di disk 'public'
 * sampai dipindahkan dengan `php artisan evidence:rehome`.
 */
class PhotoStorage
{
    /**
     * Disk privat untuk foto baru.
     */
    public const EVIDENCE = 'evidence';

    /**
     * Disk lama yang masih dapat diakses publik.
     */
    public const LEGACY_PUBLIC = 'public';

    /**
     * Folder tempat foto disimpan per jenis data.
     */
    public const DIR_MONITORING = 'monitoring';

    public const DIR_FINANCE = 'keuangan';

    /**
     * Pesan yang ditampilkan ke pengguna ketika foto gagal ditulis.
     *
     * Disimpan sebagai konstanta supaya keempat titik pemanggilan
     * storeUploaded() — monitoring dan keuangan, untuk insert dan update —
     * memakai kalimat yang sama persis.
     */
    public const MESSAGE_GAGAL_SIMPAN = 'Foto bukti gagal disimpan. Silakan coba lagi.';

    /**
     * Tentukan nama disk secara aman.
     *
     * Hanya nilai 'evidence' yang dianggap privat. Nilai lain — termasuk
     * null, kosong, atau isian yang tidak dikenal — dipetakan ke disk
     * 'public' agar foto lama tetap terbaca dan tidak terjadi error 500.
     */
    public static function diskName(mixed $fotoDisk): string
    {
        return $fotoDisk === self::EVIDENCE ? self::EVIDENCE : self::LEGACY_PUBLIC;
    }

    /**
     * Instance filesystem sesuai nilai foto_disk.
     */
    public static function disk(mixed $fotoDisk): Filesystem
    {
        return Storage::disk(self::diskName($fotoDisk));
    }

    /**
     * Bersihkan nilai path foto sebelum dipakai.
     *
     * Mengembalikan null bila kosong atau berpotensi keluar dari folder
     * penyimpanan (path traversal). Nilai foto berasal dari UploadedFile
     * sehingga normalnya sudah aman, tetapi.database bisa saja dikoreksi
     * manual oleh operator.
     */
    public static function safePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, "\0")) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return null;
        }

        return $path;
    }

    /**
     * Content-Type untuk path foto.
     *
     * Validasi upload hanya mengizinkan jpeg/jpg/png. Ekstensi lain
     * sengaja dilayani sebagai application/octet-stream agar browser
     * memaksa mengunduh, bukan merender sebagai HTML. Pemanggil yang
     * memerlukan nilai lain (misalnya penyematan gambar ke PDF) dapat
     * menentukan sendiri nilai cadangan melalui $fallback.
     */
    public static function mimeFor(string $path, string $fallback = 'application/octet-stream'): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $fallback,
        };
    }

    /**
     * Simpan file upload ke disk bukti privat.
     *
     * Menulis ke disk 'evidence' yang dikonfigurasi throw => true bisa
     * melempar exception, misalnya saat folder penyimpanan tidak
     * writable atau storage sedang penuh. Kalau dibiarkan, pengguna
     * mendapat halaman error 500 padahal yang terjadi hanya foto yang
     * tidak bisa ditulis dan tidak ada data yang berubah.
     *
     * Karena itu kegagalan dikembalikan sebagai null — termasuk saat
     * store() mengembalikan false — supaya pemanggil bisa mengembalikan
     * respons ramah. Exception tetap dilaporkan lewat report() supaya
     * penyebabnya tercatat di log dan bisa ditindaklanjuti operator.
     *
     * Mengembalikan path file bila berhasil, atau null bila file tidak
     * ada, gagal ditulis, atau store() mengembalikan nilai non-string.
     */
    public static function storeUploaded(?UploadedFile $file, string $directory): ?string
    {
        if ($file === null) {
            return null;
        }

        try {
            $path = $file->store($directory, self::EVIDENCE);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return is_string($path) ? $path : null;
    }

    /**
     * Hapus file sebagai operasi terbaik-effort.
     *
     * Disk 'evidence' dikonfigurasi throw => true, sehingga delete()
     * melempar exception saat gagal (misalnya karena lock atau permission).
     * Hampir semua pemanggilan delete() pada aplikasi ini bersifat
     * best-effort: basis data sudah dianggap benar dan file lama hanya
     * sisa yang perlu dibersihkan. Tanpa pembungkus, kegagalan delete
     * akan mengubah operasi yang sukses menjadi halaman error.
     *
     * Exception sengaja ditelan dan dikembalikan sebagai false agar
     * perilaku yang dilihat user sama seperti saat throw => false.
     */
    public static function deleteQuietly(Filesystem $disk, string $path): bool
    {
        try {
            return $disk->delete($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Periksa keberadaan file sebagai operasi terbaik-effort.
     *
     * Kegagalan pemeriksaan diperlakukan sama dengan "file tidak ada",
     * yaitu nilai yang sebelumnya dikembalikan disk ketika throw => false.
     * Untuk jalur baca (misalnya penyajian foto) kondisi ini diterjemahkan
     * menjadi 404, bukan error 500.
     */
    public static function existsQuietly(Filesystem $disk, string $path): bool
    {
        try {
            return $disk->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Baca isi file sebagai operasi terbaik-effort.
     *
     * Dipakai menggantikan pasangan exists() lalu get(). Pasangan
     * tersebut menyentuh disk dua kali dan menyisakan celah di
     * antaranya: file yang hilang tepat di antara keduanya membuat
     * get() melempar dan seluruh fitur gagal, bukan hanya satu foto.
     * Dengan satu panggilan terbungkus, kondisi apa pun yang membuat
     * file tidak terbaca berubah menjadi null, sama seperti file yang
     * memang tidak ada.
     */
    public static function getQuietly(Filesystem $disk, string $path): ?string
    {
        try {
            return $disk->get($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Path absolut file foto di sistem lokal, atau null bila tidak ada.
     *
     * response()->file() hanya bisa menyajikan file yang punya path di
     * sistem lokal. Kontrak Filesystem tidak mendeklarasikan path(), jadi
     * pemeriksaan di sini memastikan disk yang tidak punya path lokal
     * menghasilkan 404 yang wajar, bukan error fatal. Nilai null juga
     * dipakai ketika pemanggilan path() melempar exception.
     */
    public static function localPath(Filesystem $disk, string $path): ?string
    {
        if (! method_exists($disk, 'path')) {
            return null;
        }

        try {
            $local = $disk->path($path);
        } catch (Throwable) {
            return null;
        }

        return is_string($local) && $local !== '' ? $local : null;
    }

    /**
     * Apakah salinan pada disk lain berisi isi yang sama persis?
     *
     * Keberadaan file BUKAN bukti file itu utuh. Penulisan yang terputus
     * di tengah — proses mati mendadak, storage penuh, koneksi terputus —
     * bisa meninggalkan file yang ada tapi hanya berisi sebagian isi. Kalau
     * salinan seperti itu dipercayai begitu saja, lalu file asli yang masih
     * utuh dihapus, foto yang tersisa hanya cuplikan yang tidak berguna.
     *
     * Pemeriksaan dilakukan dua lapis. Ukuran lebih dulu karena murah dan
     * sudah menangkap kasus terpotong; sha256 memastikan isinya benar-benar
     * sama, bukan sekadar sama besar. Keduanya harus cocok.
     *
     * Mengembalikan false bila perbandingan TIDAK bisa dilakukan — file
     * hilang di tengah pemeriksaan, disk melempar exception, dan sebagainya.
     * Nilai default-nya sengaja tidak dipercaya supaya pemanggil memilih
     * untuk tidak menghapus file yang masih utuh di disk publik.
     */
    public static function isVerifiedCopy(Filesystem $source, string $sourcePath, Filesystem $copy, string $copyPath): bool
    {
        try {
            if ($source->size($sourcePath) !== $copy->size($copyPath)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        try {
            return hash_equals(
                hash('sha256', (string) $source->get($sourcePath)),
                hash('sha256', (string) $copy->get($copyPath))
            );
        } catch (Throwable) {
            return false;
        }
    }
}
