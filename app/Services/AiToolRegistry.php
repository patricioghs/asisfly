<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class AiToolRegistry
{
    public function __construct(
        private ?AbilityToolProvider $provider = null,
        private ?TenantAbilityService $tenantAbilities = null,
        private ?AbilityPermissionResolver $permissions = null
    ) {
        $this->provider ??= new AbilityToolProvider();
        $this->tenantAbilities ??= new TenantAbilityService();
        $this->permissions ??= new AbilityPermissionResolver();
    }

    public function resolveTools(int $companyId, array $user, array $toolKeys): array
    {
        $allowed = [];
        $blocked = [];

        foreach (array_values(array_unique($toolKeys)) as $toolKey) {
            $decision = $this->authorize($companyId, $user, $toolKey);
            if ($decision['allowed']) {
                $allowed[$toolKey] = $decision['tool'];
            } else {
                $blocked[$toolKey] = $decision;
            }
        }

        return ['allowed' => $allowed, 'blocked' => $blocked];
    }

    public function authorize(int $companyId, array $user, string $toolKey): array
    {
        $tool = $this->provider->tool($toolKey);
        if (!$tool) {
            return $this->decision(false, null, 'Herramienta IA no registrada.');
        }

        if ($companyId <= 0) {
            return $this->decision(false, $tool, 'No hay empresa activa para aislar datos.');
        }

        $abilityKey = (string) ($tool['ability_key'] ?? '');
        if ($abilityKey !== '' && !$this->tenantAbilities->isAbilityActive($companyId, $abilityKey)) {
            return $this->decision(false, $tool, 'La habilidad requerida no esta activa para esta empresa.');
        }

        if (!$this->permissions->allows($user, $tool['permission'] ?? null)) {
            return $this->decision(false, $tool, 'El usuario no tiene permiso para usar esta herramienta.');
        }

        return $this->decision(true, $tool, null);
    }

    public function audit(int $companyId, ?int $userId, string $toolKey, string $status, ?string $reason = null, array $metadata = []): void
    {
        $tool = $this->provider->tool($toolKey);
        $abilityKey = is_array($tool) ? ($tool['ability_key'] ?? null) : null;

        if (!Database::available()) {
            $_SESSION['ai_tool_audit'][] = [
                'company_id' => $companyId,
                'user_id' => $userId,
                'tool_key' => $toolKey,
                'status' => $status,
                'reason' => $reason,
                'metadata' => $metadata,
            ];
            return;
        }

        try {
            Database::connection()->prepare(
                'INSERT INTO ai_tool_audit_logs (company_id, user_id, ability_key, tool_key, action, status, reason, metadata_json)
                 VALUES (:company_id, :user_id, :ability_key, :tool_key, :action, :status, :reason, :metadata_json)'
            )->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
                'ability_key' => $abilityKey,
                'tool_key' => $toolKey,
                'action' => (string) ($tool['action'] ?? 'read'),
                'status' => in_array($status, ['allowed', 'blocked', 'executed', 'fallback'], true) ? $status : 'fallback',
                'reason' => $reason,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {
            // La auditoria no debe romper la respuesta del asistente.
        }
    }

    private function decision(bool $allowed, ?array $tool, ?string $reason): array
    {
        return [
            'allowed' => $allowed,
            'tool' => $tool,
            'reason' => $reason,
        ];
    }
}
