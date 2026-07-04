<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

function env(string $key, mixed $default = null): mixed
{
    static $loaded = false;

    if (!$loaded) {
        $path = dirname(__DIR__, 2) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = array_map('trim', explode('=', $line, 2));
                $_ENV[$name] = $value;
            }
        }
        $loaded = true;
    }

    return $_ENV[$key] ?? getenv($key) ?: $default;
}

function config(string $key, mixed $default = null): mixed
{
    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
    $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
    $values = is_file($path) ? require $path : [];

    return $item ? ($values[$item] ?? $default) : $values;
}

function url(string $path = ''): string
{
    $configured = trim((string) config('app.url', ''));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        return $base . '/' . ltrim($path, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $base = $scheme . '://' . $host . ($scriptDir === '/' ? '' : $scriptDir);

    return $base . '/' . ltrim($path, '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return \App\Core\Csrf::field();
}
