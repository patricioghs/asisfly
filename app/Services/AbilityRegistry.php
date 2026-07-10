<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class AbilityRegistry
{
    public function tablesReady(): bool
    {
        try {
            foreach (['abilities', 'tenant_abilities', 'ability_navigation_items'] as $table) {
                $statement = Database::connection()->prepare(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
                );
                $statement->execute(['table' => $table]);
                if ((int) $statement->fetchColumn() === 0) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findByKey(string $abilityKey): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM abilities WHERE ability_key = :ability_key LIMIT 1');
        $statement->execute(['ability_key' => $abilityKey]);
        $ability = $statement->fetch(PDO::FETCH_ASSOC);

        return $ability ?: null;
    }

    public function activeAbilitiesForTenant(int $companyId): array
    {
        if ($companyId <= 0 || !$this->tablesReady()) {
            return [];
        }

        $statement = Database::connection()->prepare(
            "SELECT a.*, ta.status AS tenant_status, ta.settings_json
             FROM tenant_abilities ta
             INNER JOIN abilities a ON a.id = ta.ability_id
             WHERE ta.company_id = :company_id
               AND ta.status = 'active'
               AND a.status = 'active'
             ORDER BY a.sort_order, a.commercial_name"
        );
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fallbackNavigation(): array
    {
        return [
            'Inicio' => [
                ['/dashboard', 'Dashboard', 'bi-grid-1x2', null],
                ['/my-day', 'Mi dia', 'bi-calendar2-check', null],
                ['/workbench', 'Bandeja de trabajo', 'bi-briefcase', null],
                ['/notifications', 'Notificaciones', 'bi-bell', null],
            ],
            'Asistente' => [
                ['/chat', 'Chat IA', 'bi-stars', null],
                ['/tasks', 'Tareas', 'bi-list-task', null],
                ['/automations', 'Automatizaciones', 'bi-magic', null],
                ['/actions', 'Aprobaciones', 'bi-check2-square', null],
                ['/controls', 'Controles', 'bi-sliders', null],
                ['/documents', 'Memoria', 'bi-database-check', null],
                ['/assistant', 'Reglas', 'bi-sliders2', null],
            ],
            'Comunicacion' => [
                ['/inbox', 'Omnicanal', 'bi-inboxes', null],
                ['/social', 'Asisti Social', 'bi-megaphone', null],
            ],
            'Comercial' => [
                ['/crm', 'CRM', 'bi-people', null],
                ['/quotes', 'Cotizaciones', 'bi-file-earmark-text', null],
            ],
            'Inteligencia' => [
                ['/reports', 'Reportes', 'bi-clipboard-data', null],
                ['/analytics', 'Analytics', 'bi-graph-up-arrow', null],
                ['/dashboards', 'Dashboards', 'bi-columns-gap', null],
                ['/intelligence-documents', 'Documentos', 'bi-file-earmark-text', null],
                ['/excel', 'Excel', 'bi-file-earmark-spreadsheet', null],
                ['/indicators', 'Indicadores', 'bi-bullseye', null],
                ['/kpis', 'KPIs', 'bi-speedometer2', null],
                ['/brains', 'Cerebros IA', 'bi-diagram-3', null],
            ],
            'Conocimiento' => [
                ['/knowledge-base', 'Base de conocimiento', 'bi-book', null],
                ['/documents', 'Documentos', 'bi-file-earmark', null],
                ['/catalogs', 'Catalogos', 'bi-folder2-open', null],
                ['/manuals', 'Manuales', 'bi-journal-text', null],
                ['/training', 'Entrenamiento', 'bi-mortarboard', null],
            ],
            'Integraciones' => [
                ['/integrations', 'Integraciones', 'bi-diagram-3', null],
                ['/integrations/accounts', 'Cuentas conectadas', 'bi-plug', null],
                ['/apis', 'APIs', 'bi-code-slash', null],
            ],
            'Empresa' => [
                ['/company', 'Empresa', 'bi-building', null],
                ['/users', 'Usuarios', 'bi-people', null],
                ['/roles', 'Roles', 'bi-shield-lock', null],
                ['/billing', 'Plan y facturacion', 'bi-credit-card', null],
                ['/company-settings', 'Configuracion', 'bi-gear', null],
            ],
            'Administracion (Superadmin)' => [
                ['/admin/companies', 'Empresas', 'bi-buildings', null],
                ['/admin/plans', 'Planes', 'bi-check2-square', null],
                ['/admin/ai-tokens', 'IA y tokens', 'bi-cpu', null],
                ['/admin/audit-logs', 'Auditoria y logs', 'bi-file-lock', null],
                ['/admin/system-status', 'Estado del sistema', 'bi-record-circle', null],
            ],
        ];
    }
}
