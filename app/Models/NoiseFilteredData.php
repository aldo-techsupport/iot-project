<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoiseFilteredData extends Model
{
    protected $table = 'noise_filtered_data';

    protected $fillable = [
        'device_id',
        'period',
        'calculation_date',
        'noise_level',
        'temperature',
        'humidity',
        'measured_at',
        'is_filled',
        'fill_method',
        'slot_index',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'noise_level'      => 'float',
        'temperature'      => 'float',
        'humidity'         => 'float',
        'measured_at'      => 'datetime',
        'is_filled'        => 'boolean',
        'slot_index'       => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /** Scope: filter by period */
    public function scopePeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /** Scope: filter by date */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('calculation_date', $date);
    }

    /** Scope: filter by device + period + date */
    public function scopeForSlot($query, int $deviceId, string $period, string $date)
    {
        return $query->where('device_id', $deviceId)
                     ->where('period', $period)
                     ->whereDate('calculation_date', $date);
    }
}
