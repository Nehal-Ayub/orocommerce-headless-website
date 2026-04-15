<?php

declare(strict_types=1);

namespace App\Services;

final class ContactService
{
    public function persist(array $payload): void
    {
        $logPath = APP_BASE_PATH . '/storage/logs/contact.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0775, true);
        }

        $line = json_encode(
            [
                'timestamp' => date('c'),
                'name' => (string) ($payload['name'] ?? ''),
                'email' => (string) ($payload['email'] ?? ''),
                'message' => (string) ($payload['message'] ?? ''),
            ],
            JSON_UNESCAPED_SLASHES
        );

        file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
    }
}
