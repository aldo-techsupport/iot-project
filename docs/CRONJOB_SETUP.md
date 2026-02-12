# Cronjob Setup - Automatic Noise Data Calculation

## Overview
Sistem akan otomatis recalculate noise data untuk semua device, 1 menit setelah setiap periode monitoring selesai.

## Schedule

| Period | End Time | Trigger Time | Command |
|--------|----------|--------------|---------|
| L1 | 09:10 | **09:11** | `iot:getall --period=L1 --force` |
| L2 | 11:10 | **11:11** | `iot:getall --period=L2 --force` |
| L3 | 14:10 | **14:11** | `iot:getall --period=L3 --force` |
| L4 | 16:10 | **16:11** | `iot:getall --period=L4 --force` |

## Setup di aaPanel

### 1. Buka aaPanel → Cron

### 2. Tambah Cron Job Baru

**Nama:** Laravel Scheduler

**Tipe:** Shell Script

**Waktu Eksekusi:** **Setiap menit** (Every minute)

**Cron Expression:** `* * * * *`

**Script:**
```bash
cd /www/wwwroot/your-project-path && php artisan schedule:run >> /dev/null 2>&1
```

**Ganti** `/www/wwwroot/your-project-path` dengan path project kamu.

### 3. Save dan Aktifkan

Pastikan cronjob dalam status **aktif**.

## Cara Kerja

1. **Cronjob aaPanel** jalankan `php artisan schedule:run` setiap menit
2. **Laravel Scheduler** cek jadwal internal (di `routes/console.php`)
3. Jika waktu sesuai, jalankan command yang dijadwalkan
4. Output disimpan ke log file

## Log Files

Setiap periode punya log file sendiri:

- `storage/logs/cronjob-L1.log` - Log untuk L1 (09:11)
- `storage/logs/cronjob-L2.log` - Log untuk L2 (11:11)
- `storage/logs/cronjob-L3.log` - Log untuk L3 (14:11)
- `storage/logs/cronjob-L4.log` - Log untuk L4 (16:11)

## Cek Status Schedule

Untuk melihat daftar scheduled tasks:

```bash
php artisan schedule:list
```

Output:
```
11   9  * * *  php artisan iot:getall --period=L1 --force  Next Due: 21 hours from now
11   11 * * *  php artisan iot:getall --period=L2 --force  Next Due: 23 hours from now
11   14 * * *  php artisan iot:getall --period=L3 --force  Next Due: 2 hours from now
11   16 * * *  php artisan iot:getall --period=L4 --force  Next Due: 4 hours from now
```

## Test Manual

Untuk test command secara manual:

```bash
# Test L1
php artisan iot:getall --period=L1 --force

# Test L2
php artisan iot:getall --period=L2 --force

# Test L3
php artisan iot:getall --period=L3 --force

# Test L4
php artisan iot:getall --period=L4 --force

# Test semua periode sekaligus
php artisan iot:getall --force
```

## Monitoring

### Cek Log Terakhir

```bash
# L1
tail -n 50 storage/logs/cronjob-L1.log

# L2
tail -n 50 storage/logs/cronjob-L2.log

# L3
tail -n 50 storage/logs/cronjob-L3.log

# L4
tail -n 50 storage/logs/cronjob-L4.log
```

### Cek Calculation Results

```bash
# Cek calculation hari ini
php artisan tinker --execute="
\$calcs = \App\Models\NoiseCalculation::whereDate('calculation_date', now()->toDateString())->get();
echo 'Total calculations today: ' . \$calcs->count() . PHP_EOL;
foreach (\$calcs as \$c) {
    echo \$c->device->name . ' - ' . \$c->period . ': Leq=' . \$c->leq_value . ' dB' . PHP_EOL;
}
"
```

## Troubleshooting

### Cronjob tidak jalan

1. **Cek cronjob aktif di aaPanel**
   - Pastikan status "Running"
   - Cek log execution di aaPanel

2. **Cek permission**
   ```bash
   chmod -R 775 storage
   chown -R www:www storage
   ```

3. **Test schedule:run manual**
   ```bash
   php artisan schedule:run
   ```

### Calculation gagal

1. **Cek log file**
   ```bash
   tail -n 100 storage/logs/cronjob-L1.log
   ```

2. **Cek Laravel log**
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```

3. **Test command manual**
   ```bash
   php artisan iot:getall --period=L1 --force
   ```

### Tidak ada data

1. **Cek telemetry data**
   ```bash
   php artisan tinker --execute="
   \$count = \App\Models\Telemetry::whereDate('measured_at', now()->toDateString())->count();
   echo 'Telemetry data today: ' . \$count . PHP_EOL;
   "
   ```

2. **Cek device status**
   ```bash
   php artisan tinker --execute="
   \$devices = \App\Models\Device::all();
   foreach (\$devices as \$d) {
       echo \$d->name . ' - Last seen: ' . (\$d->last_seen_at ?? 'Never') . PHP_EOL;
   }
   "
   ```

## Timezone

Pastikan timezone server sesuai dengan `Asia/Jakarta`:

```bash
# Cek timezone
php artisan tinker --execute="echo config('app.timezone');"

# Jika perlu ubah, edit .env
APP_TIMEZONE=Asia/Jakarta
```

## Backup Schedule

Untuk backup, tambahkan schedule di `routes/console.php`:

```php
// Backup calculations weekly
Schedule::command('backup:calculations')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->timezone('Asia/Jakarta');
```

## Telegram Alert System

### Setup Alert Cronjob

Tambahkan schedule untuk Telegram alert di `routes/console.php`:

```php
// Send Telegram alerts every 5 minutes
Schedule::command('telegram:send-alert')
    ->everyFiveMinutes()
    ->timezone('Asia/Jakarta');
```

### Alert Types

Sistem akan mengirim alert otomatis berdasarkan kondisi:

1. **THI > 29** - Peringatan suhu panas
2. **dB > 85** - Peringatan kebisingan
3. **dB > 85 & THI > 29** - Peringatan kritis
4. **dB > 100** - Bahaya kebisingan tinggi
5. **dB > 100 & THI > 29** - Kondisi darurat

### Test Alert

```bash
php artisan telegram:send-alert
```

### Log Alert

```bash
tail -n 50 storage/logs/telegram-alert.log
```

Lihat dokumentasi lengkap di `docs/TELEGRAM_ALERT_SYSTEM.md`

## Summary

✅ **Setup sekali** - Hanya perlu 1 cronjob di aaPanel
✅ **Otomatis** - Jalan sendiri setiap hari
✅ **Per periode** - Trigger 1 menit setelah periode selesai
✅ **Logging** - Semua hasil tersimpan di log
✅ **Monitoring** - Mudah cek status dan hasil
✅ **Alert System** - Notifikasi Telegram berbasis kondisi
