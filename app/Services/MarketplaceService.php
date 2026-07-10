<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class MarketplaceService
{
    public function __construct(
        private ?AbilityRegistry $registry = null,
        private ?TenantAbilityService $tenantAbilities = null
    ) {
        $this->registry ??= new AbilityRegistry();
        $this->tenantAbilities ??= new TenantAbilityService($this->registry);
    }

    public function items(int $companyId = 0): array
    {
        if (!$this->registry->tablesReady()) {
            return [];
        }

        $sql = "SELECT mi.*, a.ability_key, a.category, a.is_core,
                       ta.status AS tenant_status
                FROM marketplace_items mi
                INNER JOIN abilities a ON a.id = mi.ability_id
                LEFT JOIN tenant_abilities ta ON ta.ability_id = a.id AND ta.company_id = :company_id
                WHERE mi.status = 'published'
                  AND a.status = 'active'
                ORDER BY mi.sort_order, mi.title";
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activate(int $companyId, string $abilityKey, ?int $userId = null): bool
    {
        if ($companyId <= 0 || !$this->registry->tablesReady()) {
            return false;
        }

        $ability = $this->registry->findByKey($abilityKey);
        if (!$ability) {
            return false;
        }

        $pdo = Database::connection();
        $versionId = $pdo->prepare("SELECT id FROM ability_versions WHERE ability_id = :ability_id AND status = 'stable' ORDER BY released_at DESC, id DESC LIMIT 1");
        $versionId->execute(['ability_id' => (int) $ability['id']]);

        $pdo->prepare(
            "INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_by, installed_at, activated_at, settings_json)
             VALUES (:company_id, :ability_id, :ability_version_id, 'active', :installed_by, NOW(), NOW(), JSON_OBJECT('marketplace', TRUE))
             ON DUPLICATE KEY UPDATE status = 'active', activated_at = NOW(), disabled_at = NULL"
        )->execute([
            'company_id' => $companyId,
            'ability_id' => (int) $ability['id'],
            'ability_version_id' => (int) ($versionId->fetchColumn() ?: 0) ?: null,
            'installed_by' => $userId,
        ]);

        return true;
    }

    public function disable(int $companyId, string $abilityKey, ?int $userId = null): bool
    {
        return $this->tenantAbilities->setStatus($companyId, $abilityKey, 'disabled', $userId);
    }
}
