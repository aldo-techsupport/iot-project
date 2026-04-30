#!/bin/bash

# Script untuk membersihkan log files yang terlalu besar
# Jalankan dengan: bash cleanup-logs.sh

echo "=== Cleaning up Laravel logs ==="

# Backup log file yang ada (opsional)
if [ -f storage/logs/laravel.log ]; then
    echo "Backing up current laravel.log..."
    mv storage/logs/laravel.log storage/logs/laravel.log.backup.$(date +%Y%m%d_%H%M%S)
fi

# Hapus log files lama (lebih dari 7 hari)
echo "Removing log files older than 7 days..."
find storage/logs -name "*.log" -type f -mtime +7 -delete

# Hapus log files yang sangat besar (lebih dari 100MB)
echo "Removing log files larger than 100MB..."
find storage/logs -name "*.log" -type f -size +100M -delete

# Truncate cronjob logs yang terlalu besar
echo "Truncating cronjob logs..."
for logfile in storage/logs/cronjob-*.log; do
    if [ -f "$logfile" ]; then
        # Keep only last 1000 lines
        tail -n 1000 "$logfile" > "$logfile.tmp" && mv "$logfile.tmp" "$logfile"
        echo "  - Truncated: $logfile"
    fi
done

# Truncate telegram and whatsapp logs
for logfile in storage/logs/telegram-alert.log storage/logs/whatsapp-alert.log; do
    if [ -f "$logfile" ]; then
        tail -n 1000 "$logfile" > "$logfile.tmp" && mv "$logfile.tmp" "$logfile"
        echo "  - Truncated: $logfile"
    fi
done

echo ""
echo "=== Log cleanup completed ==="
echo ""
echo "Current log directory size:"
du -sh storage/logs/
echo ""
echo "Log files:"
ls -lh storage/logs/*.log 2>/dev/null || echo "No .log files found"
