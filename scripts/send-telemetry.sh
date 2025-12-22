#!/bin/bash

# Simple script untuk mengirim data telemetry sekali
# Usage: ./scripts/send-telemetry.sh [device_number] [temp] [humidity] [noise]

API_URL="${API_URL:-http://localhost:8000}"

# Device keys
DEVICE_1_KEY="dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
DEVICE_2_KEY="dev_warehouse_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"

DEVICE_NUM="${1:-1}"
TEMP="${2:-25.5}"
HUMIDITY="${3:-60.0}"
NOISE="${4:-45.0}"

if [ "$DEVICE_NUM" == "1" ]; then
    DEVICE_KEY="$DEVICE_1_KEY"
    DEVICE_NAME="Server Room"
elif [ "$DEVICE_NUM" == "2" ]; then
    DEVICE_KEY="$DEVICE_2_KEY"
    DEVICE_NAME="Warehouse"
else
    echo "Invalid device number. Use 1 or 2"
    exit 1
fi

echo "Sending telemetry to $DEVICE_NAME..."
echo "  Temperature: ${TEMP}°C"
echo "  Humidity: ${HUMIDITY}%"
echo "  Noise: ${NOISE}dB"
echo ""

curl -s -X POST "${API_URL}/api/v1/telemetry" \
  -H "Content-Type: application/json" \
  -H "X-Device-Key: ${DEVICE_KEY}" \
  -d "{
    \"temperature\": ${TEMP},
    \"humidity\": ${HUMIDITY},
    \"noise_db\": ${NOISE}
  }" | jq .

echo ""
