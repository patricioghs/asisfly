<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class MarketplaceService
{
    private const PROTECTED_ABILITIES = ['core_workspace', 'core_company_admin', 'core_superadmin'];
    private const MARKETPLACE_ABILITIES = [
        'core_workspace',
        'core_ai_assistant',
        'core_omnichannel',
        'core_memory_documents',
        'core_integrations',
        'core_company_admin',
        'core_superadmin',
        'crm',
        'quotes',
        'social_marketing',
        'intelligence',
        'ai_brains',
    ];

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

        if ($companyId > 0) {
            $this->tenantAbilities->ensureDefaultAbilities($companyId);
        }

        $allowedAbilityKeys = "'" . implode("','", self::MARKETPLACE_ABILITIES) . "'";
        $sql = "SELECT mi.id AS marketplace_id,
                       mi.slug AS marketplace_slug,
                       mi.title AS marketplace_title,
                       mi.short_description,
                       mi.long_description,
                       mi.pricing_model,
                       mi.monthly_price,
                       mi.currency,
                       mi.sort_order AS marketplace_sort_order,
                       a.id AS ability_id,
                       a.ability_key AS resolved_ability_key,
                       a.name AS ability_name,
                       a.commercial_name,
                       a.description AS ability_description,
                       a.category,
                       a.is_core,
                       ta.status AS tenant_status,
                       av.version AS installed_version,
                       latest.version AS latest_version
                FROM marketplace_items mi
                INNER JOIN abilities a ON a.id = mi.ability_id
                LEFT JOIN tenant_abilities ta ON ta.ability_id = a.id AND ta.company_id = :company_id
                LEFT JOIN ability_versions av ON av.id = ta.ability_version_id
                LEFT JOIN (
                    SELECT ability_id, MAX(version) AS version
                    FROM ability_versions
                    WHERE status = 'stable'
                    GROUP BY ability_id
                ) latest ON latest.ability_id = a.id
                WHERE mi.status = 'published'
                  AND a.status = 'active'
                  AND TRIM(a.ability_key) <> ''
                  AND a.ability_key IN ({$allowedAbilityKeys})
                ORDER BY mi.sort_order, mi.title";
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);

        $items = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['resolved_ability_key'] = trim((string) ($item['resolved_ability_key'] ?? $item['marketplace_slug'] ?? ''));
            $item['dependencies'] = $this->dependencies((int) $item['ability_id'], $companyId);
            $item['is_protected'] = in_array($item['resolved_ability_key'], self::PROTECTED_ABILITIES, true);
            $item['display_title'] = $this->displayTitle($item);
            $item['display_description'] = $this->displayDescription($item);
        }

        return $items;
    }

    public function activate(int $companyId, string $abilityKey, ?int $userId = null): array
    {
        if ($companyId <= 0 || !$this->registry->tablesReady()) {
            return ['ok' => false, 'message' => 'No se pudo acceder al registro de habilidades.'];
        }

        $ability = $this->resolveAbility($abilityKey);
        if (!$ability) {
            return ['ok' => false, 'message' => 'La habilidad seleccionada no existe.'];
        }

        $missingDependencies = $this->missingRequiredDependencies((int) $ability['id'], $companyId);
        if ($missingDependencies) {
            return [
                'ok' => false,
                'message' => 'Activa primero las dependencias requeridas: ' . implode(', ', $missingDependencies) . '.',
            ];
        }

        $pdo = Database::connection();
        $versionId = $pdo->prepare("SELECT id FROM ability_versions WHERE ability_id = :ability_id AND status = 'stable' ORDER BY released_at DESC, id DESC LIMIT 1");
        $versionId->execute(['ability_id' => (int) $ability['id']]);

        $pdo->prepare(
            "INSERT INTO tenant_abilities (company_id, ability_id, ability_version_id, status, installed_by, installed_at, activated_at, settings_json)
             VALUES (:company_id, :ability_id, :ability_version_id, 'active', :installed_by, NOW(), NOW(), JSON_OBJECT('marketplace', TRUE))
             ON DUPLICATE KEY UPDATE status = 'active', activated_at = NOW(), disabled_at = NULL, settings_json = JSON_SET(COALESCE(settings_json, JSON_OBJECT()), '$.marketplace', TRUE)"
        )->execute([
            'company_id' => $companyId,
            'ability_id' => (int) $ability['id'],
            'ability_version_id' => (int) ($versionId->fetchColumn() ?: 0) ?: null,
            'installed_by' => $userId,
        ]);

        return ['ok' => true, 'message' => 'Habilidad activada correctamente.'];
    }

    public function disable(int $companyId, string $abilityKey, ?int $userId = null): array
    {
        $ability = $this->resolveAbility($abilityKey);
        if (!$ability) {
            return ['ok' => false, 'message' => 'La habilidad seleccionada no existe.'];
        }

        $resolvedKey = (string) $ability['ability_key'];
        if (in_array($resolvedKey, self::PROTECTED_ABILITIES, true)) {
            return ['ok' => false, 'message' => 'Esta habilidad es critica para operar AsisFly y no se puede desactivar desde Marketplace.'];
        }

        if ($this->activeDependents((int) $ability['id'], $companyId)) {
            return ['ok' => false, 'message' => 'Hay habilidades activas que dependen de esta. Desactivalas primero.'];
        }

        $disabled = $this->tenantAbilities->setStatus($companyId, $resolvedKey, 'disabled', $userId);

        return [
            'ok' => $disabled,
            'message' => $disabled ? 'Habilidad desactivada. No se borraron datos.' : 'No se pudo desactivar la habilidad.',
        ];
    }

    private function dependencies(int $abilityId, int $companyId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.ability_key, a.commercial_name, d.dependency_type, ta.status AS tenant_status
             FROM ability_dependencies d
             INNER JOIN abilities a ON a.id = d.depends_on_ability_id
             LEFT JOIN tenant_abilities ta ON ta.ability_id = a.id AND ta.company_id = :company_id
             WHERE d.ability_id = :ability_id
             ORDER BY FIELD(d.dependency_type, 'required', 'recommended', 'conflicts'), a.sort_order"
        );
        $statement->execute([
            'ability_id' => $abilityId,
            'company_id' => $companyId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolveAbility(string $abilityKeyOrSlug): ?array
    {
        $key = trim($abilityKeyOrSlug);
        if ($key === '') {
            return null;
        }

        $ability = $this->registry->findByKey($key);
        if ($ability) {
            return $ability;
        }

        if (ctype_digit($key)) {
            $statement = Database::connection()->prepare('SELECT * FROM abilities WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $key]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }

            $statement = Database::connection()->prepare(
                'SELECT a.*
                 FROM marketplace_items mi
                 INNER JOIN abilities a ON a.id = mi.ability_id
                 WHERE mi.id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => (int) $key]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $statement = Database::connection()->prepare(
            'SELECT a.*
             FROM marketplace_items mi
             INNER JOIN abilities a ON a.id = mi.ability_id
             WHERE mi.slug = :slug
             LIMIT 1'
        );
        $statement->execute(['slug' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function displayTitle(array $item): string
    {
        $key = (string) ($item['resolved_ability_key'] ?? '');
        $officialLabel = self::abilityLabel($key);
        if ($officialLabel !== '') {
            return $officialLabel;
        }

        foreach (['marketplace_title', 'commercial_name', 'ability_name'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return self::abilityLabel($key) ?: 'Habilidad AsisFly';
    }

    private function displayDescription(array $item): string
    {
        $key = (string) ($item['resolved_ability_key'] ?? '');
        $officialDescription = self::abilityDescription($key);
        if ($officialDescription !== '') {
            return $officialDescription;
        }

        foreach (['long_description', 'short_description', 'ability_description'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Habilidad modular de AsisFly lista para activar por empresa.';
    }

    public static function abilityLabel(string $abilityKey): string
    {
        $labels = [
            'core_workspace' => 'Operacion diaria',
            'core_ai_assistant' => 'Asistente inteligente',
            'core_omnichannel' => 'Comunicacion inteligente',
            'core_memory_documents' => 'Memoria empresarial',
            'core_integrations' => 'Integraciones base',
            'core_company_admin' => 'Administracion de empresa',
            'core_superadmin' => 'Administracion global',
            'crm' => 'CRM comercial',
            'quotes' => 'Cotizaciones',
            'social_marketing' => 'Asisti Social',
            'intelligence' => 'Inteligencia empresarial',
            'ai_brains' => 'Cerebros IA',
        ];

        return $labels[$abilityKey] ?? '';
    }

    public static function abilityDescription(string $abilityKey): string
    {
        $descriptions = [
            'core_workspace' => 'Dashboard, Mi dia, Bandeja de trabajo y Notificaciones para operar cada jornada.',
            'core_ai_assistant' => 'Chat IA, tareas, automatizaciones, aprobaciones, controles y reglas del asistente.',
            'core_omnichannel' => 'Bandeja omnicanal y cuentas conectadas para centralizar correos y mensajes.',
            'core_memory_documents' => 'Documentos, base de conocimiento, catalogos, manuales y entrenamiento empresarial.',
            'core_integrations' => 'Integraciones, APIs y conectores base para conectar AsisFly con otras plataformas.',
            'core_company_admin' => 'Empresa, usuarios, roles, plan, facturacion y configuracion general.',
            'core_superadmin' => 'Panel global para administrar empresas, planes, consumo IA, auditoria y estado del sistema.',
            'crm' => 'Clientes, contactos, oportunidades, tareas, notas y seguimiento comercial.',
            'quotes' => 'Cotizaciones, productos, impuestos, descuentos, estados y envio comercial.',
            'social_marketing' => 'Calendario editorial, ideas, copies, campanas, hashtags y publicaciones por canal.',
            'intelligence' => 'Reportes, analytics, dashboards, Excel, indicadores, KPIs y analisis de negocio.',
            'ai_brains' => 'Cerebros comercial, administrativo, analitico, operacional y ejecutivo por empresa.',
        ];

        return $descriptions[$abilityKey] ?? '';
    }

    private function missingRequiredDependencies(int $abilityId, int $companyId): array
    {
        $missing = [];
        foreach ($this->dependencies($abilityId, $companyId) as $dependency) {
            if (($dependency['dependency_type'] ?? '') === 'required' && ($dependency['tenant_status'] ?? '') !== 'active') {
                $missing[] = (string) $dependency['commercial_name'];
            }
        }

        return $missing;
    }

    private function activeDependents(int $abilityId, int $companyId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM ability_dependencies d
             INNER JOIN tenant_abilities ta ON ta.ability_id = d.ability_id
             WHERE d.depends_on_ability_id = :ability_id
               AND d.dependency_type = 'required'
               AND ta.company_id = :company_id
               AND ta.status = 'active'"
        );
        $statement->execute([
            'ability_id' => $abilityId,
            'company_id' => $companyId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
