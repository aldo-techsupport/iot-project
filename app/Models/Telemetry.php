<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telemetry extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'temperature',
        'humidity',
        'noise_db',
        'measured_at',
        'is_filled',
        'fill_method',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'noise_db' => 'decimal:2',
            'measured_at' => 'datetime',
            'is_filled' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopeInDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('measured_at', '>=', $from);
        }
        if ($to) {
            $query->where('measured_at', '<=', $to);
        }
        return $query;
    }

    public function scopeLast24Hours($query)
    {
        return $query->where('measured_at', '>=', now()->subHours(24));
    }
}
