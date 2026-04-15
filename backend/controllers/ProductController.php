<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ApiException;
use App\Services\CacheService;
use App\Services\OroApiClient;
use App\Services\OroAuthService;
use App\Services\OroCompanyPricingService;
use App\Services\OroProductService;

final class ProductController
{
    private OroProductService $productService;

    public function __construct()
    {
        $cache = new CacheService();
        $apiClient = new OroApiClient(new OroAuthService($cache), $cache);
        $pricingService = new OroCompanyPricingService($apiClient, $cache);
        $this->productService = new OroProductService($apiClient, $pricingService);
    }

    public function index(array $request): array
    {
        try {
            $companyId = (int) ($request['user']['company_id'] ?? 0);
            $products = $this->productService->listProducts($request['query'] ?? [], $companyId);

            return [
                'status' => 200,
                'data' => [
                    'data' => $products['items'] ?? [],
                    'meta' => $products['pagination'] ?? [],
                ],
            ];
        } catch (ApiException $exception) {
            return [
                'status' => $exception->getStatusCode(),
                'data' => ['message' => $exception->getMessage()],
            ];
        }
    }

    public function show(array $request): array
    {
        try {
            $companyId = (int) ($request['user']['company_id'] ?? 0);
            $productId = (int) ($request['params']['id'] ?? 0);
            $product = $this->productService->getProductById($productId, $companyId);

            return [
                'status' => 200,
                'data' => $product,
            ];
        } catch (ApiException $exception) {
            return [
                'status' => $exception->getStatusCode(),
                'data' => ['message' => $exception->getMessage()],
            ];
        }
    }
}
