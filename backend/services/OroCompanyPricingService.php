<?php

declare(strict_types=1);

namespace App\Services;

final class OroCompanyPricingService
{
    public function __construct(
        private readonly OroApiClient $apiClient,
        private readonly CacheService $cacheService
    ) {
    }

    /**
     * Resolve all price lists linked to a company through customer users.
     * This mirrors Oro entity relationships:
     * company -> customerUsers -> priceLists.
     */
    public function getCompanyPriceLists(int $companyId): array
    {
        $cacheKey = 'company_price_lists_' . $companyId;

        return $this->cacheService->remember(
            $cacheKey,
            (int) ($_ENV['CACHE_TTL_PRICE_LISTS'] ?? 300),
            function () use ($companyId): array {
                $query = [
                    'filter[customer]' => (string) $companyId,
                    'include' => 'priceLists',
                    'page[size]' => '100',
                ];

                $response = $this->apiClient->get('/customerusers', $query, true);
                $included = is_array($response['included'] ?? null) ? $response['included'] : [];
                $includedIndex = $this->buildIncludedIndex($included);
                $priceListsById = [];

                foreach ($included as $resource) {
                    if (!is_array($resource)) {
                        continue;
                    }

                    $type = strtolower((string) ($resource['type'] ?? ''));
                    if (!str_contains($type, 'pricelist')) {
                        continue;
                    }

                    $id = (int) ($resource['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }

                    $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
                    $priceListsById[$id] = [
                        'id' => $id,
                        'name' => (string) ($attributes['name'] ?? ('Company Price List #' . $id)),
                        'currencies' => $this->extractCurrencies($attributes),
                    ];
                }

                foreach ($response['data'] ?? [] as $customerUser) {
                    if (!is_array($customerUser)) {
                        continue;
                    }

                    $relationships = is_array($customerUser['relationships'] ?? null)
                        ? $customerUser['relationships']
                        : [];
                    $priceListIdentifiers = $relationships['priceLists']['data'] ?? null;
                    if (!is_array($priceListIdentifiers)) {
                        continue;
                    }

                    $identifiers = array_is_list($priceListIdentifiers)
                        ? $priceListIdentifiers
                        : [$priceListIdentifiers];
                    foreach ($identifiers as $identifier) {
                        if (!is_array($identifier)) {
                            continue;
                        }
                        $type = (string) ($identifier['type'] ?? '');
                        $id = (string) ($identifier['id'] ?? '');
                        if ($type === '' || $id === '') {
                            continue;
                        }

                        $numericId = (int) $id;
                        if ($numericId <= 0 || isset($priceListsById[$numericId])) {
                            continue;
                        }

                        $includedResource = $includedIndex[$this->includedKey($type, $id)] ?? null;
                        $attributes = is_array($includedResource['attributes'] ?? null) ? $includedResource['attributes'] : [];
                        $priceListsById[$numericId] = [
                            'id' => $numericId,
                            'name' => (string) ($attributes['name'] ?? ('Company Price List #' . $numericId)),
                            'currencies' => $this->extractCurrencies($attributes),
                        ];
                    }
                }

                return array_values($priceListsById);
            }
        );
    }

    /**
     * Fetch company-specific price records for products using the first available
     * mapped price list.
     */
    public function getProductPricesForCompany(int $companyId, array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return [];
        }

        $cacheKey = 'company_product_prices_' . $companyId . '_' . sha1(implode(',', $productIds));
        return $this->cacheService->remember(
            $cacheKey,
            (int) ($_ENV['CACHE_TTL_PRODUCTS'] ?? 300),
            fn(): array => $this->loadProductPricesForCompany($companyId, $productIds)
        );
    }

    /**
     * @param array<int, int> $productIds
     * @return array<int, array{amount: float, currency: string, priceListId: int|null, priceListName: string}>
     */
    private function loadProductPricesForCompany(int $companyId, array $productIds): array
    {
        $priceLists = $this->getCompanyPriceLists($companyId);
        if ($priceLists === []) {
            return [];
        }

        $prices = [];
        foreach ($priceLists as $priceList) {
            $priceListId = (int) ($priceList['id'] ?? 0);
            if ($priceListId <= 0) {
                continue;
            }

            $priceMap = $this->fetchPricesForPriceList($priceList, $productIds);
            foreach ($priceMap as $productId => $price) {
                if (isset($prices[$productId])) {
                    continue;
                }
                $prices[$productId] = $price;
            }

            if (count($prices) >= count($productIds)) {
                break;
            }
        }

        return $prices;
    }

    /**
     * @param array{id?: int|null, name?: string, currencies?: array<int, string>} $priceList
     * @param array<int, int> $productIds
     * @return array<int, array{amount: float, currency: string, priceListId: int|null, priceListName: string}>
     */
    private function fetchPricesForPriceList(array $priceList, array $productIds): array
    {
        $priceListId = (int) ($priceList['id'] ?? 0);
        if ($priceListId <= 0) {
            return [];
        }

        $defaultCurrency = (string) (($priceList['currencies'][0] ?? 'USD'));
        $priceListName = (string) ($priceList['name'] ?? ('Company Price List #' . $priceListId));
        $pageSize = (string) max(100, count($productIds));
        $productIdList = implode(',', array_map('strval', $productIds));

        $attempts = [
            [
                'path' => '/pricelistproducts',
                'query' => [
                    'filter[priceList.id]' => (string) $priceListId,
                    'filter[product.id]' => $productIdList,
                    'include' => 'prices,product',
                    'page[size]' => $pageSize,
                ],
            ],
            [
                'path' => '/pricelistproducts',
                'query' => [
                    'filter[priceList]' => (string) $priceListId,
                    'filter[product]' => $productIdList,
                    'include' => 'prices,product',
                    'page[size]' => $pageSize,
                ],
            ],
            [
                'path' => '/pricelistprices',
                'query' => [
                    'filter[priceList.id]' => (string) $priceListId,
                    'filter[product.id]' => $productIdList,
                    'include' => 'product',
                    'page[size]' => $pageSize,
                ],
            ],
            [
                'path' => '/pricelistprices',
                'query' => [
                    'filter[priceList]' => (string) $priceListId,
                    'filter[product]' => $productIdList,
                    'include' => 'product',
                    'page[size]' => $pageSize,
                ],
            ],
        ];

        foreach ($attempts as $attempt) {
            try {
                $response = $this->apiClient->get($attempt['path'], $attempt['query'], true);
            } catch (\App\Models\ApiException) {
                continue;
            }

            $parsed = $this->parsePricesFromResponse($response, $priceListId, $priceListName, $defaultCurrency);
            if ($parsed !== []) {
                return $parsed;
            }
        }

        return [];
    }

    /**
     * @return array<int, array{amount: float, currency: string, priceListId: int|null, priceListName: string}>
     */
    private function parsePricesFromResponse(
        array $response,
        int $priceListId,
        string $priceListName,
        string $defaultCurrency
    ): array {
        $prices = [];
        $includedIndex = $this->buildIncludedIndex(is_array($response['included'] ?? null) ? $response['included'] : []);

        foreach ($response['data'] ?? [] as $resource) {
            if (!is_array($resource)) {
                continue;
            }

            $parsed = $this->extractPriceForProductResource($resource, $includedIndex, $defaultCurrency);
            if ($parsed === null) {
                continue;
            }

            $prices[$parsed['productId']] = [
                'amount' => $parsed['amount'],
                'currency' => $parsed['currency'],
                'priceListId' => $priceListId,
                'priceListName' => $priceListName,
            ];
        }

        return $prices;
    }

    /**
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array{productId: int, amount: float, currency: string}|null
     */
    private function extractPriceForProductResource(array $resource, array $includedIndex, string $defaultCurrency): ?array
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $productId = $this->extractProductIdFromResource($resource, $attributes, $includedIndex);
        if ($productId <= 0) {
            return null;
        }

        $price = $this->extractPriceValue($attributes, $defaultCurrency);
        if ($price === null) {
            $price = $this->extractPriceFromRelationships($resource, $includedIndex, $defaultCurrency);
        }
        if ($price === null) {
            return null;
        }

        return [
            'productId' => $productId,
            'amount' => $price['amount'],
            'currency' => $price['currency'],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, array<string, mixed>> $includedIndex
     */
    private function extractProductIdFromResource(array $resource, array $attributes, array $includedIndex): int
    {
        foreach (['product', 'productId', 'product_id'] as $field) {
            $value = $attributes[$field] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        $productRelation = $resource['relationships']['product']['data'] ?? null;
        if (is_array($productRelation)) {
            $id = $productRelation['id'] ?? null;
            if (is_numeric($id)) {
                return (int) $id;
            }

            $relationType = (string) ($productRelation['type'] ?? '');
            $relationId = (string) ($productRelation['id'] ?? '');
            if ($relationType !== '' && $relationId !== '') {
                $included = $includedIndex[$this->includedKey($relationType, $relationId)] ?? null;
                $includedAttributes = is_array($included['attributes'] ?? null) ? $included['attributes'] : [];
                foreach (['id', 'productId', 'product_id'] as $field) {
                    $value = $includedAttributes[$field] ?? null;
                    if (is_numeric($value)) {
                        return (int) $value;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{amount: float, currency: string}|null
     */
    private function extractPriceValue(array $attributes, string $defaultCurrency): ?array
    {
        foreach (['price', 'value', 'amount'] as $field) {
            $value = $attributes[$field] ?? null;
            if (is_numeric($value)) {
                return [
                    'amount' => (float) $value,
                    'currency' => (string) ($attributes['currency'] ?? $defaultCurrency),
                ];
            }
        }

        $nestedPrice = $attributes['price'] ?? null;
        if (is_array($nestedPrice)) {
            foreach (['value', 'amount', 'price'] as $field) {
                $value = $nestedPrice[$field] ?? null;
                if (!is_numeric($value)) {
                    continue;
                }
                return [
                    'amount' => (float) $value,
                    'currency' => (string) ($nestedPrice['currency'] ?? $attributes['currency'] ?? $defaultCurrency),
                ];
            }
        }

        $prices = $attributes['prices'] ?? null;
        if (!is_array($prices)) {
            return null;
        }

        $candidates = array_is_list($prices) ? $prices : [$prices];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            foreach (['value', 'amount', 'price'] as $field) {
                $value = $candidate[$field] ?? null;
                if (!is_numeric($value)) {
                    continue;
                }
                return [
                    'amount' => (float) $value,
                    'currency' => (string) ($candidate['currency'] ?? $attributes['currency'] ?? $defaultCurrency),
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array{amount: float, currency: string}|null
     */
    private function extractPriceFromRelationships(array $resource, array $includedIndex, string $defaultCurrency): ?array
    {
        $priceRelation = $resource['relationships']['prices']['data'] ?? null;
        if (!is_array($priceRelation)) {
            return null;
        }

        $identifiers = array_is_list($priceRelation) ? $priceRelation : [$priceRelation];
        foreach ($identifiers as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }
            $type = (string) ($identifier['type'] ?? '');
            $id = (string) ($identifier['id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }

            $included = $includedIndex[$this->includedKey($type, $id)] ?? null;
            if (!is_array($included)) {
                continue;
            }

            $attributes = is_array($included['attributes'] ?? null) ? $included['attributes'] : [];
            $price = $this->extractPriceValue($attributes, $defaultCurrency);
            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $included
     * @return array<string, array<string, mixed>>
     */
    private function buildIncludedIndex(array $included): array
    {
        $index = [];
        foreach ($included as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['type'] ?? '');
            $id = (string) ($resource['id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }
            $index[$this->includedKey($type, $id)] = $resource;
        }

        return $index;
    }

    private function includedKey(string $type, string $id): string
    {
        return strtolower($type . ':' . $id);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    private function extractCurrencies(array $attributes): array
    {
        $currencies = $attributes['currencies'] ?? null;
        if (is_string($currencies) && trim($currencies) !== '') {
            return [trim($currencies)];
        }

        if (!is_array($currencies)) {
            return ['USD'];
        }

        $list = [];
        foreach ($currencies as $currency) {
            if (is_string($currency) && trim($currency) !== '') {
                $list[] = trim($currency);
            }
        }

        return $list === [] ? ['USD'] : array_values(array_unique($list));
    }
}
