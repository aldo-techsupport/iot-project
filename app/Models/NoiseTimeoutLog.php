<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoiseTimeoutLog extends Model
{
    protected $fillable = [
        'device_id',
        'period',
        'expected_at',
        'action_taken',
        'consecutive_count',
        'details',
    ];

    protected $casts = [
        'expected_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
