# IoT Monitoring API Documentation

## Overview

REST API untuk menerima data telemetry dari IoT devices. Semua endpoint memerlukan autentikasi menggunakan device key.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

Setiap request harus menyertakan device key melalui salah satu cara:

1. **Header `X-Device-Key`**
   ```
   X-Device-Key: your_device_key_here
   ```

2. **Bearer Token**
   ```
   Authorization: Bearer your_device_key_here
   ```

## Response Format

Semua response menggunakan format JSON yang konsisten:

```json
{
    "success": true|false,
    "message": "Human readable message",
    "data": { ... } | null,
    "errors": { ... } | null
}
```

## Rate Limiting

- 60 requests per menit per device
- Response `429 Too Many Requests` jika melebihi limit

---

## Endpoints

### 1. POST /api/v1/telemetry

Mengirim data sensor ke server.

**Request Body:**

```json
{
    "temperature": 25.5,
    "humidity": 60.2,
    "noise_db": 45.0,
    "measured_at": "2025-12-22T10:30:00+07:00"
}
```

**Validation Rules:**
| Field | Type | Required | Range |
|-------|------|----------|-------|
| temperature | number | Yes | -20 to 80 |
| humidity | number | Yes | 0 to 100 |
| noise_db | number | Yes | 0 to 130 |
| measured_at | ISO 8601 string | No | - |

> Jika `measured_at` tidak dikirim, server akan menggunakan waktu saat request diterima.

**Success Response (201):**

```json
{
    "success": true,
    "message": "Telemetry data stored successfully",
    "data": {
        "id": 123,
        "device_id": 1,
        "temperature": 25.5,
        "humidity": 60.2,
        "noise_db": 45.0,
        "measured_at": "2025-12-22T03:30:00+00:00"
    },
    "errors": null
}
```

**Validation Error (422):**

```json
{
    "success": false,
    "message": "Validation failed",
    "data": null,
    "errors": {
        "temperature": ["Temperature must be between -20°C and 80°C"]
    }
}
```

---

### 2. GET /api/v1/devices/{id}/latest

Mendapatkan data telemetry terakhir dari device.

**Success Response (200):**

```json
{
    "success": true,
    "message": "Latest telemetry retrieved",
    "data": {
        "id": 123,
        "temperature": 25.5,
        "humidity": 60.2,
        "noise_db": 45.0,
        "measured_at": "2025-12-22T03:30:00+00:00"
    },
    "errors": null
}
```

---

### 3. GET /api/v1/devices/{id}/telemetry

Mendapatkan riwayat telemetry dengan filter tanggal.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from | date/datetime | No | Filter dari tanggal |
| to | date/datetime | No | Filter sampai tanggal |
| limit | integer | No | Jumlah data (default: 100, max: 1000) |

**Example:**
```
GET /api/v1/devices/1/telemetry?from=2025-12-21&to=2025-12-22&limit=50
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "Telemetry history retrieved",
    "data": {
        "device_id": 1,
        "count": 50,
        "telemetries": [
            {
                "id": 123,
                "temperature": 25.5,
                "humidity": 60.2,
                "noise_db": 45.0,
                "measured_at": "2025-12-22T03:30:00+00:00"
            }
        ]
    },
    "errors": null
}
```

---

## Error Responses

### 401 Unauthorized

```json
{
    "success": false,
    "message": "Device key is required",
    "data": null,
    "errors": {
        "device_key": "Missing device_key header or Bearer token"
    }
}
```

### 403 Forbidden

```json
{
    "success": false,
    "message": "Device is deactivated",
    "data": null,
    "errors": {
        "device": "This device has been deactivated"
    }
}
```

### 429 Too Many Requests

```json
{
    "success": false,
    "message": "Too many requests",
    "data": null,
    "errors": {
        "rate_limit": "Rate limit exceeded. Please wait before sending more requests."
    }
}
```

---

## Contoh cURL

### Mengirim Data Telemetry

```bash
# Menggunakan X-Device-Key header
curl -X POST https://your-domain.com/api/v1/telemetry \
  -H "Content-Type: application/json" \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" \
  -d '{
    "temperature": 25.5,
    "humidity": 60.2,
    "noise_db": 45.0
  }'

# Menggunakan Bearer token
curl -X POST https://your-domain.com/api/v1/telemetry \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" \
  -d '{
    "temperature": 25.5,
    "humidity": 60.2,
    "noise_db": 45.0,
    "measured_at": "2025-12-22T10:30:00+07:00"
  }'
```

### Mendapatkan Data Terakhir

```bash
curl -X GET https://your-domain.com/api/v1/devices/1/latest \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
```

### Mendapatkan Riwayat

```bash
curl -X GET "https://your-domain.com/api/v1/devices/1/telemetry?from=2025-12-21&to=2025-12-22&limit=100" \
  -H "X-Device-Key: dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
```

---

## Contoh Payload JSON

### Payload Minimal

```json
{
    "temperature": 25.5,
    "humidity": 60.2,
    "noise_db": 45.0
}
```

### Payload Lengkap dengan Timestamp

```json
{
    "temperature": 25.5,
    "humidity": 60.2,
    "noise_db": 45.0,
    "measured_at": "2025-12-22T10:30:00+07:00"
}
```

### Contoh Data Sensor Berbagai Kondisi

```json
// Ruang Server (suhu rendah, kelembapan terkontrol)
{
    "temperature": 22.0,
    "humidity": 45.0,
    "noise_db": 55.0
}

// Gudang (suhu tinggi, kelembapan tinggi)
{
    "temperature": 32.5,
    "humidity": 75.0,
    "noise_db": 35.0
}

// Ruang Produksi (kebisingan tinggi)
{
    "temperature": 28.0,
    "humidity": 55.0,
    "noise_db": 85.0
}
```

---

## Contoh Kode Arduino/ESP32

```cpp
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* serverUrl = "https://your-domain.com/api/v1/telemetry";
const char* deviceKey = "dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa";

void sendTelemetry(float temperature, float humidity, float noise_db) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(serverUrl);
        http.addHeader("Content-Type", "application/json");
        http.addHeader("X-Device-Key", deviceKey);

        StaticJsonDocument<200> doc;
        doc["temperature"] = temperature;
        doc["humidity"] = humidity;
        doc["noise_db"] = noise_db;

        String jsonString;
        serializeJson(doc, jsonString);

        int httpResponseCode = http.POST(jsonString);

        if (httpResponseCode > 0) {
            String response = http.getString();
            Serial.println("Response: " + response);
        } else {
            Serial.println("Error: " + String(httpResponseCode));
        }

        http.end();
    }
}

void setup() {
    Serial.begin(115200);
    WiFi.begin(ssid, password);
    while (WiFi.status() != WL_CONNECTED) {
        delay(1000);
        Serial.println("Connecting to WiFi...");
    }
    Serial.println("Connected!");
}

void loop() {
    // Baca sensor (contoh nilai dummy)
    float temp = 25.5;
    float humidity = 60.2;
    float noise = 45.0;

    sendTelemetry(temp, humidity, noise);
    delay(60000); // Kirim setiap 1 menit
}
```
