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
                    'filter[company.id]' => (string) $companyId,
                    'include' => 'priceLists',
                    'page[size]' => '100',
                ];

                $response = $this->apiClient->get('/customerusers', $query, true);
                $included = $response['included'] ?? [];
                $priceLists = [];

                foreach ($included as $resource) {
                    if (($resource['type'] ?? '') !== 'pricelists') {
                        continue;
                    }

                    $attributes = $resource['attributes'] ?? [];
                    $priceLists[] = [
                        'id' => isset($resource['id']) ? (int) $resource['id'] : null,
                        'name' => $attributes['name'] ?? 'Company Price List',
                        'currencies' => $attributes['currencies'] ?? ['USD'],
                    ];
                }

                return $priceLists;
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

        $priceLists = $this->getCompanyPriceLists($companyId);
        if ($priceLists === []) {
            return [];
        }

        $primaryPriceList = $priceLists[0];
        $priceListId = (int) ($primaryPriceList['id'] ?? 0);
        if ($priceListId <= 0) {
            return [];
        }

        $query = [
            'filter[priceList.id]' => (string) $priceListId,
            'filter[product.id]' => implode(',', array_map('strval', $productIds)),
            'page[size]' => (string) max(100, count($productIds)),
        ];

        $response = $this->apiClient->get('/pricelistproducts', $query, true);
        $prices = [];

        foreach ($response['data'] ?? [] as $item) {
            $attributes = $item['attributes'] ?? [];
            $productId = isset($attributes['product']) ? (int) $attributes['product'] : 0;
            if ($productId <= 0) {
                continue;
            }

            $prices[$productId] = [
                'amount' => (float) ($attributes['price'] ?? 0),
                'currency' => (string) ($attributes['currency'] ?? ($primaryPriceList['currencies'][0] ?? 'USD')),
                'priceListId' => $priceListId,
                'priceListName' => (string) ($primaryPriceList['name'] ?? 'Company Price List'),
            ];
        }

        return $prices;
    }
}
