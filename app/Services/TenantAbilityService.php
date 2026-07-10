<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class TenantAbilityService
{
    public function __construct(private ?AbilityRegistry $registry = null)
    {
        $this->registry ??= new AbilityRegistry();
    }

    public function ensureDefaultAbilities(int $companyId): void
    {
        if ($companyId <= 0 || !$this->registry->tablesReady()) {
            return;
        }

        try {
            $sql = "INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_at, activated_at, settings_json)
                    SELECT :company_id, a.id, av.id, 'active', NOW(), NOW(), JSON_OBJECT('auto_installed', TRUE)
                    FROM abilities a
                    LEFT JOIN ability_versions av ON av.ability_id = a.id AND av.version = '1.0.0'
                    WHERE a.is_default_enabled = TRUE
                      AND a.status = 'active'
                    ON DUPLICATE KEY UPDATE
                      ability_version_id = COALESCE(tenant_abilities.ability_version_id, VALUES(ability_version_id))";
            Database::connection()->prepare($sql)->execute(['company_id' => $companyId]);
        } catch (\Throwable) {
            // La capa de habilidades no debe interrumpir el uso actual de la plataforma.
        }
    }

    public function isAbilityActive(int $companyId, string $abilityKey): bool
    {
        if ($companyId <= 0 || !$this->registry->tablesReady()) {
            return true;
        }

        try {
            $statement = Database::connection()->prepare(
                "SELECT COUNT(*)
                 FROM tenant_abilities ta
                 INNER JOIN abilities a ON a.id = ta.ability_id
                 WHERE ta.company_id = :company_id
                   AND a.ability_key = :ability_key
                   AND ta.status = 'active'
                   AND a.status = 'active'"
            );
            $statement->execute([
                'company_id' => $companyId,
                'ability_key' => $abilityKey,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return true;
        }
    }

    public function setStatus(int $companyId, string $abilityKey, string $status, ?int $userId = null): bool
    {
        if ($companyId <= 0 || !$this->registry->tablesReady()) {
            return false;
        }

        $allowed = ['installed', 'active', 'disabled', 'updatable', 'deprecated'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare(
            "SELECT ta.id, ta.status, a.id AS ability_id
             FROM tenant_abilities ta
             INNER JOIN abilities a ON a.id = ta.ability_id
             WHERE ta.company_id = :company_id AND a.ability_key = :ability_key
             LIMIT 1"
        );
        $statement->execute([
            'company_id' => $companyId,
            'ability_key' => $abilityKey,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $pdo->prepare(
            "UPDATE tenant_abilities
             SET status = :status,
                 activated_at = IF(:status_active = 'active', NOW(), activated_at),
                 disabled_at = IF(:status_disabled = 'disabled', NOW(), disabled_at)
             WHERE id = :id"
        )->execute([
            'status' => $status,
            'status_active' => $status,
            'status_disabled' => $status,
            'id' => (int) $row['id'],
        ]);

        $this->audit($companyId, (int) $row['ability_id'], $userId, 'status_changed', (string) $row['status'], $status);

        return true;
    }

    private function audit(int $companyId, int $abilityId, ?int $userId, string $action, ?string $previous, ?string $next): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO ability_audit_logs (company_id, ability_id, user_id, action, previous_status, new_status, metadata_json)
                 VALUES (:company_id, :ability_id, :user_id, :action, :previous_status, :new_status, JSON_OBJECT())'
            )->execute([
                'company_id' => $companyId,
                'ability_id' => $abilityId,
                'user_id' => $userId,
                'action' => $action,
                'previous_status' => $previous,
                'new_status' => $next,
            ]);
        } catch (\Throwable) {
            // Auditoria best-effort para no afectar operacion.
        }
    }
}
