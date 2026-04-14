# Sistem Auto-Fill Data Timeout

## Status: ✅ AKTIF

Sistem auto-fill untuk mengisi gap data saat timeout sudah aktif dan berjalan otomatis.

---

## Komponen yang Sudah Aktif

### 1. **Scheduler Laravel** ✅
Crontab sudah dikonfigurasi untuk menjalankan scheduler setiap menit:
```bash
* * * * * cd /www/wwwroot/iot && php artisan schedule:run >> /dev/null 2>&1
```

### 2. **Command Auto-Fill** ✅
Command `iot:check-timeouts` dijadwalkan berjalan **setiap 1 menit**:
```php
Schedule::command('iot:check-timeouts')->everyMinute();
```

**Fungsi:**
- Memeriksa gap/timeout dalam periode monitoring (L1, L2, L3, L4)
- Mengisi gap dengan clone data sebelumnya
- Trigger calculation otomatis setelah periode selesai

### 3. **TimeoutHandlerService** ✅
Service yang menangani filling data saat timeout:

**Fitur:**
- Deteksi gap setiap 1 detik dalam periode monitoring
- Clone data terakhir untuk mengisi gap
- Simpan ke tabel `telemetries` dan `noise_raw_data`
- Tandai dengan `is_filled=true` dan `fill_method='copied'`
- Log timeout ke `noise_timeout_logs`

**Logika:**
- **Active Period**: Check sampai waktu sekarang dengan buffer 10 detik
- **Past Period**: Check sampai akhir periode tanpa buffer

### 4. **NoiseDataSelectionService** ✅
Service yang memilih 120 data points untuk calculation:

**Perubahan:**
- ✅ Menggunakan timestamp asli dari sensor (tidak dimanipulasi)
- ✅ Mengambil data asli DAN filled data (tidak filter `is_filled=false`)
- ✅ Prioritas: data official period → backup period (1 menit sebelumnya)

### 5. **CheckDataTimeouts Command** ✅
Command yang berjalan setiap menit untuk:

**Untuk Active Period:**
1. Jalankan `checkAndFillGaps()` untuk mengisi gap real-time
2. Jika sudah 120 data → trigger calculation
3. Jika 4 periode lengkap → trigger daily summary

**Untuk Past Period:**
1. **Isi gap dulu** dengan `checkAndFillGaps()`
2. Trigger calculation dengan force=true
3. Jika 4 periode lengkap → trigger daily summary

---

## Alur Kerja Otomatis

### Saat IoT Mengirim Data (Real-time)
```
IoT → TelemetryController::store()
  ↓
Deteksi periode (L1/L2/L3/L4)
  ↓
Simpan ke telemetries & noise_raw_data
  ↓
Panggil TimeoutHandlerService::checkAndFillGaps()
  ↓
Isi gap jika ada timeout
  ↓
Jika ≥120 data → Auto trigger calculation
```

### Setiap 1 Menit (Scheduler)
```
Cron → schedule:run
  ↓
iot:check-timeouts (setiap menit)
  ↓
Untuk setiap device & periode:
  ├─ Active period → Fill gaps real-time
  └─ Past period → Fill gaps + Force calculation
```

### Setiap 15 Menit (Scheduler)
```
Cron → schedule:run
  ↓
noise:calculate-periods (setiap 15 menit)
  ↓
Trigger calculation untuk periode yang sudah selesai
```

---

## Contoh Skenario

### Skenario 1: Data Normal (Tidak Ada Timeout)
```
09:00:00 - Data dari IoT ✓
09:00:05 - Data dari IoT ✓
09:00:10 - Data dari IoT ✓
...
09:09:55 - Data dari IoT ✓

Result: 120 data asli → Calculation otomatis
```

### Skenario 2: Ada Timeout (Auto-Fill Aktif)
```
09:00:00 - Data dari IoT ✓
09:00:05 - TIMEOUT! → Clone dari 09:00:00 (auto-fill)
09:00:10 - Data dari IoT ✓
09:00:15 - TIMEOUT! → Clone dari 09:00:10 (auto-fill)
...

Result: 89 data asli + 31 filled = 120 data → Calculation otomatis
```

### Skenario 3: Periode Sudah Lewat (Past Period)
```
Jam 09:15 (L1 sudah selesai jam 09:10)
  ↓
CheckDataTimeouts berjalan
  ↓
Deteksi past period L1
  ↓
Fill gaps untuk mencapai 120 data
  ↓
Force calculation
  ↓
Simpan hasil ke noise_calculations
```

---

## Monitoring & Verifikasi

### Cek Status Scheduler
```bash
php artisan schedule:list
```

### Cek Log Timeout
```bash
tail -f storage/logs/laravel.log | grep -i "timeout\|fill"
```

### Cek Data Filled
```sql
-- Cek berapa data yang di-fill hari ini
SELECT period, 
       COUNT(*) as total,
       SUM(CASE WHEN is_filled = 1 THEN 1 ELSE 0 END) as filled_count
FROM telemetries 
WHERE device_id = 5 
  AND DATE(measured_at) = CURDATE()
  AND measured_at BETWEEN '09:00:00' AND '09:10:00'
GROUP BY period;
```

### Cek Timeout Logs
```sql
SELECT * FROM noise_timeout_logs 
WHERE device_id = 5 
  AND DATE(expected_at) = CURDATE()
ORDER BY expected_at DESC 
LIMIT 20;
```

---

## Command Manual (Jika Diperlukan)

### Fill Gap Manual untuk Periode Tertentu
```bash
# Untuk L1
php artisan iot:fill-l1-gaps {device_id} {date}

# Contoh:
php artisan iot:fill-l1-gaps 5 2026-04-12
```

### Force Recalculation
```bash
# Recalculate semua periode dengan force
php artisan noise:calculate-periods --force

# Recalculate daily summary
php artisan iot:calculate-daily --force
```

### Manual Check Timeouts
```bash
php artisan iot:check-timeouts
```

---

## Konfigurasi Enum fill_method

Tabel `telemetries` dan `noise_raw_data` memiliki kolom `fill_method` dengan enum:
- `actual` - Data asli dari IoT
- `copied` - Data di-clone dari data sebelumnya (auto-fill)
- `zero` - Data diisi dengan nilai 0 (tidak digunakan saat ini)

---

## Kesimpulan

✅ **Sistem sudah aktif dan berjalan otomatis**

Untuk periode monitoring berikutnya (L2, L3, L4, dan hari-hari selanjutnya):
1. ✅ Gap akan otomatis terdeteksi setiap menit
2. ✅ Data akan otomatis di-clone dari data sebelumnya
3. ✅ Calculation akan otomatis trigger saat 120 data tercapai
4. ✅ Daily summary akan otomatis dihitung setelah 4 periode selesai

**Tidak perlu intervensi manual lagi!** 🎉

---

## Troubleshooting

### Jika Auto-Fill Tidak Berjalan

1. **Cek Crontab**
   ```bash
   crontab -l
   ```
   Pastikan ada: `* * * * * cd /www/wwwroot/iot && php artisan schedule:run`

2. **Cek Scheduler Status**
   ```bash
   php artisan schedule:list
   ```

3. **Test Manual**
   ```bash
   php artisan iot:check-timeouts
   ```

4. **Cek Log Error**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

### Jika Data Masih Kurang dari 120

1. Jalankan manual fill:
   ```bash
   php artisan iot:fill-l1-gaps {device_id}
   ```

2. Hapus calculation dan recalculate:
   ```bash
   php artisan noise:calculate-periods --force
   ```

---

**Dibuat:** 2026-04-12  
**Status:** Production Ready ✅
