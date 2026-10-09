<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rusun extends Model
{
    use HasFactory;

    protected $table = 'rusun';

    protected $fillable = [
        'code',
        'name',
    ];

    // Relationship: Rusun has many withdrawals
    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }
}
