<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoiseDailySummary extends Model
{
    protected $fillable = [
        'device_id',
        'calculation_date',
        'ls_value',
        'twa_value',
        'dnd_value',
        'allowable_time',
        'l1_leq',
        'l2_leq',
        'l3_leq',
        'l4_leq',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'ls_value' => 'float',
        'twa_value' => 'float',
        'dnd_value' => 'float',
        'allowable_time' => 'float',
        'l1_leq' => 'float',
        'l2_leq' => 'float',
        'l3_leq' => 'float',
        'l4_leq' => 'float',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
