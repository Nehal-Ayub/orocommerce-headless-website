<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ApiException;
use App\Services\ContactService;

final class ContactController
{
    private ContactService $contactService;

    public function __construct()
    {
        $this->contactService = new ContactService();
    }

    public function submit(array $request): array
    {
        try {
            $result = $this->contactService->submit($request['body'] ?? []);
            return [
                'status' => 201,
                'data' => [
                    'message' => 'Message submitted successfully.',
                    'data' => $result,
                ],
            ];
        } catch (ApiException $exception) {
            return [
                'status' => $exception->getStatusCode(),
                'data' => [
                    'message' => $exception->getMessage(),
                    'errors' => $exception->getContext(),
                ],
            ];
        }
    }

    public function health(array $request): array
    {
        return [
            'status' => 200,
            'data' => [
                'status' => 'ok',
                'service' => 'orocommerce-headless-middleware',
            ],
        ];
    }
}
