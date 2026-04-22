<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiException;

final class CheckoutService
{
    public function __construct(
        private readonly OroApiClient $oroApiClient,
        private readonly OroCompanyPricingService $pricingService
    ) {
    }

    /**
     * Implements Oro Checkout API style orchestration:
     * billing + shipping + payment + line items -> checkout create.
     */
    public function placeOrder(int $companyId, array $payload): array
    {
        $lineItems = $payload['lineItems'] ?? [];
        if (!is_array($lineItems) || $lineItems === []) {
            throw new ApiException('Checkout requires at least one line item.', 422);
        }

        $requestedProductIds = [];
        foreach ($lineItems as $lineItem) {
            $requestedProductIds[] = (int) ($lineItem['productId'] ?? 0);
        }

        $requestedProductIds = array_values(array_unique(array_filter($requestedProductIds)));
        $priceMap = $this->pricingService->getProductPricesForCompany($companyId, $requestedProductIds);

        $preparedLineItems = [];
        $subtotal = 0.0;

        foreach ($lineItems as $lineItem) {
            $productId = (int) ($lineItem['productId'] ?? 0);
            $quantity = max(1, (int) ($lineItem['quantity'] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            $price = $priceMap[$productId] ?? null;
            if ($price === null) {
                throw new ApiException(
                    sprintf('No company price found for product %d.', $productId),
                    422
                );
            }

            $unitPrice = (float) $price['amount'];
            $subtotal += $unitPrice * $quantity;

            $preparedLineItems[] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'currency' => (string) ($price['currency'] ?? 'USD'),
                'priceListId' => $price['priceListId'] ?? null,
            ];
        }

        $checkoutPayload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'currency' => (string) ($payload['currency'] ?? 'USD'),
                    'billingAddress' => $payload['billingAddress'] ?? [],
                    'shippingAddress' => $payload['shippingAddress'] ?? ($payload['billingAddress'] ?? []),
                    'shippingMethod' => $payload['shippingMethod'] ?? '',
                    'paymentMethod' => $payload['paymentMethod'] ?? '',
                    'lineItems' => $preparedLineItems,
                    'subtotal' => round($subtotal, 2),
                ],
            ],
        ];

        $checkoutResponse = $this->oroApiClient->post('/checkouts', $checkoutPayload, true);
        $orderId = $checkoutResponse['data']['id'] ?? null;

        return [
            'orderId' => $orderId,
            'orderNumber' => $checkoutResponse['data']['attributes']['poNumber'] ?? (string) ($orderId ?? 'pending'),
            'subtotal' => round($subtotal, 2),
            'currency' => (string) ($payload['currency'] ?? 'USD'),
            'lineItems' => $preparedLineItems,
            'raw' => $checkoutResponse,
        ];
    }
}
