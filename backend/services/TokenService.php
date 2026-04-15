<?php

declare(strict_types=1);

namespace App\Services;

final class TokenService
{
    public function generate(array $claims): string
    {
        $now = time();
        $payload = array_merge(
            [
                'iat' => $now,
                'exp' => $now + ((int) env('JWT_TTL', 3600)),
            ],
            $claims
        );

        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac(
            'sha256',
            $header . '.' . $body,
            (string) env('SESSION_SECRET', 'unsafe-secret'),
            true
        );

        return $header . '.' . $body . '.' . $this->base64UrlEncode($signature);
    }

    public function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $body, (string) env('SESSION_SECRET', 'unsafe-secret'), true)
        );

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);
        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
