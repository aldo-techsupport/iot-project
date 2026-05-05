GET /api/v1/master-data/noise-raw - Data mentah L1-L8
GET /api/v1/master-data/noise-calculations - Hasil perhitungan
GET /api/v1/master-data/daily-summaries - Ringkasan harian
GET /api/v1/master-data/timeout-logs - Log timeout
GET /api/v1/master-data/summary - Summary lengkap
Edit Data:

POST /api/v1/master-data/noise-raw - Tambah data
PUT /api/v1/master-data/noise-raw/{id} - Update data
DELETE /api/v1/master-data/noise-raw/{id} - Hapus data
Recalculate:

POST /api/v1/master-data/recalculate-period - Hitung ulang L1-L8
POST /api/v1/master-data/recalculate-daily - Hitung ulang Ls, TWA, DND