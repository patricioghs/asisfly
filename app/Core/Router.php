<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[$method][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir)) ?: '/';
        }

        if ($method === 'POST' && !$this->isCsrfExempt($path) && !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'Sesion expirada o formulario invalido. Intenta nuevamente.';
            $fallback = $_SERVER['HTTP_REFERER'] ?? '/login';
            $target = parse_url($fallback, PHP_URL_PATH) ?: '/login';
            header('Location: ' . $target);
            return;
        }

        $handler = $this->routes[$method][$this->normalize($path)] ?? null;
        if (!$handler) {
            http_response_code(404);
            echo 'Ruta no encontrada';
            return;
        }

        [$class, $action] = $handler;
        (new $class())->{$action}();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function isCsrfExempt(string $path): bool
    {
        return str_starts_with($this->normalize($path), '/webhooks/');
    }
}
