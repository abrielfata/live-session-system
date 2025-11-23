<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'scheduled_at',
        'google_calendar_event_id',
        'host_reported_gmv',
        'screenshot_path',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'host_reported_gmv' => 'decimal:2',
    ];

    // Relasi: Live Session dimiliki oleh Asset
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // Relasi: Live Session dimiliki oleh User (Host)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}