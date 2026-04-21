<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

final class OroProductService
{
    private const FALLBACK_PRODUCT_IMAGE = 'https://images.unsplash.com/photo-1581235720704-06d3acfcb36f?w=1200';

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
            'include' => 'category,names,descriptions,shortDescriptions,images,images.image,images.types',
            'filter[status]' => 'enabled',
            'sort' => (string) ($filters['sort'] ?? '-updatedAt'),
        ];

        if (!empty($filters['search'])) {
            $query['filter[name]'] = (string) $filters['search'];
        }
        if (!empty($filters['categoryId'])) {
            $query['filter[category]'] = (string) $filters['categoryId'];
        }

        $oroResponse = $this->fetchWithIncludeFallback('/products', $query, true, (int) env('CACHE_TTL_PRODUCTS', '300'));
        $items = $oroResponse['data'] ?? [];
        $includedIndex = $this->buildIncludedIndex($oroResponse['included'] ?? []);
        $resolvedNames = $this->resolveNamesForResources($items, $includedIndex);
        $resolvedDescriptions = $this->resolveDescriptionsForResources($items, $includedIndex);
        $resolvedImages = $this->resolveImagesForResources($items, $includedIndex);

        $mappedProducts = array_map(
            fn(array $item): array => $this->mapProductResource(
                $item,
                $includedIndex,
                $resolvedNames,
                $resolvedDescriptions,
                $resolvedImages
            ),
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
        $oroResponse = $this->fetchWithIncludeFallback(
            '/products/' . $productId,
            ['include' => 'category,names,descriptions,shortDescriptions,images,images.image,images.types'],
            false
        );
        $data = $oroResponse['data'] ?? null;
        if (!is_array($data)) {
            throw new ApiException('Product not found.', 404);
        }

        $includedIndex = $this->buildIncludedIndex($oroResponse['included'] ?? []);
        $resolvedNames = $this->resolveNamesForResources([$data], $includedIndex);
        $resolvedDescriptions = $this->resolveDescriptionsForResources([$data], $includedIndex);
        $resolvedImages = $this->resolveImagesForResources([$data], $includedIndex);
        $product = $this->mapProductResource($data, $includedIndex, $resolvedNames, $resolvedDescriptions, $resolvedImages);
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
     * @param array<int, string> $resolvedDescriptions
     * @param array<int, array<int, string>> $resolvedImages
     * @param array<string, array<string, mixed>> $includedIndex
     */
    private function mapProductResource(
        array $resource,
        array $includedIndex = [],
        array $resolvedNames = [],
        array $resolvedDescriptions = [],
        array $resolvedImages = []
    ): array
    {
        $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
        $resourceId = (int) ($resource['id'] ?? 0);
        $name = $resolvedNames[$resourceId] ?? $this->extractInlineName($attributes) ?? ('Product #' . $resourceId);
        $description = $resolvedDescriptions[$resourceId] ?? $this->extractInlineDescription($attributes) ?? '';
        $sku = (string) ($attributes['sku'] ?? '');
        $inlinePrice = $this->extractInlinePrice($attributes);
        $categoryId = $resource['relationships']['category']['data']['id'] ?? null;
        $categoryName = $this->resolveCategoryName($resource, $attributes, $includedIndex);

        $images = $resolvedImages[$resourceId] ?? $this->extractInlineImages($attributes);
        $images = $this->normalizeImageUrls($images);
        if ($images === []) {
            $images[] = self::FALLBACK_PRODUCT_IMAGE;
        }

        return [
            'id' => $resourceId,
            'sku' => $sku,
            'name' => (string) $name,
            'description' => (string) $description,
            'categoryId' => $categoryId ? (int) $categoryId : null,
            'categoryName' => $categoryName,
            'images' => $images,
            'price' => (float) ($inlinePrice['amount'] ?? 0.0),
            'currency' => (string) ($inlinePrice['currency'] ?? 'USD'),
            'priceListId' => null,
            'priceListName' => null,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, array<string, mixed>> $includedIndex
     */
    private function resolveCategoryName(array $resource, array $attributes, array $includedIndex): ?string
    {
        $relationship = $resource['relationships']['category']['data'] ?? null;
        if (is_array($relationship)) {
            $relationType = (string) ($relationship['type'] ?? '');
            $relationId = (string) ($relationship['id'] ?? '');
            if ($relationType !== '' && $relationId !== '') {
                $key = $this->includedKey($relationType, $relationId);
                $categoryResource = $includedIndex[$key] ?? null;
                if (is_array($categoryResource)) {
                    $categoryAttributes = is_array($categoryResource['attributes'] ?? null)
                        ? $categoryResource['attributes']
                        : [];
                    $titles = $categoryAttributes['titles'] ?? null;
                    if (is_array($titles) && isset($titles['default']) && is_string($titles['default']) && trim($titles['default']) !== '') {
                        return trim($titles['default']);
                    }
                    $categoryName = $categoryAttributes['name'] ?? null;
                    if (is_string($categoryName) && trim($categoryName) !== '') {
                        return trim($categoryName);
                    }
                }
            }
        }

        $inlineCategory = $attributes['category']['name'] ?? null;
        if (is_string($inlineCategory) && trim($inlineCategory) !== '') {
            return trim($inlineCategory);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{amount: float, currency: string}
     */
    private function extractInlinePrice(array $attributes): array
    {
        $amountFields = ['price', 'value', 'amount', 'defaultPrice'];
        foreach ($amountFields as $field) {
            $value = $attributes[$field] ?? null;
            if (is_numeric($value)) {
                return [
                    'amount' => (float) $value,
                    'currency' => (string) ($attributes['currency'] ?? 'USD'),
                ];
            }
        }

        $prices = $attributes['prices'] ?? null;
        if (is_array($prices)) {
            $priceCandidates = array_is_list($prices) ? $prices : [$prices];
            foreach ($priceCandidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $value = $candidate['value'] ?? $candidate['price'] ?? $candidate['amount'] ?? null;
                if (!is_numeric($value)) {
                    continue;
                }

                return [
                    'amount' => (float) $value,
                    'currency' => (string) ($candidate['currency'] ?? $attributes['currency'] ?? 'USD'),
                ];
            }
        }

        return [
            'amount' => 0.0,
            'currency' => (string) ($attributes['currency'] ?? 'USD'),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function extractInlineDescription(array $attributes): ?string
    {
        foreach (['descriptions', 'shortDescriptions'] as $field) {
            $resolved = $this->extractLocalizedValue($attributes[$field] ?? null, true);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach (['description', 'shortDescription'] as $field) {
            $value = $attributes[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $this->normalizeText($value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    private function extractInlineImages(array $attributes): array
    {
        $images = [];
        $inlineImages = $attributes['images'] ?? null;
        if (!is_array($inlineImages)) {
            return [];
        }

        $candidates = array_is_list($inlineImages) ? $inlineImages : [$inlineImages];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $images[] = $candidate;
                continue;
            }
            if (!is_array($candidate)) {
                continue;
            }

            foreach (['url', 'externalUrl', 'downloadUrl', 'value'] as $field) {
                $url = $candidate[$field] ?? null;
                if (is_string($url) && trim($url) !== '') {
                    $images[] = $url;
                    break;
                }
            }
        }

        return $images;
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
     * Resolve product names using the subresource endpoint when inline names
     * are not available in the product payload.
     *
     * @param array<int, array<string, mixed>> $resources
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array<int, string>
     */
    private function resolveNamesForResources(array $resources, array $includedIndex): array
    {
        $names = [];

        foreach ($resources as $resource) {
            $id = (int) ($resource['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
            $names[$id] = $this->extractInlineName($attributes)
                ?? $this->resolveLocalizedValueFromRelationship($resource, 'names', $includedIndex)
                ?? $this->fetchNameFromSubresource($id)
                ?? ('Product #' . $id);
        }

        return $names;
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array<int, string>
     */
    private function resolveDescriptionsForResources(array $resources, array $includedIndex): array
    {
        $descriptions = [];

        foreach ($resources as $resource) {
            $id = (int) ($resource['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
            $descriptions[$id] = $this->extractInlineDescription($attributes)
                ?? $this->resolveLocalizedValueFromRelationship($resource, 'descriptions', $includedIndex, true)
                ?? $this->resolveLocalizedValueFromRelationship($resource, 'shortDescriptions', $includedIndex, true)
                ?? $this->fetchDescriptionFromSubresource($id)
                ?? '';
        }

        return $descriptions;
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array<int, array<int, string>>
     */
    private function resolveImagesForResources(array $resources, array $includedIndex): array
    {
        $imagesByProduct = [];

        foreach ($resources as $resource) {
            $id = (int) ($resource['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $attributes = is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
            $images = $this->resolveImagesFromRelationship($resource, $includedIndex);
            if ($images === []) {
                $images = $this->extractInlineImages($attributes);
            }
            if ($images === []) {
                $images = $this->fetchImagesFromSubresource($id);
            }

            $imagesByProduct[$id] = $this->normalizeImageUrls($images);
        }

        return $imagesByProduct;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function extractInlineName(array $attributes): ?string
    {
        $nameFromNames = $this->extractLocalizedValue($attributes['names'] ?? null);
        if ($nameFromNames !== null) {
            return $nameFromNames;
        }

        $name = $attributes['name'] ?? $attributes['defaultName'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
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

        return $this->extractLocalizedValueFromPayload($response);
    }

    private function fetchDescriptionFromSubresource(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        foreach (['descriptions', 'shortDescriptions'] as $association) {
            try {
                $response = $this->apiClient->get(
                    '/products/' . $productId . '/' . $association,
                    [],
                    true,
                    (int) env('CACHE_TTL_PRODUCTS', '300')
                );
            } catch (ApiException) {
                continue;
            }

            $value = $this->extractLocalizedValueFromPayload($response, true);
            if ($value === null) {
                continue;
            }
            return $value;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function fetchImagesFromSubresource(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        try {
            $response = $this->apiClient->get(
                '/products/' . $productId . '/images',
                ['include' => 'image,types'],
                true,
                (int) env('CACHE_TTL_PRODUCTS', '300')
            );
        } catch (ApiException) {
            return [];
        }

        $includedIndex = $this->buildIncludedIndex($response['included'] ?? []);
        $images = [];
        foreach ($response['data'] ?? [] as $imageResource) {
            if (!is_array($imageResource)) {
                continue;
            }
            $url = $this->extractImageUrlFromProductImageResource($imageResource, $includedIndex);
            if ($url !== null) {
                $images[] = $url;
            }
        }

        return $this->normalizeImageUrls($images);
    }

    /**
     * @param array<string, array<string, mixed>> $includedIndex
     */
    private function resolveLocalizedValueFromRelationship(
        array $resource,
        string $relationshipName,
        array $includedIndex,
        bool $preferText = false
    ): ?string {
        $relationshipData = $resource['relationships'][$relationshipName]['data'] ?? null;
        if ($relationshipData === null) {
            return null;
        }

        $identifiers = is_array($relationshipData) && array_is_list($relationshipData)
            ? $relationshipData
            : [$relationshipData];

        $defaultValue = null;
        $localizedValue = null;

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
            $value = $this->extractLocalizedValue($attributes, $preferText);
            if ($value === null) {
                continue;
            }

            $localization = $included['relationships']['localization']['data'] ?? null;
            if ($localization === null) {
                $defaultValue = $value;
                break;
            }
            if ($localizedValue === null) {
                $localizedValue = $value;
            }
        }

        return $defaultValue ?? $localizedValue;
    }

    /**
     * @param array<string, mixed>|list<mixed>|string|null $value
     */
    private function extractLocalizedValue(mixed $value, bool $preferText = false): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $preferText ? $this->normalizeText($value) : trim($value);
        }

        if (!is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                $resolved = $this->extractLocalizedValue($item, $preferText);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
            return null;
        }

        if (isset($value['default'])) {
            $resolvedDefault = $this->extractLocalizedValue($value['default'], $preferText);
            if ($resolvedDefault !== null) {
                return $resolvedDefault;
            }
        }

        $scalarFields = ['string', 'value', 'text', 'scalarValue'];
        if ($preferText) {
            $scalarFields = ['text', 'string', 'value', 'scalarValue'];
        }

        foreach ($scalarFields as $field) {
            $candidate = $value[$field] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return $preferText ? $this->normalizeText($candidate) : trim($candidate);
            }
        }

        $wysiwyg = $value['wysiwyg'] ?? null;
        if (is_array($wysiwyg)) {
            $wysiwygValue = $wysiwyg['value'] ?? null;
            if (is_string($wysiwygValue) && trim($wysiwygValue) !== '') {
                return $preferText ? $this->normalizeText($wysiwygValue) : trim($wysiwygValue);
            }
        }

        foreach ($value as $nested) {
            $resolved = $this->extractLocalizedValue($nested, $preferText);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $stripped = strip_tags($value);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim($decoded);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractLocalizedValueFromPayload(array $payload, bool $preferText = false): ?string
    {
        $data = $payload['data'] ?? null;
        if ($data === null) {
            return null;
        }

        $candidates = is_array($data) && array_is_list($data) ? $data : [$data];
        $defaultValue = null;
        $localizedValue = null;

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $attributes = is_array($candidate['attributes'] ?? null) ? $candidate['attributes'] : [];
            $resolved = $this->extractLocalizedValue($attributes, $preferText);
            if ($resolved === null) {
                continue;
            }

            $localization = $candidate['relationships']['localization']['data'] ?? null;
            if ($localization === null) {
                $defaultValue = $resolved;
                break;
            }
            if ($localizedValue === null) {
                $localizedValue = $resolved;
            }
        }

        return $defaultValue ?? $localizedValue;
    }

    /**
     * @param array<string, array<string, mixed>> $includedIndex
     * @return array<int, string>
     */
    private function resolveImagesFromRelationship(array $resource, array $includedIndex): array
    {
        $relationshipData = $resource['relationships']['images']['data'] ?? null;
        if (!is_array($relationshipData)) {
            return [];
        }

        $identifiers = array_is_list($relationshipData) ? $relationshipData : [$relationshipData];
        $images = [];

        foreach ($identifiers as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }
            $type = (string) ($identifier['type'] ?? '');
            $id = (string) ($identifier['id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }

            $includedResource = $includedIndex[$this->includedKey($type, $id)] ?? null;
            if (!is_array($includedResource)) {
                continue;
            }

            if (str_starts_with(strtolower($type), 'productimage')) {
                $imageUrl = $this->extractImageUrlFromProductImageResource($includedResource, $includedIndex);
                if ($imageUrl !== null) {
                    $images[] = $imageUrl;
                }
                continue;
            }

            if (strtolower($type) === 'files') {
                $imageUrl = $this->extractFileUrl($includedResource);
                if ($imageUrl !== null) {
                    $images[] = $imageUrl;
                }
            }
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $productImageResource
     * @param array<string, array<string, mixed>> $includedIndex
     */
    private function extractImageUrlFromProductImageResource(array $productImageResource, array $includedIndex): ?string
    {
        $attributes = is_array($productImageResource['attributes'] ?? null) ? $productImageResource['attributes'] : [];
        foreach (['url', 'externalUrl', 'downloadUrl'] as $field) {
            $url = $attributes[$field] ?? null;
            if (is_string($url) && trim($url) !== '') {
                return $this->absolutizeUrl($url);
            }
        }

        $fileRelation = $productImageResource['relationships']['image']['data'] ?? null;
        if (is_array($fileRelation)) {
            $type = (string) ($fileRelation['type'] ?? '');
            $id = (string) ($fileRelation['id'] ?? '');
            if ($type !== '' && $id !== '') {
                $fileResource = $includedIndex[$this->includedKey($type, $id)] ?? null;
                if (is_array($fileResource)) {
                    return $this->extractFileUrl($fileResource);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fileResource
     */
    private function extractFileUrl(array $fileResource): ?string
    {
        $attributes = is_array($fileResource['attributes'] ?? null) ? $fileResource['attributes'] : [];
        foreach (['url', 'externalUrl', 'downloadUrl'] as $field) {
            $url = $attributes[$field] ?? null;
            if (is_string($url) && trim($url) !== '') {
                return $this->absolutizeUrl($url);
            }
        }

        $links = $fileResource['links'] ?? null;
        if (is_array($links)) {
            foreach (['self', 'related'] as $linkField) {
                $linkValue = $links[$linkField] ?? null;
                if (is_string($linkValue) && trim($linkValue) !== '') {
                    return $this->absolutizeUrl($linkValue);
                }
                if (is_array($linkValue) && is_string($linkValue['href'] ?? null) && trim($linkValue['href']) !== '') {
                    return $this->absolutizeUrl($linkValue['href']);
                }
            }
        }

        return null;
    }

    private function absolutizeUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }
        if (str_starts_with($trimmed, '//')) {
            return 'https:' . $trimmed;
        }
        if (!str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $base = (string) env('ORO_BASE_URL', '');
        if ($base === '') {
            $apiBase = (string) env('ORO_API_BASE_URL', '');
            if ($apiBase !== '') {
                $parts = parse_url($apiBase);
                if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                    $base = $parts['scheme'] . '://' . $parts['host'];
                    if (isset($parts['port'])) {
                        $base .= ':' . $parts['port'];
                    }
                }
            }
        }

        if ($base === '') {
            return $trimmed;
        }

        return rtrim($base, '/') . $trimmed;
    }

    /**
     * @param array<int, string> $images
     * @return array<int, string>
     */
    private function normalizeImageUrls(array $images): array
    {
        $normalized = [];
        foreach ($images as $image) {
            if (!is_string($image)) {
                continue;
            }
            $resolved = $this->absolutizeUrl($image);
            if (trim($resolved) !== '') {
                $normalized[] = $resolved;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function fetchWithIncludeFallback(
        string $path,
        array $query,
        bool $useCache,
        int $cacheTtl = 120
    ): array {
        try {
            return $this->apiClient->get($path, $query, $useCache, $cacheTtl);
        } catch (ApiException $exception) {
            $requestedInclude = (string) ($query['include'] ?? '');
            if ($requestedInclude === '' || $requestedInclude === 'category') {
                throw $exception;
            }

            $fallbackQuery = $query;
            $fallbackQuery['include'] = 'category';
            return $this->apiClient->get($path, $fallbackQuery, $useCache, $cacheTtl);
        }
    }
}
