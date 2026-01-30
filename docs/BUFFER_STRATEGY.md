# Buffer Strategy untuk Pengumpulan Data IoT

## 📋 Ringkasan

Implementasi strategi buffer untuk memastikan pengumpulan data noise monitoring mencapai atau mendekati 120 data points per periode, dengan cara yang "diam-diam" tanpa mengubah tampilan di dashboard.

## 🎯 Tujuan

Mengatasi masalah data yang tidak mencapai 120 points karena:
- Koneksi ESP32 terputus sesaat
- Delay pengiriman data
- Timeout jaringan

## 🔧 Cara Kerja

### 1. **Periode Official (Tampilan Dashboard)**
Tetap menampilkan waktu resmi:
- **L1**: 09:00 - 09:10 (10 menit)
- **L2**: 11:00 - 11:10 (10 menit)
- **L3**: 14:00 - 14:10 (10 menit)
- **L4**: 16:00 - 16:10 (10 menit)

### 2. **Periode Pengumpulan Aktual (Backend)**
Menggunakan buffer **±3 menit**:
- **L1**: 08:57 - 09:13 (16 menit = hingga 192 data points)
- **L2**: 10:57 - 11:13 (16 menit = hingga 192 data points)
- **L3**: 13:57 - 14:13 (16 menit = hingga 192 data points)
- **L4**: 15:57 - 16:13 (16 menit = hingga 192 data points)

### 3. **Strategi Seleksi Data**

Sistem akan memilih data dengan prioritas:

1. **Jika ≥120 data dari periode official** → Ambil 120 pertama dari periode official
2. **Jika <120 data dari periode official** → Lengkapi dengan data dari buffer:
   - Prioritas 1: Data dari periode official (09:00-09:10)
   - Prioritas 2: Data sebelum periode (08:57-09:00)
   - Prioritas 3: Data setelah periode (09:10-09:13)
3. **Jika total <120** → Gunakan semua data yang tersedia

## 📊 Metadata Tracking

Setiap calculation menyimpan metadata:
- `data_count`: Jumlah data yang digunakan untuk perhitungan (target: 120)
- `total_collected`: Total data yang terkumpul dalam buffer range
- `from_official_period`: Jumlah data dari periode official

Contoh:
```json
{
  "data_count": 120,
  "total_collected": 145,
  "from_official_period": 108
}
```

Artinya: Dari 145 data yang terkumpul, 108 dari periode official, dan sistem mengambil 120 terbaik untuk perhitungan.

## 🔄 Alur Kerja

### ESP32 → Server
```
08:57:00 → Data masuk (buffer awal)
08:57:05 → Data masuk (buffer awal)
...
09:00:00 → Data masuk (periode official dimulai)
09:00:05 → Data masuk
...
09:10:00 → Data masuk (periode official selesai)
09:10:05 → Data masuk (buffer akhir)
...
09:13:00 → Data masuk (buffer akhir)
```

### Backend Processing
```
1. Kumpulkan semua data dari 08:57 - 09:13
2. Filter data periode official (09:00 - 09:10)
3. Jika <120, tambahkan dari buffer
4. Pilih 120 data terbaik
5. Hitung statistik (Leq, THI, dll)
6. Simpan hasil + metadata
```

## 📁 File yang Dimodifikasi

### 1. **TimeoutHandlerService.php**
- Method `getPeriodDates()`: Menambahkan buffer ±3 menit
- Return value sekarang include `official_start` dan `official_end`

### 2. **DashboardController.php**
- Method `getRealTimeNoiseData()`: Implementasi smart data selection
- Method `triggerCalculation()`: Menggunakan buffer strategy untuk calculation
- Minimum data untuk forced calculation: 60 points (turun dari 120)

### 3. **CheckDataTimeouts.php**
- Menambahkan komentar untuk klarifikasi periode official vs buffer

### 4. **NoiseCalculation Model**
- Menambahkan field: `total_collected`, `from_official_period`

### 5. **Migration**
- `2026_01_28_200852_add_buffer_metadata_to_noise_calculations_table.php`

## 🎨 Dashboard (Tidak Berubah)

User tetap melihat:
- L1 (09:00): 108/120
- L2 (11:00): 0/120
- L3 (14:00): 98/120
- L4 (16:00): 120/120

Tapi di backend, sistem sebenarnya mengumpulkan dari range yang lebih luas.

## 🧪 Testing

### Test Case 1: Data Lengkap
```
Periode: L1 (09:00-09:10)
Data terkumpul: 120 dari official period
Hasil: Ambil 120 data dari official period
```

### Test Case 2: Data Kurang dari Official
```
Periode: L1 (09:00-09:10)
Data official: 108
Data buffer awal (08:57-09:00): 36
Data buffer akhir (09:10-09:13): 24
Total: 168
Hasil: Ambil 108 official + 12 dari buffer awal = 120 data
```

### Test Case 3: Data Sangat Kurang
```
Periode: L1 (09:00-09:10)
Total data: 85
Hasil: Gunakan semua 85 data (jika forced=true)
```

## ⚙️ Configuration

Untuk mengubah buffer time, edit di `TimeoutHandlerService.php`:

```php
// Current: ±3 minutes
$startTime = Carbon::parse(...)->subMinutes(3);
$endTime = Carbon::parse(...)->addMinutes(3);

// Untuk ±5 minutes:
$startTime = Carbon::parse(...)->subMinutes(5);
$endTime = Carbon::parse(...)->addMinutes(5);
```

## 🚀 Deployment

1. Pull latest code
2. Run migration:
   ```bash
   php artisan migrate
   ```
3. Restart services (jika ada)

## 📝 Notes

- ESP32 tetap mengirim data setiap 5 detik
- ESP32 tetap mengirim dengan label period yang sama (L1, L2, L3, L4)
- Tidak perlu perubahan di ESP32 code
- Semua logic ada di backend Laravel
- Dashboard tetap menampilkan periode official

## 🔍 Monitoring

Untuk melihat metadata collection:

```bash
# Via API
GET /api/v1/iot/noise-data/realtime?device_id=1&period=L1&date=2026-01-28

Response:
{
  "success": true,
  "data": [...],
  "count": 120,
  "total_collected": 145,
  "from_official_period": 108
}
```

## ✅ Keuntungan

1. ✅ Lebih toleran terhadap data loss
2. ✅ Meningkatkan kemungkinan mencapai 120 data
3. ✅ Tidak mengubah tampilan dashboard
4. ✅ Transparent dengan metadata
5. ✅ Tidak perlu ubah ESP32 code
6. ✅ Backward compatible

## ⚠️ Pertimbangan

- Data dari buffer (sebelum/sesudah periode) tetap valid karena masih dalam range waktu yang dekat
- Metadata memungkinkan audit trail yang jelas
- Forced calculation sekarang minimal 60 data (50% dari target)
