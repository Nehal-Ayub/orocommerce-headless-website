<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ApiException;
use App\Services\CacheService;
use App\Services\OroApiClient;
use App\Services\OroAuthService;
use App\Services\OroCompanyPricingService;
use App\Services\OroProductService;
use App\Services\TokenService;

final class ProductController
{
    private OroProductService $productService;
    private TokenService $tokenService;

    public function __construct()
    {
        $cache = new CacheService();
        $apiClient = new OroApiClient(new OroAuthService($cache), $cache);
        $pricingService = new OroCompanyPricingService($apiClient, $cache);
        $this->productService = new OroProductService($apiClient, $pricingService);
        $this->tokenService = new TokenService();
    }

    public function index(array $request): array
    {
        try {
            $companyId = $this->resolveCompanyId($request);
            $products = $this->productService->getProducts($request['query'] ?? [], $companyId);

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
            $companyId = $this->resolveCompanyId($request);
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

    private function resolveCompanyId(array $request): int
    {
        $companyId = (int) ($request['user']['company_id'] ?? 0);
        if ($companyId > 0) {
            return $companyId;
        }

        $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $authHeader = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return 0;
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return 0;
        }

        $payload = $this->tokenService->validate($token);
        if (!is_array($payload)) {
            return 0;
        }

        return max(0, (int) ($payload['company_id'] ?? 0));
    }
}
