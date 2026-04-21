<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

final class OroProductService
{
    public function __construct(
        private readonly OroApiClient $apiClient,
        private readonly OroCompanyPricingService $pricingService
    ) {
    }

    public function getProducts(array $filters, int $companyId): array
    {
        $query = [
            'page[number]' => max((int) ($filters['page'] ?? 1), 1),
            'page[size]' => min(max((int) ($filters['perPage'] ?? 12), 1), 60),
            'include' => 'category',
            'filter[status]' => 'enabled',
            'sort' => (string) ($filters['sort'] ?? '-updatedAt'),
        ];

        if (!empty($filters['search'])) {
            $query['filter[name]'] = (string) $filters['search'];
        }
        if (!empty($filters['categoryId'])) {
            $query['filter[category]'] = (string) $filters['categoryId'];
        }

        $oroResponse = $this->apiClient->get('/products', $query, true, (int) env('CACHE_TTL_PRODUCTS', '300'));
        $items = $oroResponse['data'] ?? [];
        $resolvedNames = $this->resolveNamesForResources($items);

        $mappedProducts = array_map(
            fn(array $item): array => $this->mapProductResource($item, $resolvedNames),
            $items
        );
        $productIds = array_values(array_map(static fn(array $item): int => (int) $item['id'], $mappedProducts));
        $priceMap = $this->pricingService->getProductPricesForCompany($companyId, $productIds);
        $priceLists = $this->pricingService->getCompanyPriceLists($companyId);
        $fallbackList = $priceLists[0] ?? ['name' => 'Default Price List', 'currencies' => ['USD']];

        $finalItems = [];
        foreach ($mappedProducts as $product) {
            $price = $priceMap[(int) $product['id']] ?? [
                'amount' => 0,
                'currency' => $fallbackList['currencies'][0] ?? 'USD',
                'priceListId' => null,
                'priceListName' => $fallbackList['name'] ?? 'Default Price List',
            ];

            if ($product['price'] <= 0) {
                $product['price'] = (float) $price['amount'];
            }

            $product['currency'] = (string) $price['currency'];
            $product['priceListId'] = $price['priceListId'];
            $product['priceListName'] = (string) $price['priceListName'];
            $finalItems[] = $product;
        }

        if (!empty($filters['minPrice']) || !empty($filters['maxPrice'])) {
            $min = isset($filters['minPrice']) ? (float) $filters['minPrice'] : 0.0;
            $max = isset($filters['maxPrice']) ? (float) $filters['maxPrice'] : INF;
            $finalItems = array_values(array_filter(
                $finalItems,
                static fn(array $item): bool => $item['price'] >= $min && $item['price'] <= $max
            ));
        }

        if (!empty($filters['featured'])) {
            $finalItems = array_values(array_slice($finalItems, 0, 8));
        }

        return [
            'items' => $finalItems,
            'pagination' => [
                'page' => (int) $query['page[number]'],
                'perPage' => (int) $query['page[size]'],
                'total' => (int) ($oroResponse['meta']['totalCount'] ?? count($finalItems)),
            ],
        ];
    }

    public function getProductById(int $productId, int $companyId): array
    {
        $oroResponse = $this->apiClient->get('/products/' . $productId, ['include' => 'category'], false);
        $data = $oroResponse['data'] ?? null;
        if (!is_array($data)) {
            throw new ApiException('Product not found.', 404);
        }

        $resolvedNames = $this->resolveNamesForResources([$data]);
        $product = $this->mapProductResource($data, $resolvedNames);
        $prices = $this->pricingService->getProductPricesForCompany($companyId, [$productId]);
        $price = $prices[$productId] ?? null;

        if ($price) {
            $product['price'] = (float) $price['amount'];
            $product['currency'] = (string) $price['currency'];
            $product['priceListId'] = $price['priceListId'];
            $product['priceListName'] = (string) $price['priceListName'];
        }

        $related = $this->getProducts(['perPage' => 4], $companyId)['items'] ?? [];
        $related = array_values(array_filter(
            $related,
            static fn(array $item): bool => (int) $item['id'] !== $productId
        ));

        return [
            'data' => $product,
            'relatedProducts' => array_slice($related, 0, 4),
        ];
    }

    /**
     * @param array<int, string> $resolvedNames
     */
    private function mapProductResource(array $resource, array $resolvedNames = []): array
    {
        $attributes = $resource['attributes'] ?? [];
        $description = $attributes['descriptions']['default'] ?? ($attributes['description'] ?? '');
        $resourceId = (int) ($resource['id'] ?? 0);
        $name = $resolvedNames[$resourceId] ?? $this->extractInlineName($attributes) ?? 'Unnamed Product';
        $sku = $attributes['sku'] ?? '';
        $rawPrice = $attributes['price'] ?? ($attributes['prices']['default'] ?? 0);
        $categoryId = $resource['relationships']['category']['data']['id'] ?? null;
        $categoryName = $attributes['category']['name'] ?? null;

        $images = [];
        foreach (($attributes['images'] ?? []) as $image) {
            if (is_string($image)) {
                $images[] = $image;
            } elseif (is_array($image) && isset($image['url'])) {
                $images[] = (string) $image['url'];
            }
        }

        if ($images === []) {
            $images[] = 'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=1200';
        }

        return [
            'id' => $resourceId,
            'sku' => (string) $sku,
            'name' => (string) $name,
            'description' => (string) $description,
            'categoryId' => $categoryId ? (int) $categoryId : null,
            'categoryName' => $categoryName,
            'images' => $images,
            'price' => (float) $rawPrice,
            'currency' => 'USD',
            'priceListId' => null,
            'priceListName' => null,
        ];
    }

    /**
     * Resolve product names using the subresource endpoint when inline names
     * are not available in the product payload.
     *
     * @param array<int, array<string, mixed>> $resources
     * @return array<int, string>
     */
    private function resolveNamesForResources(array $resources): array
    {
        $names = [];
        $missingIds = [];

        foreach ($resources as $resource) {
            $id = (int) ($resource['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $attributes = $resource['attributes'] ?? [];
            $inlineName = $this->extractInlineName(is_array($attributes) ? $attributes : []);
            if ($inlineName !== null) {
                $names[$id] = $inlineName;
                continue;
            }

            $missingIds[] = $id;
        }

        foreach ($missingIds as $id) {
            $subresourceName = $this->fetchNameFromSubresource($id);
            if ($subresourceName !== null) {
                $names[$id] = $subresourceName;
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function extractInlineName(array $attributes): ?string
    {
        $names = $attributes['names'] ?? null;
        if (is_array($names)) {
            if (!empty($names['default']) && is_string($names['default'])) {
                return $names['default'];
            }

            foreach ($names as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        $name = $attributes['name'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return null;
    }

    private function fetchNameFromSubresource(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        try {
            $response = $this->apiClient->get('/products/' . $productId . '/names', [], true, (int) env('CACHE_TTL_PRODUCTS', '300'));
        } catch (ApiException) {
            return null;
        }

        return $this->extractNameFromSubresourcePayload($response);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNameFromSubresourcePayload(array $payload): ?string
    {
        $data = $payload['data'] ?? null;
        if ($data === null) {
            return null;
        }

        $candidates = is_array($data) && array_is_list($data) ? $data : [$data];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $attributes = $candidate['attributes'] ?? null;
            if (!is_array($attributes)) {
                continue;
            }

            foreach (['string', 'value', 'text', 'fallback'] as $field) {
                $fieldValue = $attributes[$field] ?? null;
                if (is_string($fieldValue) && trim($fieldValue) !== '') {
                    return $fieldValue;
                }
            }
        }

        return null;
    }
}
