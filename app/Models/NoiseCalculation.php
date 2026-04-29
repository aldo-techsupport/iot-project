<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoiseCalculation extends Model
{
    protected $fillable = [
        'device_id',
        'period',
        'calculation_date',
        'data_count',
        'total_collected',
        'from_official_period',
        'min_value',
        'max_value',
        'average_value',
        'range_value',
        'class_count',
        'class_interval',
        'leq_value',
        'frequency_distribution',
        'thi_average',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'data_count' => 'integer',
        'total_collected' => 'integer',
        'from_official_period' => 'integer',
        'min_value' => 'float',
        'max_value' => 'float',
        'average_value' => 'float',
        'range_value' => 'float',
        'class_count' => 'float',
        'class_interval' => 'float',
        'leq_value' => 'float',
        'frequency_distribution' => 'array',
        'thi_average' => 'float',
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
     * Scope for filtering by date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('calculation_date', $date);
    }

    /**
     * Check if calculation is complete (has 720 data points)
     */
    public function isComplete(): bool
    {
        return $this->data_count === 720;
    }

    /**
     * Get formatted Leq value
     */
    public function getFormattedLeqAttribute(): string
    {
        return $this->leq_value ? number_format($this->leq_value, 2) . ' dB' : 'N/A';
    }
}
