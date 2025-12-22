<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'device_key',
        'device_key_hash',
        'location',
        'description',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'device_key',
        'device_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            $device->slug = $device->slug ?? self::generateSlug($device->name);
        });

        static::updating(function ($device) {
            if ($device->isDirty('name')) {
                $device->slug = self::generateSlug($device->name);
            }
        });
    }

    public static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    public static function generateDeviceKey(): string
    {
        return Str::random(64);
    }

    public static function hashDeviceKey(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function findByDeviceKey(string $key): ?self
    {
        $hash = self::hashDeviceKey($key);
        return self::where('device_key_hash', $hash)->first();
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(Telemetry::class);
    }

    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(Telemetry::class)->latestOfMany('measured_at');
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->last_seen_at) {
            return 'never_connected';
        }

        $minutesSinceLastSeen = $this->last_seen_at->diffInMinutes(now());

        if ($minutesSinceLastSeen <= 5) {
            return 'online';
        } elseif ($minutesSinceLastSeen <= 60) {
            return 'idle';
        }

        return 'offline';
    }
}
