<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\CheckoutController;
use App\Controllers\ContactController;
use App\Controllers\ProductController;
use App\Middlewares\AuthMiddleware;

return [
    [
        'method' => 'POST',
        'path' => '/api/auth/login',
        'controller' => AuthController::class,
        'action' => 'login',
        // 'middlewares' => [],
    ],
    [
        'method' => 'GET',
        'path' => '/api/products',
        'controller' => ProductController::class,
        'action' => 'index',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'GET',
        'path' => '/api/product/{id}',
        'controller' => ProductController::class,
        'action' => 'show',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'GET',
        'path' => '/api/categories',
        'controller' => CategoryController::class,
        'action' => 'index',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'GET',
        'path' => '/api/cart',
        'controller' => CartController::class,
        'action' => 'index',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'POST',
        'path' => '/api/cart',
        'controller' => CartController::class,
        'action' => 'update',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'POST',
        'path' => '/api/checkout/place',
        'controller' => CheckoutController::class,
        'action' => 'process',
        // 'middlewares' => [AuthMiddleware::class],
    ],
    [
        'method' => 'POST',
        'path' => '/api/contact',
        'controller' => ContactController::class,
        'action' => 'submit',
        // 'middlewares' => [],
    ],
    [
        'method' => 'GET',
        'path' => '/health',
        'controller' => ContactController::class,
        'action' => 'health',
        // 'middlewares' => [],
    ],
];
