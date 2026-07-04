<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ControlRepository
{
    public function overview(int $companyId, array $filters = []): array
    {
        $controls = $this->controls($companyId, $filters);
        $selected = $this->selected($companyId, (int) ($filters['control_id'] ?? ($controls[0]['id'] ?? 0)));

        return [
            'controls' => $controls,
            'selected' => $selected,
            'metrics' => $this->metrics($companyId),
            'alerts' => $this->alerts($companyId, (int) ($selected['id'] ?? 0), 6),
            'entries' => $this->entries($companyId, (int) ($selected['id'] ?? 0), 6),
            'events' => $this->events($companyId, (int) ($selected['id'] ?? 0), 6),
        ];
    }

    public function controls(int $companyId, array $filters = []): array
    {
        if (!$this->ready()) {
            return $this->fallbackControls();
        }

        $where = ['company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (($filters['category'] ?? '') !== '') {
            $where[] = 'category = :category';
            $params['category'] = $filters['category'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(name LIKE :q OR objective LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $statement = Database::connection()->prepare('SELECT * FROM business_controls WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(status, "active", "draft", "paused", "archived"), next_review_at IS NULL, next_review_at, id DESC');
        $statement->execute($params);

        return array_map(fn (array $row): array => $this->formatControl($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function selected(int $companyId, int $controlId): ?array
    {
        if (!$this->ready() || $controlId <= 0) {
            return $this->fallbackControls()[0] ?? null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM business_controls WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $controlId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatControl($row) : null;
    }

    public function create(int $companyId, int $userId, array $input, string $currency): int
    {
        if (!$this->ready()) {
            return 0;
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $key = $this->slug($name);
        $category = $this->allowed((string) ($input['category'] ?? 'custom'), ['financial', 'commercial', 'operations', 'administrative', 'inventory', 'custom'], 'custom');
        $frequency = $this->allowed((string) ($input['frequency'] ?? 'weekly'), ['daily', 'weekly', 'monthly', 'on_demand'], 'weekly');
        $sourceType = $this->allowed((string) ($input['source_type'] ?? 'manual'), ['manual', 'spreadsheet', 'documents', 'email', 'integration', 'mixed'], 'manual');
        $brainKey = $this->brainForCategory($category);
        $objective = trim((string) ($input['objective'] ?? ''));
        if ($objective === '') {
            $objective = 'Controlar, ordenar y generar alertas sobre ' . strtolower($name) . ' para esta empresa.';
        }

        $rules = [
            'approval_required' => true,
            'created_from' => 'controls_module',
            'currency' => $currency,
            'review_owner' => $userId,
        ];
        $metrics = [
            'pending_items' => 0,
            'open_alerts' => 0,
            'progress' => 12,
        ];

        $statement = Database::connection()->prepare(
            'INSERT INTO business_controls (company_id, owner_id, name, control_key, objective, category, brain_key, status, frequency, source_type, rules_json, metrics_json, next_review_at)
             VALUES (:company_id, :owner_id, :name, :control_key, :objective, :category, :brain_key, "draft", :frequency, :source_type, :rules_json, :metrics_json, DATE_ADD(NOW(), INTERVAL 7 DAY))
             ON DUPLICATE KEY UPDATE objective = VALUES(objective), category = VALUES(category), frequency = VALUES(frequency), source_type = VALUES(source_type), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'company_id' => $companyId,
            'owner_id' => $userId,
            'name' => $name,
            'control_key' => $key,
            'objective' => $objective,
            'category' => $category,
            'brain_key' => $brainKey,
            'frequency' => $frequency,
            'source_type' => $sourceType,
            'rules_json' => json_encode($rules, JSON_UNESCAPED_UNICODE),
            'metrics_json' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int) Database::connection()->lastInsertId();
        if ($id <= 0) {
            $id = $this->idByKey($companyId, $key);
        }
        $this->log($companyId, $id, $userId, 'created', 'Control creado desde modulo Controles.', ['category' => $category]);

        return $id;
    }

    public function addEntry(int $companyId, int $controlId, int $userId, array $input, string $currency): void
    {
        if (!$this->ready() || $controlId <= 0) {
            return;
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return;
        }

        $amount = trim((string) ($input['amount'] ?? ''));
        Database::connection()->prepare(
            'INSERT INTO business_control_entries (company_id, control_id, user_id, title, entry_type, amount, currency, period_label, status, data_json)
             VALUES (:company_id, :control_id, :user_id, :title, :entry_type, :amount, :currency, :period_label, "pending", :data_json)'
        )->execute([
            'company_id' => $companyId,
            'control_id' => $controlId,
            'user_id' => $userId,
            'title' => $title,
            'entry_type' => trim((string) ($input['entry_type'] ?? 'manual')),
            'amount' => $amount !== '' ? (float) $amount : null,
            'currency' => $currency,
            'period_label' => trim((string) ($input['period_label'] ?? date('Y-m'))),
            'data_json' => json_encode(['note' => trim((string) ($input['note'] ?? ''))], JSON_UNESCAPED_UNICODE),
        ]);
        $this->log($companyId, $controlId, $userId, 'entry_added', 'Entrada agregada al control.', ['title' => $title]);
    }

    public function transition(int $companyId, int $controlId, int $userId, string $status): void
    {
        if (!$this->ready() || !in_array($status, ['draft', 'active', 'paused', 'archived'], true)) {
            return;
        }

        Database::connection()->prepare('UPDATE business_controls SET status = :status WHERE company_id = :company_id AND id = :id')->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $controlId,
        ]);
        $this->log($companyId, $controlId, $userId, 'status_changed', 'Estado del control actualizado a ' . $status . '.', ['status' => $status]);
    }

    public function resolveAlert(int $companyId, int $alertId, int $userId): void
    {
        if (!$this->ready() || $alertId <= 0) {
            return;
        }

        $statement = Database::connection()->prepare('SELECT control_id FROM business_control_alerts WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $alertId]);
        $controlId = (int) ($statement->fetchColumn() ?: 0);

        Database::connection()->prepare('UPDATE business_control_alerts SET status = "resolved", resolved_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $alertId,
        ]);
        if ($controlId > 0) {
            $this->log($companyId, $controlId, $userId, 'alert_resolved', 'Alerta marcada como resuelta.', ['alert_id' => $alertId]);
        }
    }

    public function metrics(int $companyId): array
    {
        if (!$this->ready()) {
            return [
                ['label' => 'Controles activos', 'value' => '4', 'hint' => 'Procesos vivos'],
                ['label' => 'Alertas abiertas', 'value' => '3', 'hint' => 'Requieren revision'],
                ['label' => 'Entradas pendientes', 'value' => '2', 'hint' => 'Por clasificar'],
                ['label' => 'Revision proxima', 'value' => '24h', 'hint' => 'Mas urgente'],
            ];
        }

        $pdo = Database::connection();
        $active = $this->scalar($pdo, 'SELECT COUNT(*) FROM business_controls WHERE company_id = :company_id AND status = "active"', $companyId);
        $alerts = $this->scalar($pdo, 'SELECT COUNT(*) FROM business_control_alerts WHERE company_id = :company_id AND status IN ("open", "reviewing")', $companyId);
        $entries = $this->scalar($pdo, 'SELECT COUNT(*) FROM business_control_entries WHERE company_id = :company_id AND status = "pending"', $companyId);

        return [
            ['label' => 'Controles activos', 'value' => (string) $active, 'hint' => 'Procesos vivos'],
            ['label' => 'Alertas abiertas', 'value' => (string) $alerts, 'hint' => 'Requieren revision'],
            ['label' => 'Entradas pendientes', 'value' => (string) $entries, 'hint' => 'Por clasificar'],
            ['label' => 'Revision proxima', 'value' => '24h', 'hint' => 'Mas urgente'],
        ];
    }

    public function alerts(int $companyId, int $controlId = 0, int $limit = 8): array
    {
        if (!$this->ready() || $controlId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM business_control_alerts WHERE company_id = :company_id AND control_id = :control_id ORDER BY FIELD(status, "open", "reviewing", "resolved", "dismissed"), FIELD(severity, "critical", "high", "medium", "low"), id DESC LIMIT ' . max(1, $limit));
        $statement->execute(['company_id' => $companyId, 'control_id' => $controlId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entries(int $companyId, int $controlId = 0, int $limit = 8): array
    {
        if (!$this->ready() || $controlId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM business_control_entries WHERE company_id = :company_id AND control_id = :control_id ORDER BY id DESC LIMIT ' . max(1, $limit));
        $statement->execute(['company_id' => $companyId, 'control_id' => $controlId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function events(int $companyId, int $controlId = 0, int $limit = 8): array
    {
        if (!$this->ready() || $controlId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT e.*, u.name AS user_name FROM business_control_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.company_id = :company_id AND e.control_id = :control_id ORDER BY e.id DESC LIMIT ' . max(1, $limit));
        $statement->execute(['company_id' => $companyId, 'control_id' => $controlId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function formatControl(array $row): array
    {
        return [
            ...$row,
            'rules' => json_decode((string) ($row['rules_json'] ?? '{}'), true) ?: [],
            'metrics' => json_decode((string) ($row['metrics_json'] ?? '{}'), true) ?: [],
            'icon' => $this->iconForCategory((string) ($row['category'] ?? 'custom')),
        ];
    }

    private function ready(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM business_controls LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function log(int $companyId, int $controlId, int $userId, string $type, string $summary, array $metadata): void
    {
        if ($controlId <= 0) {
            return;
        }

        Database::connection()->prepare(
            'INSERT INTO business_control_events (company_id, control_id, user_id, event_type, summary, metadata_json)
             VALUES (:company_id, :control_id, :user_id, :event_type, :summary, :metadata_json)'
        )->execute([
            'company_id' => $companyId,
            'control_id' => $controlId,
            'user_id' => $userId,
            'event_type' => $type,
            'summary' => $summary,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function scalar(PDO $pdo, string $sql, int $companyId): int
    {
        $statement = $pdo->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        return (int) $statement->fetchColumn();
    }

    private function idByKey(int $companyId, string $key): int
    {
        $statement = Database::connection()->prepare('SELECT id FROM business_controls WHERE company_id = :company_id AND control_key = :control_key LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'control_key' => $key]);
        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function allowed(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'control';
        return trim($value, '-') ?: 'control';
    }

    private function brainForCategory(string $category): string
    {
        return match ($category) {
            'financial', 'inventory' => 'analytical',
            'commercial' => 'commercial',
            'operations' => 'operational',
            'administrative' => 'administrative',
            default => 'executive',
        };
    }

    private function iconForCategory(string $category): string
    {
        return match ($category) {
            'financial' => 'bi-cash-coin',
            'commercial' => 'bi-graph-up-arrow',
            'operations' => 'bi-kanban',
            'administrative' => 'bi-calendar-check',
            'inventory' => 'bi-box-seam',
            default => 'bi-sliders',
        };
    }

    private function fallbackControls(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Control de gastos',
                'objective' => 'Clasificar gastos, detectar aumentos y preparar resumen mensual.',
                'category' => 'financial',
                'brain_key' => 'analytical',
                'status' => 'active',
                'frequency' => 'weekly',
                'source_type' => 'mixed',
                'next_review_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'metrics' => ['pending_items' => 4, 'open_alerts' => 1, 'progress' => 28],
                'rules' => ['approval_required' => true],
                'icon' => 'bi-cash-coin',
            ],
        ];
    }
}
