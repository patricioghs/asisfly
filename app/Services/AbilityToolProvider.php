<?php

declare(strict_types=1);

namespace App\Services;

final class AbilityToolProvider
{
    public function tools(): array
    {
        return [
            'memory.search' => [
                'label' => 'Buscar memoria empresarial',
                'ability_key' => 'core_memory_documents',
                'permission' => 'documents.manage',
                'risk' => 'low',
                'action' => 'read',
            ],
            'social.generate_campaign' => [
                'label' => 'Generar campana Asisti Social',
                'ability_key' => 'social_marketing',
                'permission' => 'chat.use',
                'risk' => 'medium',
                'action' => 'write',
            ],
            'action_center.create_suggestion' => [
                'label' => 'Crear accion sugerida',
                'ability_key' => 'core_ai_assistant',
                'permission' => 'chat.use',
                'risk' => 'medium',
                'action' => 'write',
            ],
            'controls.create' => [
                'label' => 'Crear control operativo',
                'ability_key' => 'core_ai_assistant',
                'permission' => 'chat.use',
                'risk' => 'medium',
                'action' => 'write',
            ],
            'crm.assist' => [
                'label' => 'Asistir CRM comercial',
                'ability_key' => 'crm',
                'permission' => 'crm.manage',
                'risk' => 'low',
                'action' => 'read',
            ],
            'quotes.assist' => [
                'label' => 'Asistir cotizaciones',
                'ability_key' => 'quotes',
                'permission' => 'quotes.manage',
                'risk' => 'low',
                'action' => 'read',
            ],
            'intelligence.analyze' => [
                'label' => 'Analizar inteligencia empresarial',
                'ability_key' => 'intelligence',
                'permission' => 'dashboard.view',
                'risk' => 'low',
                'action' => 'read',
            ],
            'omnichannel.assist' => [
                'label' => 'Asistir comunicacion omnicanal',
                'ability_key' => 'core_omnichannel',
                'permission' => 'chat.use',
                'risk' => 'medium',
                'action' => 'read',
            ],
            'documents.assist' => [
                'label' => 'Asistir documentos',
                'ability_key' => 'core_memory_documents',
                'permission' => 'documents.manage',
                'risk' => 'low',
                'action' => 'read',
            ],
        ];
    }

    public function tool(string $toolKey): ?array
    {
        return $this->tools()[$toolKey] ?? null;
    }

    public function routeTools(array $route, string $prompt = ''): array
    {
        $module = (string) ($route['module'] ?? '');
        $text = strtolower($prompt);

        return match ($module) {
            'Asisti Social' => ['social.generate_campaign', 'action_center.create_suggestion'],
            'Comercial' => str_contains($text, 'cotizacion') || str_contains($text, 'cotizaciones')
                ? ['quotes.assist', 'crm.assist', 'action_center.create_suggestion']
                : ['crm.assist', 'action_center.create_suggestion'],
            'Analitica' => ['intelligence.analyze', 'memory.search', 'action_center.create_suggestion'],
            'Administracion' => ['documents.assist', 'memory.search', 'action_center.create_suggestion'],
            'Operaciones' => ['omnichannel.assist', 'intelligence.analyze', 'action_center.create_suggestion'],
            default => ['action_center.create_suggestion'],
        };
    }
}
