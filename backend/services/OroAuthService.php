<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

class OroAuthService
{
    public function __construct(private readonly CacheService $cacheService)
    {
    }

    public function getAccessToken(): string
    {
        $cached = $this->cacheService->get('oro_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $tokenEndpoint = rtrim((string) env('ORO_OAUTH_TOKEN_URL', ''), '/');
        if ($tokenEndpoint === '') {
            throw new ApiException('Missing ORO_OAUTH_TOKEN_URL configuration.', 500);
        }
        $payload = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => env('ORO_CLIENT_ID', ''),
            'client_secret' => env('ORO_CLIENT_SECRET', ''),
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode >= 400) {
            throw new ApiException(
                'Unable to authenticate with OroCommerce API.',
                502,
                ['statusCode' => $statusCode, 'error' => $curlError]
            );
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            throw new ApiException('OroCommerce auth response is invalid.', 502);
        }

        $ttl = isset($decoded['expires_in']) ? max(((int) $decoded['expires_in']) - 60, 120) : 300;
        $this->cacheService->set('oro_access_token', (string) $decoded['access_token'], $ttl);

        return (string) $decoded['access_token'];
    }
}
