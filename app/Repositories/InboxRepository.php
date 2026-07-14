<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\AutonomyRepository;
use App\Services\AIResponseReviewService;
use App\Services\BrandRoutingDetector;
use App\Services\OmnichannelAiResponder;
use PDO;

final class InboxRepository
{
    public function conversations(int $companyId, array $filters = []): array
    {
        if (!$this->databaseReady()) {
            return $this->fallbackConversations();
        }

        $where = ['c.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (!empty($filters['q'])) {
            $where[] = '(c.customer_name LIKE :q OR c.customer_handle LIKE :q OR c.subject LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['channel'])) {
            $where[] = 'c.channel = :channel';
            $params['channel'] = $filters['channel'];
        }

        if (!empty($filters['account_id'])) {
            $where[] = 'c.account_id = :account_id';
            $params['account_id'] = (int) $filters['account_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 'c.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        $sql = 'SELECT c.*, a.display_name AS account_name, a.brain_key AS account_brain, a.status AS account_status, u.name AS assigned_name, MAX(m.created_at) AS last_message_at,
                       SUBSTRING_INDEX(GROUP_CONCAT(m.body ORDER BY m.created_at DESC SEPARATOR "||"), "||", 1) AS last_message,
                       MAX(CASE WHEN m.direction = "outbound" AND m.ai_generated = 1 AND m.status = "sent" THEN 1 ELSE 0 END) AS has_ai_sent,
                       MAX(CASE WHEN m.direction = "outbound" AND m.ai_generated = 1 AND m.status IN ("draft", "approved", "failed") THEN 1 ELSE 0 END) AS has_ai_draft,
                       SUM(CASE WHEN m.direction = "inbound" THEN 1 ELSE 0 END) AS inbound_count,
                       SUM(CASE WHEN m.direction = "outbound" THEN 1 ELSE 0 END) AS outbound_count
                FROM inbox_conversations c
                LEFT JOIN omnichannel_accounts a ON a.id = c.account_id AND a.company_id = c.company_id
                LEFT JOIN users u ON u.id = c.assigned_to
                LEFT JOIN inbox_messages m ON m.conversation_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY c.id
                ORDER BY FIELD(c.status, "new", "pending_approval", "open", "answered", "closed"), c.priority DESC, last_message_at DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        $conversations = array_map(fn (array $row): array => $this->decorateConversation($row), $statement->fetchAll(PDO::FETCH_ASSOC));
        $conversations = $this->attachBrandDetections($companyId, $conversations);

        return $this->filterBySupervisionState($conversations, $filters);
    }

    public function selectedConversation(int $companyId, int $id, array $filters = []): ?array
    {
        $conversations = $this->conversations($companyId, $filters);
        $id = $id ?: (int) ($conversations[0]['id'] ?? 0);
        if (!$id) {
            return null;
        }

        if (!$this->databaseReady()) {
            $conversation = $conversations[0] ?? null;
            if (!$conversation) {
                return null;
            }
            $conversation['messages'] = $this->fallbackMessages();
            return $conversation;
        }

        $statement = Database::connection()->prepare('SELECT c.*, a.display_name AS account_name, a.brain_key AS account_brain, a.status AS account_status, u.name AS assigned_name,
            EXISTS(SELECT 1 FROM inbox_messages sm WHERE sm.conversation_id = c.id AND sm.direction = "outbound" AND sm.ai_generated = 1 AND sm.status = "sent" LIMIT 1) AS has_ai_sent,
            EXISTS(SELECT 1 FROM inbox_messages dm WHERE dm.conversation_id = c.id AND dm.direction = "outbound" AND dm.ai_generated = 1 AND dm.status IN ("draft", "approved", "failed") LIMIT 1) AS has_ai_draft
            FROM inbox_conversations c
            LEFT JOIN omnichannel_accounts a ON a.id = c.account_id AND a.company_id = c.company_id
            LEFT JOIN users u ON u.id = c.assigned_to
            WHERE c.company_id = :company_id AND c.id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $id]);
        $conversation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$conversation) {
            return null;
        }

        $conversation = $this->decorateConversation($conversation);
        $messages = Database::connection()->prepare('SELECT * FROM inbox_messages WHERE company_id = :company_id AND conversation_id = :conversation_id ORDER BY created_at');
        $messages->execute(['company_id' => $companyId, 'conversation_id' => $id]);
        $conversation['messages'] = $messages->fetchAll(PDO::FETCH_ASSOC);
        $conversation['latest_draft'] = $this->latestDraft($companyId, $id);
        $conversation = $this->applyLatestAiDecision($companyId, $id, $conversation);
        $conversation['brand_detection'] = $this->brandDetection($companyId, $id);
        $conversation['decision_timeline'] = $this->decisionTimeline($companyId, $id);

        return $conversation;
    }

    public function updateStatus(int $companyId, int $conversationId, string $status): int
    {
        if (!$this->databaseReady() || $conversationId <= 0 || !in_array($status, ['new', 'open', 'pending_approval', 'answered', 'closed'], true)) {
            return 0;
        }

        Database::connection()->prepare('UPDATE inbox_conversations SET status = :status WHERE company_id = :company_id AND id = :id')->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);

        return $status === 'closed' ? $this->cancelPendingTasksForConversation($companyId, $conversationId) : 0;
    }

    private function cancelPendingTasksForConversation(int $companyId, int $conversationId): int
    {
        try {
            $tasks = Database::connection()->prepare(
                'SELECT id FROM crm_tasks
                 WHERE company_id = :company_id AND source_type = "omnichannel" AND source_id = :conversation_id AND status = "pending"'
            );
            $tasks->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
            $taskIds = array_map('intval', $tasks->fetchAll(PDO::FETCH_COLUMN));
            if (!$taskIds) {
                return 0;
            }

            Database::connection()->prepare(
                'UPDATE crm_tasks
                 SET status = "cancelled", completed_at = CURRENT_TIMESTAMP
                 WHERE company_id = :company_id AND source_type = "omnichannel" AND source_id = :conversation_id AND status = "pending"'
            )->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);

            try {
                $event = Database::connection()->prepare(
                    'INSERT INTO crm_task_events (company_id, task_id, user_id, event_type, summary, metadata_json)
                     VALUES (:company_id, :task_id, NULL, "conversation_closed", "Tarea cancelada al cerrar la conversacion de origen.", JSON_OBJECT("conversation_id", :conversation_id))'
                );
                foreach ($taskIds as $taskId) {
                    $event->execute(['company_id' => $companyId, 'task_id' => $taskId, 'conversation_id' => $conversationId]);
                }
            } catch (\Throwable) {
                // El historial es opcional: la sincronizacion de estado no debe bloquearse.
            }

            return count($taskIds);
        } catch (\Throwable) {
            // La columna de origen puede no existir durante un despliegue incompleto.
            return 0;
        }
    }

    public function metrics(int $companyId): array
    {
        $conversations = $this->conversations($companyId);
        $countState = fn (string $state): int => count(array_filter($conversations, fn (array $conversation) => ($conversation['ai_state'] ?? '') === $state));
        $received = count($conversations);
        $aiResolved = $countState('ai_resolved');
        $humanRequired = $countState('human_required');
        $approvalRequired = $countState('approval_required');
        $autonomy = $received > 0 ? (string) round(($aiResolved / $received) * 100) . '%' : '0%';
        $savedMinutes = $aiResolved * 4;

        return [
            ['label' => 'Recibidas', 'value' => (string) $received],
            ['label' => 'Resueltas por IA', 'value' => (string) $aiResolved],
            ['label' => 'Por aprobar', 'value' => (string) $approvalRequired],
            ['label' => 'Requieren humano', 'value' => (string) $humanRequired],
            ['label' => 'Tiempo ahorrado', 'value' => $savedMinutes . ' min'],
            ['label' => 'Autonomia', 'value' => $autonomy],
        ];
    }

    public function supervisionReport(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return [
                'decisionCounts' => [],
                'generatedCounts' => [],
                'channels' => [],
                'recentContexts' => [],
                'routingSummary' => [],
            ];
        }

        return [
            'decisionCounts' => $this->decisionCounts($companyId),
            'generatedCounts' => $this->generatedResponseCounts($companyId),
            'channels' => $this->channelAutonomySettings($companyId),
            'recentContexts' => $this->recentContextLogs($companyId),
            'routingSummary' => $this->brandRoutingSummary($companyId),
        ];
    }

    public function brandRoutes(int $companyId): array
    {
        return (new BrandRoutingDetector())->activeRoutes($companyId);
    }

    public function confirmBrandRoute(int $companyId, int $conversationId, int $routeId, int $userId): array
    {
        if (!$this->databaseReady() || $conversationId <= 0 || $routeId <= 0) {
            return ['ok' => false, 'message' => 'Selecciona una conversacion y una marca valida.'];
        }

        return (new BrandRoutingDetector())->confirmRoute($companyId, $conversationId, $routeId, $userId);
    }

    public function suggestReply(int $companyId, int $conversationId, int $userId): void
    {
        $conversation = $this->selectedConversation($companyId, $conversationId);
        if (!$conversation) {
            return;
        }

        $lastInbound = $this->lastInboundMessage($conversation);
        $lastInboundRow = $this->lastInboundRow($conversation);
        $reply = $this->draftReply($conversation, $lastInbound);
        $draftTrace = [];

        try {
            $aiDraft = (new OmnichannelAiResponder())->draft(
                $companyId,
                $this->accountPayload($conversation),
                $this->messagePayload($conversation, $lastInboundRow, $lastInbound),
                $this->decisionPayload($conversation),
                $conversation['messages'] ?? [],
                $userId
            );
            $reply = (string) ($aiDraft['body'] ?? $reply);
            $draftTrace = $aiDraft;
        } catch (\Throwable) {
            $draftTrace = [];
        }

        if (!$this->databaseReady()) {
            $_SESSION['inbox_suggestions'][] = $reply;
            return;
        }

        $existingDraft = Database::connection()->prepare('SELECT id FROM inbox_messages WHERE company_id = :company_id AND conversation_id = :conversation_id AND direction = "outbound" AND ai_generated = 1 AND status IN ("draft", "approved", "failed") ORDER BY id DESC LIMIT 1');
        $existingDraft->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
        $draftId = (int) $existingDraft->fetchColumn();

        if ($draftId > 0) {
            Database::connection()->prepare('UPDATE inbox_messages SET body = :body, sender_name = "AsisFly", status = "draft" WHERE company_id = :company_id AND id = :id')->execute([
                'body' => $reply,
                'company_id' => $companyId,
                'id' => $draftId,
            ]);
        } else {
            $statement = Database::connection()->prepare('INSERT INTO inbox_messages (company_id, account_id, conversation_id, provider, direction, sender_name, body, ai_generated, status) VALUES (:company_id, :account_id, :conversation_id, :provider, :direction, :sender_name, :body, :ai_generated, :status)');
            $statement->execute([
                'company_id' => $companyId,
                'account_id' => $conversation['account_id'] ?? null,
                'conversation_id' => $conversationId,
                'provider' => $conversation['provider'] ?? null,
                'direction' => 'outbound',
                'sender_name' => 'AsisFly',
                'body' => $reply,
                'ai_generated' => 1,
                'status' => 'draft',
            ]);
            $draftId = (int) Database::connection()->lastInsertId();
        }

        if (!empty($draftTrace)) {
            $this->logTrainingTrace($companyId, $conversationId, $conversation, $draftTrace, $draftId);
        }

        Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval" WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);

        if ($draftId > 0 && !$this->hasPendingReplyApproval($companyId, $conversationId)) {
            (new ActionRepository())->create($companyId, $userId, [
                'title' => 'Aprobar respuesta omnicanal',
                'description' => 'AsisFly redacto una respuesta para ' . $conversation['customer_name'] . ' en ' . ($conversation['account_name'] ?: $conversation['channel']) . '.',
                'module' => 'Bandeja Omnicanal',
                'brain' => 'Cerebro Comercial',
                'action_type' => 'send_omnichannel_reply',
                'priority' => $conversation['priority'],
                'risk_level' => 'medium',
                'payload' => ['conversation_id' => $conversationId, 'account_id' => $conversation['account_id'] ?? null, 'account' => $conversation['account_name'] ?? null, 'channel' => $conversation['channel'], 'reply' => $reply],
                'requires_approval' => true,
            ]);
        }
    }

    public function saveDraft(int $companyId, int $conversationId, int $userId, string $userName, string $body): void
    {
        $body = trim($body);
        if (!$this->databaseReady() || $conversationId <= 0 || $body === '') {
            return;
        }

        $conversation = $this->selectedConversation($companyId, $conversationId);
        if (!$conversation) {
            return;
        }

        $draft = $this->latestDraft($companyId, $conversationId);
        if ($draft) {
            $previousBody = trim((string) ($draft['body'] ?? ''));
            Database::connection()->prepare('UPDATE inbox_messages SET body = :body, sender_name = :sender_name, status = "draft" WHERE company_id = :company_id AND id = :id')->execute([
                'body' => $body,
                'sender_name' => !empty($draft['ai_generated']) ? 'AsisFly editado por ' . $userName : $userName,
                'company_id' => $companyId,
                'id' => (int) $draft['id'],
            ]);
            if (!empty($draft['ai_generated']) && trim((string) $draft['body']) !== $body) {
                (new AIResponseReviewService())->record($companyId, [
                    'conversation_id' => $conversationId,
                    'message_id' => (int) $draft['id'],
                    'customer_message' => $this->lastInboundMessage($conversation),
                    'ai_response' => $previousBody,
                    'final_response' => $body,
                    'reviewed_by' => $userId,
                    'result' => 'edited',
                    'correction_reason' => 'Edicion manual desde Centro de Supervision.',
                    'channel' => $conversation['channel'] ?? null,
                    'intent' => $conversation['subject'] ?? null,
                    'confidence' => $conversation['ai_confidence'] ?? null,
                    'sources' => $this->latestDraftTrace($companyId, $conversationId),
                    'generated_response_id' => $this->latestGeneratedResponseId($companyId, $conversationId),
                ]);
                (new AutonomyRepository())->recordSignal($companyId, 'corrected');
            }
        } else {
            Database::connection()->prepare('INSERT INTO inbox_messages (company_id, account_id, conversation_id, provider, direction, sender_name, body, ai_generated, status) VALUES (:company_id, :account_id, :conversation_id, :provider, "outbound", :sender_name, :body, 0, "draft")')->execute([
                'company_id' => $companyId,
                'account_id' => $conversation['account_id'] ?? null,
                'conversation_id' => $conversationId,
                'provider' => $conversation['provider'] ?? null,
                'sender_name' => $userName,
                'body' => $body,
            ]);
            (new AutonomyRepository())->recordSignal($companyId, 'corrected');
        }

        Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval", updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);
    }

    public function sendDraft(int $companyId, int $conversationId, int $userId): array
    {
        if (!$this->databaseReady() || $conversationId <= 0) {
            return ['ok' => false, 'message' => 'Bandeja omnicanal no disponible.'];
        }

        $conversation = $this->selectedConversation($companyId, $conversationId);
        $draft = $this->latestDraft($companyId, $conversationId);
        if (!$conversation || !$draft) {
            return ['ok' => false, 'message' => 'No hay borrador para enviar.'];
        }

        $result = (new OmnichannelRepository())->sendOutboundAttempt($companyId, $conversationId, [
            'conversation_id' => $conversationId,
            'message_id' => (int) $draft['id'],
            'approved_by' => $userId,
            'body' => $draft['body'],
        ]);

        if (empty($result['ok'])) {
            Database::connection()->prepare('UPDATE inbox_messages SET status = "failed" WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $companyId,
                'id' => (int) $draft['id'],
            ]);
            Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval", updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $companyId,
                'id' => $conversationId,
            ]);

            return $result;
        }

        Database::connection()->prepare('UPDATE inbox_messages SET status = "sent", sent_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => (int) $draft['id'],
        ]);
        Database::connection()->prepare('UPDATE inbox_conversations SET status = "answered", last_outbound_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);

        if (!empty($draft['ai_generated'])) {
            $trace = $this->latestDraftTrace($companyId, $conversationId);
            $generatedResponseId = $this->latestGeneratedResponseId($companyId, $conversationId);
            if (!$this->reviewExists($companyId, (int) $draft['id'])) {
                (new AIResponseReviewService())->record($companyId, [
                    'conversation_id' => $conversationId,
                    'message_id' => (int) $draft['id'],
                    'customer_message' => $this->lastInboundMessage($conversation),
                    'ai_response' => (string) ($draft['body'] ?? ''),
                    'final_response' => (string) ($draft['body'] ?? ''),
                    'reviewed_by' => $userId,
                    'result' => str_contains((string) ($draft['sender_name'] ?? ''), 'editado') ? 'edited' : 'approved',
                    'correction_reason' => 'Aprobacion y envio desde Centro de Supervision.',
                    'channel' => $conversation['channel'] ?? null,
                    'intent' => $conversation['subject'] ?? null,
                    'confidence' => $conversation['ai_confidence'] ?? null,
                    'sources' => $trace,
                    'generated_response_id' => $generatedResponseId,
                ]);
            }
            (new AIResponseReviewService())->markGeneratedResponse($companyId, $generatedResponseId, 'sent');
        }

        (new AutonomyRepository())->recordSignal($companyId, 'executed');
        return $result;
    }

    public function assignHuman(int $companyId, int $conversationId, int $userId): void
    {
        if (!$this->databaseReady() || $conversationId <= 0) {
            return;
        }

        Database::connection()->prepare('UPDATE inbox_conversations SET assigned_to = :assigned_to, status = "open" WHERE company_id = :company_id AND id = :id')->execute([
            'assigned_to' => $userId,
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);

        $conversation = $this->selectedConversation($companyId, $conversationId);
        Database::connection()->prepare('INSERT INTO inbox_messages (company_id, account_id, conversation_id, provider, direction, sender_name, body, ai_generated, status) VALUES (:company_id, :account_id, :conversation_id, :provider, "outbound", "Sistema", :body, 0, "sent")')->execute([
            'company_id' => $companyId,
            'account_id' => $conversation['account_id'] ?? null,
            'conversation_id' => $conversationId,
            'provider' => $conversation['provider'] ?? null,
            'body' => 'Conversacion derivada a humano para seguimiento manual.',
        ]);
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM inbox_conversations LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function lastInboundMessage(array $conversation): string
    {
        $messages = $conversation['messages'] ?? [];
        $inbound = array_values(array_filter($messages, fn (array $message) => ($message['direction'] ?? '') === 'inbound'));
        return (string) ($inbound[array_key_last($inbound)]['body'] ?? ($conversation['last_message'] ?? ''));
    }

    private function lastInboundRow(array $conversation): array
    {
        $messages = $conversation['messages'] ?? [];
        $inbound = array_values(array_filter($messages, fn (array $message) => ($message['direction'] ?? '') === 'inbound'));
        return $inbound ? (array) $inbound[array_key_last($inbound)] : [];
    }

    private function latestDraft(int $companyId, int $conversationId): ?array
    {
        if (!$this->databaseReady()) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM inbox_messages WHERE company_id = :company_id AND conversation_id = :conversation_id AND direction = "outbound" AND status IN ("draft", "approved", "failed") ORDER BY id DESC LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function hasPendingReplyApproval(int $companyId, int $conversationId): bool
    {
        try {
            $statement = Database::connection()->prepare(
                'SELECT id FROM action_center_items
                 WHERE company_id = :company_id
                   AND action_type = "send_omnichannel_reply"
                   AND status IN ("pending", "approved")
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, "$.conversation_id")) = :conversation_id
                 LIMIT 1'
            );
            $statement->execute([
                'company_id' => $companyId,
                'conversation_id' => (string) $conversationId,
            ]);

            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            // A missing optional audit table must not prevent a response draft.
            return false;
        }
    }

    private function decorateConversation(array $conversation): array
    {
        $status = (string) ($conversation['status'] ?? 'new');
        $priority = (string) ($conversation['priority'] ?? 'medium');
        $hasAiSent = !empty($conversation['has_ai_sent']);
        $hasAiDraft = !empty($conversation['has_ai_draft']);
        $assigned = !empty($conversation['assigned_to']) || !empty($conversation['assigned_name']);

        $state = match ($status) {
            'answered' => $hasAiSent ? 'ai_resolved' : 'human_answered',
            'pending_approval' => 'approval_required',
            'open' => $assigned ? 'human_reviewing' : 'human_required',
            'closed' => 'closed',
            default => 'human_required',
        };

        if ($status === 'new' && $hasAiDraft) {
            $state = 'approval_required';
        }

        $labels = [
            'ai_resolved' => 'Resuelta por IA',
            'approval_required' => 'Requiere aprobacion',
            'human_required' => 'Requiere humano',
            'human_reviewing' => 'En revision humana',
            'human_answered' => 'Respondida por humano',
            'closed' => 'Cerrada',
        ];

        $activities = [
            'ai_resolved' => 'IA respondio automaticamente',
            'approval_required' => 'IA preparo una respuesta para revisar',
            'human_required' => 'IA necesita apoyo humano',
            'human_reviewing' => 'Humano esta revisando',
            'human_answered' => 'Humano respondio la conversacion',
            'closed' => 'Conversacion cerrada',
        ];

        $reasons = [
            'ai_resolved' => 'La conversacion ya fue atendida y queda auditada en el historial.',
            'approval_required' => 'Hay una respuesta lista, pero requiere aprobacion antes de enviarse.',
            'human_required' => 'AsisFly necesita mas contexto, autorizacion o una decision comercial.',
            'human_reviewing' => 'La conversacion fue tomada por una persona del equipo.',
            'human_answered' => 'La ultima gestion fue realizada manualmente por el equipo.',
            'closed' => 'No requiere nuevas acciones por ahora.',
        ];

        $confidence = match ($state) {
            'ai_resolved' => 92,
            'approval_required' => 76,
            'human_reviewing' => 68,
            'human_answered' => 64,
            'closed' => 100,
            default => $priority === 'critical' || $priority === 'high' ? 48 : 55,
        };

        $customer = (string) ($conversation['customer_name'] ?? 'cliente');
        $subject = (string) ($conversation['subject'] ?? 'conversacion');
        $channel = (string) (($conversation['account_name'] ?? '') ?: ($conversation['channel'] ?? 'canal'));

        $conversation['ai_state'] = $state;
        $conversation['ai_state_label'] = $labels[$state] ?? 'Requiere supervision';
        $conversation['ai_activity'] = $activities[$state] ?? 'AsisFly reviso la conversacion';
        $conversation['ai_reason'] = $reasons[$state] ?? 'AsisFly dejo la conversacion en supervision.';
        $conversation['ai_confidence'] = $confidence;
        $conversation['ai_summary'] = "Conversacion con {$customer} sobre {$subject} desde {$channel}.";
        $conversation['requires_human'] = in_array($state, ['approval_required', 'human_required', 'human_reviewing'], true);

        return $conversation;
    }

    private function applyLatestAiDecision(int $companyId, int $conversationId, array $conversation): array
    {
        $decision = $this->latestAiDecision($companyId, $conversationId);
        if (!$decision) {
            return $conversation;
        }

        $mode = (string) ($decision['mode'] ?? '');
        $state = match ($mode) {
            'auto_resolved' => 'ai_resolved',
            'approval_required' => 'approval_required',
            'human_required' => 'human_required',
            default => (string) ($conversation['ai_state'] ?? 'human_required'),
        };
        $labels = [
            'ai_resolved' => 'Resuelta por IA',
            'approval_required' => 'Requiere aprobacion',
            'human_required' => 'Requiere humano',
            'human_reviewing' => 'En revision humana',
            'human_answered' => 'Respondida por humano',
            'closed' => 'Cerrada',
        ];
        $activities = [
            'ai_resolved' => 'IA respondio automaticamente',
            'approval_required' => 'IA preparo una respuesta para revisar',
            'human_required' => 'IA escalo la conversacion',
            'human_reviewing' => 'Humano esta revisando',
            'human_answered' => 'Humano respondio la conversacion',
            'closed' => 'Conversacion cerrada',
        ];

        $conversation['ai_state'] = $state;
        $conversation['ai_state_label'] = $labels[$state] ?? 'Requiere supervision';
        $conversation['ai_activity'] = $activities[$state] ?? 'AsisFly reviso la conversacion';
        $conversation['ai_reason'] = (string) ($decision['reason'] ?? $conversation['ai_reason'] ?? '');
        $conversation['ai_confidence'] = (int) ($decision['confidence'] ?? $conversation['ai_confidence'] ?? 0);
        $conversation['ai_decision'] = $decision;

        return $conversation;
    }

    private function filterBySupervisionState(array $conversations, array $filters): array
    {
        $state = (string) ($filters['ai_state'] ?? '');
        if ($state !== '') {
            $conversations = array_values(array_filter($conversations, fn (array $conversation): bool => ($conversation['ai_state'] ?? '') === $state));
        }

        if (!empty($filters['intervention'])) {
            $conversations = array_values(array_filter($conversations, fn (array $conversation): bool => !empty($conversation['requires_human'])));
        }

        $brandRouteId = (int) ($filters['brand_route_id'] ?? 0);
        if ($brandRouteId > 0) {
            $conversations = array_values(array_filter($conversations, fn (array $conversation): bool => (int) ($conversation['brand_detection']['route_id'] ?? 0) === $brandRouteId));
        }

        $routingStatus = (string) ($filters['routing_status'] ?? '');
        if ($routingStatus !== '') {
            $conversations = array_values(array_filter($conversations, function (array $conversation) use ($routingStatus): bool {
                $status = (string) ($conversation['brand_detection']['status'] ?? '');
                if ($routingStatus === 'unrouted') {
                    return $status === '';
                }

                if ($routingStatus === 'manual') {
                    return in_array($status, ['confirmed', 'corrected'], true);
                }

                return $status === $routingStatus;
            }));
        }

        return $conversations;
    }

    private function latestAiDecision(int $companyId, int $conversationId): ?array
    {
        try {
            $statement = Database::connection()->prepare('SELECT payload_json FROM omnichannel_events WHERE company_id = :company_id AND conversation_id = :conversation_id AND payload_json LIKE :needle ORDER BY id DESC LIMIT 1');
            $statement->execute([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'needle' => '%"ai_decision"%',
            ]);
            $payload = json_decode((string) $statement->fetchColumn(), true);
            $decision = is_array($payload) ? ($payload['ai_decision'] ?? null) : null;
            return is_array($decision) ? $decision : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function attachBrandDetections(int $companyId, array $conversations): array
    {
        foreach ($conversations as $index => $conversation) {
            $conversations[$index]['brand_detection'] = $this->brandDetection($companyId, (int) ($conversation['id'] ?? 0));
        }

        return $conversations;
    }

    private function brandDetection(int $companyId, int $conversationId): ?array
    {
        return (new BrandRoutingDetector())->latestForConversation($companyId, $conversationId);
    }

    private function decisionTimeline(int $companyId, int $conversationId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT status, payload_json, created_at FROM omnichannel_events WHERE company_id = :company_id AND conversation_id = :conversation_id AND payload_json LIKE :needle ORDER BY id DESC LIMIT 8');
            $statement->execute([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'needle' => '%"ai_decision"%',
            ]);

            return array_values(array_filter(array_map(function (array $row): ?array {
                $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
                $decision = is_array($payload) ? ($payload['ai_decision'] ?? null) : null;
                if (!is_array($decision)) {
                    return null;
                }

                return [
                    'status' => (string) ($row['status'] ?? ''),
                    'mode' => (string) ($decision['mode'] ?? ''),
                    'risk' => (string) ($decision['risk'] ?? 'medium'),
                    'confidence' => (int) ($decision['confidence'] ?? 0),
                    'reason' => (string) ($decision['reason'] ?? ''),
                    'next_step' => (string) ($payload['next_step'] ?? ''),
                    'draft_status' => (string) ($payload['ai_draft']['status'] ?? ''),
                    'draft_provider' => (string) ($payload['ai_draft']['provider'] ?? ''),
                    'draft_model' => (string) ($payload['ai_draft']['model'] ?? ''),
                    'memory_hits' => (int) ($payload['ai_draft']['memory_hits'] ?? 0),
                    'knowledge_hits' => (int) ($payload['ai_draft']['knowledge_hits'] ?? 0),
                    'context_log_id' => (int) ($payload['ai_draft']['context_log_id'] ?? 0),
                    'generated_response_id' => (int) ($payload['ai_draft']['generated_response_id'] ?? 0),
                    'brand_route' => is_array($payload['ai_draft']['brand_route'] ?? null) ? $payload['ai_draft']['brand_route'] : [],
                    'channel_mode' => (string) ($decision['channel_mode'] ?? ''),
                    'min_confidence' => (int) ($decision['min_confidence'] ?? 0),
                    'commercial_ok' => (bool) ($payload['commercial_automation']['ok'] ?? false),
                    'commercial_customer_id' => (int) ($payload['commercial_automation']['customer_id'] ?? 0),
                    'commercial_actions' => $payload['commercial_automation']['actions'] ?? [],
                    'commercial_reason' => (string) ($payload['commercial_automation']['reason'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }, $statement->fetchAll(PDO::FETCH_ASSOC))));
        } catch (\Throwable) {
            return [];
        }
    }

    private function decisionCounts(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT payload_json FROM omnichannel_events WHERE company_id = :company_id AND payload_json LIKE :needle ORDER BY id DESC LIMIT 200');
            $statement->execute(['company_id' => $companyId, 'needle' => '%"ai_decision"%']);
            $counts = ['approval_required' => 0, 'human_required' => 0, 'auto_resolved' => 0];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
                $mode = is_array($payload) ? (string) ($payload['ai_decision']['mode'] ?? '') : '';
                if (isset($counts[$mode])) {
                    $counts[$mode]++;
                }
            }
            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }

    private function generatedResponseCounts(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT status, COUNT(*) AS total FROM ai_generated_responses WHERE company_id = :company_id AND module = "Omnicanal" GROUP BY status');
            $statement->execute(['company_id' => $companyId]);
            $counts = ['draft' => 0, 'approved' => 0, 'edited' => 0, 'rejected' => 0, 'sent' => 0];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[(string) $row['status']] = (int) $row['total'];
            }
            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }

    private function brandRoutingSummary(int $companyId): array
    {
        $conversations = $this->conversations($companyId);
        $summary = [
            'total' => count($conversations),
            'routed' => 0,
            'manual' => 0,
            'suggested' => 0,
            'uncertain' => 0,
            'unrouted' => 0,
            'by_brand' => [],
        ];

        foreach ($conversations as $conversation) {
            $detection = $conversation['brand_detection'] ?? null;
            $status = is_array($detection) ? (string) ($detection['status'] ?? '') : '';
            $brand = is_array($detection) ? (string) ($detection['brand_name'] ?? '') : '';

            if ($brand === '') {
                $summary['unrouted']++;
                continue;
            }

            $summary['routed']++;
            $summary['by_brand'][$brand] = ($summary['by_brand'][$brand] ?? 0) + 1;

            if (in_array($status, ['confirmed', 'corrected'], true)) {
                $summary['manual']++;
            } elseif ($status === 'suggested') {
                $summary['suggested']++;
            } elseif ($status === 'uncertain') {
                $summary['uncertain']++;
            }
        }

        arsort($summary['by_brand']);
        return $summary;
    }

    private function channelAutonomySettings(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT channel, mode, min_confidence, require_approval_for_sensitive, status FROM ai_channel_settings WHERE company_id = :company_id ORDER BY FIELD(channel, "all", "email", "whatsapp", "instagram", "facebook"), channel');
            $statement->execute(['company_id' => $companyId]);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function recentContextLogs(int $companyId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT id, module, query_text, token_estimate, created_at FROM ai_context_logs WHERE company_id = :company_id AND module = "Omnicanal" ORDER BY id DESC LIMIT 5');
            $statement->execute(['company_id' => $companyId]);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function accountPayload(array $conversation): array
    {
        return [
            'id' => (int) ($conversation['account_id'] ?? 0),
            'display_name' => (string) (($conversation['account_name'] ?? '') ?: ($conversation['channel'] ?? 'Cuenta conectada')),
            'provider' => (string) ($conversation['provider'] ?? 'unknown'),
            'channel' => (string) ($conversation['channel'] ?? 'Omnicanal'),
            'brain_key' => (string) (($conversation['account_brain'] ?? '') ?: 'commercial'),
        ];
    }

    private function messagePayload(array $conversation, array $lastInboundRow, string $lastInbound): array
    {
        return [
            'customer_name' => (string) ($conversation['customer_name'] ?? ($lastInboundRow['sender_name'] ?? 'Cliente')),
            'customer_handle' => (string) ($conversation['customer_handle'] ?? ''),
            'subject' => (string) ($conversation['subject'] ?? 'Nueva conversacion'),
            'body' => $lastInbound,
            'priority' => (string) ($conversation['priority'] ?? 'medium'),
            'brand_route' => $conversation['brand_detection'] ?? null,
        ];
    }

    private function decisionPayload(array $conversation): array
    {
        return [
            'mode' => 'approval_required',
            'risk' => (string) ($conversation['priority'] ?? 'medium'),
            'confidence' => (int) ($conversation['ai_confidence'] ?? 72),
            'reason' => 'Sugerencia solicitada por humano desde Centro de Supervision.',
        ];
    }

    private function logTrainingTrace(int $companyId, int $conversationId, array $conversation, array $draftTrace, int $draftId): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO omnichannel_events (company_id, account_id, conversation_id, provider, channel, direction, event_type, status, payload_json)
                 VALUES (:company_id, :account_id, :conversation_id, :provider, :channel, "outbound", "ai_training_trace", "queued", :payload_json)'
            )->execute([
                'company_id' => $companyId,
                'account_id' => !empty($conversation['account_id']) ? (int) $conversation['account_id'] : null,
                'conversation_id' => $conversationId,
                'provider' => (string) ($conversation['provider'] ?? 'unknown'),
                'channel' => (string) ($conversation['channel'] ?? 'Omnicanal'),
                'payload_json' => json_encode([
                    'draft_message_id' => $draftId,
                    'ai_draft' => $draftTrace,
                    'generated_response_id' => $draftTrace['generated_response_id'] ?? null,
                    'context_log_id' => $draftTrace['context_log_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Trace is useful for training, but it should not block the inbox.
        }
    }

    private function latestDraftTrace(int $companyId, int $conversationId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT payload_json FROM omnichannel_events WHERE company_id = :company_id AND conversation_id = :conversation_id AND payload_json LIKE :needle ORDER BY id DESC LIMIT 1');
            $statement->execute([
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'needle' => '%"ai_draft"%',
            ]);
            $payload = json_decode((string) $statement->fetchColumn(), true);
            return is_array($payload) ? $payload : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function latestGeneratedResponseId(int $companyId, int $conversationId): int
    {
        $trace = $this->latestDraftTrace($companyId, $conversationId);
        if (!empty($trace['generated_response_id'])) {
            return (int) $trace['generated_response_id'];
        }
        if (!empty($trace['ai_draft']['generated_response_id'])) {
            return (int) $trace['ai_draft']['generated_response_id'];
        }

        return 0;
    }

    private function reviewExists(int $companyId, int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        try {
            $statement = Database::connection()->prepare('SELECT id FROM ai_response_reviews WHERE company_id = :company_id AND message_id = :message_id LIMIT 1');
            $statement->execute(['company_id' => $companyId, 'message_id' => $messageId]);
            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function draftReply(array $conversation, string $lastMessage): string
    {
        $name = $conversation['customer_name'] ?? 'cliente';
        return "Hola {$name}, gracias por escribirnos. Vi tu consulta: \"{$lastMessage}\". Te puedo ayudar con disponibilidad, precio y despacho. Si quieres, te preparo una cotizacion ahora mismo.";
    }

    private function fallbackConversations(): array
    {
        return [];
    }

    private function fallbackMessages(): array
    {
        return [
            ['direction' => 'inbound', 'sender_name' => 'Valentina Rojas', 'body' => 'Hola, quiero saber precios y despacho.', 'status' => 'sent', 'created_at' => date('Y-m-d H:i:s')],
        ];
    }
}
