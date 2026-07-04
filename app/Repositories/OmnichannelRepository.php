<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\ConnectionTester;
use App\Services\SecretVault;
use PDO;

final class OmnichannelRepository
{
    public function accounts(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE company_id = :company_id ORDER BY channel, display_name, provider');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accountMetrics(int $companyId): array
    {
        $accounts = $this->accounts($companyId);
        $count = fn (callable $filter): int => count(array_filter($accounts, $filter));

        return [
            ['label' => 'Cuentas', 'value' => (string) count($accounts), 'hint' => 'Conectadas a la empresa'],
            ['label' => 'Entrada activa', 'value' => (string) $count(fn (array $account): bool => !empty($account['inbound_enabled'])), 'hint' => 'Reciben mensajes'],
            ['label' => 'Salida real', 'value' => (string) $count(fn (array $account): bool => !empty($account['outbound_enabled'])), 'hint' => 'Envio directo habilitado'],
            ['label' => 'Con aprobacion', 'value' => (string) $count(fn (array $account): bool => !empty($account['requires_approval'])), 'hint' => 'Control humano activo'],
        ];
    }

    public function companyUsers(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, name, email FROM users WHERE company_id = :company_id AND status = "active" ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveAccount(int $companyId, array $input): void
    {
        if (!$this->databaseReady()) {
            return;
        }

        $provider = $this->provider((string) ($input['provider'] ?? ''));
        $channel = $this->channel((string) ($input['channel'] ?? ''));
        $displayName = trim((string) ($input['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $channel . ' ' . ucfirst(str_replace('_', ' ', $provider));
        }

        $statement = Database::connection()->prepare('INSERT INTO omnichannel_accounts (company_id, provider, channel, display_name, external_account_id, webhook_token, status, inbound_enabled, outbound_enabled, requires_approval, brain_key, assigned_user_id, settings_json)
            VALUES (:company_id, :provider, :channel, :display_name, :external_account_id, :webhook_token, :status, :inbound_enabled, :outbound_enabled, :requires_approval, :brain_key, :assigned_user_id, :settings_json)');
        $statement->execute([
            'company_id' => $companyId,
            'provider' => $provider,
            'channel' => $channel,
            'display_name' => $displayName,
            'external_account_id' => trim((string) ($input['external_account_id'] ?? '')),
            'webhook_token' => $this->webhookToken($companyId, $provider),
            'status' => $this->status((string) ($input['status'] ?? 'sandbox')),
            'inbound_enabled' => !empty($input['inbound_enabled']) ? 1 : 0,
            'outbound_enabled' => !empty($input['outbound_enabled']) ? 1 : 0,
            'requires_approval' => !empty($input['requires_approval']) ? 1 : 0,
            'brain_key' => $this->brainKey((string) ($input['brain_key'] ?? 'commercial')),
            'assigned_user_id' => !empty($input['assigned_user_id']) ? (int) $input['assigned_user_id'] : null,
            'settings_json' => json_encode(['send_mode' => !empty($input['outbound_enabled']) ? 'outbound_enabled' : 'approval_required'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function updateAccount(int $companyId, int $accountId, array $input): void
    {
        if (!$this->databaseReady() || $accountId <= 0) {
            return;
        }

        $statement = Database::connection()->prepare('UPDATE omnichannel_accounts
            SET display_name = :display_name,
                external_account_id = :external_account_id,
                status = :status,
                inbound_enabled = :inbound_enabled,
                outbound_enabled = :outbound_enabled,
                requires_approval = :requires_approval,
                brain_key = :brain_key,
                assigned_user_id = :assigned_user_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE company_id = :company_id AND id = :id');
        $statement->execute([
            'display_name' => trim((string) ($input['display_name'] ?? 'Cuenta conectada')),
            'external_account_id' => trim((string) ($input['external_account_id'] ?? '')),
            'status' => $this->status((string) ($input['status'] ?? 'sandbox')),
            'inbound_enabled' => !empty($input['inbound_enabled']) ? 1 : 0,
            'outbound_enabled' => !empty($input['outbound_enabled']) ? 1 : 0,
            'requires_approval' => !empty($input['requires_approval']) ? 1 : 0,
            'brain_key' => $this->brainKey((string) ($input['brain_key'] ?? 'commercial')),
            'assigned_user_id' => !empty($input['assigned_user_id']) ? (int) $input['assigned_user_id'] : null,
            'company_id' => $companyId,
            'id' => $accountId,
        ]);
    }

    public function saveCredentials(int $companyId, int $accountId, array $input): void
    {
        $account = $this->account($companyId, $accountId);
        if (!$account) {
            return;
        }

        $credentials = $this->credentialsFromInput($account, $input);
        $vault = new SecretVault();
        $encrypted = $vault->encrypt(json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $settings = $this->settings($account);
        $settings['credential_type'] = $credentials['type'];
        $settings['configured_at'] = date('c');

        Database::connection()->prepare('UPDATE omnichannel_accounts
            SET encrypted_credentials = :encrypted_credentials,
                credentials_last4 = :credentials_last4,
                credentials_updated_at = CURRENT_TIMESTAMP,
                settings_json = :settings_json,
                status = "sandbox",
                updated_at = CURRENT_TIMESTAMP
            WHERE company_id = :company_id AND id = :id')->execute([
            'encrypted_credentials' => $encrypted,
            'credentials_last4' => $this->credentialLabel($credentials),
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'company_id' => $companyId,
            'id' => $accountId,
        ]);
    }

    public function testCredentials(int $companyId, int $accountId): array
    {
        $account = $this->account($companyId, $accountId);
        if (!$account) {
            return ['ok' => false, 'message' => 'Cuenta no encontrada.'];
        }

        $credentials = $this->decryptCredentials($account);
        if (!$credentials) {
            return ['ok' => false, 'message' => 'Primero guarda las credenciales de esta cuenta.'];
        }

        $result = (new ConnectionTester())->test($credentials);
        Database::connection()->prepare('UPDATE omnichannel_accounts SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')
            ->execute([
                'status' => $result['ok'] ? 'connected' : 'error',
                'company_id' => $companyId,
                'id' => $accountId,
            ]);

        return $result;
    }

    public function receiveWebhook(string $token, array $payload): array
    {
        if (!$this->databaseReady()) {
            return ['ok' => false, 'message' => 'Base omnicanal no disponible.'];
        }

        $account = $this->accountByToken($token);
        if (!$account || !$account['inbound_enabled']) {
            return ['ok' => false, 'message' => 'Token o canal invalido.'];
        }

        $message = $this->normalizeInbound($account, $payload);
        if (trim($message['body']) === '') {
            $this->logEvent((int) $account['company_id'], (int) $account['id'], null, $account['provider'], $account['channel'], 'inbound', 'ignored', $payload, 'Mensaje sin cuerpo util.');
            return ['ok' => false, 'message' => 'Mensaje ignorado por falta de contenido.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        $conversationId = $this->upsertConversation((int) $account['company_id'], $account, $message);
        $this->insertInboundMessage((int) $account['company_id'], $conversationId, $account, $message);
        $this->logEvent((int) $account['company_id'], (int) $account['id'], $conversationId, $account['provider'], $account['channel'], 'inbound', 'received', $payload);

        $pdo->commit();

        return ['ok' => true, 'conversation_id' => $conversationId, 'message' => 'Mensaje recibido e ingresado a la bandeja.'];
    }

    public function markOutboundAttempt(int $companyId, int $conversationId, array $payload): string
    {
        if (!$this->databaseReady()) {
            return 'Respuesta enviada en modo simulado.';
        }

        $conversation = $this->conversation($companyId, $conversationId);
        if (!$conversation) {
            return 'No se encontro la conversacion para enviar.';
        }

        $account = $this->accountForConversation($companyId, (int) ($conversation['account_id'] ?? 0), $conversation['provider'] ?? '', $conversation['channel']);
        $status = 'queued';
        $message = 'Envio registrado. ';

        if (!$account) {
            $status = 'failed';
            $message .= 'No hay cuenta omnicanal configurada para este canal.';
        } elseif (!$account['outbound_enabled']) {
            $status = 'queued';
            $message .= 'El conector esta en sandbox; queda listo para proveedor real con aprobacion humana.';
        } else {
            $message .= 'Conector outbound activo; envio real pendiente de adaptador especifico.';
        }

        $this->logEvent($companyId, (int) ($account['id'] ?? 0) ?: null, $conversationId, $conversation['provider'] ?: (string) ($account['provider'] ?? 'unknown'), $conversation['channel'], 'outbound', $status, $payload);

        return $message;
    }

    private function databaseReady(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM omnichannel_accounts LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function account(int $companyId, int $accountId): ?array
    {
        if (!$this->databaseReady() || $accountId <= 0) {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function decryptCredentials(array $account): ?array
    {
        $json = (new SecretVault())->decrypt($account['encrypted_credentials'] ?? null);
        if (!$json) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function credentialsFromInput(array $account, array $input): array
    {
        if (($account['provider'] ?? '') === 'obraok') {
            return [
                'type' => 'obraok_api',
                'api_base_url' => trim((string) ($input['api_base_url'] ?? '')),
                'api_token' => trim((string) ($input['api_token'] ?? '')),
                'workspace_id' => trim((string) ($input['workspace_id'] ?? '')),
            ];
        }

        return [
            'type' => 'email_imap_smtp',
            'email_address' => trim((string) ($input['email_address'] ?? $account['external_account_id'] ?? '')),
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => (string) ($input['password'] ?? ''),
            'imap_host' => trim((string) ($input['imap_host'] ?? '')),
            'imap_port' => (int) ($input['imap_port'] ?? 993),
            'imap_encryption' => $this->encryption((string) ($input['imap_encryption'] ?? 'ssl')),
            'smtp_host' => trim((string) ($input['smtp_host'] ?? '')),
            'smtp_port' => (int) ($input['smtp_port'] ?? 587),
            'smtp_encryption' => $this->encryption((string) ($input['smtp_encryption'] ?? 'tls')),
        ];
    }

    private function credentialLabel(array $credentials): string
    {
        if (($credentials['type'] ?? '') === 'obraok_api') {
            $token = (string) ($credentials['api_token'] ?? '');
            return $token !== '' ? 'token ****' . substr($token, -4) : 'api';
        }

        return (string) ($credentials['email_address'] ?? $credentials['username'] ?? 'email');
    }

    private function settings(array $account): array
    {
        $decoded = json_decode((string) ($account['settings_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function accountByToken(string $token): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE webhook_token = :token AND status != "disabled" LIMIT 1');
        $statement->execute(['token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function accountForConversation(int $companyId, int $accountId, string $provider, string $channel): ?array
    {
        if ($accountId > 0) {
            $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE company_id = :company_id AND id = :id LIMIT 1');
            $statement->execute(['company_id' => $companyId, 'id' => $accountId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE company_id = :company_id AND channel = :channel AND (:provider = "" OR provider = :provider) ORDER BY FIELD(status, "connected", "sandbox", "simulated", "error", "disabled") LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'channel' => $channel, 'provider' => $provider]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function conversation(int $companyId, int $conversationId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM inbox_conversations WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $conversationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function normalizeInbound(array $account, array $payload): array
    {
        $provider = (string) $account['provider'];
        $channel = (string) $account['channel'];

        $body = $payload['body'] ?? $payload['text'] ?? $payload['message'] ?? $payload['Body'] ?? '';
        $customerName = $payload['customer_name'] ?? $payload['name'] ?? $payload['profile_name'] ?? $payload['FromName'] ?? 'Cliente ' . $channel;
        $handle = $payload['customer_handle'] ?? $payload['from'] ?? $payload['phone'] ?? $payload['email'] ?? $payload['From'] ?? null;
        $externalThread = $payload['conversation_id'] ?? $payload['thread_id'] ?? $payload['from'] ?? $payload['phone'] ?? $payload['email'] ?? $payload['From'] ?? null;
        $externalMessage = $payload['message_id'] ?? $payload['id'] ?? $payload['MessageSid'] ?? null;
        $subject = $payload['subject'] ?? $payload['Subject'] ?? $this->subjectFromBody((string) $body);

        return [
            'provider' => $provider,
            'channel' => $channel,
            'body' => trim((string) $body),
            'customer_name' => trim((string) $customerName) ?: 'Cliente ' . $channel,
            'customer_handle' => $handle ? trim((string) $handle) : null,
            'external_thread_id' => trim((string) ($externalThread ?: $handle ?: uniqid($provider . '_', true))),
            'external_message_id' => $externalMessage ? trim((string) $externalMessage) : null,
            'subject' => trim((string) $subject) ?: 'Nueva conversacion ' . $channel,
            'priority' => $this->priority((string) $body),
        ];
    }

    private function upsertConversation(int $companyId, array $account, array $message): int
    {
        $statement = Database::connection()->prepare('SELECT id FROM inbox_conversations WHERE company_id = :company_id AND account_id = :account_id AND external_id = :external_id LIMIT 1');
        $statement->execute([
            'company_id' => $companyId,
            'account_id' => $account['id'],
            'external_id' => $message['external_thread_id'],
        ]);
        $id = (int) $statement->fetchColumn();
        if ($id > 0) {
            Database::connection()->prepare('UPDATE inbox_conversations SET status = IF(status = "closed", "open", status), priority = :priority, last_inbound_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'priority' => $message['priority'],
                'company_id' => $companyId,
                'id' => $id,
            ]);
            return $id;
        }

        $insert = Database::connection()->prepare('INSERT INTO inbox_conversations (company_id, account_id, channel, provider, external_id, customer_name, customer_handle, subject, status, priority, source_brain, last_inbound_at) VALUES (:company_id, :account_id, :channel, :provider, :external_id, :customer_name, :customer_handle, :subject, "new", :priority, :source_brain, CURRENT_TIMESTAMP)');
        $insert->execute([
            'company_id' => $companyId,
            'account_id' => $account['id'],
            'channel' => $account['channel'],
            'provider' => $account['provider'],
            'external_id' => $message['external_thread_id'],
            'customer_name' => $message['customer_name'],
            'customer_handle' => $message['customer_handle'],
            'subject' => $message['subject'],
            'priority' => $message['priority'],
            'source_brain' => $account['brain_key'] ?? 'commercial',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function insertInboundMessage(int $companyId, int $conversationId, array $account, array $message): void
    {
        if ($message['external_message_id']) {
            $existing = Database::connection()->prepare('SELECT id FROM inbox_messages WHERE company_id = :company_id AND external_message_id = :external_message_id LIMIT 1');
            $existing->execute(['company_id' => $companyId, 'external_message_id' => $message['external_message_id']]);
            if ($existing->fetchColumn()) {
                return;
            }
        }

        $insert = Database::connection()->prepare('INSERT INTO inbox_messages (company_id, account_id, conversation_id, provider, external_message_id, direction, sender_name, body, ai_generated, status) VALUES (:company_id, :account_id, :conversation_id, :provider, :external_message_id, "inbound", :sender_name, :body, 0, "received")');
        $insert->execute([
            'company_id' => $companyId,
            'account_id' => $account['id'],
            'conversation_id' => $conversationId,
            'provider' => $account['provider'],
            'external_message_id' => $message['external_message_id'],
            'sender_name' => $message['customer_name'],
            'body' => $message['body'],
        ]);
    }

    private function logEvent(int $companyId, ?int $accountId, ?int $conversationId, string $provider, string $channel, string $direction, string $status, array $payload, ?string $error = null): void
    {
        $statement = Database::connection()->prepare('INSERT INTO omnichannel_events (company_id, account_id, conversation_id, provider, channel, direction, event_type, status, payload_json, error_message) VALUES (:company_id, :account_id, :conversation_id, :provider, :channel, :direction, :event_type, :status, :payload_json, :error_message)');
        $statement->execute([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'conversation_id' => $conversationId,
            'provider' => $provider,
            'channel' => $channel,
            'direction' => $direction,
            'event_type' => $direction === 'inbound' ? 'message_received' : 'message_send_attempt',
            'status' => $status,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'error_message' => $error,
        ]);
    }

    private function subjectFromBody(string $body): string
    {
        $body = trim($body);
        return strlen($body) > 70 ? substr($body, 0, 67) . '...' : ($body ?: 'Nuevo mensaje');
    }

    private function priority(string $body): string
    {
        $text = strtolower($body);
        if (str_contains($text, 'urgente') || str_contains($text, 'reclamo') || str_contains($text, 'hoy')) {
            return 'high';
        }

        if (str_contains($text, 'cotizacion') || str_contains($text, 'comprar') || str_contains($text, 'precio')) {
            return 'high';
        }

        return 'medium';
    }

    private function provider(string $provider): string
    {
        return in_array($provider, ['whatsapp_cloud', 'gmail', 'outlook', 'imap', 'meta', 'telegram', 'obraok'], true) ? $provider : 'imap';
    }

    private function channel(string $channel): string
    {
        return in_array($channel, ['WhatsApp', 'Email', 'Instagram', 'Messenger', 'Telegram', 'Operaciones'], true) ? $channel : 'Email';
    }

    private function status(string $status): string
    {
        return in_array($status, ['simulated', 'sandbox', 'connected', 'disabled', 'error'], true) ? $status : 'sandbox';
    }

    private function brainKey(string $brain): string
    {
        return in_array($brain, ['commercial', 'administrative', 'analytical', 'operational', 'executive'], true) ? $brain : 'commercial';
    }

    private function webhookToken(int $companyId, string $provider): string
    {
        return 'acct-' . $companyId . '-' . $provider . '-' . bin2hex(random_bytes(8));
    }

    private function encryption(string $value): string
    {
        return in_array($value, ['ssl', 'tls', 'none'], true) ? $value : 'tls';
    }
}
