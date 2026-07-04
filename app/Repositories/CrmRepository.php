<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CrmRepository
{
    public function overview(int $companyId, array $filters, string $currency): array
    {
        $customers = $this->customers($companyId, $filters, $currency);

        return [
            'metrics' => $this->metrics($companyId, $currency),
            'customers' => $customers,
            'selected' => $this->selectedCustomer($companyId, (int) ($filters['customer_id'] ?? ($customers[0]['id'] ?? 0)), $currency),
            'stages' => ['Nuevo', 'Calificado', 'Propuesta enviada', 'Negociacion', 'Ganado', 'Perdido'],
        ];
    }

    public function customers(int $companyId, array $filters, string $currency): array
    {
        if (!Database::available()) {
            return [];
        }

        $where = ['c.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (!empty($filters['q'])) {
            $where[] = '(c.name LIKE :q OR c.contact_name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['stage'])) {
            $where[] = 'c.stage = :stage';
            $params['stage'] = $filters['stage'];
        }

        if (!empty($filters['temperature'])) {
            if ($filters['temperature'] === 'cold') {
                $where[] = '(c.temperature = "cold" OR c.last_activity_at IS NULL OR c.last_activity_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR c.next_follow_up_at < NOW())';
            } elseif (in_array($filters['temperature'], ['hot', 'warm'], true)) {
                $where[] = 'c.temperature = :temperature';
                $params['temperature'] = $filters['temperature'];
            }
        }

        $sql = 'SELECT c.*, u.name AS owner_name,
                       COUNT(DISTINCT o.id) AS opportunities_count,
                       COALESCE(SUM(CASE WHEN o.stage NOT IN ("ganado","perdido") THEN o.amount ELSE 0 END), c.estimated_value) AS open_amount,
                       COUNT(DISTINCT t.id) AS pending_tasks
                FROM crm_customers c
                LEFT JOIN users u ON u.id = c.owner_id
                LEFT JOIN crm_opportunities o ON o.company_id = c.company_id AND o.customer_id = c.id
                LEFT JOIN crm_tasks t ON t.company_id = c.company_id AND t.customer_id = c.id AND t.status = "pending"
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY c.id
                ORDER BY FIELD(c.temperature, "hot", "warm", "cold"), COALESCE(c.next_follow_up_at, "2099-01-01"), c.id DESC
                LIMIT 80';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row): array => $this->formatCustomer($row, $currency), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function selectedCustomer(int $companyId, int $customerId, string $currency): ?array
    {
        if (!Database::available() || $customerId <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT c.*, u.name AS owner_name FROM crm_customers c LEFT JOIN users u ON u.id = c.owner_id WHERE c.company_id = :company_id AND c.id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $customerId]);
        $customer = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            return null;
        }

        return [
            ...$this->formatCustomer($customer, $currency),
            'contacts' => $this->rows('SELECT * FROM crm_contacts WHERE company_id = :company_id AND customer_id = :customer_id ORDER BY is_primary DESC, id DESC', $companyId, $customerId),
            'opportunities' => array_map(fn (array $row): array => [
                ...$row,
                'amount_label' => $this->money((float) $row['amount'], $row['currency'] ?: $currency),
            ], $this->rows('SELECT * FROM crm_opportunities WHERE company_id = :company_id AND customer_id = :customer_id ORDER BY FIELD(stage, "nuevo","calificado","propuesta","negociacion","ganado","perdido"), id DESC', $companyId, $customerId)),
            'tasks' => $this->rows('SELECT t.*, u.name AS assigned_name FROM crm_tasks t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.company_id = :company_id AND t.customer_id = :customer_id ORDER BY FIELD(t.status, "pending","done","cancelled"), t.due_at IS NULL, t.due_at', $companyId, $customerId),
            'notes' => $this->rows('SELECT n.*, u.name AS user_name FROM crm_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.company_id = :company_id AND n.customer_id = :customer_id ORDER BY n.id DESC LIMIT 8', $companyId, $customerId),
            'activities' => $this->rows('SELECT a.*, u.name AS user_name FROM crm_activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.company_id = :company_id AND a.customer_id = :customer_id ORDER BY a.id DESC LIMIT 10', $companyId, $customerId),
        ];
    }

    public function createCustomer(int $companyId, int $userId, array $input, string $currency): int
    {
        $value = (float) preg_replace('/[^0-9.]/', '', $input['value'] ?? '0');
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $statement = $pdo->prepare('INSERT INTO crm_customers (company_id, owner_id, name, contact_name, email, phone, source, temperature, stage, estimated_value, currency, last_activity_at, next_follow_up_at) VALUES (:company_id, :owner_id, :name, :contact_name, :email, :phone, :source, :temperature, :stage, :estimated_value, :currency, CURRENT_TIMESTAMP, DATE_ADD(NOW(), INTERVAL 2 DAY))');
        $statement->execute([
            'company_id' => $companyId,
            'owner_id' => $userId ?: null,
            'name' => trim((string) ($input['company'] ?? '')),
            'contact_name' => trim((string) ($input['contact'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'source' => trim((string) ($input['source'] ?? 'Manual')) ?: 'Manual',
            'temperature' => $input['temperature'] ?? 'warm',
            'stage' => trim((string) ($input['stage'] ?? 'Nuevo')),
            'estimated_value' => $value,
            'currency' => $currency,
        ]);
        $customerId = (int) $pdo->lastInsertId();

        $this->createContact($companyId, $customerId, [
            'name' => $input['contact'] ?? '',
            'email' => $input['email'] ?? '',
            'phone' => $input['phone'] ?? '',
            'role' => 'Contacto principal',
            'is_primary' => 1,
        ]);
        $this->createOpportunity($companyId, $customerId, [
            'title' => 'Oportunidad inicial',
            'stage' => $this->stageToOpportunity($input['stage'] ?? 'Nuevo'),
            'amount' => $value,
            'currency' => $currency,
            'probability' => 30,
            'source' => $input['source'] ?? 'Manual',
        ]);
        $this->logActivity($companyId, $customerId, $userId, 'customer_created', 'Cliente creado en CRM.');

        $pdo->commit();
        return $customerId;
    }

    public function addNote(int $companyId, int $customerId, int $userId, string $note): void
    {
        if (trim($note) === '') {
            return;
        }

        Database::connection()->prepare('INSERT INTO crm_notes (company_id, customer_id, user_id, note) VALUES (:company_id, :customer_id, :user_id, :note)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'user_id' => $userId ?: null,
            'note' => trim($note),
        ]);
        $this->touchCustomer($companyId, $customerId);
        $this->logActivity($companyId, $customerId, $userId, 'note', 'Nota agregada al cliente.');
    }

    public function addTask(int $companyId, int $customerId, int $userId, array $input): void
    {
        Database::connection()->prepare('INSERT INTO crm_tasks (company_id, customer_id, assigned_to, title, task_type, due_at, priority) VALUES (:company_id, :customer_id, :assigned_to, :title, :task_type, :due_at, :priority)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'assigned_to' => $userId ?: null,
            'title' => trim((string) ($input['title'] ?? 'Seguimiento comercial')),
            'task_type' => $input['task_type'] ?? 'follow_up',
            'due_at' => trim((string) ($input['due_at'] ?? '')) ?: null,
            'priority' => $input['priority'] ?? 'medium',
        ]);
        $this->logActivity($companyId, $customerId, $userId, 'task', 'Tarea creada: ' . ($input['title'] ?? 'Seguimiento comercial'));
    }

    public function completeTask(int $companyId, int $taskId, int $userId): void
    {
        $statement = Database::connection()->prepare('SELECT customer_id, title FROM crm_tasks WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $taskId]);
        $task = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            return;
        }

        Database::connection()->prepare('UPDATE crm_tasks SET status = "done", completed_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute(['company_id' => $companyId, 'id' => $taskId]);
        $this->touchCustomer($companyId, (int) $task['customer_id']);
        $this->logActivity($companyId, (int) $task['customer_id'], $userId, 'task_done', 'Tarea completada: ' . $task['title']);
    }

    public function updateCustomerState(int $companyId, int $customerId, int $userId, array $input): void
    {
        $stage = trim((string) ($input['stage'] ?? ''));
        $temperature = trim((string) ($input['temperature'] ?? ''));
        if ($customerId <= 0 || $stage === '' || !in_array($temperature, ['hot', 'warm', 'cold'], true)) {
            return;
        }

        Database::connection()->prepare('UPDATE crm_customers SET stage = :stage, temperature = :temperature, last_activity_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'stage' => $stage,
            'temperature' => $temperature,
            'company_id' => $companyId,
            'id' => $customerId,
        ]);
        $this->logActivity($companyId, $customerId, $userId, 'state_changed', 'Estado actualizado: ' . $stage . ' / ' . $temperature);
    }

    public function addOpportunity(int $companyId, int $customerId, int $userId, array $input, string $currency): void
    {
        $this->createOpportunity($companyId, $customerId, [
            'title' => trim((string) ($input['title'] ?? 'Nueva oportunidad')),
            'stage' => $input['stage'] ?? 'nuevo',
            'amount' => (float) ($input['amount'] ?? 0),
            'currency' => $currency,
            'probability' => (int) ($input['probability'] ?? 30),
            'expected_close_date' => trim((string) ($input['expected_close_date'] ?? '')) ?: null,
            'source' => $input['source'] ?? 'Manual',
        ]);
        $this->logActivity($companyId, $customerId, $userId, 'opportunity', 'Oportunidad creada: ' . ($input['title'] ?? 'Nueva oportunidad'));
    }

    public function createAutomaticFollowups(int $companyId, int $userId): int
    {
        $sql = 'SELECT id, name FROM crm_customers
                WHERE company_id = :company_id
                  AND (temperature = "cold" OR last_activity_at IS NULL OR last_activity_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR next_follow_up_at < NOW())
                  AND id NOT IN (SELECT customer_id FROM crm_tasks WHERE company_id = :company_id AND status = "pending" AND task_type = "follow_up")';
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        $customers = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($customers as $customer) {
            $this->addTask($companyId, (int) $customer['id'], $userId, [
                'title' => 'Recuperar cliente frio: ' . $customer['name'],
                'task_type' => 'follow_up',
                'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'priority' => 'high',
            ]);
        }

        return count($customers);
    }

    private function metrics(int $companyId, string $currency): array
    {
        if (!Database::available()) {
            return [];
        }

        $row = Database::connection()->prepare('SELECT COUNT(*) AS customers,
            SUM(CASE WHEN temperature = "cold" OR last_activity_at IS NULL OR last_activity_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS cold,
            COALESCE(SUM(estimated_value), 0) AS pipeline
            FROM crm_customers WHERE company_id = :company_id');
        $row->execute(['company_id' => $companyId]);
        $summary = $row->fetch(PDO::FETCH_ASSOC) ?: ['customers' => 0, 'cold' => 0, 'pipeline' => 0];

        $tasks = Database::connection()->prepare('SELECT COUNT(*) FROM crm_tasks WHERE company_id = :company_id AND status = "pending"');
        $tasks->execute(['company_id' => $companyId]);

        return [
            ['label' => 'Clientes', 'value' => (string) (int) $summary['customers'], 'hint' => 'Base comercial'],
            ['label' => 'Clientes frios', 'value' => (string) (int) $summary['cold'], 'hint' => 'Recuperacion'],
            ['label' => 'Pipeline', 'value' => $this->money((float) $summary['pipeline'], $currency), 'hint' => 'Valor estimado'],
            ['label' => 'Tareas pendientes', 'value' => (string) (int) $tasks->fetchColumn(), 'hint' => 'Seguimientos'],
        ];
    }

    private function createContact(int $companyId, int $customerId, array $input): void
    {
        Database::connection()->prepare('INSERT INTO crm_contacts (company_id, customer_id, name, email, phone, role, is_primary) VALUES (:company_id, :customer_id, :name, :email, :phone, :role, :is_primary)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'name' => trim((string) ($input['name'] ?? 'Contacto')),
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'role' => trim((string) ($input['role'] ?? '')) ?: null,
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
        ]);
    }

    private function createOpportunity(int $companyId, int $customerId, array $input): void
    {
        Database::connection()->prepare('INSERT INTO crm_opportunities (company_id, customer_id, title, stage, amount, currency, probability, expected_close_date, source) VALUES (:company_id, :customer_id, :title, :stage, :amount, :currency, :probability, :expected_close_date, :source)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'title' => $input['title'],
            'stage' => $input['stage'],
            'amount' => (float) ($input['amount'] ?? 0),
            'currency' => $input['currency'] ?? 'CLP',
            'probability' => (int) ($input['probability'] ?? 30),
            'expected_close_date' => $input['expected_close_date'] ?? null,
            'source' => $input['source'] ?? null,
        ]);
    }

    private function rows(string $sql, int $companyId, int $customerId): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId, 'customer_id' => $customerId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function touchCustomer(int $companyId, int $customerId): void
    {
        Database::connection()->prepare('UPDATE crm_customers SET last_activity_at = CURRENT_TIMESTAMP, next_follow_up_at = DATE_ADD(NOW(), INTERVAL 3 DAY), temperature = IF(temperature = "cold", "warm", temperature) WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $customerId,
        ]);
    }

    private function logActivity(int $companyId, int $customerId, int $userId, string $type, string $summary): void
    {
        Database::connection()->prepare('INSERT INTO crm_activities (company_id, customer_id, user_id, activity_type, summary) VALUES (:company_id, :customer_id, :user_id, :activity_type, :summary)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'user_id' => $userId ?: null,
            'activity_type' => $type,
            'summary' => $summary,
        ]);
    }

    private function formatCustomer(array $row, string $currency): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'contact' => $row['contact_name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'stage' => $row['stage'],
            'source' => $row['source'] ?? 'Manual',
            'temperature' => $row['temperature'] ?? 'warm',
            'owner' => $row['owner_name'] ?? 'Sin responsable',
            'value' => $this->money((float) ($row['open_amount'] ?? $row['estimated_value'] ?? 0), $row['currency'] ?? $currency),
            'raw_value' => (float) ($row['open_amount'] ?? $row['estimated_value'] ?? 0),
            'opportunities_count' => (int) ($row['opportunities_count'] ?? 0),
            'pending_tasks' => (int) ($row['pending_tasks'] ?? 0),
            'last_activity_at' => $row['last_activity_at'] ?? null,
            'next_follow_up_at' => $row['next_follow_up_at'] ?? null,
        ];
    }

    private function stageToOpportunity(string $stage): string
    {
        $normalized = strtolower($stage);
        return match (true) {
            str_contains($normalized, 'propuesta') => 'propuesta',
            str_contains($normalized, 'negoci') => 'negociacion',
            str_contains($normalized, 'gan') => 'ganado',
            str_contains($normalized, 'perd') => 'perdido',
            str_contains($normalized, 'calif') => 'calificado',
            default => 'nuevo',
        };
    }

    private function money(float $amount, string $currency): string
    {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }
}
