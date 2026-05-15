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
        'thi_avg_daily',
        'temperature_avg_daily',
        'humidity_avg_daily',
        'is_valid',
        'invalid_reason',
        'invalid_periods',
        'l1_leq',
        'l1_thi_avg',
        'l2_leq',
        'l2_thi_avg',
        'l3_leq',
        'l3_thi_avg',
        'l4_leq',
        'l4_thi_avg',
        'l5_leq',
        'l5_thi_avg',
        'l6_leq',
        'l6_thi_avg',
        'l7_leq',
        'l7_thi_avg',
        'l8_leq',
        'l8_thi_avg',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'ls_value' => 'float',
        'twa_value' => 'float',
        'dnd_value' => 'float',
        'allowable_time' => 'float',
        'thi_avg_daily' => 'float',
        'temperature_avg_daily' => 'float',
        'humidity_avg_daily' => 'float',
        'is_valid' => 'boolean',
        'invalid_periods' => 'array',
        'l1_leq' => 'float',
        'l1_thi_avg' => 'float',
        'l2_leq' => 'float',
        'l2_thi_avg' => 'float',
        'l3_leq' => 'float',
        'l3_thi_avg' => 'float',
        'l4_leq' => 'float',
        'l4_thi_avg' => 'float',
        'l5_leq' => 'float',
        'l5_thi_avg' => 'float',
        'l6_leq' => 'float',
        'l6_thi_avg' => 'float',
        'l7_leq' => 'float',
        'l7_thi_avg' => 'float',
        'l8_leq' => 'float',
        'l8_thi_avg' => 'float',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
