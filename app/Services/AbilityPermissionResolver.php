<?php

declare(strict_types=1);

namespace App\Services;

final class AbilityPermissionResolver
{
    public function allows(array $user, ?string $permission): bool
    {
        if ($permission === null || $permission === '') {
            return true;
        }

        $permissions = $user['permissions'] ?? [];

        if ($permission === '*') {
            return in_array('*', $permissions, true) || (($user['role'] ?? '') === 'Superadmin');
        }

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
