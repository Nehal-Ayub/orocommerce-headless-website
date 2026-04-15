<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CacheService;
use App\Services\OroApiClient;
use App\Services\OroAuthService;
use App\Services\OroCategoryService;

final class CategoryController
{
    private OroCategoryService $categoryService;

    public function __construct()
    {
        $cache = new CacheService();
        $apiClient = new OroApiClient(new OroAuthService($cache), $cache);
        $this->categoryService = new OroCategoryService($apiClient, $cache);
    }

    public function index(array $request): array
    {
        $query = $request['query'] ?? [];
        $categories = $this->categoryService->listCategories($query);

        return [
            'status' => 200,
            'data' => [
                'data' => $categories,
                'meta' => ['count' => count($categories)],
            ],
        ];
    }
}
