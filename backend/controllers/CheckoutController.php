<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ApiException;
use App\Services\CacheService;
use App\Services\CheckoutService;
use App\Services\OroApiClient;
use App\Services\OroAuthService;
use App\Services\OroCompanyPricingService;

final class CheckoutController
{
    private CheckoutService $checkoutService;

    public function __construct()
    {
        $cache = new CacheService();
        $apiClient = new OroApiClient(new OroAuthService($cache), $cache);
        $pricing = new OroCompanyPricingService($apiClient, $cache);
        $this->checkoutService = new CheckoutService($apiClient, $pricing);
    }

    public function checkout(array $request): array
    {
        $companyId = (int) ($request['user']['company_id'] ?? 0);
        if ($companyId <= 0) {
            return ['status' => 401, 'data' => ['message' => 'Unauthorized']];
        }

        try {
            $order = $this->checkoutService->placeOrder(
                $companyId,
                $request['body'] ?? []
            );

            return ['status' => 201, 'data' => ['data' => $order]];
        } catch (ApiException $exception) {
            return ['status' => $exception->getStatusCode(), 'data' => ['message' => $exception->getMessage()]];
        } catch (\Throwable $exception) {
            return ['status' => 500, 'data' => ['message' => $exception->getMessage()]];
        }
    }

    public function start(array $request): array
    {
        return $this->checkout($request);
    }

    public function shippingMethods(array $request): array
    {
        return [
            'status' => 200,
            'data' => [
                'data' => [
                    ['code' => 'standard', 'label' => 'Standard Ground', 'price' => 12.0],
                    ['code' => 'express', 'label' => 'Express', 'price' => 25.0],
                ],
            ],
        ];
    }

    public function paymentMethods(array $request): array
    {
        return [
            'status' => 200,
            'data' => [
                'data' => [
                    ['code' => 'credit_card', 'label' => 'Credit Card'],
                    ['code' => 'purchase_order', 'label' => 'Purchase Order'],
                ],
            ],
        ];
    }

    public function review(array $request): array
    {
        $body = $request['body'] ?? [];
        return [
            'status' => 200,
            'data' => [
                'data' => [
                    'billingAddress' => $body['billingAddress'] ?? [],
                    'shippingMethod' => $body['shippingMethod'] ?? '',
                    'paymentMethod' => $body['paymentMethod'] ?? '',
                    'lineItems' => $body['lineItems'] ?? [],
                ],
            ],
        ];
    }
}
