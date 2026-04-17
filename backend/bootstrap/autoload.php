<?php

declare(strict_types=1);

define('APP_BASE_PATH', dirname(__DIR__));

/**
 * Tiny env loader for a framework-less setup.
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), "\"'");

        if ($name === '') {
            continue;
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

load_env(APP_BASE_PATH . '/.env');
require_once APP_BASE_PATH . '/helpers/response.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_BASE_PATH . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass);

    $candidates = [
        $baseDir . $relativePath . '.php',
    ];

    $segments = explode('/', $relativePath);
    if (count($segments) > 1) {
        $segments[0] = strtolower($segments[0]);
        $candidates[] = $baseDir . implode('/', $segments) . '.php';
    }

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
