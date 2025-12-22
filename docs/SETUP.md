# IoT Monitoring System - Setup Guide

## Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+ atau MariaDB 10.6+

## Langkah Setup

### 1. Clone dan Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 2. Konfigurasi Environment

```bash
# Copy file environment
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Konfigurasi Database

Edit file `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iot_monitoring
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Jalankan Migration dan Seeder

```bash
# Jalankan migration
php artisan migrate

# Jalankan seeder untuk data dummy
php artisan db:seed
```

Setelah seeding, akan muncul 2 device dengan key:
- **Sensor Ruang Server**: `dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa`
- **Sensor Gudang**: `dev_warehouse_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb`

### 5. Build Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### 6. Jalankan Server

```bash
# Development (menggunakan Laravel's built-in server)
php artisan serve

# Atau gunakan composer script
composer dev
```

### 7. Akses Aplikasi

- **Web Dashboard**: http://localhost:8000/iot
- **API Base URL**: http://localhost:8000/api/v1

Login dengan:
- Email: `test@example.com`
- Password: `password`

---

## Testing API

### Test dengan cURL

```bash
# Test POST telemetry
curl -X POST http://localhost:8000/api/v1/telemetry \
  -H "Content-Type: application/json" \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" \
  -d '{"temperature": 25.5, "humidity": 60.2, "noise_db": 45.0}'

# Test GET latest
curl http://localhost:8000/api/v1/devices/1/latest \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

# Test GET history
curl "http://localhost:8000/api/v1/devices/1/telemetry?limit=10" \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
```

---

## Struktur Folder

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   │   └── TelemetryController.php    # API controller
│   │   │   └── IoT/
│   │   │       └── DashboardController.php    # Web dashboard controller
│   │   ├── Middleware/
│   │   │   └── AuthenticateDevice.php         # Device authentication
│   │   └── Requests/
│   │       └── Api/
│   │           └── StoreTelemetryRequest.php  # Validation
│   └── Models/
│       ├── Device.php                         # Device model
│       └── Telemetry.php                      # Telemetry model
├── database/
│   ├── migrations/
│   │   ├── 2025_12_22_000001_create_devices_table.php
│   │   └── 2025_12_22_000002_create_telemetries_table.php
│   └── seeders/
│       └── DeviceSeeder.php                   # Dummy data seeder
├── resources/js/
│   ├── pages/iot/
│   │   ├── dashboard.tsx                      # Device list page
│   │   ├── device-detail.tsx                  # Device detail + charts
│   │   └── telemetry-log.tsx                  # Telemetry history table
│   └── types/
│       └── iot.d.ts                           # TypeScript types
├── routes/
│   ├── api.php                                # API routes
│   └── web.php                                # Web routes
└── docs/
    ├── IOT_API.md                             # API documentation
    └── SETUP.md                               # This file
```

---

## Best Practices untuk Produksi

### 1. Keamanan

```env
# Gunakan HTTPS
APP_URL=https://your-domain.com

# Disable debug mode
APP_DEBUG=false
APP_ENV=production
```

### 2. Rate Limiting

Rate limiting sudah dikonfigurasi di `routes/api.php`:
- 60 requests/menit per device
- Bisa disesuaikan sesuai kebutuhan

### 3. Queue untuk Heavy Processing

Jika perlu processing tambahan (alerts, notifications):

```bash
# Jalankan queue worker
php artisan queue:work

# Atau dengan supervisor untuk production
```

### 4. Data Retention

Untuk menghapus data lama, buat scheduled command:

```php
// app/Console/Commands/PruneTelemetry.php
$this->telemetry->where('measured_at', '<', now()->subDays(90))->delete();
```

Tambahkan ke scheduler:

```php
// routes/console.php
Schedule::command('telemetry:prune')->daily();
```

### 5. Caching

Untuk dashboard yang sering diakses:

```php
// Cache latest telemetry
Cache::remember("device.{$id}.latest", 60, fn() => $device->latestTelemetry);
```

### 6. Database Optimization

```sql
-- Index sudah dibuat di migration, tapi untuk data besar:
-- Pertimbangkan partitioning berdasarkan measured_at

-- Atau gunakan TimescaleDB untuk time-series data
```

### 7. Monitoring

Gunakan Laravel Telescope atau external monitoring:

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### 8. Backup

```bash
# Backup database
php artisan backup:run

# Atau gunakan mysqldump
mysqldump -u user -p iot_monitoring > backup.sql
```

---

## Troubleshooting

### Device tidak bisa connect

1. Pastikan device key benar (64 karakter)
2. Cek apakah device aktif (`is_active = true`)
3. Cek rate limiting (max 60 req/menit)

### Data tidak muncul di dashboard

1. Pastikan sudah login
2. Cek apakah telemetry tersimpan di database
3. Refresh halaman atau clear cache

### Migration error

```bash
# Reset dan migrate ulang
php artisan migrate:fresh --seed
```
