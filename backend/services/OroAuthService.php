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
        $clientId = (string) (env('ORO_CLIENT_ID', '') ?: env('ORO_API_CLIENT_ID', ''));
        $clientSecret = (string) (env('ORO_CLIENT_SECRET', '') ?: env('ORO_API_CLIENT_SECRET', ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new ApiException(
                'Missing Oro OAuth client credentials. Set ORO_CLIENT_ID/ORO_CLIENT_SECRET (or ORO_API_CLIENT_ID/ORO_API_CLIENT_SECRET).',
                500
            );
        }

        $payload = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
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
            $rawBody = is_string($response) ? $response : '';
            $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
            $errorMessage = 'Unable to authenticate with OroCommerce API.';
            if (is_array($decoded)) {
                $errorMessage = (string) ($decoded['error_description'] ?? $decoded['error'] ?? $errorMessage);
            }

            throw new ApiException(
                sprintf(
                    '%s (status %d). Verify ORO_OAUTH_TOKEN_URL and OAuth client credentials.',
                    $errorMessage,
                    $statusCode
                ),
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
