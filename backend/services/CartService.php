<?php

declare(strict_types=1);

namespace App\Services;

final class CartService
{
    private string $cartStoragePath;

    public function __construct()
    {
        $this->cartStoragePath = APP_BASE_PATH . '/storage/cache/carts';
        if (!is_dir($this->cartStoragePath)) {
            mkdir($this->cartStoragePath, 0775, true);
        }
    }

    public function get(string $companyId): array
    {
        $path = $this->getCartPath($companyId);
        if (!is_file($path)) {
            return [
                'items' => [],
                'totals' => ['subtotal' => 0.0, 'currency' => 'USD'],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $items = is_array($decoded) ? $decoded : [];
        return $this->buildCartResponse($items);
    }

    public function upsert(string $companyId, array $payload): array
    {
        $incomingItems = $payload['items'] ?? null;
        if (!is_array($incomingItems)) {
            $incomingItems = [$payload];
        }

        $existing = $this->get($companyId)['items'];

        foreach ($incomingItems as $item) {
            if (!is_array($item) || empty($item['productId'])) {
                continue;
            }

            $productId = (int) $item['productId'];
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $existing = array_values(array_filter($existing, static fn (array $line): bool => (int) $line['productId'] !== $productId));
            $existing[] = [
                'id' => $productId,
                'productId' => $productId,
                'sku' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['name'] ?? 'Product'),
                'quantity' => $quantity,
                'price' => (float) ($item['price'] ?? 0),
                'currency' => (string) ($item['currency'] ?? 'USD'),
                'image' => (string) ($item['image'] ?? ''),
                'priceListName' => (string) ($item['priceListName'] ?? ''),
            ];
        }

        file_put_contents($this->getCartPath($companyId), json_encode($existing, JSON_PRETTY_PRINT));
        return $this->buildCartResponse($existing);
    }

    private function buildCartResponse(array $items): array
    {
        $subtotal = 0.0;
        $currency = 'USD';

        foreach ($items as $item) {
            $linePrice = (float) ($item['price'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $subtotal += $linePrice * $quantity;
            $currency = (string) ($item['currency'] ?? $currency);
        }

        return [
            'items' => $items,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'currency' => $currency,
            ],
        ];
    }

    private function getCartPath(string $companyId): string
    {
        $safeCompanyId = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $companyId) ?: 'unknown';
        return $this->cartStoragePath . '/' . $safeCompanyId . '.json';
    }
}
