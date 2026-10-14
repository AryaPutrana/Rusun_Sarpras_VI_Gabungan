<?php

namespace App\Models\Concerns;

use App\Support\PhotoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

trait HasEvidencePhoto
{
    /**
     * Nama disk tempat foto record ini tersimpan.
     */
    public function fotoDiskName(): string
    {
        return PhotoStorage::diskName($this->foto_disk);
    }

    /**
     * Instance filesystem untuk foto record ini.
     */
    public function fotoDisk(): Filesystem
    {
        return PhotoStorage::disk($this->foto_disk);
    }

    /**
     * Path foto yang sudah dibersihkan, atau null bila tidak usable.
     */
    public function fotoPath(): ?string
    {
        return PhotoStorage::safePath($this->foto);
    }

    /**
     * Apakah file foto benar-benar ada di disk yang sesuai.
     *
     * Kegagalan pemeriksaan dianggap "tidak ada" supaya tidak berubah
     * menjadi error 500 pada halaman yang hanya menampilkan foto.
     */
    public function fotoExists(): bool
    {
        $path = $this->fotoPath();

        if ($path === null) {
            return false;
        }

        return PhotoStorage::existsQuietly($this->fotoDisk(), $path);
    }

    /**
     * Hapus file foto bila ada. Aman dipanggil berkali-kali.
     *
     * Best-effort: kegagalan tidak dilempar.
     */
    public function deleteFotoFile(): bool
    {
        $path = $this->fotoPath();

        if ($path === null) {
            return false;
        }

        $disk = $this->fotoDisk();

        if (! PhotoStorage::existsQuietly($disk, $path)) {
            return false;
        }

        return PhotoStorage::deleteQuietly($disk, $path);
    }

    /**
     * Token versi foto record ini.
     *
     * Diambil dari nama file foto sebab nama file berubah setiap kali foto
     * diganti. Nilai ini dipakai sebagai query string ?v= pada fotoUrl()
     * supaya browser tidak menyajikan foto lama dari cache.
     */
    public function fotoVersion(): ?string
    {
        $path = $this->fotoPath();

        return $path === null ? null : basename($path);
    }

    /**
     * URL foto record ini, lengkap dengan versi cache-busting.
     *
     * Bila file foto tersedia, parameter ?v= berisi token versi sehingga
     * URL ikut berubah begitu foto diganti. Bila foto tidak ada, URL tetap
     * dikembalikan tanpa versi (endpoint akan memberi respons 404).
     */
    public function fotoUrl(string $routeName): string
    {
        $url = route($routeName, $this);

        $version = $this->fotoVersion();

        return $version === null ? $url : $url.'?v='.rawurlencode($version);
    }
}
