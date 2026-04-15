<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

final class OroApiClient
{
    public function __construct(
        private readonly OroAuthService $oroAuthService,
        private readonly CacheService $cacheService
    ) {
    }

    public function get(string $path, array $query = [], bool $useCache = true, int $cacheTtl = 120): array
    {
        $cacheKey = 'oro:get:' . sha1($path . ':' . json_encode($query));
        if ($useCache) {
            $cached = $this->cacheService->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = $this->request('GET', $path, $query);
        if ($useCache) {
            $this->cacheService->set($cacheKey, $response, $cacheTtl);
        }

        return $response;
    }

    public function post(string $path, array $payload = [], bool $checkout = false): array
    {
        return $this->request('POST', $path, [], $payload, $checkout);
    }

    public function patch(string $path, array $payload = [], bool $checkout = false): array
    {
        return $this->request('PATCH', $path, [], $payload, $checkout);
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        array $payload = [],
        bool $checkout = false
    ): array {
        $baseUrl = rtrim(
            $checkout ? env('ORO_CHECKOUT_API_BASE_URL', '') : env('ORO_API_BASE_URL', ''),
            '/'
        );
        if ($baseUrl === '') {
            // Fallback for environments that only set ORO_BASE_URL.
            $baseUrl = rtrim((string) env('ORO_BASE_URL', ''), '/');
        }

        if ($baseUrl === '') {
            throw new ApiException('Missing ORO API base URL configuration.', 500);
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $token = $this->oroAuthService->getAccessToken();
        $headers = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/vnd.api+json',
            'Authorization: Bearer ' . $token,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) env('HTTP_TIMEOUT_SECONDS', '30'),
        ]);

        if ($payload !== []) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ApiException('Oro API connection failure: ' . $error, 502);
        }

        $decoded = json_decode($raw, true);
        if ($code >= 400) {
            $message = is_array($decoded)
                ? ($decoded['errors'][0]['detail'] ?? $decoded['errors'][0]['title'] ?? 'Oro API request failed')
                : 'Oro API request failed';

            throw new ApiException($message, $code);
        }

        return is_array($decoded) ? $decoded : [];
    }
}

