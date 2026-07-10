<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class MarketplaceService
{
    private const PROTECTED_ABILITIES = ['core_workspace', 'core_company_admin', 'core_superadmin'];
    private const CATALOG = [
        'core_workspace' => ['name' => 'Core Workspace', 'label' => 'Operacion diaria', 'category' => 'core', 'description' => 'Dashboard, Mi dia, Bandeja de trabajo y Notificaciones para operar cada jornada.', 'is_core' => true, 'default' => true, 'sort' => 10],
        'core_ai_assistant' => ['name' => 'Core AI Assistant', 'label' => 'Asistente inteligente', 'category' => 'core', 'description' => 'Chat IA, tareas, automatizaciones, aprobaciones, controles y reglas del asistente.', 'is_core' => true, 'default' => true, 'sort' => 20],
        'core_omnichannel' => ['name' => 'Core Omnichannel', 'label' => 'Comunicacion inteligente', 'category' => 'base', 'description' => 'Bandeja omnicanal y cuentas conectadas para centralizar correos y mensajes.', 'is_core' => true, 'default' => false, 'sort' => 30],
        'core_memory_documents' => ['name' => 'Core Memory Documents', 'label' => 'Memoria empresarial', 'category' => 'base', 'description' => 'Documentos, base de conocimiento, catalogos, manuales y entrenamiento empresarial.', 'is_core' => true, 'default' => true, 'sort' => 40],
        'core_integrations' => ['name' => 'Core Integrations', 'label' => 'Integraciones base', 'category' => 'core', 'description' => 'Integraciones, APIs y conectores base para conectar AsisFly con otras plataformas.', 'is_core' => true, 'default' => true, 'sort' => 50],
        'core_company_admin' => ['name' => 'Core Company Admin', 'label' => 'Administracion de empresa', 'category' => 'core', 'description' => 'Empresa, usuarios, roles, plan, facturacion y configuracion general.', 'is_core' => true, 'default' => true, 'sort' => 60],
        'core_superadmin' => ['name' => 'Core Superadmin', 'label' => 'Administracion global', 'category' => 'core', 'description' => 'Panel global para administrar empresas, planes, consumo IA, auditoria y estado del sistema.', 'is_core' => true, 'default' => true, 'sort' => 70],
        'crm' => ['name' => 'CRM', 'label' => 'CRM comercial', 'category' => 'professional', 'description' => 'Clientes, contactos, oportunidades, tareas, notas y seguimiento comercial.', 'is_core' => false, 'default' => false, 'sort' => 100],
        'quotes' => ['name' => 'Quotes', 'label' => 'Cotizaciones', 'category' => 'professional', 'description' => 'Cotizaciones, productos, impuestos, descuentos, estados y envio comercial.', 'is_core' => false, 'default' => false, 'sort' => 110],
        'social_marketing' => ['name' => 'Social Marketing', 'label' => 'Asisti Social', 'category' => 'professional', 'description' => 'Calendario editorial, ideas, copies, campanas, hashtags y publicaciones por canal.', 'is_core' => false, 'default' => false, 'sort' => 120],
        'intelligence' => ['name' => 'Business Intelligence', 'label' => 'Inteligencia empresarial', 'category' => 'professional', 'description' => 'Reportes, analytics, dashboards, Excel, indicadores, KPIs y analisis de negocio.', 'is_core' => false, 'default' => false, 'sort' => 130],
        'ai_brains' => ['name' => 'AI Brains', 'label' => 'Cerebros IA', 'category' => 'base', 'description' => 'Cerebros comercial, administrativo, analitico, operacional y ejecutivo por empresa.', 'is_core' => true, 'default' => true, 'sort' => 140],
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

        $this->ensureCanonicalCatalog();

        if ($companyId > 0) {
            $this->tenantAbilities->ensureDefaultAbilities($companyId);
        }

        $allowedAbilityKeys = "'" . implode("','", array_keys(self::CATALOG)) . "'";
        $sql = "SELECT mi.id AS marketplace_id,
                       COALESCE(mi.slug, a.ability_key) AS marketplace_slug,
                       COALESCE(mi.title, a.commercial_name) AS marketplace_title,
                       COALESCE(mi.short_description, LEFT(a.description, 255)) AS short_description,
                       COALESCE(mi.long_description, a.description) AS long_description,
                       COALESCE(mi.pricing_model, IF(a.category IN ('core','base'), 'included', 'addon')) AS pricing_model,
                       COALESCE(mi.monthly_price, 0) AS monthly_price,
                       COALESCE(mi.currency, 'USD') AS currency,
                       COALESCE(mi.sort_order, a.sort_order) AS marketplace_sort_order,
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
                FROM abilities a
                LEFT JOIN marketplace_items mi ON mi.ability_id = a.id AND mi.slug = a.ability_key
                LEFT JOIN tenant_abilities ta ON ta.ability_id = a.id AND ta.company_id = :company_id
                LEFT JOIN ability_versions av ON av.id = ta.ability_version_id
                LEFT JOIN (
                    SELECT ability_id, MAX(version) AS version
                    FROM ability_versions
                    WHERE status = 'stable'
                    GROUP BY ability_id
                ) latest ON latest.ability_id = a.id
                WHERE a.status = 'active'
                  AND TRIM(a.ability_key) <> ''
                  AND a.ability_key IN ({$allowedAbilityKeys})
                ORDER BY a.sort_order, a.commercial_name";
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

        $this->ensureCanonicalCatalog();

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
        $this->ensureCanonicalCatalog();

        $ability = $this->resolveAbility($abilityKey);
        if (!$ability) {
            return ['ok' => false, 'message' => 'La habilidad seleccionada no existe.'];
        }

        $resolvedKey = (string) $ability['ability_key'];
        if (!isset(self::CATALOG[$resolvedKey])) {
            return ['ok' => false, 'message' => 'La habilidad seleccionada no forma parte del catalogo oficial.'];
        }

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
            if ($row && isset(self::CATALOG[(string) $row['ability_key']])) {
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
            if ($row && isset(self::CATALOG[(string) $row['ability_key']])) {
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

        return ($row && isset(self::CATALOG[(string) $row['ability_key']])) ? $row : null;
    }

    private function ensureCanonicalCatalog(): void
    {
        $pdo = Database::connection();
        $abilityStatement = $pdo->prepare(
            "INSERT INTO abilities (ability_key, name, commercial_name, category, description, status, is_core, is_default_enabled, sort_order, metadata_json)
             VALUES (:ability_key, :name, :commercial_name, :category, :description, 'active', :is_core, :is_default_enabled, :sort_order, JSON_OBJECT('source','canonical_catalog'))
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               commercial_name = VALUES(commercial_name),
               category = VALUES(category),
               description = VALUES(description),
               status = 'active',
               is_core = VALUES(is_core),
               is_default_enabled = VALUES(is_default_enabled),
               sort_order = VALUES(sort_order)"
        );
        $versionStatement = $pdo->prepare(
            "INSERT INTO ability_versions (ability_id, version, status, manifest_json, released_at)
             SELECT id, '1.0.0', 'stable', JSON_OBJECT('canonical', TRUE), NOW()
             FROM abilities
             WHERE ability_key = :ability_key
             ON DUPLICATE KEY UPDATE status = 'stable'"
        );
        $marketplaceStatement = $pdo->prepare(
            "INSERT INTO marketplace_items (ability_id, slug, title, short_description, long_description, pricing_model, monthly_price, currency, status, sort_order, metadata_json)
             SELECT id, :slug, :title, :short_description, :long_description, :pricing_model, 0, 'USD', 'published', :sort_order, JSON_OBJECT('source','canonical_catalog')
             FROM abilities
             WHERE ability_key = :ability_key
             ON DUPLICATE KEY UPDATE
               ability_id = VALUES(ability_id),
               title = VALUES(title),
               short_description = VALUES(short_description),
               long_description = VALUES(long_description),
               pricing_model = VALUES(pricing_model),
               status = 'published',
               sort_order = VALUES(sort_order)"
        );

        foreach (self::CATALOG as $key => $definition) {
            $abilityStatement->execute([
                'ability_key' => $key,
                'name' => $definition['name'],
                'commercial_name' => $definition['label'],
                'category' => $definition['category'],
                'description' => $definition['description'],
                'is_core' => $definition['is_core'] ? 1 : 0,
                'is_default_enabled' => $definition['default'] ? 1 : 0,
                'sort_order' => $definition['sort'],
            ]);
            $versionStatement->execute(['ability_key' => $key]);
            $marketplaceStatement->execute([
                'ability_key' => $key,
                'slug' => $key,
                'title' => $definition['label'],
                'short_description' => substr($definition['description'], 0, 255),
                'long_description' => $definition['description'],
                'pricing_model' => in_array($definition['category'], ['core', 'base'], true) ? 'included' : 'addon',
                'sort_order' => $definition['sort'],
            ]);
        }
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
        return (string) (self::CATALOG[$abilityKey]['label'] ?? '');
    }

    public static function abilityDescription(string $abilityKey): string
    {
        return (string) (self::CATALOG[$abilityKey]['description'] ?? '');
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
