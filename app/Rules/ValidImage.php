<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidImage implements ValidationRule
{
    /**
     * Validasi file gambar berdasarkan magic bytes (file signature).
     * 
     * Validasi MIME type bisa di-bypass dengan rename extension,
     * jadi kita cek signature byte pertama file untuk memastikan
     * file benar-benar gambar.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! is_object($value) || ! method_exists($value, 'getRealPath')) {
            $fail('File yang dipilih tidak valid.');
            return;
        }

        $path = $value->getRealPath();

        if (! is_readable($path)) {
            $fail('File tidak dapat dibaca.');
            return;
        }

        // Baca 12 bytes pertama untuk deteksi file signature
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $fail('Tidak dapat membuka file.');
            return;
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false || strlen($bytes) < 2) {
            $fail('File terlalu kecil atau corrupt.');
            return;
        }

        // Cek magic bytes untuk format gambar yang diizinkan
        $valid = false;

        // JPEG: FF D8 FF
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            $valid = true;
        }
        // PNG: 89 50 4E 47 0D 0A 1A 0A
        elseif (substr($bytes, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            $valid = true;
        }
        // WebP: RIFF .... WEBP
        elseif (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            $valid = true;
        }

        if (! $valid) {
            $fail('File harus berupa gambar JPG, JPEG, PNG, atau WebP yang valid.');
        }
    }
}
