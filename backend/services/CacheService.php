<?php

declare(strict_types=1);

namespace App\Services;

final class CacheService
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir
            ? rtrim($cacheDir, '/')
            : dirname(__DIR__) . '/storage/cache';
    }

    public function get(string $key): mixed
    {
        $path = $this->resolvePath($key);
        if (!is_file($path)) {
            return null;
        }

        $content = json_decode((string) file_get_contents($path), true);
        if (!is_array($content)) {
            return null;
        }

        if (($content['expires_at'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }

        return $content['value'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }

        file_put_contents(
            $this->resolvePath($key),
            json_encode(
                [
                    'expires_at' => time() + max($ttlSeconds, 1),
                    'value' => $value,
                ],
                JSON_PRETTY_PRINT
            )
        );
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        $this->set($key, $value, $ttlSeconds);
        return $value;
    }

    private function resolvePath(string $key): string
    {
        return $this->cacheDir . '/' . sha1($key) . '.json';
    }
}
