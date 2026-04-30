# Perubahan: Nonaktifkan Auto-Fill untuk Telemetry Log

## Ringkasan

Auto-fill (gap filling) sekarang **HANYA** untuk `NoiseRawData` (perhitungan noise monitoring), **TIDAK** untuk `Telemetry` (log utama). Ini membuat telemetry log hanya menampilkan data asli dari device, tanpa label "TIMEOUT (AUTO-FILLED)".

## Alasan Perubahan

**Sebelumnya:**
- TimeoutHandlerService mengisi gap di 2 tabel: `NoiseRawData` dan `Telemetry`
- Telemetry log menampilkan label "TIMEOUT (AUTO-FILLED)" yang membingungkan
- User melihat banyak data "filled" di log utama

**Sekarang:**
- Auto-fill HANYA untuk `NoiseRawData` (tetap diperlukan untuk perhitungan LAeq)
- Telemetry log HANYA menampilkan data asli dari device
- Lebih jelas mana data real dan mana yang di-fill

## File yang Dimodifikasi

### 1. **app/Services/TimeoutHandlerService.php**

#### Method `checkAndFillGaps()`
**Perubahan:**
```php
// SEBELUMNYA: Fill both tables
$batchInserts = [];      // NoiseRawData
$batchTelemetry = [];    // Telemetry ❌

NoiseRawData::insert($batchInserts);
Telemetry::insert($batchTelemetry);

// SEKARANG: Fill NoiseRawData only
$batchInserts = [];      // NoiseRawData only ✅
// REMOVED: $batchTelemetry

NoiseRawData::insert($batchInserts);
// REMOVED: Telemetry::insert()
```

#### Method `fillMissingPoint()`
**Perubahan:**
```php
// SEBELUMNYA: Fill both tables
NoiseRawData::create([...]);
Telemetry::create([...]);  // ❌ REMOVED

// SEKARANG: Fill NoiseRawData only
NoiseRawData::create([...]);  // ✅ Only this
```

### 2. **app/Console/Commands/FillL1Gaps.php**

**Perubahan:**
```php
// SEBELUMNYA: Fill both tables
$newTelemetry = Telemetry::create([...]);  // ❌ REMOVED
NoiseRawData::create([...]);

// SEKARANG: Fill NoiseRawData only
$newData = NoiseRawData::create([...]);  // ✅ Only this
```

## Dampak Perubahan

### ✅ Positif
1. **Telemetry Log Lebih Bersih**: Hanya menampilkan data asli dari device
2. **Tidak Ada Label "TIMEOUT"**: User tidak bingung dengan label auto-filled
3. **Perhitungan Tetap Akurat**: NoiseRawData tetap di-fill untuk perhitungan LAeq
4. **Performa Lebih Baik**: Mengurangi insert ke database (hanya 1 tabel vs 2 tabel)

### ⚠️ Perhatian
1. **Gap di Telemetry Log**: Jika device timeout, akan ada gap di log (tidak ada data)
2. **Noise Monitoring Tetap OK**: Perhitungan noise tetap lengkap karena NoiseRawData di-fill
3. **Data Historis**: Data telemetry yang sudah di-fill sebelumnya tetap ada (tidak dihapus)

## Tabel Perbandingan

| Aspek | Sebelumnya | Sekarang |
|-------|------------|----------|
| **Telemetry Log** | Menampilkan data filled | Hanya data asli ✅ |
| **NoiseRawData** | Di-fill otomatis | Di-fill otomatis ✅ |
| **Label "TIMEOUT"** | Muncul di log | Tidak muncul ✅ |
| **Perhitungan LAeq** | Akurat | Tetap akurat ✅ |
| **Database Insert** | 2 tabel | 1 tabel ✅ |

## Contoh Skenario

### Skenario: Device Timeout 30 Detik

**Sebelumnya:**
```
08:00:00 - 30.5°C, 65%, 70dB ✅ Real
08:00:05 - 30.5°C, 65%, 70dB ⚠️ TIMEOUT (AUTO-FILLED)
08:00:10 - 30.5°C, 65%, 70dB ⚠️ TIMEOUT (AUTO-FILLED)
08:00:15 - 30.5°C, 65%, 70dB ⚠️ TIMEOUT (AUTO-FILLED)
08:00:20 - 30.5°C, 65%, 70dB ⚠️ TIMEOUT (AUTO-FILLED)
08:00:25 - 30.5°C, 65%, 70dB ⚠️ TIMEOUT (AUTO-FILLED)
08:00:30 - 30.6°C, 66%, 71dB ✅ Real
```

**Sekarang:**
```
08:00:00 - 30.5°C, 65%, 70dB ✅ Real
[gap - tidak ada data di telemetry log]
08:00:30 - 30.6°C, 66%, 71dB ✅ Real
```

**Noise Monitoring (tetap lengkap):**
```
NoiseRawData untuk periode L1:
08:00:00 - 70dB ✅ Real
08:00:05 - 70dB ⚠️ Filled (untuk perhitungan)
08:00:10 - 70dB ⚠️ Filled (untuk perhitungan)
08:00:15 - 70dB ⚠️ Filled (untuk perhitungan)
08:00:20 - 70dB ⚠️ Filled (untuk perhitungan)
08:00:25 - 70dB ⚠️ Filled (untuk perhitungan)
08:00:30 - 71dB ✅ Real
```

## Verifikasi

### 1. Cek Telemetry Log (Harus Bersih)
```sql
-- Tidak boleh ada data dengan is_filled = true yang baru
SELECT * FROM telemetries 
WHERE is_filled = true 
  AND created_at > NOW() - INTERVAL 1 DAY;
-- Result: Empty (atau hanya data lama)
```

### 2. Cek NoiseRawData (Tetap Ada Filled)
```sql
-- Harus ada data filled untuk perhitungan
SELECT period, is_filled, COUNT(*) 
FROM noise_raw_data 
WHERE device_id = 1 
  AND DATE(measured_at) = CURDATE()
GROUP BY period, is_filled;
-- Result: Ada data dengan is_filled = true
```

### 3. Cek UI
- Buka telemetry log
- Tidak boleh ada label "TIMEOUT (AUTO-FILLED)"
- Hanya data asli yang ditampilkan

## Rollback (Jika Diperlukan)

Jika perlu kembali ke behavior lama (fill both tables):

1. **TimeoutHandlerService.php**:
   - Kembalikan `$batchTelemetry = []`
   - Kembalikan `Telemetry::insert($batchTelemetry)`
   - Kembalikan `Telemetry::create()` di `fillMissingPoint()`

2. **FillL1Gaps.php**:
   - Kembalikan `Telemetry::create()` sebelum `NoiseRawData::create()`

## Data Lama yang Sudah Filled

Data telemetry yang sudah di-fill sebelumnya **TIDAK** dihapus otomatis. Jika ingin membersihkan:

```sql
-- HATI-HATI: Ini akan menghapus semua data filled di telemetry
DELETE FROM telemetries WHERE is_filled = true;

-- Atau hanya data filled dalam periode tertentu
DELETE FROM telemetries 
WHERE is_filled = true 
  AND measured_at >= '2026-04-01' 
  AND measured_at < '2026-05-01';
```

⚠️ **Rekomendasi**: Biarkan data lama, hanya data baru yang tidak akan di-fill.

---

**Tanggal**: 30 April 2026  
**Status**: ✅ Selesai  
**Impact**: Telemetry log lebih bersih, noise monitoring tetap akurat
