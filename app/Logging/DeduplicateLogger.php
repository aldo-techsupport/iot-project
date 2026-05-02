<?php

namespace App\Logging;

use Monolog\Logger;

class DeduplicateLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('deduplicate');
        $handler = new DeduplicateHandler(
            $config['path'] ?? storage_path('logs/laravel.log'),
            $config['level'] ?? Logger::DEBUG
        );
        $logger->pushHandler($handler);
        
        return $logger;
    }
}
