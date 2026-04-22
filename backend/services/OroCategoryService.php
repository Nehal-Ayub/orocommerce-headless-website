<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

final class OroCategoryService
{
    public function __construct(
        private readonly OroApiClient $apiClient,
        private readonly CacheService $cacheService
    ) {
    }

    public function getCategories(array $query = []): array
    {
        $cacheKey = 'categories_' . sha1(json_encode($query));
        return $this->cacheService->remember(
            $cacheKey,
            (int) env('CACHE_TTL_CATEGORIES', '600'),
            function () use ($query): array {
                try {
                    $response = $this->apiClient->get('/mastercatalogcategories', $query, true);
                    return $this->mapCategoryResponse($response);
                } catch (ApiException $exception) {
                    // Some Oro versions/editions don't expose `mastercatalogcategories`.
                    // Fallback: derive categories from product includes/relationships.
                    if (!str_contains(strtolower($exception->getMessage()), 'unknown entity type')) {
                        throw $exception;
                    }
                }

                $productsResponse = $this->apiClient->get('/products', [
                    'page[number]' => '1',
                    'page[size]' => '200',
                    'include' => 'category',
                    'filter[status]' => 'enabled',
                ], true);

                return $this->deriveCategoriesFromProducts($productsResponse);
            }
        );
    }

    private function mapCategoryResponse(array $response): array
    {
        $categories = [];

        foreach ($response['data'] ?? [] as $category) {
            $attributes = $category['attributes'] ?? [];
            $titles = $attributes['titles'] ?? [];
            $categories[] = [
                'id' => isset($category['id']) ? (int) $category['id'] : 0,
                'name' => (string) ($titles['default'] ?? $attributes['name'] ?? 'Untitled Category'),
                'description' => (string) ($attributes['shortDescriptions']['default'] ?? ''),
                'parentId' => isset($attributes['parentCategory']) ? (int) $attributes['parentCategory'] : null,
            ];
        }

        return [
            'items' => $categories,
            'meta' => [
                'count' => count($categories),
            ],
        ];
    }

    private function deriveCategoriesFromProducts(array $productsResponse): array
    {
        $categoriesById = [];

        foreach ($productsResponse['included'] ?? [] as $resource) {
            $type = (string) ($resource['type'] ?? '');
            if (!str_contains($type, 'category')) {
                continue;
            }

            $attributes = $resource['attributes'] ?? [];
            $titles = $attributes['titles'] ?? [];
            $id = isset($resource['id']) ? (int) $resource['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $categoriesById[$id] = [
                'id' => $id,
                'name' => (string) ($titles['default'] ?? $attributes['name'] ?? 'Untitled Category'),
                'description' => (string) ($attributes['shortDescriptions']['default'] ?? ''),
                'parentId' => isset($attributes['parentCategory']) ? (int) $attributes['parentCategory'] : null,
            ];
        }

        foreach ($productsResponse['data'] ?? [] as $product) {
            $relationship = $product['relationships']['category']['data'] ?? null;
            if (!is_array($relationship)) {
                continue;
            }
            $id = isset($relationship['id']) ? (int) $relationship['id'] : 0;
            if ($id <= 0 || isset($categoriesById[$id])) {
                continue;
            }

            $categoriesById[$id] = [
                'id' => $id,
                'name' => 'Category ' . $id,
                'description' => '',
                'parentId' => null,
            ];
        }

        $categories = array_values($categoriesById);

        return [
            'items' => $categories,
            'meta' => [
                'count' => count($categories),
                'derivedFromProducts' => true,
            ],
        ];
    }
}
