<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public function hit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $_SESSION['_rate_limits'][$key] ??= [];
        $_SESSION['_rate_limits'][$key] = array_values(array_filter(
            $_SESSION['_rate_limits'][$key],
            fn (int $timestamp): bool => $timestamp > $now - $windowSeconds
        ));

        if (count($_SESSION['_rate_limits'][$key]) >= $maxAttempts) {
            return false;
        }

        $_SESSION['_rate_limits'][$key][] = $now;
        return true;
    }
}
