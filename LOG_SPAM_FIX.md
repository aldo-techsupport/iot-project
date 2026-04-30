# Perbaikan Log Spam Laravel

## Masalah
Log Laravel menghasilkan 1 juta baris dalam 10 menit karena:
1. Command `iot:check-timeouts` berjalan setiap menit
2. Service `TimeoutHandlerService` melakukan logging untuk setiap data point yang diisi (bisa ribuan per menit)
3. Tidak ada log rotation yang dikonfigurasi

## Solusi yang Diterapkan

### 1. Optimasi Logging di TimeoutHandlerService
**File**: `app/Services/TimeoutHandlerService.php`

**Perubahan**:
- ✅ Mengganti logging individual dengan summary logging
- ✅ Hanya log 1 baris summary untuk semua gap yang diisi
- ✅ Menambahkan safety check untuk mencegah infinite loop (max 3600 fills)
- ✅ Mengubah log level dari `info` ke `debug` untuk individual fills

**Sebelum**:
```php
// Log setiap data point yang diisi
Log::info("Filled missing data for device {$device->id}...");
```

**Sesudah**:
```php
// Log summary saja
if ($filledCount > 0) {
    Log::info("Filled {$filledCount} missing data points...");
}
```

### 2. Konfigurasi Log Rotation
**File**: `config/logging.php`

**Perubahan**:
- ✅ Mengubah default channel dari `stack` ke `daily`
- ✅ Mengubah log level dari `debug` ke `info`
- ✅ Mengurangi retention dari 14 hari ke 7 hari

### 3. Script Cleanup Manual
**File**: `cleanup-logs.sh`

Script bash untuk membersihkan log secara manual:
```bash
bash cleanup-logs.sh
```

Fitur:
- Backup log file yang ada
- Hapus log files lebih dari 7 hari
- Hapus log files lebih dari 100MB
- Truncate cronjob logs (keep last 1000 lines)

### 4. Automated Log Cleanup
**File**: `routes/console.php`

Menambahkan scheduled task yang berjalan setiap hari jam 2 pagi:
- Hapus log files lebih dari 7 hari
- Truncate cronjob logs yang lebih dari 10MB

### 5. Update Environment Configuration
**File**: `.env.example`

Menambahkan konfigurasi:
```env
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=7
```

## Cara Menggunakan

### 1. Update File .env
Tambahkan atau update konfigurasi berikut di file `.env`:
```env
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=7
```

### 2. Bersihkan Log yang Ada
Jalankan script cleanup:
```bash
bash cleanup-logs.sh
```

### 3. Clear Config Cache
```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Restart Scheduler
Jika menggunakan supervisor atau systemd, restart service scheduler:
```bash
# Jika menggunakan supervisor
sudo supervisorctl restart laravel-scheduler

# Atau restart cron
sudo service cron restart
```

## Monitoring

### Cek Ukuran Log Directory
```bash
du -sh storage/logs/
```

### Cek Log Files Terbesar
```bash
ls -lhS storage/logs/*.log | head -10
```

### Monitor Log Real-time
```bash
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Hitung Jumlah Baris Log Hari Ini
```bash
wc -l storage/logs/laravel-$(date +%Y-%m-%d).log
```

## Estimasi Pengurangan Log

**Sebelum**:
- 1,000,000 baris dalam 10 menit
- ~100,000 baris per menit
- ~6,000,000 baris per jam

**Sesudah**:
- 1 baris summary per device per period per menit
- Dengan 5 devices dan 8 periods: ~40 baris per menit
- ~2,400 baris per jam

**Pengurangan**: ~99.96% 🎉

## Catatan Penting

1. **Backup**: Script cleanup akan membuat backup sebelum menghapus log
2. **Monitoring**: Pantau log size selama beberapa hari pertama
3. **Debugging**: Jika perlu debug detail, ubah `LOG_LEVEL=debug` sementara
4. **Disk Space**: Pastikan ada cukup disk space untuk log rotation

## Troubleshooting

### Log Masih Besar?
1. Cek apakah config sudah di-clear: `php artisan config:clear`
2. Cek file `.env` sudah diupdate
3. Cek apakah ada service lain yang menulis log

### Scheduler Tidak Jalan?
1. Cek cron job: `crontab -l`
2. Pastikan ada: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`
3. Cek log scheduler: `storage/logs/laravel-*.log`

### Perlu Cleanup Segera?
Jalankan manual:
```bash
# Hapus semua log lama
find storage/logs -name "*.log" -type f -mtime +1 -delete

# Atau truncate semua log
truncate -s 0 storage/logs/*.log
```
