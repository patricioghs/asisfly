<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AiProviderRepository;

final class AiContextBuilder
{
    public function __construct(
        private ?AbilityRegistry $abilityRegistry = null,
        private ?AbilityToolProvider $toolProvider = null,
        private ?AiToolRegistry $toolRegistry = null,
        private ?AiProviderRepository $aiProvider = null
    ) {
        $this->abilityRegistry ??= new AbilityRegistry();
        $this->toolProvider ??= new AbilityToolProvider();
        $this->toolRegistry ??= new AiToolRegistry($this->toolProvider);
        $this->aiProvider ??= new AiProviderRepository();
    }

    public function build(array $company, array $user, array $route, string $prompt): array
    {
        $companyId = (int) ($company['id'] ?? 0);
        $toolKeys = $this->toolProvider->routeTools($route, $prompt);
        $tools = $this->toolRegistry->resolveTools($companyId, $user, $toolKeys);

        return [
            'company' => [
                'id' => $companyId,
                'name' => $company['name'] ?? 'Empresa',
                'country' => $company['country'] ?? 'LatAm',
                'currency' => $company['currency'] ?? 'USD',
                'plan' => $company['plan'] ?? 'Starter',
            ],
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'role' => $user['role'] ?? 'Usuario',
                'permissions' => $user['permissions'] ?? [],
            ],
            'route' => $route,
            'abilities' => $this->abilityRegistry->activeAbilitiesForTenant($companyId),
            'tools' => $tools,
            'plan_limits' => $this->aiProvider->planLimits($companyId),
        ];
    }

    public function isToolAllowed(array $context, string $toolKey): bool
    {
        return isset($context['tools']['allowed'][$toolKey]);
    }

    public function blockedTool(array $context, string $toolKey): ?array
    {
        return $context['tools']['blocked'][$toolKey] ?? null;
    }
}
