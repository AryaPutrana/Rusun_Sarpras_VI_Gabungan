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
}
