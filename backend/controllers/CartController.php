<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ApiException;
use App\Services\CartService;

final class CartController
{
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
    }

    public function index(array $request): array
    {
        try {
            $companyId = (int) ($request['user']['company_id'] ?? 0);
            if ($companyId <= 0) {
                throw new ApiException('Company context is required.', 400);
            }

            return [
                'status' => 200,
                'data' => [
                    'data' => $this->cartService->get((string) $companyId),
                ],
            ];
        } catch (ApiException $exception) {
            return [
                'status' => $exception->getStatusCode(),
                'data' => ['message' => $exception->getMessage()],
            ];
        }
    }

    public function update(array $request): array
    {
        try {
            $companyId = (int) ($request['user']['company_id'] ?? 0);
            if ($companyId <= 0) {
                throw new ApiException('Company context is required.', 400);
            }

            return [
                'status' => 200,
                'data' => [
                    'data' => $this->cartService->upsert((string) $companyId, $request['body'] ?? []),
                ],
            ];
        } catch (ApiException $exception) {
            return [
                'status' => $exception->getStatusCode(),
                'data' => ['message' => $exception->getMessage()],
            ];
        }
    }
}
