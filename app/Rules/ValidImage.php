<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidImage implements ValidationRule
{
    /**
     * Validasi file gambar yang kuat untuk mencegah upload file berbahaya.
     * 
     * Validasi berlapis:
     * 1. MIME type check
     * 2. File size check
     * 3. Magic number validation
     * 4. CRITICAL: Full image decode untuk memastikan file benar-benar gambar
     * 5. Content scan untuk mencegah PHP code injection
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 1. Cek apakah file uploaded
        if (!$value instanceof \Illuminate\Http\UploadedFile) {
            $fail('File harus berupa gambar yang valid.');
            return;
        }

        // 2. Cek MIME type dari upload
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($value->getMimeType(), $allowedMimes)) {
            $fail('File harus berformat JPG, PNG, atau WebP.');
            return;
        }

        // 3. Cek ukuran file (max 2MB)
        if ($value->getSize() > 2 * 1024 * 1024) {
            $fail('Ukuran gambar maksimal 2MB.');
            return;
        }

        $path = $value->getRealPath();
        
        if (!is_readable($path)) {
            $fail('File tidak dapat dibaca.');
            return;
        }

        // 4. Baca konten file untuk validasi magic number
        $content = file_get_contents($path);
        if ($content === false || strlen($content) < 12) {
            $fail('File gambar tidak valid atau corrupt.');
            return;
        }

        // 5. Validasi magic number (12 bytes pertama)
        $validMagic = false;
        
        // JPEG: FF D8 FF
        if (substr($content, 0, 3) === "\xFF\xD8\xFF") {
            $validMagic = true;
        }
        // PNG: 89 50 4E 47 0D 0A 1A 0A
        elseif (substr($content, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            $validMagic = true;
        }
        // WebP: RIFF .... WEBP
        elseif (substr($content, 0, 4) === 'RIFF' && strlen($content) >= 12 && substr($content, 8, 4) === 'WEBP') {
            $validMagic = true;
        }

        if (!$validMagic) {
            $fail('File bukan gambar yang valid (magic number tidak sesuai).');
            return;
        }

        // 6. CRITICAL: Full image decode untuk memastikan file benar-benar gambar valid
        // Ini mencegah PHP file dengan magic number gambar palsu
        try {
            $imageInfo = @getimagesize($path);
            
            if ($imageInfo === false) {
                $fail('File bukan gambar yang valid (gagal decode).');
                return;
            }

            // Validasi tipe gambar dari getimagesize
            $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
            if (!in_array($imageInfo[2], $allowedTypes)) {
                $fail('Format gambar tidak didukung.');
                return;
            }

            // Coba buat resource image untuk decode penuh
            $image = null;
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = @imagecreatefromjpeg($path);
                    break;
                case IMAGETYPE_PNG:
                    $image = @imagecreatefrompng($path);
                    break;
                case IMAGETYPE_WEBP:
                    $image = @imagecreatefromwebp($path);
                    break;
            }

            if ($image === false || $image === null) {
                $fail('File gambar tidak dapat diproses (corrupt atau format tidak valid).');
                return;
            }

            // Cleanup resource
            imagedestroy($image);

        } catch (\Throwable $e) {
            $fail('File gambar tidak valid.');
            return;
        }

        // 7. Scan untuk konten PHP yang mencurigakan
        if (preg_match('/<\?php|<\?=|<script/i', $content)) {
            $fail('File gambar mengandung konten berbahaya.');
            return;
        }

        // Semua validasi passed
    }
}
