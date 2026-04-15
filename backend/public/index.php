<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';

$routes = require __DIR__ . '/../routes/api.php';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

$requestBody = file_get_contents('php://input');
$payload = $requestBody ? json_decode($requestBody, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

$request = [
    'query' => $_GET,
    'body' => $payload,
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'method' => $method,
    'uri' => $uri,
];

try {
    foreach ($routes as $route) {
        if (($route['method'] ?? 'GET') !== $method) {
            continue;
        }

        $pattern = '#^' . preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $route['path']) . '$#';
        if (!preg_match($pattern, $uri, $matches)) {
            continue;
        }

        $params = array_filter(
            $matches,
            static fn ($key) => !is_int($key),
            ARRAY_FILTER_USE_KEY
        );

        $requestForRoute = $request;
        $requestForRoute['params'] = $params;

        foreach (($route['middlewares'] ?? []) as $middlewareClass) {
            $middleware = new $middlewareClass();
            $requestForRoute = $middleware->handle($requestForRoute);
        }

        $controllerClass = $route['controller'];
        $action = $route['action'];
        $controller = new $controllerClass();
        $response = $controller->{$action}($requestForRoute);

        if (!is_array($response) || !array_key_exists('data', $response)) {
            json_response(['message' => 'Invalid route response'], 500);
        }

        json_response($response['data'], (int) ($response['status'] ?? 200));
    }

    json_response(['message' => 'Route not found'], 404);
} catch (Throwable $exception) {
    json_response(
        ['message' => $exception->getMessage()],
        method_exists($exception, 'getCode') && is_int($exception->getCode()) && $exception->getCode() >= 400
            ? $exception->getCode()
            : 500
    );
}
