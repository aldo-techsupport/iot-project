# Data Selection Strategy untuk Pengumpulan Data IoT

## 📋 Ringkasan

Implementasi strategi seleksi data untuk memastikan pengumpulan data noise monitoring mencapai atau mendekati 120 data points per periode dengan timing yang presisi, dimulai tepat pada waktu 00 detik.

## 🎯 Tujuan

Mengatasi masalah data yang tidak mencapai 120 points karena:
- Koneksi ESP32 terputus sesaat
- Delay pengiriman data
- Timeout jaringan

Dengan menggunakan data terdekat dari interval 5 detik yang diharapkan.

## 🔧 Cara Kerja

### 1. **Periode Official (Timing Presisi)**
Dimulai tepat pada waktu 00 detik:
- **L1**: 09:00:00 - 09:09:55 (120 data points @ 5 detik interval)
- **L2**: 11:00:00 - 11:09:55 (120 data points @ 5 detik interval)
- **L3**: 14:00:00 - 14:09:55 (120 data points @ 5 detik interval)
- **L4**: 16:00:00 - 16:09:55 (120 data points @ 5 detik interval)

### 2. **Expected Timestamps**
Sistem mengharapkan data pada waktu-waktu berikut:
```
09:00:00, 09:00:05, 09:00:10, 09:00:15, ..., 09:09:55
```
Total: 120 timestamp dalam 10 menit

### 3. **Strategi Seleksi Data dengan Timeout Handling**

Untuk setiap expected timestamp (interval 5 detik):

1. **Cari data terdekat** dalam toleransi ±2.5 detik
2. **Jika ada timeout** (tidak ada data di waktu yang tepat):
   - Ambil data terdekat yang tersedia dalam window ±2.5 detik
   - Contoh: Expected 09:00:05, tapi data datang di 09:00:06 → Gunakan data 09:00:06
3. **Hindari duplikasi**: Setiap data point hanya digunakan sekali
4. **Return hasil**: Bisa kurang dari 120 jika banyak timeout dan tidak ada data terdekat

## 📊 Contoh Skenario

### Skenario 1: Data Normal (Tidak Ada Timeout)
```
Expected: 09:00:00 → Data: 09:00:00 ✅
Expected: 09:00:05 → Data: 09:00:05 ✅
Expected: 09:00:10 → Data: 09:00:10 ✅
...
Hasil: 120 data points
```

### Skenario 2: Ada Timeout, Gunakan Data Terdekat
```
Expected: 09:00:00 → Data: 09:00:00 ✅
Expected: 09:00:05 → Timeout! → Data terdekat: 09:00:06 ✅ (dalam toleransi ±2.5s)
Expected: 09:00:10 → Data: 09:00:11 ✅ (dalam toleransi ±2.5s)
Expected: 09:00:15 → Data: 09:00:15 ✅
...
Hasil: 120 data points (dengan beberapa data yang sedikit bergeser)
```

### Skenario 3: Timeout Panjang (Tidak Ada Data Terdekat)
```
Expected: 09:00:00 → Data: 09:00:00 ✅
Expected: 09:00:05 → Timeout! → Tidak ada data dalam ±2.5s ❌
Expected: 09:00:10 → Timeout! → Tidak ada data dalam ±2.5s ❌
Expected: 09:00:15 → Data: 09:00:15 ✅
...
Hasil: 118 data points (2 data hilang karena timeout panjang)
```

## 🔄 Alur Kerja

### ESP32 → Server
```
09:00:00 → Data masuk ✅
09:00:05 → Data masuk ✅
09:00:10 → Timeout (tidak ada data)
09:00:11 → Data masuk (terlambat 1 detik) ✅
09:00:15 → Data masuk ✅
...
```

### Backend Processing
```
1. Generate 120 expected timestamps (09:00:00, 09:00:05, ..., 09:09:55)
2. Untuk setiap expected timestamp:
   a. Cari data dalam window ±2.5 detik
   b. Pilih data terdekat yang belum digunakan
   c. Tandai data sebagai "sudah digunakan"
3. Return collection of selected data
4. Hitung statistik (Leq, THI, dll)
5. Simpan hasil
```

## 📁 File yang Dimodifikasi

### 1. **NoiseDataSelectionService.php**
- Method `selectFiveSecondIntervalData()`: 
  - Tidak lagi menggunakan buffer 1 menit sebelum start
  - Mulai tepat di 00 detik (09:00:00)
  - Generate 120 expected timestamps (bukan 132)
  - Toleransi ±2.5 detik untuk mencari data terdekat
  - Menangani timeout dengan mencari data terdekat

### 2. **TimeoutHandlerService.php**
- Method `getPeriodDates()`: 
  - Menghapus buffer ±3 menit
  - Menggunakan waktu official yang tepat
  - Start time di-set ke 00 detik

## 🎨 Dashboard

User akan melihat:
- L1 (09:00:00): 118/120 (jika ada 2 timeout tanpa data terdekat)
- L2 (11:00:00): 120/120 (semua data lengkap)
- L3 (14:00:00): 115/120 (jika ada 5 timeout tanpa data terdekat)
- L4 (16:00:00): 120/120 (semua data lengkap)

## 🧪 Testing

### Test Case 1: Data Lengkap Tepat Waktu
```
Input: 120 data pada 09:00:00, 09:00:05, ..., 09:09:55
Output: 120 data points
```

### Test Case 2: Data Terlambat Tapi Dalam Toleransi
```
Input: 
  - 09:00:00 ✅
  - 09:00:06 (expected 09:00:05, terlambat 1s) ✅
  - 09:00:11 (expected 09:00:10, terlambat 1s) ✅
  - ...
Output: 120 data points (menggunakan data terdekat)
```

### Test Case 3: Timeout Panjang
```
Input: 
  - 09:00:00 ✅
  - 09:00:05 ❌ (timeout, tidak ada data dalam ±2.5s)
  - 09:00:10 ❌ (timeout, tidak ada data dalam ±2.5s)
  - 09:00:15 ✅
  - ...
Output: 118 data points
```

## ⚙️ Configuration

Untuk mengubah toleransi pencarian data terdekat, edit di `NoiseDataSelectionService.php`:

```php
// Current: ±2.5 seconds
abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 2.5

// Untuk ±3 seconds:
abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 3
```

## 🚀 Deployment

1. Pull latest code
2. Tidak perlu migration baru
3. Restart services (jika ada)

## 📝 Notes

- ESP32 tetap mengirim data setiap 5 detik (atau sesuai konfigurasi)
- Tidak perlu perubahan di ESP32 code
- Semua logic ada di backend Laravel
- Timing lebih presisi dengan start di 00 detik
- Lebih toleran terhadap delay kecil (±2.5 detik)

## 🔍 Monitoring

Untuk melihat data yang terpilih:

```bash
# Via API
GET /api/v1/iot/noise-data/realtime?device_id=1&period=L1&date=2026-02-08

Response:
{
  "success": true,
  "data": [...],
  "count": 118  // Bisa kurang dari 120 jika ada timeout
}
```

## ✅ Keuntungan

1. ✅ Timing lebih presisi (mulai tepat di 00 detik)
2. ✅ Toleran terhadap delay kecil (±2.5 detik)
3. ✅ Tidak perlu buffer sebelum periode dimulai
4. ✅ Lebih mudah dipahami dan di-debug
5. ✅ Konsisten dengan interval 5 detik
6. ✅ Menangani timeout dengan mencari data terdekat

## ⚠️ Pertimbangan

- Jika timeout panjang (>2.5 detik) dan tidak ada data terdekat, data point akan hilang
- Hasil bisa kurang dari 120 points jika banyak timeout panjang
- Forced calculation tetap bisa dilakukan dengan minimum 60 data (50% dari target)
