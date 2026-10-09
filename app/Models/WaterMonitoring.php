<?php

namespace App\Models;

use App\Models\Concerns\HasEvidencePhoto;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WaterMonitoring extends Model
{
    use HasEvidencePhoto;
    use HasFactory;

    public const KONDISI = [
        'normal' => 'Normal',
        'debit_turun' => 'Debit Turun',
        'tekanan_air_kecil' => 'Tekanan Air Kecil',
    ];

    public const SESI = [
        'pagi' => 'Pagi',
        'siang' => 'Siang',
        'sore' => 'Sore',
    ];

    public const SESI_WAKTU = [
        'pagi' => '08:00',
        'siang' => '12:00',
        'sore' => '16:00',
    ];

    public const STATUS_PERINGATAN = [
        'normal' => 'NORMAL',
        'debit_turun' => 'PERLU PERHATIAN',
        'tekanan_air_kecil' => 'PERLU PERHATIAN',
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
        'sesi',
        'waktu',
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

    protected function waktu(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::createFromFormat('H:i:s', $value) : null,
            set: fn (mixed $value) => $value ? Carbon::parse($value)->format('H:i:s') : null,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MonitoringLocation::class, 'location_id');
    }
}
