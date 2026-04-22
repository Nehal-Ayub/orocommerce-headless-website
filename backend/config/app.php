<?php

declare(strict_types=1);

return [
    'app_name' => getenv('APP_NAME') ?: 'Oro Headless Middleware',
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8080',
    'frontend_url' => getenv('FRONTEND_URL') ?: 'http://localhost:5173',
    'session_secret' => getenv('SESSION_SECRET') ?: 'change-me',
    'jwt_ttl' => (int) (getenv('JWT_TTL') ?: 3600),
    'cache_ttl_products' => (int) (getenv('CACHE_TTL_PRODUCTS') ?: 300),
    'cache_ttl_categories' => (int) (getenv('CACHE_TTL_CATEGORIES') ?: 600),
    'cache_ttl_price_lists' => (int) (getenv('CACHE_TTL_PRICE_LISTS') ?: 300),
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
];
