<?php

declare(strict_types=1);

namespace App\Services;

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
                $response = $this->apiClient->get('/api/mastercatalogcategories', $query, true);
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
        );
    }
}
