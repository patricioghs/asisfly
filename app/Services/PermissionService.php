<?php

declare(strict_types=1);

namespace App\Services;

final class PermissionService
{
    public function allows(array $user, string $permission): bool
    {
        $permissions = $user['permissions'] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
