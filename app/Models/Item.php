<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use HasFactory;

    /**
     * Placeholder resmi untuk barang tanpa foto / foto hilang.
     * Disimpan di public/ agar ikut ter-deploy dan selalu tersedia.
     */
    public const DEFAULT_PHOTO_URL = 'images/items/default.jpg';

    /** Harga satuan maksimal: Rp 1.000.000.000 (batas yang disetujui). */
    public const MAX_UNIT_PRICE = 1000000000;

    /** Batas kolom INT MySQL (signed) untuk kolom stock / min_stock. */
    public const MAX_STOCK = 2147483647;

    /** Batas aman Penambahan stok per permintaan, mencegah overflow INT. */
    public const MAX_ADD_STOCK = 1000000;

    protected $fillable = [
        'item_code',
        'name',
        'photo',
        'unit_price',
        'stock',
        'min_stock',
        'unit',
        'description',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'stock' => 'integer',
    ];

    /**
     * Apakah file foto barang benar-benar tersedia di storage.
     */
    public function hasPhoto(): bool
    {
        return $this->photo && Storage::disk('public')->exists('items/'.$this->photo);
    }

    /**
     * URL foto barang.
     *
     * Foto hasil upload disimpan di disk "public" (storage/app/public/items/...).
     * Bila kolom photo kosong atau file-nya benar-benar hilang di storage,
     * kembalikan placeholder resmi di public/ supaya tidak ada gambar rusak.
     */
    public function photoUrl(): string
    {
        if ($this->hasPhoto()) {
            return asset('storage/items/'.rawurlencode($this->photo));
        }

        return asset(self::DEFAULT_PHOTO_URL);
    }

    /**
     * Thumbnail foto barang sebagai data URI, siap dipasang di PDF.
     *
     * PDF tidak boleh memakai photoUrl(): DomPDF dijalankan dengan
     * enable_remote = false, jadi URL http:// tidak bisa diambil dan fotonya
     * akan hilang. Karena itu fotonya dibaca dari storage, dikecilkan, lalu
     * ditanam langsung sebagai base64.
     *
     * Dismallkan karena ukuran foto asli sangat varies (1,9 KB sampai
     * 1,5 MB). Tanpa thumbnail, satu baris saja bisa membengkakkan PDF.
     *
     * Mengembalikan null bila foto tidak ada atau tidak bisa diproses, supaya
     * pemanggil cukup memakai placeholder tanpa perlu try/catch.
     *
     * @return array{data: string, width: int, height: int}|null
     */
    public function photoThumbnail(int $max = 44): ?array
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        // Kunci cache memuat nama file + ukuran, jadi mengganti foto atau
        // mengubah ukuran thumbnail otomatis menghasilkan cache baru.
        $key = 'item-thumb:'.sha1($this->photo.'|'.$max.'|v1');

        return Cache::remember($key, now()->addDays(30), function () use ($max) {
            $path = Storage::disk('public')->path('items/'.$this->photo);

            if (! is_readable($path)) {
                return null;
            }

            $contents = @file_get_contents($path);

            if ($contents === false || $contents === '') {
                return null;
            }

            $source = @imagecreatefromstring($contents);

            if ($source === false) {
                return null;
            }

            try {
                $width = imagesx($source);
                $height = imagesy($source);

                if ($width < 1 || $height < 1) {
                    return null;
                }

                // Jangan diperbesar: foto kecil tetap kecil, hanya dibatasi.
                $scale = min($max / $width, $max / $height, 1);
                $newWidth = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));

                $canvas = imagecreatetruecolor($newWidth, $newHeight);

                if ($canvas === false) {
                    return null;
                }

                try {
                    if (! imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
                        return null;
                    }

                    ob_start();
                    $ok = imagejpeg($canvas, null, 72);
                    $jpeg = ob_get_clean();

                    if (! $ok || $jpeg === false || $jpeg === '') {
                        return null;
                    }

                    return [
                        'data' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
                        'width' => $newWidth,
                        'height' => $newHeight,
                    ];
                } finally {
                    imagedestroy($canvas);
                }
            } finally {
                imagedestroy($source);
            }
        });
    }

    // Relationship: Item has many withdrawal details
    public function withdrawalItems()
    {
        return $this->hasMany(WithdrawalItem::class);
    }

    // Relationship: Item pernah muncul di banyak transaksi pengambilan
    public function withdrawals()
    {
        return $this->hasManyThrough(
            Withdrawal::class,
            WithdrawalItem::class,
            'item_id',
            'id',
            'id',
            'withdrawal_id'
        );
    }

    // Check if stock is low
    public function isLowStock()
    {
        return $this->stock <= $this->min_stock && $this->min_stock > 0;
    }

    // Get stock status
    public function getStockStatus()
    {
        if ($this->stock == 0) {
            return 'empty';
        } elseif ($this->isLowStock()) {
            return 'low';
        } elseif ($this->stock > $this->min_stock * 2) {
            return 'good';
        } else {
            return 'normal';
        }
    }
}
