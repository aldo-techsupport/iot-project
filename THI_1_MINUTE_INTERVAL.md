# Fitur Baru: THI dengan Interval 1 Menit

## Ringkasan

Menambahkan opsi untuk mengambil data THI (Temperature Humidity Index) dengan interval **1 menit** pada sub-tab THI, sementara dashboard overview tetap menggunakan interval **5 detik** seperti biasa.

## Perubahan

### ✅ Yang TIDAK Berubah (Tetap 5 Detik)
- **Dashboard Overview**: Tetap menggunakan data setiap 5 detik
- **Rate Limit API**: Tetap 5 detik minimum
- **Noise Monitoring**: Tetap menggunakan interval 5 detik
- **Telemetry Storage**: Semua data tetap disimpan setiap 5 detik

### ✨ Yang Baru (Interval 1 Menit untuk THI)
- **Sub-tab THI**: Sekarang bisa menampilkan data dengan interval 1 menit
- **Method Baru**: `getThiDataByMinute()` di `ThiCalculationService`
- **API Parameter**: Tambahan `group_by=minute` di endpoint THI

## File yang Dimodifikasi

### 1. **app/Services/ThiCalculationService.php**
Menambahkan method baru:

```php
/**
 * Get THI data with 1-minute intervals for a specific date
 * Selects data closest to each minute mark (00 seconds)
 */
public static function getThiDataByMinute(int $deviceId, string $date): array
```

**Cara Kerja:**
- Loop setiap menit dalam 24 jam (1440 data points)
- Untuk setiap menit, cari data terdekat dalam window ±30 detik
- Hitung THI dari temperature dan humidity data tersebut
- Return array dengan format:
  ```php
  [
      'time' => '08:00',
      'hour' => 8,
      'minute' => 0,
      'temperature' => 30.5,
      'humidity' => 65.2,
      'thi' => 28.87,
      'measured_at' => '2026-04-30T08:00:03+07:00',
      'target_time' => '2026-04-30T08:00:00+07:00',
  ]
  ```

### 2. **app/Http/Controllers/Api/V1/ThiController.php**
Update validation dan logic:

```php
'group_by' => 'string|in:interval,hour,minute', // Tambah 'minute'

if ($groupBy === 'minute') {
    $data = ThiCalculationService::getThiDataByMinute($deviceId, $date);
}
```

## Cara Penggunaan

### API Endpoint

**GET** `/api/v1/thi/by-date`

**Parameters:**
- `device_id` (required): ID device
- `date` (required): Tanggal format YYYY-MM-DD
- `group_by` (optional): `interval` | `hour` | `minute`

**Contoh Request:**

```bash
# Interval 30 menit (default lama)
GET /api/v1/thi/by-date?device_id=1&date=2026-04-30&group_by=interval

# Per jam (rata-rata 2 interval 30 menit)
GET /api/v1/thi/by-date?device_id=1&date=2026-04-30&group_by=hour

# Per menit (BARU - 1 data per menit)
GET /api/v1/thi/by-date?device_id=1&date=2026-04-30&group_by=minute
```

**Response Format:**

```json
{
  "success": true,
  "data": [
    {
      "time": "08:00",
      "hour": 8,
      "minute": 0,
      "temperature": 30.5,
      "humidity": 65.2,
      "thi": 28.87,
      "measured_at": "2026-04-30T08:00:03+07:00",
      "target_time": "2026-04-30T08:00:00+07:00"
    },
    {
      "time": "08:01",
      "hour": 8,
      "minute": 1,
      "temperature": 30.6,
      "humidity": 65.3,
      "thi": 28.92,
      "measured_at": "2026-04-30T08:01:02+07:00",
      "target_time": "2026-04-30T08:01:00+07:00"
    }
    // ... 1440 data points untuk 24 jam
  ],
  "count": 1440,
  "group_by": "minute"
}
```

## Implementasi di Frontend

Update komponen THI tab untuk menggunakan parameter `group_by=minute`:

```javascript
// Sebelumnya (30 menit interval)
fetch(`/api/v1/thi/by-date?device_id=${deviceId}&date=${date}&group_by=interval`)

// Sekarang (1 menit interval)
fetch(`/api/v1/thi/by-date?device_id=${deviceId}&date=${date}&group_by=minute`)
```

## Performa

### Data Points per Hari
- **Interval 30 menit**: 48 data points
- **Per jam**: 24 data points
- **Per menit**: 1440 data points ⚠️

### Optimasi Query
Method `getThiDataByMinute()` menggunakan:
- `whereBetween()` dengan window ±30 detik untuk setiap menit
- `orderByRaw()` untuk mencari data terdekat
- `first()` untuk mengambil 1 data saja per menit

### Rekomendasi
- Gunakan caching untuk data historis
- Pertimbangkan pagination jika performa lambat
- Monitor query time di production

## Testing

```bash
# Test API endpoint
curl "http://localhost/api/v1/thi/by-date?device_id=1&date=2026-04-30&group_by=minute"

# Verify data count (should be ~1440 for full day)
# Verify each data point is ~1 minute apart
```

## Rollback

Jika perlu rollback, cukup:
1. Hapus method `getThiDataByMinute()` dari `ThiCalculationService`
2. Kembalikan validation di `ThiController`: `in:interval,hour` (hapus `minute`)
3. Update frontend untuk tidak menggunakan `group_by=minute`

---

**Tanggal**: 30 April 2026  
**Status**: ✅ Selesai  
**Impact**: Hanya sub-tab THI, tidak mempengaruhi sistem lain
