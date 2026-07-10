<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\AutonomyRepository;
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

        return array_map(fn (array $row): array => $this->decorateConversation($row), $statement->fetchAll(PDO::FETCH_ASSOC));
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

        return $conversation;
    }

    public function updateStatus(int $companyId, int $conversationId, string $status): void
    {
        if (!$this->databaseReady() || $conversationId <= 0 || !in_array($status, ['new', 'open', 'pending_approval', 'answered', 'closed'], true)) {
            return;
        }

        Database::connection()->prepare('UPDATE inbox_conversations SET status = :status WHERE company_id = :company_id AND id = :id')->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);
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

    public function suggestReply(int $companyId, int $conversationId, int $userId): void
    {
        $conversation = $this->selectedConversation($companyId, $conversationId);
        if (!$conversation) {
            return;
        }

        $lastInbound = $this->lastInboundMessage($conversation);
        $reply = $this->draftReply($conversation, $lastInbound);

        if (!$this->databaseReady()) {
            $_SESSION['inbox_suggestions'][] = $reply;
            return;
        }

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

        Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval" WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);

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
            Database::connection()->prepare('UPDATE inbox_messages SET body = :body, sender_name = :sender_name, status = "draft" WHERE company_id = :company_id AND id = :id')->execute([
                'body' => $body,
                'sender_name' => !empty($draft['ai_generated']) ? 'AsisFly editado por ' . $userName : $userName,
                'company_id' => $companyId,
                'id' => (int) $draft['id'],
            ]);
            if (!empty($draft['ai_generated']) && trim((string) $draft['body']) !== $body) {
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
