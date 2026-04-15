<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TokenService;

final class AuthController
{
    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService();
    }

    public function login(array $request): array
    {
        $payload = $request['body'] ?? [];
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $companyCode = trim((string) ($payload['companyCode'] ?? ''));

        if ($email === '' || $password === '' || $companyCode === '') {
            return [
                'status' => 422,
                'data' => [
                    'message' => 'Email, password, and companyCode are required.',
                ],
            ];
        }

        $users = $this->inMemoryUsers();
        $user = $users[$email] ?? null;
        if (!$user || $user['password'] !== $password || $user['companyCode'] !== $companyCode) {
            return [
                'status' => 401,
                'data' => [
                    'message' => 'Invalid credentials or company.',
                ],
            ];
        }

        $token = $this->tokenService->generate([
            'sub' => (string) $user['id'],
            'email' => $email,
            'company_id' => $user['companyId'],
            'company_name' => $user['companyName'],
            'company_code' => $user['companyCode'],
            'name' => $user['name'],
        ]);

        return [
            'status' => 200,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $email,
                    'name' => $user['name'],
                    'companyId' => $user['companyId'],
                    'companyName' => $user['companyName'],
                    'companyCode' => $user['companyCode'],
                ],
            ],
        ];
    }

    private function inMemoryUsers(): array
    {
        return [
            'buyer@acme.com' => [
                'id' => 1201,
                'name' => 'Acme Buyer',
                'password' => 'secret123',
                'companyId' => 501,
                'companyName' => 'Acme Industries',
                'companyCode' => 'acme',
            ],
            'procurement@globex.com' => [
                'id' => 1202,
                'name' => 'Globex Procurement',
                'password' => 'secret123',
                'companyId' => 502,
                'companyName' => 'Globex Corporation',
                'companyCode' => 'globex',
            ],
        ];
    }
}
