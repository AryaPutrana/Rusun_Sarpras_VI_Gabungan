<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    /** Batas aman jumlah pengambilan per baris detail (mencegah overflow INT). */
    public const MAX_QUANTITY = 1000000;

    /** Batas jumlah jenis barang dalam satu transaksi. */
    public const MAX_ITEMS_PER_TRANSACTION = 50;

    /** Batas maksimum kolom DECIMAL(15,2) MySQL: 9999999999999.99 */
    public const MAX_SUBTOTAL = 9999999999999.99;

    protected $fillable = [
        'taken_by',
        'rusun_id',
        'total_quantity',
        'total_value',
        'taken_at',
        'description',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'total_value' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    // Relationship: Withdrawal has many WithdrawalItem (satu transaksi = banyak barang)
    public function items()
    {
        return $this->hasMany(WithdrawalItem::class);
    }

    // Relationship: Withdrawal belongs to Rusun
    public function rusun()
    {
        return $this->belongsTo(Rusun::class);
    }

    // Relationship: Semua barang yang pernah diambil pada transaksi ini.
    // Sengaja bukan bernama "item()" supaya tidak ketukar dengan relasi
    // satu barang milik WithdrawalItem.
    public function withdrawnItems()
    {
        return $this->hasManyThrough(
            Item::class,
            WithdrawalItem::class,
            'withdrawal_id',
            'id',
            'id',
            'item_id'
        );
    }
}
