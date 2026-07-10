<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class HomeController extends Controller
{
    public function myDay(): void
    {
        $this->requireAuth();

        $items = $this->workbenchItems($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), [
            'view' => 'mine',
            'type' => '',
            'priority' => '',
            'q' => '',
        ]);

        $this->view('home/my-day', [
            'title' => 'Mi dia',
            'agenda' => $this->myDayAgenda($items),
            'suggestions' => $this->myDaySuggestions($items),
            'priorityItem' => $this->myDayPriorityItem($items),
        ]);
    }

    public function workbench(): void
    {
        $this->requireAuth();

        $filters = [
            'view' => trim((string) ($_GET['view'] ?? 'mine')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'priority' => trim((string) ($_GET['priority'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $items = $this->workbenchItems($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $filters);

        $this->view('home/workbench', [
            'title' => 'Bandeja de trabajo',
            'items' => $items,
            'filters' => $filters,
            'metrics' => $this->workbenchMetrics($items),
            'company' => $this->currentCompany(),
        ]);
    }

    public function completeWorkbenchTask(): void
    {
        $this->requireAuth();

        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId > 0 && Database::available() && $this->tableExists('crm_tasks')) {
            Database::connection()->prepare('UPDATE crm_tasks SET status = "done", completed_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $this->companyId(),
                'id' => $taskId,
            ]);
        }

        $query = http_build_query(array_filter([
            'view' => $_POST['filter_view'] ?? null,
            'type' => $_POST['filter_type'] ?? null,
            'priority' => $_POST['filter_priority'] ?? null,
            'q' => $_POST['filter_q'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));
        $this->redirect('/workbench' . ($query ? '?' . $query : ''));
    }

    public function markWorkbenchMessageReviewed(): void
    {
        $this->requireAuth();

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        if ($conversationId > 0 && Database::available() && $this->tableExists('inbox_conversations')) {
            Database::connection()->prepare(
                'UPDATE inbox_conversations
                 SET status = "answered", updated_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id
                   AND id = :id
                   AND status IN ("new", "open", "pending_approval")'
            )->execute([
                'company_id' => $this->companyId(),
                'id' => $conversationId,
            ]);
        }

        $this->redirect('/workbench' . $this->workbenchFilterQuery($_POST));
    }

    public function markWorkbenchEmailsReviewed(): void
    {
        $this->requireAuth();

        if (Database::available() && $this->tableExists('inbox_conversations')) {
            Database::connection()->prepare(
                'UPDATE inbox_conversations
                 SET status = "answered", updated_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id
                   AND channel = "Email"
                   AND status IN ("new", "open", "pending_approval")'
            )->execute(['company_id' => $this->companyId()]);
        }

        $this->redirect('/workbench' . $this->workbenchFilterQuery($_POST));
    }

    public function notifications(): void
    {
        $this->requireAuth();

        $filters = [
            'type' => trim((string) ($_GET['type'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $notifications = $this->notificationItems($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $filters);

        $this->view('home/notifications', [
            'title' => 'Notificaciones',
            'notifications' => $notifications,
            'filters' => $filters,
            'metrics' => $this->notificationMetrics($notifications),
        ]);
    }

    public function markNotificationReviewed(): void
    {
        $this->requireAuth();

        $notificationId = trim((string) ($_POST['notification_id'] ?? ''));
        if ($notificationId !== '' && Database::available()) {
            $this->markNotificationIdsReviewed($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), [$notificationId]);
        }

        $this->redirect('/notifications' . $this->notificationFilterQuery($_POST));
    }

    public function markNotificationsReviewed(): void
    {
        $this->requireAuth();

        if (Database::available()) {
            $filters = [
                'type' => trim((string) ($_POST['filter_type'] ?? '')),
                'status' => trim((string) ($_POST['filter_status'] ?? '')),
                'q' => trim((string) ($_POST['filter_q'] ?? '')),
            ];
            $notifications = $this->notificationItems($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), $filters);
            $this->markNotificationIdsReviewed(
                $this->companyId(),
                (int) ($_SESSION['user']['id'] ?? 0),
                array_map(fn (array $item): string => (string) ($item['id'] ?? ''), $notifications)
            );
        }

        $this->redirect('/notifications' . $this->notificationFilterQuery($_POST));
    }

    private function notificationItems(int $companyId, int $userId, array $filters): array
    {
        if (!Database::available()) {
            return [];
        }

        $items = [
            ...$this->actionNotifications($companyId, $userId),
            ...$this->taskNotifications($companyId, $userId),
            ...$this->controlNotifications($companyId),
            ...$this->inboxNotifications($companyId),
            ...$this->quoteNotifications($companyId),
        ];
        $items = array_map(fn (array $item): array => $this->normalizeNotification($item), $items);
        $reviewed = $this->notificationReviewMap($companyId, $userId);
        $items = array_map(function (array $item) use ($reviewed): array {
            if (isset($reviewed[$item['id']])) {
                $item['status'] = 'read';
                $item['reviewed_at'] = $reviewed[$item['id']];
            }

            return $item;
        }, $items);
        $items = array_values(array_filter($items, function (array $item) use ($filters): bool {
            if (($filters['status'] ?? '') === '' && $item['status'] === 'read') {
                return false;
            }
            if (($filters['type'] ?? '') !== '' && $item['type'] !== $filters['type']) {
                return false;
            }
            if (($filters['status'] ?? '') !== '' && $item['status'] !== $filters['status']) {
                return false;
            }
            if (($filters['q'] ?? '') !== '') {
                $haystack = strtolower(implode(' ', [$item['title'], $item['body'], $item['module'], $item['type_label']]));
                return str_contains($haystack, strtolower((string) $filters['q']));
            }
            return true;
        }));

        usort($items, fn (array $a, array $b): int => [$this->priorityWeight($b['priority']), $b['sort_at']] <=> [$this->priorityWeight($a['priority']), $a['sort_at']]);
        return array_slice($items, 0, 60);
    }

    private function markNotificationIdsReviewed(int $companyId, int $userId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (string $id): string => preg_match('/^[a-z]+-\d+$/', $id) ? $id : '',
            $ids
        ))));
        if ($ids === [] || !$this->ensureNotificationReviewsTable()) {
            return;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO notification_reviews (company_id, user_id, source_key, reviewed_at)
             VALUES (:company_id, :user_id, :source_key, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE reviewed_at = CURRENT_TIMESTAMP'
        );

        foreach ($ids as $id) {
            $statement->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
                'source_key' => $id,
            ]);
            $this->markNotificationSourceRead($companyId, $userId, $id);
        }
    }

    private function markNotificationSourceRead(int $companyId, int $userId, string $id): void
    {
        [$type, $rawId] = array_pad(explode('-', $id, 2), 2, '');
        $sourceId = (int) $rawId;
        if ($sourceId <= 0) {
            return;
        }

        if ($type === 'action' && $this->tableExists('action_center_notifications')) {
            Database::connection()->prepare(
                'UPDATE action_center_notifications SET status = "read", read_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id AND (user_id = :user_id OR user_id IS NULL)'
            )->execute(['company_id' => $companyId, 'id' => $sourceId, 'user_id' => $userId]);
        }

        if ($type === 'task' && $this->tableExists('crm_task_notifications')) {
            Database::connection()->prepare(
                'UPDATE crm_task_notifications SET status = "read", read_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id AND (user_id = :user_id OR user_id IS NULL)'
            )->execute(['company_id' => $companyId, 'id' => $sourceId, 'user_id' => $userId]);
        }
    }

    private function notificationReviewMap(int $companyId, int $userId): array
    {
        if (!$this->ensureNotificationReviewsTable()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT source_key, reviewed_at FROM notification_reviews WHERE company_id = :company_id AND user_id = :user_id');
        $statement->execute(['company_id' => $companyId, 'user_id' => $userId]);

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['source_key']] = (string) $row['reviewed_at'];
        }

        return $map;
    }

    private function ensureNotificationReviewsTable(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS notification_reviews (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    source_key VARCHAR(80) NOT NULL,
                    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_notification_review_user_source (company_id, user_id, source_key),
                    INDEX idx_notification_reviews_user (company_id, user_id, reviewed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return $ready = true;
        } catch (\Throwable) {
            return $ready = false;
        }
    }

    private function notificationFilterQuery(array $source): string
    {
        $query = http_build_query(array_filter([
            'type' => $source['filter_type'] ?? null,
            'status' => $source['filter_status'] ?? null,
            'q' => $source['filter_q'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        return $query ? '?' . $query : '';
    }

    private function actionNotifications(int $companyId, int $userId): array
    {
        if (!$this->tableExists('action_center_notifications')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT n.id, n.title, n.body, n.status, COALESCE(n.sent_at, n.created_at) AS created_at, a.module, a.priority, a.risk_level, a.status AS action_status
             FROM action_center_notifications n
             LEFT JOIN action_center_items a ON a.id = n.action_id AND a.company_id = n.company_id
             WHERE n.company_id = :company_id AND (n.user_id = :user_id OR n.user_id IS NULL)
             ORDER BY n.id DESC
             LIMIT 16'
        );
        $statement->execute(['company_id' => $companyId, 'user_id' => $userId]);

        return array_map(fn (array $row): array => [
            'id' => 'action-' . $row['id'],
            'type' => 'approval',
            'type_label' => 'Aprobaciones',
            'module' => $row['module'] ?: 'Centro de Acciones',
            'title' => $row['title'],
            'body' => $row['body'],
            'status' => $row['status'] === 'read' ? 'read' : 'unread',
            'priority' => $row['priority'] ?: 'medium',
            'risk' => $row['risk_level'] ?: 'medium',
            'icon' => 'bi-shield-check',
            'action_url' => '/actions',
            'action_label' => 'Revisar',
            'created_at' => $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function taskNotifications(int $companyId, int $userId): array
    {
        if (!$this->tableExists('crm_task_notifications')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT id, title, body, status, created_at, task_id
             FROM crm_task_notifications
             WHERE company_id = :company_id AND (user_id = :user_id OR user_id IS NULL)
             ORDER BY id DESC
             LIMIT 16'
        );
        $statement->execute(['company_id' => $companyId, 'user_id' => $userId]);

        return array_map(fn (array $row): array => [
            'id' => 'task-' . $row['id'],
            'type' => 'task',
            'type_label' => 'Tareas',
            'module' => 'Tareas',
            'title' => $row['title'],
            'body' => $row['body'],
            'status' => $row['status'] === 'read' ? 'read' : 'unread',
            'priority' => 'medium',
            'risk' => 'low',
            'icon' => 'bi-list-check',
            'action_url' => '/tasks',
            'action_label' => 'Abrir',
            'created_at' => $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function controlNotifications(int $companyId): array
    {
        if (!$this->tableExists('business_control_alerts')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT a.id, a.control_id, a.severity, a.title, a.body, a.status, a.created_at, c.name AS control_name
             FROM business_control_alerts a
             LEFT JOIN business_controls c ON c.id = a.control_id AND c.company_id = a.company_id
             WHERE a.company_id = :company_id AND a.status IN ("open", "reviewing")
             ORDER BY FIELD(a.severity, "critical", "high", "medium", "low"), a.id DESC
             LIMIT 16'
        );
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row): array => [
            'id' => 'control-' . $row['id'],
            'type' => 'control',
            'type_label' => 'Controles',
            'module' => $row['control_name'] ?: 'Controles',
            'title' => $row['title'],
            'body' => $row['body'],
            'status' => $row['status'] === 'open' ? 'unread' : 'pending',
            'priority' => $row['severity'],
            'risk' => in_array($row['severity'], ['critical', 'high'], true) ? 'high' : 'medium',
            'icon' => 'bi-sliders',
            'action_url' => '/controls?control_id=' . (int) $row['control_id'],
            'action_label' => 'Ver control',
            'created_at' => $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function inboxNotifications(int $companyId): array
    {
        if (!$this->tableExists('inbox_conversations')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT id, channel, customer_name, subject, status, priority, updated_at
             FROM inbox_conversations
             WHERE company_id = :company_id AND status IN ("new", "open", "pending_approval")
             ORDER BY FIELD(priority, "critical", "high", "medium", "low"), updated_at DESC
             LIMIT 16'
        );
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row): array => [
            'id' => 'inbox-' . $row['id'],
            'type' => 'message',
            'type_label' => 'Omnicanal',
            'module' => $row['channel'],
            'title' => $row['subject'],
            'body' => 'Mensaje pendiente de ' . ($row['customer_name'] ?: 'cliente') . '.',
            'status' => $row['status'] === 'pending_approval' ? 'pending' : 'unread',
            'priority' => $row['priority'],
            'risk' => $row['priority'] === 'critical' ? 'high' : 'medium',
            'icon' => $row['channel'] === 'WhatsApp' ? 'bi-whatsapp' : 'bi-chat-dots',
            'action_url' => '/inbox?id=' . (int) $row['id'],
            'action_label' => 'Responder',
            'created_at' => $row['updated_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function quoteNotifications(int $companyId): array
    {
        if (!$this->tableExists('quotes')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT id, quote_number, customer_name, status, total, currency, valid_until, created_at
             FROM quotes
             WHERE company_id = :company_id AND status IN ("draft", "sent") AND (valid_until IS NULL OR valid_until <= DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY))
             ORDER BY valid_until IS NULL, valid_until, id DESC
             LIMIT 12'
        );
        $statement->execute(['company_id' => $companyId]);

        return array_map(function (array $row): array {
            $expiresSoon = !empty($row['valid_until']) && strtotime((string) $row['valid_until']) <= strtotime('+1 day');
            return [
                'id' => 'quote-' . $row['id'],
                'type' => 'quote',
                'type_label' => 'Cotizaciones',
                'module' => 'Cotizaciones',
                'title' => 'Cotizacion ' . $row['quote_number'] . ' requiere seguimiento',
                'body' => ($row['customer_name'] ?: 'Cliente directo') . ' / ' . $row['currency'] . ' ' . number_format((float) $row['total'], 0, ',', '.') . '.',
                'status' => 'pending',
                'priority' => $expiresSoon ? 'high' : 'medium',
                'risk' => 'medium',
                'icon' => 'bi-file-earmark-text',
                'action_url' => '/quotes',
                'action_label' => 'Ver cotizacion',
                'created_at' => $row['valid_until'] ?: $row['created_at'],
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function normalizeNotification(array $item): array
    {
        $created = (string) (($item['created_at'] ?? '') ?: date('Y-m-d H:i:s'));
        return [
            'id' => (string) ($item['id'] ?? uniqid('notification-', false)),
            'type' => (string) ($item['type'] ?? 'system'),
            'type_label' => (string) ($item['type_label'] ?? 'Sistema'),
            'module' => (string) ($item['module'] ?? 'AsisFly'),
            'title' => (string) ($item['title'] ?? 'Notificacion'),
            'body' => (string) ($item['body'] ?? 'Hay una novedad pendiente de revision.'),
            'status' => (string) (($item['status'] ?? '') ?: 'unread'),
            'priority' => (string) (($item['priority'] ?? '') ?: 'medium'),
            'risk' => (string) (($item['risk'] ?? '') ?: 'medium'),
            'icon' => (string) (($item['icon'] ?? '') ?: 'bi-bell'),
            'action_url' => (string) (($item['action_url'] ?? '') ?: '/notifications'),
            'action_label' => (string) (($item['action_label'] ?? '') ?: 'Abrir'),
            'created_at' => $created,
            'time' => $this->relativeTime($created),
            'sort_at' => strtotime($created) ?: time(),
        ];
    }

    private function notificationMetrics(array $notifications): array
    {
        $count = fn (callable $fn): string => (string) count(array_filter($notifications, $fn));
        return [
            ['label' => 'No leidas', 'value' => $count(fn (array $item): bool => $item['status'] === 'unread'), 'hint' => 'Requieren mirada'],
            ['label' => 'Criticas', 'value' => $count(fn (array $item): bool => in_array($item['priority'], ['critical', 'high'], true)), 'hint' => 'Alta prioridad'],
            ['label' => 'Mensajes', 'value' => $count(fn (array $item): bool => $item['type'] === 'message'), 'hint' => 'Omnicanal'],
            ['label' => 'Controles', 'value' => $count(fn (array $item): bool => $item['type'] === 'control'), 'hint' => 'Alertas vivas'],
        ];
    }

    private function workbenchItems(int $companyId, int $userId, array $filters): array
    {
        if (!Database::available()) {
            return [];
        }

        $items = [
            ...$this->approvalItems($companyId, $userId, $filters['view'] ?? 'mine'),
            ...$this->inboxItems($companyId),
            ...$this->taskItems($companyId, $userId, $filters['view'] ?? 'mine'),
            ...$this->quoteItems($companyId),
        ];
        $items = array_map(fn (array $item): array => $this->normalizeWorkbenchItem($item), $items);

        $items = array_values(array_filter($items, function (array $item) use ($filters): bool {
            if (($filters['type'] ?? '') !== '' && $item['type'] !== $filters['type']) {
                return false;
            }
            if (($filters['priority'] ?? '') !== '' && $item['priority'] !== $filters['priority']) {
                return false;
            }
            if (($filters['q'] ?? '') !== '') {
                $haystack = strtolower(implode(' ', [$item['title'], $item['detail'], $item['module'], $item['customer'] ?? '']));
                return str_contains($haystack, strtolower((string) $filters['q']));
            }
            return true;
        }));

        usort($items, fn (array $a, array $b): int => [$this->priorityWeight($b['priority']), $b['sort_at']] <=> [$this->priorityWeight($a['priority']), $a['sort_at']]);
        return array_slice($items, 0, 40);
    }

    private function workbenchFilterQuery(array $source): string
    {
        $query = http_build_query(array_filter([
            'view' => $source['filter_view'] ?? null,
            'type' => $source['filter_type'] ?? null,
            'priority' => $source['filter_priority'] ?? null,
            'q' => $source['filter_q'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        return $query ? '?' . $query : '';
    }

    private function myDayAgenda(array $items): array
    {
        return array_map(function (array $item): array {
            return [
                'time' => (string) ($item['due_label'] ?? 'Hoy'),
                'title' => (string) ($item['title'] ?? 'Pendiente'),
                'detail' => (string) ($item['detail'] ?? ''),
                'status' => (string) ($item['status'] ?? 'pending'),
            ];
        }, array_slice($items, 0, 4));
    }

    private function myDaySuggestions(array $items): array
    {
        $suggestions = [];
        foreach ($items as $item) {
            $priority = (string) ($item['priority'] ?? 'medium');
            if (!in_array($priority, ['critical', 'high'], true)) {
                continue;
            }

            $suggestions[] = [
                'title' => (string) ($item['title'] ?? 'Revisar pendiente'),
                'detail' => (string) ($item['detail'] ?? 'Hay un pendiente real que requiere atencion.'),
            ];

            if (count($suggestions) >= 3) {
                break;
            }
        }

        return $suggestions;
    }

    private function myDayPriorityItem(array $items): ?array
    {
        foreach ($items as $item) {
            if (in_array((string) ($item['priority'] ?? ''), ['critical', 'high'], true)) {
                return $item;
            }
        }

        return $items[0] ?? null;
    }

    private function normalizeWorkbenchItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'type' => (string) ($item['type'] ?? 'task'),
            'module' => (string) ($item['module'] ?? 'AsisFly'),
            'title' => (string) ($item['title'] ?? 'Pendiente'),
            'detail' => (string) ($item['detail'] ?? 'Revisar este pendiente en AsisFly.'),
            'customer' => (string) ($item['customer'] ?? 'AsisFly'),
            'priority' => (string) (($item['priority'] ?? '') ?: 'medium'),
            'status' => (string) (($item['status'] ?? '') ?: 'pending'),
            'risk' => (string) (($item['risk'] ?? '') ?: 'medium'),
            'icon' => (string) (($item['icon'] ?? '') ?: 'bi-briefcase'),
            'action_label' => (string) (($item['action_label'] ?? '') ?: 'Abrir'),
            'action_url' => (string) (($item['action_url'] ?? '') ?: '/workbench'),
            'sort_at' => (int) ($item['sort_at'] ?? time()),
            'due_label' => (string) (($item['due_label'] ?? '') ?: 'Sin fecha'),
        ];
    }

    private function approvalItems(int $companyId, int $userId, string $view): array
    {
        if (!$this->tableExists('action_center_items')) {
            return [];
        }

        $where = ['company_id = :company_id', 'status IN ("pending", "approved", "failed")'];
        $params = ['company_id' => $companyId];
        if ($view === 'mine') {
            $where[] = '(assigned_to = :user_id OR requested_by = :user_id OR assigned_to IS NULL)';
            $params['user_id'] = $userId;
        }

        $statement = Database::connection()->prepare('SELECT id, title, description, module, brain, status, priority, risk_level, due_at, created_at FROM action_center_items WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(priority, "critical", "high", "medium", "low"), id DESC LIMIT 12');
        $statement->execute($params);

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => 'approval',
            'module' => $row['module'] ?: 'Aprobaciones',
            'title' => $row['title'],
            'detail' => $row['description'],
            'customer' => $row['brain'] ?: 'AsisFly',
            'priority' => $row['priority'],
            'status' => $row['status'],
            'risk' => $row['risk_level'],
            'icon' => 'bi-shield-check',
            'action_label' => $row['status'] === 'approved' ? 'Ejecutar' : 'Revisar',
            'action_url' => '/actions?status=' . urlencode((string) $row['status']),
            'sort_at' => strtotime((string) ($row['due_at'] ?: $row['created_at'])) ?: time(),
            'due_label' => $row['due_at'] ? 'Vence ' . $row['due_at'] : 'Creada ' . $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function inboxItems(int $companyId): array
    {
        if (!$this->tableExists('inbox_conversations')) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, channel, customer_name, subject, status, priority, updated_at FROM inbox_conversations WHERE company_id = :company_id AND status IN ("new", "open", "pending_approval") ORDER BY FIELD(priority, "critical", "high", "medium", "low"), updated_at DESC LIMIT 12');
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => 'message',
            'module' => $row['channel'],
            'title' => $row['subject'],
            'detail' => 'Mensaje de ' . $row['customer_name'] . '. Estado: ' . $row['status'] . '.',
            'customer' => $row['customer_name'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'risk' => $row['priority'] === 'critical' ? 'high' : 'medium',
            'icon' => $row['channel'] === 'WhatsApp' ? 'bi-whatsapp' : 'bi-chat-dots',
            'action_label' => 'Responder',
            'action_url' => '/inbox?id=' . (int) $row['id'],
            'sort_at' => strtotime((string) $row['updated_at']) ?: time(),
            'due_label' => 'Actualizado ' . $row['updated_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function taskItems(int $companyId, int $userId, string $view): array
    {
        if (!$this->tableExists('crm_tasks')) {
            return [];
        }

        $where = ['t.company_id = :company_id', 't.status = "pending"'];
        $params = ['company_id' => $companyId];
        if ($view === 'mine') {
            $where[] = '(t.assigned_to = :user_id OR t.assigned_to IS NULL)';
            $params['user_id'] = $userId;
        }

        $statement = Database::connection()->prepare('SELECT t.id, t.title, t.task_type, t.priority, t.due_at, t.created_at, c.name AS customer_name FROM crm_tasks t LEFT JOIN crm_customers c ON c.id = t.customer_id AND c.company_id = t.company_id WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(t.priority, "critical", "high", "medium", "low"), t.due_at IS NULL, t.due_at LIMIT 12');
        $statement->execute($params);

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => 'task',
            'module' => 'Tareas',
            'title' => $row['title'],
            'detail' => 'Pendiente de ' . ($row['customer_name'] ?: 'equipo interno') . '. Tipo: ' . $row['task_type'] . '.',
            'customer' => $row['customer_name'] ?: 'Equipo interno',
            'priority' => $row['priority'],
            'status' => 'pending',
            'risk' => in_array($row['priority'], ['critical', 'high'], true) ? 'high' : 'low',
            'icon' => 'bi-list-check',
            'action_label' => 'Abrir tareas',
            'action_url' => '/tasks',
            'sort_at' => strtotime((string) ($row['due_at'] ?: $row['created_at'])) ?: time(),
            'due_label' => $row['due_at'] ? 'Vence ' . $row['due_at'] : 'Sin fecha',
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function quoteItems(int $companyId): array
    {
        if (!$this->tableExists('quotes')) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, quote_number, customer_name, status, total, currency, valid_until, created_at FROM quotes WHERE company_id = :company_id AND status IN ("draft", "sent") ORDER BY valid_until IS NULL, valid_until, id DESC LIMIT 10');
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'type' => 'quote',
            'module' => 'Cotizaciones',
            'title' => 'Revisar ' . $row['quote_number'],
            'detail' => ($row['customer_name'] ?: 'Cliente directo') . ' / ' . $row['currency'] . ' ' . number_format((float) $row['total'], 0, ',', '.') . '. Estado: ' . $row['status'] . '.',
            'customer' => $row['customer_name'] ?: 'Cliente directo',
            'priority' => $row['valid_until'] && strtotime((string) $row['valid_until']) <= strtotime('+1 day') ? 'high' : 'medium',
            'status' => $row['status'],
            'risk' => 'medium',
            'icon' => 'bi-file-earmark-text',
            'action_label' => 'Ver cotizacion',
            'action_url' => '/quotes',
            'sort_at' => strtotime((string) ($row['valid_until'] ?: $row['created_at'])) ?: time(),
            'due_label' => $row['valid_until'] ? 'Vence ' . $row['valid_until'] : 'Creada ' . $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function workbenchMetrics(array $items): array
    {
        $countType = fn (string $type): string => (string) count(array_filter($items, fn (array $item): bool => $item['type'] === $type));
        $urgent = count(array_filter($items, fn (array $item): bool => in_array($item['priority'], ['critical', 'high'], true)));

        return [
            ['label' => 'Pendientes', 'value' => (string) count($items), 'hint' => 'En bandeja'],
            ['label' => 'Urgentes', 'value' => (string) $urgent, 'hint' => 'Alta prioridad'],
            ['label' => 'Mensajes', 'value' => $countType('message'), 'hint' => 'Por responder'],
            ['label' => 'Aprobaciones', 'value' => $countType('approval'), 'hint' => 'IA esperando'],
            ['label' => 'Tareas', 'value' => $countType('task'), 'hint' => 'Operativas'],
        ];
    }

    private function priorityWeight(string $priority): int
    {
        return ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$priority] ?? 0;
    }

    private function relativeTime(string $date): string
    {
        $timestamp = strtotime($date) ?: time();
        $diff = max(0, time() - $timestamp);
        if ($diff < 60) {
            return 'Ahora';
        }
        if ($diff < 3600) {
            return 'Hace ' . floor($diff / 60) . ' min';
        }
        if ($diff < 86400) {
            return 'Hace ' . floor($diff / 3600) . ' h';
        }
        return 'Hace ' . floor($diff / 86400) . ' dias';
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            Database::connection()->query("SELECT 1 FROM {$table} LIMIT 1");
            return $cache[$table] = true;
        } catch (\Throwable) {
            return $cache[$table] = false;
        }
    }

    private function fallbackWorkbenchItems(): array
    {
        return [];
    }

    private function fallbackNotifications(): array
    {
        return [];
    }
}
