<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoiseRawData extends Model
{
    protected $table = 'noise_raw_data';

    protected $fillable = [
        'device_id',
        'period',
        'noise_level',
        'temperature',
        'humidity',
        'measured_at',
        'is_filled',
        'fill_method',
        'consecutive_timeouts',
    ];

    protected $casts = [
        'noise_level' => 'float',
        'temperature' => 'float',
        'humidity' => 'float',
        'measured_at' => 'datetime',
        'is_filled' => 'boolean',
    ];

    /**
     * Relationship to Device model
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Scope for filtering by period
     */
    public function scopePeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('measured_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get latest 120 records for a period
     */
    public function scopeLatestForPeriod($query, int $deviceId, string $period, int $limit = 120)
    {
        return $query->where('device_id', $deviceId)
                    ->where('period', $period)
                    ->orderBy('measured_at', 'desc')
                    ->limit($limit);
    }
}
