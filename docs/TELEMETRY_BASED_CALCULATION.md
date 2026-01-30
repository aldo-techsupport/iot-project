# Telemetry-Based Noise Data Calculation

## Overview
Sistem sekarang menggunakan **24-hour telemetry data** sebagai sumber untuk kalkulasi noise monitoring, bukan lagi bergantung pada `noise_raw_data` yang hanya ter-record saat periode monitoring.

## Why This Approach is Better

### Previous Approach (noise_raw_data)
❌ **Problems:**
- Data hanya ter-record saat periode monitoring aktif (09:00-09:10)
- Tidak ada backup data sebelum periode official
- Tidak bisa kalkulasi retroaktif untuk data lama
- Bergantung pada `detectPeriod()` yang harus tepat waktu

### New Approach (telemetry 24-hour)
✅ **Benefits:**
- ESP32 kirim data 24/7 → selalu ada backup data
- Bisa ambil data dari 1 menit sebelum periode official
- Bisa kalkulasi ulang data lama kapan saja
- Lebih reliable dan fleksibel

## Implementation

### 1. NoiseDataSelectionService
**File:** `app/Services/NoiseDataSelectionService.php`

**Changes:**
- Source: `NoiseRawData` → `Telemetry`
- Query: Filter by period → Filter by time range only
- Field mapping: `noise_level` → `noise_db`

```php
// Get all telemetry data from extended period (24-hour continuous data)
$allData = Telemetry::where('device_id', $deviceId)
    ->whereBetween('measured_at', [$extendedStart, $officialEnd])
    ->where('is_filled', false)
    ->orderBy('measured_at')
    ->get();
```

### 2. DashboardController
**File:** `app/Http/Controllers/IoT/DashboardController.php`

**Changes:**
- Query telemetry instead of noise_raw_data
- Map `noise_db` field to `noise_level` in response
- Calculate `from_official_period` correctly

```php
// Get total telemetry data collected
$totalCollected = \App\Models\Telemetry::where('device_id', $validated['device_id'])
    ->whereDate('measured_at', $date)
    ->whereBetween('measured_at', [$extendedStart, $officialEnd])
    ->where('is_filled', false)
    ->count();
```

## Data Selection Strategy

### Extended Period
- **Official Period:** 09:00:00 - 09:10:00 (10 minutes)
- **Extended Period:** 08:59:00 - 09:10:00 (11 minutes)
- **Buffer:** 1 minute before official start

### Selection Algorithm
1. Get all telemetry data from extended period (08:59-09:10)
2. Generate 132 expected timestamps at 5-second intervals
3. For each timestamp, find closest telemetry data within ±2 seconds
4. Select exactly 120 unique data points
5. Prioritize data from official period

### Example Result
```
Total telemetry data: 130
Selected data points: 120
  - From official period (09:00-09:10): 108
  - From buffer (08:59-09:00): 12
```

## Testing Results

### Device 5 - 2026-01-30 L1

**Before (noise_raw_data):**
```
Data Used: 117
Total Collected: 118
From Official Period: 117
```

**After (telemetry):**
```
Data Used: 120 ✅
Total Collected: 130
From Official Period: 108
```

## Monitoring Periods

| Period | Official Time | Extended Time | Expected Data |
|--------|--------------|---------------|---------------|
| L1 | 09:00-09:10 | 08:59-09:10 | 120 points |
| L2 | 11:00-11:10 | 10:59-11:10 | 120 points |
| L3 | 14:00-14:10 | 13:59-14:10 | 120 points |
| L4 | 16:00-16:10 | 15:59-16:10 | 120 points |

## Retroactive Calculation

✅ **Can now recalculate old data!**

Since telemetry data is stored 24/7, we can now:
- Recalculate any past period
- Fix missing calculations
- Regenerate statistics with new algorithms

Example:
```php
// Recalculate L1 for 2026-01-30
$selectedData = NoiseDataSelectionService::selectFiveSecondIntervalData(
    5, // device_id
    'L1',
    '2026-01-30 09:00:00',
    '2026-01-30 09:10:00'
);
// Will get 120 points from telemetry data
```

## Migration Notes

### noise_raw_data Table
- **Status:** Still used for storing period-specific data
- **Purpose:** Historical record of what was calculated
- **Future:** Can be deprecated if we fully rely on telemetry

### TelemetryController
- **detectPeriod():** Still used for auto-triggering calculations
- **Extended periods:** Still active (08:59-09:10, etc)
- **Purpose:** Ensure ESP32 sends data during extended periods

## Next Steps

1. ✅ Test with real-time data (wait for next period)
2. ✅ Verify 120 data points consistently
3. ⏳ Consider deprecating noise_raw_data table
4. ⏳ Add UI to recalculate past periods
