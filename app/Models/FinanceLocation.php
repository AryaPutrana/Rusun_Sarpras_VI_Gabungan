<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceLocation extends Model
{
    use HasFactory;

    public const STATUS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_lokasi',
        'keterangan',
        'status',
    ];

    public function financeReports(): HasMany
    {
        return $this->hasMany(FinanceReport::class, 'location_id');
    }
}
