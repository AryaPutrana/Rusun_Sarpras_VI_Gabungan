<?php

namespace App\Models;

use App\Models\Concerns\HasEvidencePhoto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReport extends Model
{
    use HasEvidencePhoto;
    use HasFactory;

    public const KONDISI = [
        'normal' => 'Normal',
        'debit_air_kurang' => 'Debit Air Kurang',
        'tekanan_pdam_kurang' => 'Tekanan PDAM Kurang',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'location_id',
        'tanggal',
        'kondisi',
        'keterangan',
        'foto',
        'foto_disk',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(FinanceLocation::class, 'location_id');
    }
}
