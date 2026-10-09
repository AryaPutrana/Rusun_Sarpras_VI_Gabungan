<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawal_id',
        'item_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationship: WithdrawalItem belongs to Withdrawal (transaksi induk)
    public function withdrawal()
    {
        return $this->belongsTo(Withdrawal::class);
    }

    // Relationship: WithdrawalItem belongs to Item
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
