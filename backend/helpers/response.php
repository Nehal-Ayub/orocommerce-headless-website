<?php

declare(strict_types=1);

function send_cors_headers(): void
{
    $frontendUrl = env('FRONTEND_URL', '*');
    $allowOrigin = $frontendUrl !== '' ? $frontendUrl : '*';

    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

function json_response(array $payload, int $status = 200): void
{
    send_cors_headers();
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success(array $payload = [], int $status = 200): void
{
    json_response([
        'success' => true,
        'data' => $payload,
    ], $status);
}

function json_error(string $message, int $status = 400, array $errors = []): void
{
    json_response([
        'success' => false,
        'message' => $message,
        'errors' => $errors,
    ], $status);
}
