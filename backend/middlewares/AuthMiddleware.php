<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Models\ApiException;
use App\Services\TokenService;

final class AuthMiddleware
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function handle(array $request): array
    {
        $headers = $request['headers'] ?? [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new ApiException('Unauthorized', 401);
        }

        $token = trim(substr($authHeader, 7));
        if (!$token) {
            throw new ApiException('Unauthorized', 401);
        }

        $payload = $this->tokenService->validate($token);
        if (!$payload) {
            throw new ApiException('Invalid or expired token', 401);
        }

        $request['user'] = [
            'id' => (int) ($payload['sub'] ?? 0),
            'email' => (string) ($payload['email'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'company_id' => (int) ($payload['company_id'] ?? 0),
            'company_name' => (string) ($payload['company_name'] ?? ''),
        ];

        return $request;
    }
}
