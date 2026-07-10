<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\AutonomyRepository;
use PDO;

final class ActionRepository
{
    public function all(int $companyId, array $filters = []): array
    {
        if (!$this->databaseReady()) {
            return $this->filterFallback($_SESSION['actions'] ?? $this->fallbackActions(), $filters);
        }

        $where = ['a.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['module'] ?? '') !== '') {
            $where[] = 'a.module = :module';
            $params['module'] = $filters['module'];
        }
        if (($filters['risk_level'] ?? '') !== '') {
            $where[] = 'a.risk_level = :risk_level';
            $params['risk_level'] = $filters['risk_level'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.title LIKE :q OR a.description LIKE :q OR a.brain LIKE :q OR a.action_type LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $statement = Database::connection()->prepare('SELECT a.*, requester.name AS requested_name, approver.name AS approved_name, assignee.name AS assigned_name
            FROM action_center_items a
            LEFT JOIN users requester ON requester.id = a.requested_by
            LEFT JOIN users approver ON approver.id = a.approved_by
            LEFT JOIN users assignee ON assignee.id = a.assigned_to
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY FIELD(a.status, "pending", "approved", "executed", "failed", "rejected"), a.priority DESC, a.id DESC');
        $statement->execute($params);

        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'module' => $row['module'],
            'brain' => $row['brain'],
            'action_type' => $row['action_type'],
            'status' => $row['status'],
            'execution_status' => $row['execution_status'] ?? 'not_started',
            'retry_count' => (int) ($row['retry_count'] ?? 0),
            'max_retries' => (int) ($row['max_retries'] ?? 2),
            'priority' => $row['priority'],
            'risk_level' => $row['risk_level'],
            'requires_approval' => (bool) $row['requires_approval'],
            'required_permission' => $row['required_permission'] ?? '',
            'required_role' => $row['required_role'] ?? '',
            'requested_name' => $row['requested_name'] ?? 'Sistema',
            'approved_name' => $row['approved_name'] ?? '',
            'assigned_name' => $row['assigned_name'] ?? '',
            'payload' => json_decode($row['payload_json'] ?? '[]', true) ?: [],
            'result_summary' => $row['result_summary'] ?? '',
            'last_transition_at' => $row['last_transition_at'] ?? null,
            'due_at' => $row['due_at'] ?? null,
            'comments' => $this->comments((int) $row['company_id'], (int) $row['id']),
            'events' => $this->events((int) $row['company_id'], (int) $row['id']),
            'notifications' => $this->notifications((int) $row['company_id'], (int) $row['id']),
            'created_at' => $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function modules(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return array_values(array_unique(array_column($this->fallbackActions(), 'module')));
        }

        $statement = Database::connection()->prepare('SELECT DISTINCT module FROM action_center_items WHERE company_id = :company_id ORDER BY module');
        $statement->execute(['company_id' => $companyId]);
        return array_values(array_filter(array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'module')));
    }

    public function metrics(int $companyId): array
    {
        $actions = $this->all($companyId);
        $count = fn (string $status): int => count(array_filter($actions, fn (array $action) => $action['status'] === $status));

        return [
            ['label' => 'Pendientes', 'value' => (string) $count('pending'), 'status' => 'pending'],
            ['label' => 'Aprobadas', 'value' => (string) $count('approved'), 'status' => 'approved'],
            ['label' => 'Ejecutadas', 'value' => (string) $count('executed'), 'status' => 'executed'],
            ['label' => 'Rechazadas', 'value' => (string) $count('rejected'), 'status' => 'rejected'],
            ['label' => 'Fallidas', 'value' => (string) $count('failed'), 'status' => 'failed'],
        ];
    }

    public function create(int $companyId, ?int $userId, array $data): void
    {
        $item = [
            'id' => random_int(1000, 999999),
            'title' => $data['title'] ?? 'Accion sugerida',
            'description' => $data['description'] ?? '',
            'module' => $data['module'] ?? 'General',
            'brain' => $data['brain'] ?? 'Cerebro Ejecutivo',
            'action_type' => $data['action_type'] ?? 'review',
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'risk_level' => $data['risk_level'] ?? 'medium',
            'requires_approval' => $data['requires_approval'] ?? true,
            'required_permission' => $data['required_permission'] ?? $this->defaultPermission($data['action_type'] ?? 'review'),
            'required_role' => $data['required_role'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? $userId,
            'max_retries' => (int) ($data['max_retries'] ?? 2),
            'due_at' => $data['due_at'] ?? date('Y-m-d H:i:s', strtotime('+1 day')),
            'payload' => $data['payload'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$this->databaseReady()) {
            $_SESSION['actions'][] = $item;
            return;
        }

        $statement = Database::connection()->prepare('INSERT INTO action_center_items (company_id, requested_by, assigned_to, title, description, module, brain, action_type, status, priority, risk_level, requires_approval, required_permission, required_role, max_retries, due_at, payload_json) VALUES (:company_id, :requested_by, :assigned_to, :title, :description, :module, :brain, :action_type, :status, :priority, :risk_level, :requires_approval, :required_permission, :required_role, :max_retries, :due_at, :payload_json)');
        $statement->execute([
            'company_id' => $companyId,
            'requested_by' => $userId,
            'assigned_to' => $item['assigned_to'] ?: null,
            'title' => $item['title'],
            'description' => $item['description'],
            'module' => $item['module'],
            'brain' => $item['brain'],
            'action_type' => $item['action_type'],
            'status' => $item['status'],
            'priority' => $item['priority'],
            'risk_level' => $item['risk_level'],
            'requires_approval' => $item['requires_approval'] ? 1 : 0,
            'required_permission' => $item['required_permission'],
            'required_role' => $item['required_role'],
            'max_retries' => $item['max_retries'],
            'due_at' => $item['due_at'],
            'payload_json' => json_encode($item['payload'], JSON_UNESCAPED_UNICODE),
        ]);
        $actionId = (int) Database::connection()->lastInsertId();
        $this->logEvent($companyId, $actionId, $userId ?: 0, 'created', 'Accion creada y enviada a aprobacion.', ['risk_level' => $item['risk_level'], 'priority' => $item['priority']]);
        $this->notify($companyId, $actionId, $item['assigned_to'] ?: $userId, 'Nueva accion pendiente', $item['title']);
    }

    public function transition(int $companyId, int $id, string $status, int $userId, array $user = []): void
    {
        if (!in_array($status, ['approved', 'rejected', 'executed', 'failed'], true)) {
            return;
        }

        if (!$this->databaseReady()) {
            foreach ($_SESSION['actions'] ?? [] as &$action) {
                if ((int) $action['id'] === $id) {
                    $action['status'] = $status;
                }
            }
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        $action = $this->findForUpdate($companyId, $id);
        if (!$action) {
            $pdo->rollBack();
            return;
        }
        if (!$this->canTransition($action, $status, $user)) {
            $this->logEvent($companyId, $id, $userId, 'permission_denied', 'El usuario no tiene permiso o rol suficiente para esta accion.', ['target_status' => $status]);
            $pdo->commit();
            return;
        }

        $executionStatus = $this->executionStatusFor($status, $action);
        $result = $this->applyEffect($companyId, $action, $status);
        $executedAt = $status === 'executed' ? ', executed_at = CURRENT_TIMESTAMP' : '';
        $statement = $pdo->prepare("UPDATE action_center_items SET status = :status, execution_status = :execution_status, approved_by = :approved_by, result_summary = :result_summary, last_transition_at = CURRENT_TIMESTAMP {$executedAt} WHERE company_id = :company_id AND id = :id");
        $statement->execute([
            'status' => $status,
            'execution_status' => $executionStatus,
            'approved_by' => $userId,
            'result_summary' => $result,
            'company_id' => $companyId,
            'id' => $id,
        ]);

        $this->logEvent($companyId, $id, $userId, $status, $result, ['action_type' => $action['action_type'], 'execution_status' => $executionStatus, 'retry_count' => (int) ($action['retry_count'] ?? 0)]);
        if (in_array($status, ['approved', 'executed', 'rejected'], true)) {
            (new AutonomyRepository())->recordSignal($companyId, $status);
        }
        if (in_array($status, ['approved', 'executed', 'failed', 'rejected'], true)) {
            $this->notify($companyId, $id, (int) ($action['requested_by'] ?? 0), 'Accion ' . $status, $result);
        }
        $pdo->commit();
    }

    public function addComment(int $companyId, int $actionId, int $userId, string $comment): void
    {
        if (trim($comment) === '' || !$this->databaseReady()) {
            return;
        }

        Database::connection()->prepare('INSERT INTO action_center_comments (company_id, action_id, user_id, comment) VALUES (:company_id, :action_id, :user_id, :comment)')->execute([
            'company_id' => $companyId,
            'action_id' => $actionId,
            'user_id' => $userId ?: null,
            'comment' => trim($comment),
        ]);
        $this->logEvent($companyId, $actionId, $userId, 'commented', 'Comentario interno agregado.', []);
        (new AutonomyRepository())->recordSignal($companyId, 'corrected');
    }

    public function retry(int $companyId, int $actionId, int $userId): void
    {
        if (!$this->databaseReady()) {
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $action = $this->findForUpdate($companyId, $actionId);
        if (!$action || (int) ($action['retry_count'] ?? 0) >= (int) ($action['max_retries'] ?? 0)) {
            $pdo->rollBack();
            return;
        }

        $retry = (int) ($action['retry_count'] ?? 0) + 1;
        $statement = $pdo->prepare('UPDATE action_center_items SET status = "approved", execution_status = "queued", retry_count = :retry_count, result_summary = :summary, last_transition_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id');
        $statement->execute([
            'retry_count' => $retry,
            'summary' => 'Reintento programado #' . $retry . '.',
            'company_id' => $companyId,
            'id' => $actionId,
        ]);
        $this->logEvent($companyId, $actionId, $userId, 'retry_queued', 'Reintento programado #' . $retry . '.', ['retry_count' => $retry]);
        $this->notify($companyId, $actionId, (int) ($action['assigned_to'] ?? $action['requested_by'] ?? 0), 'Reintento programado', 'La accion quedo lista para ejecutar nuevamente.');
        $pdo->commit();
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM action_center_items LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function findForUpdate(int $companyId, int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM action_center_items WHERE company_id = :company_id AND id = :id FOR UPDATE');
        $statement->execute(['company_id' => $companyId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function canTransition(array $action, string $status, array $user): bool
    {
        if ($status === 'executed' && ($action['status'] ?? '') !== 'approved') {
            return false;
        }
        if ($status === 'approved' && ($action['status'] ?? '') !== 'pending') {
            return false;
        }

        $permissions = $user['permissions'] ?? [];
        if (in_array('*', $permissions, true)) {
            return true;
        }

        $requiredPermission = (string) ($action['required_permission'] ?? '');
        if ($requiredPermission !== '' && !in_array($requiredPermission, $permissions, true)) {
            return false;
        }

        $requiredRole = (string) ($action['required_role'] ?? '');
        if ($requiredRole !== '' && ($user['role'] ?? '') !== $requiredRole) {
            return false;
        }

        return true;
    }

    private function executionStatusFor(string $status, array $action): string
    {
        return match ($status) {
            'approved' => 'queued',
            'executed' => 'succeeded',
            'failed' => 'failed',
            'rejected' => 'cancelled',
            default => (string) ($action['execution_status'] ?? 'not_started'),
        };
    }

    private function applyEffect(int $companyId, array $action, string $status): string
    {
        $payload = json_decode($action['payload_json'] ?? '[]', true) ?: [];
        $type = $action['action_type'];

        if ($status === 'rejected') {
            return 'Accion rechazada. No se ejecutaron cambios en modulos conectados.';
        }

        if (in_array($type, ['approve_social_calendar', 'approve_social_campaign', 'approve_campaign'], true)) {
            if ($status === 'approved') {
                $affected = $this->updateSocialPosts($companyId, 'draft', 'approved');
                return "Calendario social aprobado. {$affected} publicaciones pasaron de borrador a aprobado.";
            }

            if ($status === 'executed') {
                $affected = $this->updateSocialPosts($companyId, 'approved', 'scheduled');
                return "Calendario social programado. {$affected} publicaciones pasaron a estado programado.";
            }
        }

        if ($type === 'send_followup' || $type === 'approve_commercial_action') {
            if ($status === 'approved') {
                return 'Seguimiento comercial aprobado. AsisFly queda listo para preparar mensajes personalizados.';
            }

            if ($status === 'executed') {
                return 'Seguimiento comercial marcado como ejecutado en modo simulado.';
            }
        }

        if ($type === 'send_omnichannel_reply') {
            $conversationId = (int) ($payload['conversation_id'] ?? 0);
            if ($status === 'approved') {
                $affected = $this->updateInboxDraft($companyId, $conversationId, 'draft', 'approved');
                return "Respuesta omnicanal aprobada. {$affected} borrador quedo listo para enviar.";
            }

            if ($status === 'executed') {
                $result = (new OmnichannelRepository())->markOutboundAttempt($companyId, $conversationId, $payload);
                if (str_contains(strtolower($result), 'enviado')) {
                    $affected = $this->updateInboxDraft($companyId, $conversationId, 'approved', 'sent');
                    $this->markConversationAnswered($companyId, $conversationId);
                    return "{$result} {$affected} mensaje quedo marcado como enviado.";
                }

                $this->updateInboxDraft($companyId, $conversationId, 'approved', 'failed');
                $this->markConversationPendingApproval($companyId, $conversationId);
                return $result . ' El borrador queda pendiente para correccion o reintento.';
            }
        }

        if ($type === 'send_quote') {
            $quoteId = (int) ($payload['quote_id'] ?? 0);
            $channel = (string) ($payload['channel'] ?? 'Email');
            if ($status === 'approved') {
                return 'Envio de cotizacion aprobado. La propuesta queda lista para despacho por ' . $channel . '.';
            }

            if ($status === 'executed') {
                return (new QuoteRepository())->markSent($companyId, $quoteId, $channel);
            }
        }

        return match ($status) {
            'approved' => 'Accion aprobada y lista para ejecutar.',
            'executed' => 'Accion marcada como ejecutada.',
            'failed' => 'Accion marcada como fallida.',
            default => 'Estado actualizado.',
        };
    }

    private function updateSocialPosts(int $companyId, string $from, string $to): int
    {
        $statement = Database::connection()->prepare('UPDATE social_posts SET status = :to WHERE company_id = :company_id AND status = :from');
        $statement->execute([
            'to' => $to,
            'company_id' => $companyId,
            'from' => $from,
        ]);

        return $statement->rowCount();
    }

    private function updateInboxDraft(int $companyId, int $conversationId, string $from, string $to): int
    {
        $statement = Database::connection()->prepare('UPDATE inbox_messages SET status = :to WHERE company_id = :company_id AND conversation_id = :conversation_id AND direction = "outbound" AND ai_generated = 1 AND status = :from ORDER BY id DESC LIMIT 1');
        $statement->execute([
            'to' => $to,
            'company_id' => $companyId,
            'conversation_id' => $conversationId,
            'from' => $from,
        ]);

        return $statement->rowCount();
    }

    private function markConversationAnswered(int $companyId, int $conversationId): void
    {
        Database::connection()->prepare('UPDATE inbox_conversations SET status = "answered" WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);
    }

    private function markConversationPendingApproval(int $companyId, int $conversationId): void
    {
        Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval" WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);
    }

    private function logEvent(int $companyId, int $actionId, int $userId, string $eventType, string $notes, array $metadata): void
    {
        $statement = Database::connection()->prepare('INSERT INTO action_center_events (company_id, action_id, user_id, event_type, notes, metadata_json) VALUES (:company_id, :action_id, :user_id, :event_type, :notes, :metadata_json)');
        $statement->execute([
            'company_id' => $companyId,
            'action_id' => $actionId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'notes' => $notes,
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function comments(int $companyId, int $actionId): array
    {
        if (!$this->hasTable('action_center_comments')) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT c.*, u.name AS user_name FROM action_center_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.company_id = :company_id AND c.action_id = :action_id ORDER BY c.id DESC LIMIT 5');
        $statement->execute(['company_id' => $companyId, 'action_id' => $actionId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function events(int $companyId, int $actionId): array
    {
        $statement = Database::connection()->prepare('SELECT e.*, u.name AS user_name FROM action_center_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.company_id = :company_id AND e.action_id = :action_id ORDER BY e.id DESC LIMIT 6');
        $statement->execute(['company_id' => $companyId, 'action_id' => $actionId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function notifications(int $companyId, int $actionId): array
    {
        if (!$this->hasTable('action_center_notifications')) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT n.*, u.name AS user_name FROM action_center_notifications n LEFT JOIN users u ON u.id = n.user_id WHERE n.company_id = :company_id AND n.action_id = :action_id ORDER BY n.id DESC LIMIT 4');
        $statement->execute(['company_id' => $companyId, 'action_id' => $actionId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function notify(int $companyId, int $actionId, ?int $userId, string $title, string $body): void
    {
        if (!$this->hasTable('action_center_notifications')) {
            return;
        }

        Database::connection()->prepare('INSERT INTO action_center_notifications (company_id, action_id, user_id, title, body, status, sent_at) VALUES (:company_id, :action_id, :user_id, :title, :body, "sent", CURRENT_TIMESTAMP)')->execute([
            'company_id' => $companyId,
            'action_id' => $actionId,
            'user_id' => $userId ?: null,
            'title' => $title,
            'body' => $body,
        ]);
        Database::connection()->prepare('UPDATE action_center_items SET notified_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $actionId,
        ]);
    }

    private function hasTable(string $table): bool
    {
        try {
            Database::connection()->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function defaultPermission(string $actionType): string
    {
        return match (true) {
            str_contains($actionType, 'quote') => 'quotes.manage',
            str_contains($actionType, 'social') || str_contains($actionType, 'campaign') => 'chat.use',
            str_contains($actionType, 'omnichannel') => 'chat.use',
            str_contains($actionType, 'commercial') || str_contains($actionType, 'followup') => 'crm.manage',
            default => 'dashboard.view',
        };
    }

    private function fallbackActions(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Aprobar campana social de la semana',
                'description' => 'Asisti Social preparo publicaciones para vender mas pedidos por WhatsApp.',
                'module' => 'Asisti Social',
                'brain' => 'Cerebro Comercial',
                'action_type' => 'approve_campaign',
                'status' => 'pending',
                'priority' => 'high',
                'risk_level' => 'medium',
                'requires_approval' => true,
                'payload' => ['channel' => 'Instagram'],
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    private function filterFallback(array $actions, array $filters): array
    {
        return array_values(array_filter($actions, function (array $action) use ($filters): bool {
            if (($filters['status'] ?? '') !== '' && ($action['status'] ?? '') !== $filters['status']) {
                return false;
            }
            if (($filters['module'] ?? '') !== '' && ($action['module'] ?? '') !== $filters['module']) {
                return false;
            }
            if (($filters['risk_level'] ?? '') !== '' && ($action['risk_level'] ?? '') !== $filters['risk_level']) {
                return false;
            }
            if (($filters['q'] ?? '') !== '') {
                $haystack = strtolower(implode(' ', [
                    $action['title'] ?? '',
                    $action['description'] ?? '',
                    $action['brain'] ?? '',
                    $action['action_type'] ?? '',
                ]));
                return str_contains($haystack, strtolower((string) $filters['q']));
            }

            return true;
        }));
    }
}
