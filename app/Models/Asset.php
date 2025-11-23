<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'platform',
        'user_id',
    ];

    // Relasi: Asset dimiliki oleh User (Host)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Asset memiliki banyak Live Sessions
    public function liveSessions()
    {
        return $this->hasMany(LiveSession::class);
    }
}