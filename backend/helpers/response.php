<?php

declare(strict_types=1);

function json_response(array $payload, int $status = 200): void
{
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
