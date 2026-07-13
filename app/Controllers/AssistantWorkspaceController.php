<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class AssistantWorkspaceController extends Controller
{
    public function tasks(): void
    {
        $this->requireAuth();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'pending')),
            'assigned_to' => trim((string) ($_GET['assigned_to'] ?? '')),
            'priority' => trim((string) ($_GET['priority'] ?? '')),
        ];
        $tasks = $this->tasksData($this->companyId(), $filters);
        $this->view('assistant_workspace/tasks', [
            'title' => 'Tareas',
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $this->usersData($this->companyId()),
            'metrics' => $this->taskMetrics($this->companyId()),
        ]);
    }

    public function updateTask(): void
    {
        $this->requireAuth();
        $this->updateTaskData($this->companyId(), (int) ($_POST['task_id'] ?? 0), $_POST, (int) $_SESSION['user']['id']);
        $this->redirectToTasks();
    }

    public function addTaskComment(): void
    {
        $this->requireAuth();

        $companyId = $this->companyId();
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($taskId <= 0 || $comment === '' || !Database::available() || !$this->taskCollaborationReady()) {
            $this->redirectToTasks();
        }

        $task = $this->taskData($companyId, $taskId);
        if (!$task) {
            $this->redirectToTasks();
        }

        $mentions = $this->mentionedUserIds($companyId, $comment);
        Database::connection()->prepare(
            'INSERT INTO crm_task_comments (company_id, task_id, user_id, comment, mentions_json)
             VALUES (:company_id, :task_id, :user_id, :comment, :mentions_json)'
        )->execute([
            'company_id' => $companyId,
            'task_id' => $taskId,
            'user_id' => (int) $_SESSION['user']['id'],
            'comment' => $comment,
            'mentions_json' => $mentions ? json_encode(array_values($mentions), JSON_UNESCAPED_UNICODE) : null,
        ]);

        $this->logTaskEvent($companyId, $taskId, (int) $_SESSION['user']['id'], 'commented', 'Agrego un comentario interno.', ['mentions' => $mentions]);
        $notify = array_unique(array_filter([(int) ($task['assigned_to'] ?? 0), ...$mentions]));
        foreach ($notify as $userId) {
            if ($userId !== (int) $_SESSION['user']['id']) {
                $this->notifyTaskUser($companyId, $taskId, $userId, 'Nuevo comentario en tarea', (string) $task['title']);
            }
        }

        $this->redirectToTasks();
    }

    public function requestTaskUpdate(): void
    {
        $this->requireAuth();

        $companyId = $this->companyId();
        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId <= 0 || !Database::available() || !$this->taskCollaborationReady()) {
            $this->redirectToTasks();
        }

        $task = $this->taskData($companyId, $taskId);
        if ($task) {
            $this->logTaskEvent($companyId, $taskId, (int) $_SESSION['user']['id'], 'update_requested', 'Pidio una actualizacion al responsable.', []);
            if (!empty($task['assigned_to']) && (int) $task['assigned_to'] !== (int) $_SESSION['user']['id']) {
                $this->notifyTaskUser($companyId, $taskId, (int) $task['assigned_to'], 'Actualizacion solicitada', (string) $task['title']);
            }
        }

        $this->redirectToTasks();
    }

    public function automations(): void
    {
        $this->requireAuth();

        $rules = $this->automationData($this->companyId());
        $this->view('assistant_workspace/automations', [
            'title' => 'Automatizaciones',
            'rules' => $rules,
        ]);
    }

    private function tasksData(int $companyId, array $filters): array
    {
        if (!Database::available()) {
            return $this->fallbackTasks();
        }

        $where = ['t.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['assigned_to'] ?? '') !== '') {
            if ($filters['assigned_to'] === 'none') {
                $where[] = 't.assigned_to IS NULL';
            } else {
                $where[] = 't.assigned_to = :assigned_to';
                $params['assigned_to'] = (int) $filters['assigned_to'];
            }
        }
        if (($filters['priority'] ?? '') !== '') {
            $where[] = 't.priority = :priority';
            $params['priority'] = $filters['priority'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(t.title LIKE :q OR c.name LIKE :q OR c.contact_name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT t.id, t.title, t.task_type, t.due_at, t.priority, t.status, t.assigned_to, t.completed_at,
                       t.source_type, t.source_id, t.source_label, c.name AS customer_name, u.name AS assigned_name
                FROM crm_tasks t
                LEFT JOIN crm_customers c ON c.id = t.customer_id AND c.company_id = t.company_id
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(t.status, "pending", "done", "cancelled"), FIELD(t.priority, "critical", "high", "medium", "low"), t.due_at IS NULL, t.due_at
                LIMIT 80';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($this->taskCollaborationReady()) {
            foreach ($rows as &$row) {
                $row['comments'] = $this->taskComments($companyId, (int) $row['id']);
                $row['events'] = $this->taskEvents($companyId, (int) $row['id']);
            }
            unset($row);
        }

        return $rows;
    }

    private function updateTaskData(int $companyId, int $taskId, array $input, int $userId): void
    {
        if (!Database::available() || $taskId <= 0) {
            return;
        }

        $before = $this->taskData($companyId, $taskId);
        $status = in_array($input['status'] ?? '', ['pending', 'done', 'cancelled'], true) ? $input['status'] : 'pending';
        $priority = in_array($input['priority'] ?? '', ['critical', 'high', 'medium', 'low'], true) ? $input['priority'] : 'medium';
        $assignedTo = !empty($input['assigned_to']) && $input['assigned_to'] !== 'none' ? (int) $input['assigned_to'] : null;
        $completedAt = $status === 'done' ? ', completed_at = COALESCE(completed_at, CURRENT_TIMESTAMP)' : ', completed_at = NULL';

        $sql = "UPDATE crm_tasks SET status = :status, priority = :priority, assigned_to = :assigned_to {$completedAt} WHERE company_id = :company_id AND id = :id";
        Database::connection()->prepare($sql)->execute([
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assignedTo,
            'company_id' => $companyId,
            'id' => $taskId,
        ]);

        if ($this->taskCollaborationReady()) {
            $changes = [];
            if (($before['status'] ?? null) !== $status) {
                $changes[] = 'estado';
            }
            if (($before['priority'] ?? null) !== $priority) {
                $changes[] = 'prioridad';
            }
            if ((string) ($before['assigned_to'] ?? '') !== (string) ($assignedTo ?? '')) {
                $changes[] = 'responsable';
            }
            if ($changes) {
                $this->logTaskEvent($companyId, $taskId, $userId, 'updated', 'Actualizo ' . implode(', ', $changes) . '.', [
                    'status' => $status,
                    'priority' => $priority,
                    'assigned_to' => $assignedTo,
                ]);
                if ($assignedTo && $assignedTo !== $userId) {
                    $this->notifyTaskUser($companyId, $taskId, $assignedTo, 'Tarea actualizada', (string) ($before['title'] ?? 'Tarea'));
                }
            }
        }
    }

    private function taskData(int $companyId, int $taskId): ?array
    {
        if (!Database::available() || $taskId <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT id, title, status, priority, assigned_to FROM crm_tasks WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $taskId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function taskComments(int $companyId, int $taskId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.comment, c.created_at, u.name AS user_name
             FROM crm_task_comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.company_id = :company_id AND c.task_id = :task_id
             ORDER BY c.id DESC
             LIMIT 3'
        );
        $statement->execute(['company_id' => $companyId, 'task_id' => $taskId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function taskEvents(int $companyId, int $taskId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.event_type, e.summary, e.created_at, u.name AS user_name
             FROM crm_task_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.company_id = :company_id AND e.task_id = :task_id
             ORDER BY e.id DESC
             LIMIT 4'
        );
        $statement->execute(['company_id' => $companyId, 'task_id' => $taskId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logTaskEvent(int $companyId, int $taskId, int $userId, string $type, string $summary, array $metadata): void
    {
        if (!$this->taskCollaborationReady()) {
            return;
        }

        Database::connection()->prepare(
            'INSERT INTO crm_task_events (company_id, task_id, user_id, event_type, summary, metadata_json)
             VALUES (:company_id, :task_id, :user_id, :event_type, :summary, :metadata_json)'
        )->execute([
            'company_id' => $companyId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'event_type' => $type,
            'summary' => $summary,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function notifyTaskUser(int $companyId, int $taskId, int $userId, string $title, string $body): void
    {
        if (!$this->taskCollaborationReady()) {
            return;
        }

        Database::connection()->prepare(
            'INSERT INTO crm_task_notifications (company_id, task_id, user_id, title, body)
             VALUES (:company_id, :task_id, :user_id, :title, :body)'
        )->execute([
            'company_id' => $companyId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
        ]);
    }

    private function mentionedUserIds(int $companyId, string $comment): array
    {
        if (!preg_match_all('/@([A-Za-z0-9._-]+)/', $comment, $matches)) {
            return [];
        }

        $tokens = array_map('strtolower', $matches[1] ?? []);
        $ids = [];
        foreach ($this->usersData($companyId) as $user) {
            $nameToken = strtolower(str_replace(' ', '.', (string) $user['name']));
            $emailToken = strtolower(strtok((string) $user['email'], '@') ?: '');
            if (in_array($nameToken, $tokens, true) || in_array($emailToken, $tokens, true)) {
                $ids[] = (int) $user['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function taskCollaborationReady(): bool
    {
        return $this->tableExists('crm_task_comments')
            && $this->tableExists('crm_task_events')
            && $this->tableExists('crm_task_notifications');
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $statement = Database::connection()->prepare('SHOW TABLES LIKE :table');
            $statement->execute(['table' => $table]);
            return $cache[$table] = (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return $cache[$table] = false;
        }
    }

    private function redirectToTasks(): void
    {
        $query = http_build_query(array_filter([
            'status' => $_POST['filter_status'] ?? null,
            'assigned_to' => $_POST['filter_assigned_to'] ?? null,
            'priority' => $_POST['filter_priority'] ?? null,
            'q' => $_POST['filter_q'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
        $this->redirect('/tasks' . ($query ? '?' . $query : ''));
    }

    private function usersData(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, name, email FROM users WHERE company_id = :company_id AND status = "active" ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function taskMetrics(int $companyId): array
    {
        if (!Database::available()) {
            return [
                ['label' => 'Pendientes', 'value' => '3', 'status' => 'pending'],
                ['label' => 'Terminadas', 'value' => '0', 'status' => 'done'],
                ['label' => 'Canceladas', 'value' => '0', 'status' => 'cancelled'],
            ];
        }

        $statement = Database::connection()->prepare('SELECT status, COUNT(*) AS total FROM crm_tasks WHERE company_id = :company_id GROUP BY status');
        $statement->execute(['company_id' => $companyId]);
        $counts = ['pending' => 0, 'done' => 0, 'cancelled' => 0];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return [
            ['label' => 'Pendientes', 'value' => (string) $counts['pending'], 'status' => 'pending'],
            ['label' => 'Terminadas', 'value' => (string) $counts['done'], 'status' => 'done'],
            ['label' => 'Canceladas', 'value' => (string) $counts['cancelled'], 'status' => 'cancelled'],
        ];
    }

    private function automationData(int $companyId): array
    {
        if (!Database::available()) {
            return $this->fallbackAutomations();
        }

        $statement = Database::connection()->prepare('SELECT name, trigger_json, action_json, requires_approval, is_active FROM automation_rules WHERE company_id = :company_id ORDER BY is_active DESC, id DESC LIMIT 24');
        $statement->execute(['company_id' => $companyId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => [
            ...$row,
            'trigger' => $this->jsonSummary((string) ($row['trigger_json'] ?? '{}')),
            'action' => $this->jsonSummary((string) ($row['action_json'] ?? '{}')),
        ], $rows ?: $this->fallbackAutomations());
    }

    private function fallbackTasks(): array
    {
        return [
            ['id' => 1, 'title' => 'Responder clientes calientes', 'task_type' => 'follow_up', 'due_at' => 'Hoy 10:30', 'priority' => 'high', 'status' => 'pending', 'customer_name' => 'Constructora X', 'assigned_name' => 'Demo'],
            ['id' => 2, 'title' => 'Enviar cotizacion actualizada', 'task_type' => 'quote', 'due_at' => 'Hoy 12:00', 'priority' => 'high', 'status' => 'pending', 'customer_name' => 'Cliente XYZ', 'assigned_name' => 'Demo'],
            ['id' => 3, 'title' => 'Revisar documentos pendientes', 'task_type' => 'todo', 'due_at' => 'Hoy 16:00', 'priority' => 'medium', 'status' => 'pending', 'customer_name' => 'Equipo interno', 'assigned_name' => 'Demo'],
        ];
    }

    private function fallbackAutomations(): array
    {
        return [
            ['name' => 'Correo con cotizacion', 'trigger' => 'email.subject contiene cotizacion', 'action' => 'crear oportunidad comercial', 'requires_approval' => true, 'is_active' => true],
            ['name' => 'Cliente sin respuesta 3 dias', 'trigger' => 'crm.customer.inactive por 3 dias', 'action' => 'crear tarea de seguimiento', 'requires_approval' => false, 'is_active' => true],
            ['name' => 'Mensaje fuera de horario', 'trigger' => 'inbox.message fuera de horario laboral', 'action' => 'redactar respuesta automatica', 'requires_approval' => true, 'is_active' => false],
        ];
    }

    private function jsonSummary(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode(' / ', $parts);
    }
}
