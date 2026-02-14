# Setup Cron Job untuk Noise Monitoring

## Perintah Cron yang Perlu Ditambahkan

Tambahkan cron job berikut di panel hosting Anda:

### Opsi 1: Laravel Scheduler (Recommended)
```bash
* * * * * cd /www/wwwroot/iot && php artisan schedule:run >> /dev/null 2>&1
```

**Penjelasan:**
- Berjalan setiap menit
- Laravel scheduler akan otomatis menjalankan command `iot:update-noise-calculations` sesuai jadwal di `routes/console.php`
- Scheduler sudah dikonfigurasi untuk berjalan jam 08:00-18:00 WIB saja

### Opsi 2: Direct Command (Alternative)
```bash
* * * * * cd /www/wwwroot/iot && php artisan iot:update-noise-calculations >> /dev/null 2>&1
```

**Penjelasan:**
- Langsung menjalankan command noise calculations setiap menit
- Lebih sederhana tapi tidak menggunakan Laravel scheduler

---

## Cara Setting di Panel Hosting

### 1. **cPanel / DirectAdmin / Plesk**
1. Login ke panel hosting
2. Cari menu **Cron Jobs** atau **Scheduled Tasks**
3. Tambahkan cron job baru
4. Set interval: **Every Minute** atau `* * * * *`
5. Command: Pilih salah satu opsi di atas (sesuaikan path `/www/wwwroot/iot`)
6. Save

### 2. **Manual via SSH**
```bash
# Edit crontab
crontab -e

# Tambahkan baris ini (pilih salah satu opsi di atas)
* * * * * cd /www/wwwroot/iot && php artisan schedule:run >> /dev/null 2>&1

# Save dan exit (Ctrl+X, Y, Enter)

# Verifikasi cron sudah terdaftar
crontab -l
```

---

## Verifikasi Cron Berjalan

### 1. Cek Log File
```bash
# Cek log noise calculations
tail -f storage/logs/noise-calculations.log

# Cek log Laravel
tail -f storage/logs/laravel.log
```

### 2. Cek Database
```bash
# Cek apakah ada data noise calculations hari ini
php artisan tinker --execute="
echo 'Noise Calculations Today: ' . \App\Models\NoiseCalculation::whereDate('calculation_date', today())->count() . PHP_EOL;
echo 'Latest calculation: ' . PHP_EOL;
\$latest = \App\Models\NoiseCalculation::latest()->first();
if (\$latest) {
    echo '  Device: ' . \$latest->device_id . PHP_EOL;
    echo '  Period: ' . \$latest->period . PHP_EOL;
    echo '  Date: ' . \$latest->calculation_date . PHP_EOL;
    echo '  Leq: ' . \$latest->leq_value . ' dB' . PHP_EOL;
}
"
```

### 3. Manual Test
```bash
# Jalankan command manual untuk test
php artisan iot:update-noise-calculations
```

---

## Jadwal Otomatis (Sudah Dikonfigurasi)

Command akan berjalan otomatis:
- **Setiap menit** dari jam **08:00 - 18:00 WIB**
- Memproses periode yang sudah lewat:
  - **L1**: 09:00-09:10 WIB
  - **L2**: 11:00-11:10 WIB
  - **L3**: 14:00-14:00 WIB
  - **L4**: 16:00-16:10 WIB

---

## Monitoring & Troubleshooting

### Log Messages yang Normal:
```
✓ Success - Original: 120, Filled: 0, Total: 120
  (Data lengkap, tidak ada timeout)

✓ Success - Original: 110, Filled: 8, Total: 120
  ⚠️ TIMEOUT: 10 data points hilang dari ESP (data loss: 8.33%)
  📋 Forward filled: 8 data points menggunakan nilai sebelumnya (6.67%)
  (Ada timeout tapi masih bisa dikalkulasi dengan forward fill)

✓ Success - Original: 95, Filled: 4, Total: 120
  ⚠️ TIMEOUT: 25 data points hilang dari ESP (data loss: 20.83%)
  📋 Forward filled: 4 data points menggunakan nilai sebelumnya (3.33%)
  ⚠️ Zero-filled: 21 data points (timeout >2x berturut-turut, diisi nilai 0) (17.5%)
  (Banyak timeout, data diisi 0 setelah 2x forward fill)
```

### Log Messages yang Error:
```
✗ Failed: Data tidak lengkap. Diterima: 30/120 data...
  (Data terlalu sedikit, tidak bisa dikalkulasi)
```

### Cek Status Cron:
```bash
# Cek apakah cron service berjalan
systemctl status cron    # Ubuntu/Debian
systemctl status crond   # CentOS/RHEL

# Cek log cron system
tail -f /var/log/cron     # CentOS/RHEL
tail -f /var/log/syslog   # Ubuntu/Debian
```

---

## Path yang Perlu Disesuaikan

Jika path aplikasi Anda berbeda dari `/www/wwwroot/iot`, sesuaikan di:

1. **Cron command**: Ganti `/www/wwwroot/iot` dengan path aplikasi Anda
2. **PHP path**: Jika PHP tidak di PATH, gunakan full path seperti `/usr/bin/php` atau `/usr/local/bin/php`

Contoh dengan full path:
```bash
* * * * * cd /home/username/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## Tips

1. **Gunakan Opsi 1 (Laravel Scheduler)** - Lebih fleksibel dan mudah diatur
2. **Cek log secara berkala** untuk memastikan tidak ada error
3. **Monitor data loss percentage** - jika sering >20%, cek koneksi ESP/jaringan
4. **Backup database** secara rutin

---

## Kontak & Support

Jika ada masalah:
1. Cek file log: `storage/logs/laravel.log` dan `storage/logs/noise-calculations.log`
2. Jalankan manual test: `php artisan iot:update-noise-calculations`
3. Cek permission: `chmod -R 775 storage bootstrap/cache`
