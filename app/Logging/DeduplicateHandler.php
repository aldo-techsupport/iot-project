<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;

class DeduplicateHandler extends StreamHandler
{
    private array $logCache = [];
    private int $cacheSize = 100;
    private int $deduplicateWindow = 60; // seconds

    public function handle(LogRecord $record): bool
    {
        $hash = $this->getLogHash($record);
        $now = time();

        // Bersihkan cache lama
        $this->cleanOldCache($now);

        // Cek apakah log sudah ada dalam window waktu
        if (isset($this->logCache[$hash]) && ($now - $this->logCache[$hash]) < $this->deduplicateWindow) {
            return false; // Skip log duplikat
        }

        $this->logCache[$hash] = $now;
        return parent::handle($record);
    }

    private function getLogHash(LogRecord $record): string
    {
        return md5($record->level->value . $record->message . json_encode($record->context));
    }

    private function cleanOldCache(int $now): void
    {
        if (count($this->logCache) > $this->cacheSize) {
            $this->logCache = array_filter($this->logCache, fn($time) => ($now - $time) < $this->deduplicateWindow);
        }
    }
}
