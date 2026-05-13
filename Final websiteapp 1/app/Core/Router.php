<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function any(string $path, array $handler): void
    {
        $this->get($path, $handler);
        $this->post($path, $handler);
    }

    public function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = $this->resolvePath();
        $handler = $this->routes[$method][$path] ?? $this->routes['GET'][$path] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo '404 - Route not found: ' . e($path);
            return;
        }

        try {
            [$controller, $action] = $handler;
            (new $controller())->$action();
        } catch (Throwable $exception) {
            if (str_starts_with($path, '/api/')) {
                Response::json(false, null, $exception->getMessage());
            }
            http_response_code(500);
            echo 'Application error: ' . e($exception->getMessage());
        }
    }

    private function resolvePath(): string
    {
        if (isset($_GET['route'])) {
            return $this->normalize((string) $_GET['route']);
        }

        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = preg_replace('#/(index|pos|api|install)\.php$#', '/', $script);
        $base = preg_replace('#/public/index\.php$#', '/', (string) $base);

        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base) - 1);
        }

        return $this->normalize($uri);
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
