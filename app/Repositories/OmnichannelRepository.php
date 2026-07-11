<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\ActionRepository;
use App\Repositories\AutonomyRepository;
use App\Services\ConnectionTester;
use App\Services\AIResponseReviewService;
use App\Services\OmnichannelCommercialAutomation;
use App\Services\OmnichannelAiResponder;
use App\Services\OmnichannelAutonomyPolicy;
use App\Services\BrandRoutingDetector;
use App\Services\SecretVault;
use App\Services\SmtpMailer;
use App\Services\WhatsAppCloudClient;
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
        return array_map(fn (array $account): array => $this->withCredentialSummary($account), $statement->fetchAll(PDO::FETCH_ASSOC));
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
        $externalAccountId = trim((string) ($input['external_account_id'] ?? ''));
        $brainKey = !empty($input['brain_key'])
            ? $this->brainKey((string) $input['brain_key'])
            : $this->autoBrainKey($provider, $channel, $displayName, $externalAccountId);

        $statement = Database::connection()->prepare('INSERT INTO omnichannel_accounts (company_id, provider, channel, display_name, external_account_id, webhook_token, status, inbound_enabled, outbound_enabled, requires_approval, brain_key, assigned_user_id, settings_json)
            VALUES (:company_id, :provider, :channel, :display_name, :external_account_id, :webhook_token, :status, :inbound_enabled, :outbound_enabled, :requires_approval, :brain_key, :assigned_user_id, :settings_json)');
        $statement->execute([
            'company_id' => $companyId,
            'provider' => $provider,
            'channel' => $channel,
            'display_name' => $displayName,
            'external_account_id' => $externalAccountId,
            'webhook_token' => $this->webhookToken($companyId, $provider),
            'status' => $this->status((string) ($input['status'] ?? 'sandbox')),
            'inbound_enabled' => !empty($input['inbound_enabled']) ? 1 : 0,
            'outbound_enabled' => !empty($input['outbound_enabled']) ? 1 : 0,
            'requires_approval' => !empty($input['requires_approval']) ? 1 : 0,
            'brain_key' => $brainKey,
            'assigned_user_id' => !empty($input['assigned_user_id']) ? (int) $input['assigned_user_id'] : null,
            'settings_json' => json_encode(['send_mode' => !empty($input['outbound_enabled']) ? 'outbound_enabled' : 'approval_required'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function saveWhatsAppEmbeddedAccount(int $companyId, array $input): int
    {
        if (!$this->databaseReady()) {
            return 0;
        }

        $phoneNumberId = trim((string) ($input['phone_number_id'] ?? ''));
        $displayPhoneNumber = preg_replace('/\D+/', '', (string) ($input['display_phone_number'] ?? '')) ?: '';
        $wabaId = trim((string) ($input['waba_id'] ?? ''));
        $businessName = trim((string) ($input['business_name'] ?? ''));
        $externalAccountId = $phoneNumberId !== '' ? $phoneNumberId : $displayPhoneNumber;
        if ($externalAccountId === '') {
            return 0;
        }

        $existing = $this->accountByProviderExternal('whatsapp_cloud', $externalAccountId);
        if (!$existing && $displayPhoneNumber !== '') {
            $existing = $this->accountByProviderExternal('whatsapp_cloud', $displayPhoneNumber);
        }

        $settings = [
            'send_mode' => 'approval_required',
            'embedded_signup' => true,
            'whatsapp_phone_number_id' => $phoneNumberId,
            'whatsapp_display_phone_number' => $displayPhoneNumber,
            'whatsapp_business_account_id' => $wabaId,
            'business_name' => $businessName,
            'connected_at' => date('c'),
            'raw_signup' => $input['raw_signup'] ?? null,
        ];

        if ($existing) {
            $mergedSettings = array_merge($this->settings($existing), $settings);
            Database::connection()->prepare('UPDATE omnichannel_accounts
                SET display_name = :display_name,
                    external_account_id = :external_account_id,
                    channel = "WhatsApp",
                    provider = "whatsapp_cloud",
                    status = "sandbox",
                    inbound_enabled = 1,
                    outbound_enabled = 1,
                    requires_approval = 1,
                    brain_key = "commercial",
                    settings_json = :settings_json,
                    updated_at = CURRENT_TIMESTAMP
                WHERE company_id = :company_id AND id = :id')->execute([
                'display_name' => $businessName !== '' ? 'WhatsApp ' . $businessName : 'WhatsApp Empresa',
                'external_account_id' => $externalAccountId,
                'settings_json' => json_encode($mergedSettings, JSON_UNESCAPED_UNICODE),
                'company_id' => $companyId,
                'id' => (int) $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $statement = Database::connection()->prepare('INSERT INTO omnichannel_accounts (company_id, provider, channel, display_name, external_account_id, webhook_token, status, inbound_enabled, outbound_enabled, requires_approval, brain_key, assigned_user_id, settings_json)
            VALUES (:company_id, "whatsapp_cloud", "WhatsApp", :display_name, :external_account_id, :webhook_token, "sandbox", 1, 1, 1, "commercial", NULL, :settings_json)');
        $statement->execute([
            'company_id' => $companyId,
            'display_name' => $businessName !== '' ? 'WhatsApp ' . $businessName : 'WhatsApp Empresa',
            'external_account_id' => $externalAccountId,
            'webhook_token' => $this->webhookToken($companyId, 'whatsapp_cloud'),
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) Database::connection()->lastInsertId();
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

        if (($credentials['type'] ?? '') === 'whatsapp_cloud') {
            $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));
            if ($phoneNumberId !== '') {
                Database::connection()->prepare('UPDATE omnichannel_accounts
                    SET external_account_id = :external_account_id,
                        channel = "WhatsApp",
                        provider = "whatsapp_cloud",
                        updated_at = CURRENT_TIMESTAMP
                    WHERE company_id = :company_id AND id = :id')->execute([
                    'external_account_id' => $phoneNumberId,
                    'company_id' => $companyId,
                    'id' => $accountId,
                ]);
            }
        }
    }

    public function testCredentials(int $companyId, int $accountId): array
    {
        $account = $this->account($companyId, $accountId);
        if (!$account) {
            return ['ok' => false, 'message' => 'Cuenta no encontrada.'];
        }

        $credentials = ($account['provider'] ?? '') === 'whatsapp_cloud'
            ? $this->whatsAppCloudCredentials($account)
            : $this->decryptCredentials($account);
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

    public function syncEmailAccount(int $companyId, int $accountId, int $limit = 15): array
    {
        $mailbox = null;

        try {
            $account = $this->account($companyId, $accountId);
            if (!$account || !in_array((string) ($account['provider'] ?? ''), ['imap', 'gmail', 'outlook'], true)) {
                return ['ok' => false, 'message' => 'Selecciona una cuenta de correo valida.', 'imported' => 0];
            }

            if (empty($account['inbound_enabled'])) {
                return ['ok' => false, 'message' => 'La entrada de esta cuenta esta pausada.', 'imported' => 0];
            }

            $credentials = $this->decryptCredentials($account);
            if (!$credentials) {
                return ['ok' => false, 'message' => 'Primero guarda las credenciales IMAP de esta cuenta.', 'imported' => 0];
            }

            if (!function_exists('imap_open')) {
                return ['ok' => false, 'message' => 'La extension IMAP de PHP no esta habilitada en este servidor.', 'imported' => 0];
            }

            $mailboxPath = $this->mailboxPath($credentials);
            $username = (string) ($credentials['username'] ?? $credentials['email_address'] ?? '');
            $password = (string) ($credentials['password'] ?? '');
            if ($mailboxPath === '' || $username === '' || $password === '') {
                return ['ok' => false, 'message' => 'Credenciales IMAP incompletas.', 'imported' => 0];
            }

            $mailbox = @imap_open($mailboxPath, $username, $password, OP_READONLY, 1);
            if (!$mailbox) {
                return ['ok' => false, 'message' => 'No se pudo abrir IMAP: ' . (imap_last_error() ?: 'sin detalle'), 'imported' => 0];
            }

            $uids = imap_search($mailbox, 'UNSEEN', SE_UID) ?: imap_search($mailbox, 'ALL', SE_UID) ?: [];
            rsort($uids, SORT_NUMERIC);
            $uids = array_slice($uids, 0, max(1, min($limit, 50)));
            $imported = 0;
            $skipped = 0;
            $lastError = null;

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                try {
                    $overviewList = imap_fetch_overview($mailbox, (string) $uid, FT_UID);
                    $overview = is_array($overviewList) ? ($overviewList[0] ?? null) : null;
                    if (!$overview) {
                        $skipped++;
                        continue;
                    }

                    $messageId = trim((string) ($overview->message_id ?? '')) ?: 'imap-' . $account['id'] . '-' . $uid;
                    $messageId = substr($messageId, 0, 170);
                    if ($this->messageExists($companyId, $messageId)) {
                        $skipped++;
                        continue;
                    }

                    $subject = $this->decodeMime((string) ($overview->subject ?? 'Correo sin asunto'));
                    $from = $this->emailSender((string) ($overview->from ?? 'Cliente Email'));
                    $body = $this->emailBody($mailbox, (int) $uid);
                    if (trim($body) === '') {
                        $body = '(Correo sin cuerpo legible. Revisa el mensaje original en tu bandeja.)';
                    }

                    $result = $this->receiveWebhook((string) $account['webhook_token'], [
                        'body' => $body,
                        'customer_name' => $from['name'],
                        'customer_handle' => $from['email'],
                        'email' => $from['email'],
                        'subject' => $subject,
                        'conversation_id' => $this->emailThreadId((int) $account['id'], $from['email'], $subject),
                        'message_id' => $messageId,
                    ]);

                    $imported += !empty($result['ok']) ? 1 : 0;
                    if (empty($result['ok'])) {
                        $skipped++;
                        $lastError = (string) ($result['message'] ?? 'No se pudo importar un correo.');
                    }
                } catch (\Throwable $exception) {
                    $skipped++;
                    $lastError = $exception->getMessage();
                }
            }

            return [
                'ok' => true,
                'message' => "Sincronizacion completada. Correos nuevos: {$imported}. Omitidos: {$skipped}." . ($lastError ? ' Ultimo aviso: ' . $lastError : ''),
                'imported' => $imported,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'message' => 'No se pudo sincronizar IMAP: ' . $exception->getMessage(), 'imported' => 0];
        } finally {
            if (is_resource($mailbox) || (class_exists('\\IMAP\\Connection') && $mailbox instanceof \IMAP\Connection)) {
                imap_close($mailbox);
            }
        }
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
        $inserted = $this->insertInboundMessage((int) $account['company_id'], $conversationId, $account, $message);
        $this->logEvent((int) $account['company_id'], (int) $account['id'], $conversationId, $account['provider'], $account['channel'], 'inbound', 'received', $payload);

        $pdo->commit();
        if (!$inserted) {
            return ['ok' => true, 'conversation_id' => $conversationId, 'message' => 'Mensaje duplicado omitido.'];
        }

        $this->detectBrandRoutePassive((int) $account['company_id'], $conversationId, $message);
        $decision = $this->superviseInboundConversation((int) $account['company_id'], $conversationId, $account, $message);

        return [
            'ok' => true,
            'conversation_id' => $conversationId,
            'message' => 'Mensaje recibido. ' . $decision['message'],
        ];
    }

    public function verifyWebhookToken(string $token): bool
    {
        return $token !== '' && $this->databaseReady() && $this->accountByToken($token) !== null;
    }

    public function receiveWhatsAppCloud(array $payload): array
    {
        if (!$this->databaseReady()) {
            return ['ok' => false, 'message' => 'Base omnicanal no disponible.'];
        }

        $items = $this->whatsAppCloudInboundItems($payload);
        if (empty($items)) {
            return ['ok' => true, 'message' => 'Webhook WhatsApp recibido sin mensajes nuevos.', 'processed' => 0, 'skipped' => 0];
        }

        $processed = 0;
        $skipped = 0;
        $lastError = null;

        foreach ($items as $item) {
            $phoneNumberId = (string) ($item['phone_number_id'] ?? '');
            $displayPhoneNumber = (string) ($item['display_phone_number'] ?? '');
            $account = $this->accountByProviderExternal('whatsapp_cloud', $phoneNumberId)
                ?: $this->accountByProviderExternal('whatsapp_cloud', $displayPhoneNumber);
            if (!$account) {
                $skipped++;
                $lastError = 'No existe una cuenta WhatsApp Cloud activa para Phone Number ID ' . $phoneNumberId . ' o numero ' . $displayPhoneNumber . '.';
                $this->logWebhookWarning('whatsapp_cloud_account_missing', ['phone_number_id' => $phoneNumberId, 'display_phone_number' => $displayPhoneNumber, 'payload' => $item]);
                continue;
            }

            $this->rememberWhatsAppPhoneNumberId($account, $phoneNumberId, $displayPhoneNumber);

            $result = $this->receiveWebhook((string) $account['webhook_token'], (array) ($item['payload'] ?? []));
            if (!empty($result['ok'])) {
                $processed++;
            } else {
                $skipped++;
                $lastError = (string) ($result['message'] ?? 'No se pudo procesar un mensaje WhatsApp.');
            }
        }

        return [
            'ok' => true,
            'message' => 'Webhook WhatsApp procesado. Mensajes: ' . $processed . '. Omitidos: ' . $skipped . ($lastError ? '. Ultimo aviso: ' . $lastError : ''),
            'processed' => $processed,
            'skipped' => $skipped,
        ];
    }

    public function markOutboundAttempt(int $companyId, int $conversationId, array $payload): string
    {
        return $this->sendOutboundAttempt($companyId, $conversationId, $payload)['message'];
    }

    public function sendOutboundAttempt(int $companyId, int $conversationId, array $payload): array
    {
        if (!$this->databaseReady()) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'Base omnicanal no disponible.'];
        }

        $conversation = $this->conversation($companyId, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'No se encontro la conversacion para enviar.'];
        }

        $account = $this->accountForConversation($companyId, (int) ($conversation['account_id'] ?? 0), $conversation['provider'] ?? '', $conversation['channel']);
        $status = 'queued';
        $message = 'Envio registrado. ';
        $ok = false;

        if (!$account) {
            $status = 'failed';
            $message .= 'No hay cuenta omnicanal configurada para este canal.';
        } elseif (!$account['outbound_enabled']) {
            $status = 'queued';
            $message .= 'La salida real de esta cuenta no esta habilitada. Activa "Enviar" en Cuentas conectadas.';
        } elseif (($conversation['channel'] ?? '') === 'Email') {
            $send = $this->sendEmailOutbound($account, $conversation, $payload);
            $ok = $send['ok'];
            $status = $send['ok'] ? 'sent' : 'failed';
            $message = $send['message'];
        } elseif (($account['provider'] ?? '') === 'whatsapp_cloud' && ($conversation['channel'] ?? '') === 'WhatsApp') {
            $send = $this->sendWhatsAppCloudOutbound($account, $conversation, $payload);
            $ok = $send['ok'];
            $status = $send['ok'] ? 'sent' : 'failed';
            $message = $send['message'];
        } else {
            $message .= 'Conector outbound activo; envio real pendiente de adaptador especifico para ' . $conversation['channel'] . '.';
        }

        $this->logEvent($companyId, (int) ($account['id'] ?? 0) ?: null, $conversationId, $conversation['provider'] ?: (string) ($account['provider'] ?? 'unknown'), $conversation['channel'], 'outbound', $status, $payload);

        return ['ok' => $ok, 'status' => $status, 'message' => $message];
    }

    private function sendEmailOutbound(array $account, array $conversation, array $payload): array
    {
        $credentials = $this->decryptCredentials($account);
        if (!$credentials || ($credentials['type'] ?? '') !== 'email_imap_smtp') {
            return ['ok' => false, 'message' => 'No hay credenciales SMTP guardadas para esta cuenta.'];
        }

        $to = trim((string) ($conversation['customer_handle'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'El cliente no tiene un correo valido como destinatario.'];
        }

        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '') {
            return ['ok' => false, 'message' => 'El borrador esta vacio.'];
        }

        try {
            (new SmtpMailer())->send(
                $credentials,
                $to,
                'Re: ' . (string) ($conversation['subject'] ?? 'Respuesta'),
                $body
            );

            return ['ok' => true, 'message' => 'Correo enviado por SMTP a ' . $to . '.'];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'message' => 'No se pudo enviar por SMTP: ' . $exception->getMessage()];
        }
    }

    private function sendWhatsAppCloudOutbound(array $account, array $conversation, array $payload): array
    {
        $credentials = $this->whatsAppCloudCredentials($account);
        if (!$credentials || ($credentials['type'] ?? '') !== 'whatsapp_cloud') {
            return ['ok' => false, 'message' => 'No hay credenciales WhatsApp Cloud guardadas para esta cuenta.'];
        }

        $to = trim((string) ($conversation['customer_handle'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));

        $result = (new WhatsAppCloudClient())->sendText($credentials, $to, $body);
        if (!empty($result['ok'])) {
            return ['ok' => true, 'message' => (string) $result['message']];
        }

        return ['ok' => false, 'message' => (string) ($result['message'] ?? 'No se pudo enviar WhatsApp Cloud.')];
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

    private function detectBrandRoutePassive(int $companyId, int $conversationId, array $message): void
    {
        try {
            $detector = new BrandRoutingDetector();
            $detector->record($companyId, $conversationId, null, $detector->detect($companyId, $message));
        } catch (\Throwable) {
            // Brand routing is observational in this phase and must not affect Omnicanal.
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

    private function messageWithBrandRoute(int $companyId, int $conversationId, array $message): array
    {
        $brandRoute = (new BrandRoutingDetector())->latestForConversation($companyId, $conversationId);
        if ($brandRoute) {
            $message['brand_route'] = $brandRoute;
        }

        return $message;
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

    private function withCredentialSummary(array $account): array
    {
        $credentials = $this->decryptCredentials($account);
        if (($account['provider'] ?? '') === 'whatsapp_cloud') {
            $credentials = $this->whatsAppCloudCredentials($account);
        }
        $summary = [
            'is_configured' => !empty($account['credentials_updated_at']),
            'type' => null,
            'label' => !empty($account['credentials_updated_at']) ? (string) ($account['credentials_last4'] ?? 'Configuradas') : 'Sin credenciales',
            'updated_at' => $account['credentials_updated_at'] ?? null,
        ];

        if (is_array($credentials)) {
            $summary['type'] = $credentials['type'] ?? null;
            if (($credentials['type'] ?? '') === 'obraok_api') {
                $summary += [
                    'api_base_url' => (string) ($credentials['api_base_url'] ?? ''),
                    'workspace_id' => (string) ($credentials['workspace_id'] ?? ''),
                ];
            } elseif (($credentials['type'] ?? '') === 'whatsapp_cloud') {
                $hasAccessToken = trim((string) ($credentials['access_token'] ?? '')) !== '';
                $usesPlatformToken = !empty($credentials['uses_platform_token']);
                $summary += [
                    'phone_number_id' => (string) ($credentials['phone_number_id'] ?? $account['external_account_id'] ?? ''),
                    'business_account_id' => (string) ($credentials['business_account_id'] ?? ''),
                    'graph_version' => (string) ($credentials['graph_version'] ?? 'v20.0'),
                    'webhook_token' => (string) ($account['webhook_token'] ?? ''),
                    'token_source' => $usesPlatformToken ? 'Global Superadmin' : ($hasAccessToken ? 'Cuenta' : 'Sin token'),
                ];
                $summary['is_configured'] = $hasAccessToken && trim((string) ($summary['phone_number_id'] ?? '')) !== '';
                $summary['label'] = $summary['is_configured'] ? (string) ($summary['phone_number_id'] ?? 'WhatsApp Cloud') : 'Pendiente';
            } else {
                $summary += [
                    'email_address' => (string) ($credentials['email_address'] ?? $account['external_account_id'] ?? ''),
                    'username' => (string) ($credentials['username'] ?? ''),
                    'imap_host' => (string) ($credentials['imap_host'] ?? ''),
                    'imap_port' => (int) ($credentials['imap_port'] ?? 993),
                    'imap_encryption' => (string) ($credentials['imap_encryption'] ?? 'ssl'),
                    'smtp_host' => (string) ($credentials['smtp_host'] ?? ''),
                    'smtp_port' => (int) ($credentials['smtp_port'] ?? 587),
                    'smtp_encryption' => (string) ($credentials['smtp_encryption'] ?? 'tls'),
                ];
            }
        }

        unset($account['encrypted_credentials']);
        $account['credential_summary'] = $summary;

        return $account;
    }

    private function credentialsFromInput(array $account, array $input): array
    {
        $existing = $this->decryptCredentials($account) ?? [];

        if (($account['provider'] ?? '') === 'obraok') {
            $apiToken = trim((string) ($input['api_token'] ?? ''));
            return [
                'type' => 'obraok_api',
                'api_base_url' => trim((string) ($input['api_base_url'] ?? '')),
                'api_token' => $apiToken !== '' ? $apiToken : (string) ($existing['api_token'] ?? ''),
                'workspace_id' => trim((string) ($input['workspace_id'] ?? '')),
            ];
        }

        if (($account['provider'] ?? '') === 'whatsapp_cloud') {
            $accessToken = trim((string) ($input['access_token'] ?? ''));
            $phoneNumberId = trim((string) ($input['phone_number_id'] ?? $account['external_account_id'] ?? ''));
            return [
                'type' => 'whatsapp_cloud',
                'access_token' => $accessToken !== '' ? $accessToken : (string) ($existing['access_token'] ?? ''),
                'phone_number_id' => $phoneNumberId,
                'business_account_id' => trim((string) ($input['business_account_id'] ?? $existing['business_account_id'] ?? '')),
                'graph_version' => $this->graphVersion((string) ($input['graph_version'] ?? $existing['graph_version'] ?? 'v20.0')),
            ];
        }

        $password = (string) ($input['password'] ?? '');
        return [
            'type' => 'email_imap_smtp',
            'email_address' => trim((string) ($input['email_address'] ?? $account['external_account_id'] ?? '')),
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => $password !== '' ? $password : (string) ($existing['password'] ?? ''),
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

        if (($credentials['type'] ?? '') === 'whatsapp_cloud') {
            $token = (string) ($credentials['access_token'] ?? '');
            $phoneNumberId = (string) ($credentials['phone_number_id'] ?? 'whatsapp');
            return $token !== '' ? $phoneNumberId . ' / token ****' . substr($token, -4) : $phoneNumberId;
        }

        return (string) ($credentials['email_address'] ?? $credentials['username'] ?? 'email');
    }

    private function whatsAppCloudCredentials(array $account): ?array
    {
        $credentials = $this->decryptCredentials($account) ?? [];
        $settings = $this->settings($account);
        if (($credentials['type'] ?? '') !== 'whatsapp_cloud') {
            $credentials = [
                'type' => 'whatsapp_cloud',
                'phone_number_id' => (string) ($settings['whatsapp_phone_number_id'] ?? $account['external_account_id'] ?? ''),
                'business_account_id' => '',
                'graph_version' => 'v20.0',
                'access_token' => '',
            ];
        }

        if (!empty($settings['whatsapp_phone_number_id'])) {
            $credentials['phone_number_id'] = (string) $settings['whatsapp_phone_number_id'];
        }

        if (trim((string) ($credentials['phone_number_id'] ?? '')) === '') {
            $credentials['phone_number_id'] = (string) ($account['external_account_id'] ?? '');
        }

        if (trim((string) ($credentials['graph_version'] ?? '')) === '') {
            $credentials['graph_version'] = 'v20.0';
        }

        if (trim((string) ($credentials['access_token'] ?? '')) === '') {
            $platformToken = (new AiProviderRepository())->resolvedPlatformApiKey('whatsapp_cloud');
            if ($platformToken !== '') {
                $credentials['access_token'] = $platformToken;
                $credentials['uses_platform_token'] = true;
            }
        }

        return is_array($credentials) ? $credentials : null;
    }

    private function rememberWhatsAppPhoneNumberId(array $account, string $phoneNumberId, string $displayPhoneNumber): void
    {
        if ($phoneNumberId === '' || (int) ($account['id'] ?? 0) < 1) {
            return;
        }

        $settings = $this->settings($account);
        $settings['whatsapp_phone_number_id'] = $phoneNumberId;
        if ($displayPhoneNumber !== '') {
            $settings['whatsapp_display_phone_number'] = $displayPhoneNumber;
        }

        Database::connection()->prepare('UPDATE omnichannel_accounts
            SET settings_json = :settings_json,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id')->execute([
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'id' => (int) $account['id'],
        ]);
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

    private function accountByProviderExternal(string $provider, string $externalAccountId): ?array
    {
        if ($externalAccountId === '') {
            return null;
        }

        $statement = Database::connection()->prepare('SELECT * FROM omnichannel_accounts WHERE provider = :provider AND external_account_id = :external_account_id AND status != "disabled" ORDER BY FIELD(status, "connected", "sandbox", "simulated", "error") LIMIT 1');
        $statement->execute(['provider' => $provider, 'external_account_id' => $externalAccountId]);
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

    private function messageExists(int $companyId, string $externalMessageId): bool
    {
        $statement = Database::connection()->prepare('SELECT id FROM inbox_messages WHERE company_id = :company_id AND external_message_id = :external_message_id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'external_message_id' => $externalMessageId]);
        return (bool) $statement->fetchColumn();
    }

    private function mailboxPath(array $credentials): string
    {
        $host = trim((string) ($credentials['imap_host'] ?? ''));
        $port = (int) ($credentials['imap_port'] ?? 993);
        if ($host === '' || $port <= 0) {
            return '';
        }

        $encryption = $this->encryption((string) ($credentials['imap_encryption'] ?? 'ssl'));
        $flags = $encryption === 'ssl' ? '/imap/ssl/novalidate-cert' : ($encryption === 'tls' ? '/imap/tls/novalidate-cert' : '/imap/notls');
        return '{' . $host . ':' . $port . $flags . '}INBOX';
    }

    private function decodeMime(string $value): string
    {
        if (!function_exists('imap_mime_header_decode')) {
            return trim($value);
        }

        $parts = @imap_mime_header_decode($value) ?: [];
        $decoded = '';
        foreach ($parts as $part) {
            $decoded .= (string) ($part->text ?? '');
        }

        return trim($decoded) ?: trim($value);
    }

    private function emailSender(string $from): array
    {
        $email = '';
        $name = $this->decodeMime($from);

        if (function_exists('imap_rfc822_parse_adrlist')) {
            $addresses = @imap_rfc822_parse_adrlist($from, '');
            $first = is_array($addresses) ? ($addresses[0] ?? null) : null;
            if ($first) {
                $mailbox = (string) ($first->mailbox ?? '');
                $host = (string) ($first->host ?? '');
                $email = $mailbox !== '' && $host !== '' ? $mailbox . '@' . $host : '';
                $personal = $this->decodeMime((string) ($first->personal ?? ''));
                $name = $personal !== '' ? $personal : ($email ?: $name);
            }
        }

        if ($email === '' && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $from, $matches)) {
            $email = $matches[0];
        }

        return ['name' => $name ?: ($email ?: 'Cliente Email'), 'email' => $email ?: null];
    }

    private function emailBody($mailbox, int $uid): string
    {
        $body = (string) @imap_fetchbody($mailbox, $uid, '1', FT_UID | FT_PEEK);
        if (trim($body) === '') {
            $body = (string) @imap_body($mailbox, $uid, FT_UID | FT_PEEK);
        }

        $decoded = quoted_printable_decode($body);
        $decoded = strip_tags($decoded);
        $decoded = preg_replace('/\s+/', ' ', $decoded) ?: $decoded;

        return trim(substr($decoded, 0, 6000));
    }

    private function emailThreadId(int $accountId, ?string $email, string $subject): string
    {
        $normalizedSubject = strtolower(trim(preg_replace('/^(re|fw|fwd):\s*/i', '', $subject) ?? $subject));
        return 'email-' . $accountId . '-' . sha1(($email ?: 'unknown') . '|' . $normalizedSubject);
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

    private function whatsAppCloudInboundItems(array $payload): array
    {
        $items = [];
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];
                $phoneNumberId = trim((string) ($metadata['phone_number_id'] ?? ''));
                $displayPhoneNumber = preg_replace('/\D+/', '', (string) ($metadata['display_phone_number'] ?? '')) ?: '';
                $contacts = $this->whatsAppContactsByWaId(is_array($value['contacts'] ?? null) ? $value['contacts'] : []);
                $messages = is_array($value['messages'] ?? null) ? $value['messages'] : [];

                foreach ($messages as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? '')) ?: '';
                    $contact = $contacts[$from] ?? [];
                    $name = trim((string) ($contact['name'] ?? '')) ?: ($from !== '' ? '+' . $from : 'Cliente WhatsApp');
                    $body = $this->whatsAppMessageBody($message);
                    $messageId = trim((string) ($message['id'] ?? ''));
                    $threadId = 'wa-' . $phoneNumberId . '-' . ($from ?: sha1($messageId ?: json_encode($message)));

                    $items[] = [
                        'phone_number_id' => $phoneNumberId,
                        'display_phone_number' => $displayPhoneNumber,
                        'payload' => [
                            'body' => $body,
                            'customer_name' => $name,
                            'customer_handle' => $from,
                            'phone' => $from,
                            'subject' => 'WhatsApp de ' . $name,
                            'conversation_id' => $threadId,
                            'message_id' => $messageId,
                            'whatsapp_type' => (string) ($message['type'] ?? 'unknown'),
                            'raw_whatsapp' => $message,
                        ],
                    ];
                }
            }
        }

        return $items;
    }

    private function whatsAppContactsByWaId(array $contacts): array
    {
        $indexed = [];
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $waId = preg_replace('/\D+/', '', (string) ($contact['wa_id'] ?? '')) ?: '';
            if ($waId === '') {
                continue;
            }

            $profile = is_array($contact['profile'] ?? null) ? $contact['profile'] : [];
            $indexed[$waId] = ['name' => (string) ($profile['name'] ?? '')];
        }

        return $indexed;
    }

    private function whatsAppMessageBody(array $message): string
    {
        $type = (string) ($message['type'] ?? 'text');
        if ($type === 'text') {
            return trim((string) ($message['text']['body'] ?? ''));
        }

        if ($type === 'interactive') {
            $interactive = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];
            $button = is_array($interactive['button_reply'] ?? null) ? $interactive['button_reply'] : [];
            $list = is_array($interactive['list_reply'] ?? null) ? $interactive['list_reply'] : [];
            return trim((string) ($button['title'] ?? $list['title'] ?? '[Respuesta interactiva recibida]'));
        }

        if ($type === 'button') {
            return trim((string) ($message['button']['text'] ?? '[Boton recibido]'));
        }

        foreach (['image' => 'Imagen', 'video' => 'Video', 'document' => 'Documento', 'audio' => 'Audio', 'sticker' => 'Sticker'] as $mediaType => $label) {
            if ($type === $mediaType) {
                $media = is_array($message[$mediaType] ?? null) ? $message[$mediaType] : [];
                $caption = trim((string) ($media['caption'] ?? $media['filename'] ?? ''));
                return '[' . $label . ' recibido]' . ($caption !== '' ? ' ' . $caption : '');
            }
        }

        if ($type === 'location') {
            return '[Ubicacion recibida]';
        }

        if ($type === 'contacts') {
            return '[Contacto recibido]';
        }

        return '[Mensaje WhatsApp recibido: ' . $type . ']';
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
            Database::connection()->prepare('UPDATE inbox_conversations SET status = IF(status IN ("answered", "closed"), "open", status), priority = :priority, last_inbound_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
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

    private function insertInboundMessage(int $companyId, int $conversationId, array $account, array $message): bool
    {
        if ($message['external_message_id']) {
            $existing = Database::connection()->prepare('SELECT id FROM inbox_messages WHERE company_id = :company_id AND external_message_id = :external_message_id LIMIT 1');
            $existing->execute(['company_id' => $companyId, 'external_message_id' => $message['external_message_id']]);
            if ($existing->fetchColumn()) {
                return false;
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

        return true;
    }

    private function superviseInboundConversation(int $companyId, int $conversationId, array $account, array $message): array
    {
        $autonomy = (new AutonomyRepository())->profile($companyId);
        $decision = $this->automationDecision($account, $message, $autonomy);
        $commercial = (new OmnichannelCommercialAutomation())->handle($companyId, $account, $message, $decision);

        if ($decision['mode'] === 'human_required') {
            Database::connection()->prepare('UPDATE inbox_conversations SET status = "open", assigned_to = :assigned_to, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'assigned_to' => !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : null,
                'company_id' => $companyId,
                'id' => $conversationId,
            ]);
            $this->logEvent($companyId, (int) $account['id'], $conversationId, (string) $account['provider'], (string) $account['channel'], 'inbound', 'queued', [
                'ai_decision' => $decision,
                'commercial_automation' => $commercial,
                'next_step' => 'human_review',
            ]);

            return ['mode' => 'human_required', 'message' => 'AsisFly derivo la conversacion a supervision humana.'];
        }

        $message = $this->messageWithBrandRoute($companyId, $conversationId, $message);
        $aiDraft = (new OmnichannelAiResponder())->draft(
            $companyId,
            $account,
            $message,
            $decision,
            $this->conversationHistory($companyId, $conversationId),
            !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : 0
        );
        $draft = (string) $aiDraft['body'];
        $draftId = $this->insertAiDraft($companyId, $conversationId, $account, $draft);

        if ($decision['mode'] === 'approval_required') {
            Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval", updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $companyId,
                'id' => $conversationId,
            ]);
            $this->createApprovalIfNeeded($companyId, $conversationId, $account, $message, $draft, $draftId, $decision);
            $this->logEvent($companyId, (int) $account['id'], $conversationId, (string) $account['provider'], (string) $account['channel'], 'inbound', 'queued', [
                'ai_decision' => $decision,
                'ai_draft' => $this->draftTrace($aiDraft),
                'commercial_automation' => $commercial,
                'draft_message_id' => $draftId,
                'next_step' => 'approval',
            ]);

            return ['mode' => 'approval_required', 'message' => 'AsisFly preparo un borrador y lo dejo pendiente de aprobacion.'];
        }

        $send = $this->sendOutboundAttempt($companyId, $conversationId, [
            'conversation_id' => $conversationId,
            'message_id' => $draftId,
            'approved_by' => null,
            'body' => $draft,
            'ai_autonomous' => true,
            'ai_decision' => $decision,
            'ai_draft' => $this->draftTrace($aiDraft),
            'commercial_automation' => $commercial,
        ]);

        if (!empty($send['ok'])) {
            Database::connection()->prepare('UPDATE inbox_messages SET status = "sent", sent_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $companyId,
                'id' => $draftId,
            ]);
            Database::connection()->prepare('UPDATE inbox_conversations SET status = "answered", last_outbound_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
                'company_id' => $companyId,
                'id' => $conversationId,
            ]);
            (new AIResponseReviewService())->markGeneratedResponse($companyId, (int) ($aiDraft['generated_response_id'] ?? 0), 'sent');
            (new AutonomyRepository())->recordSignal($companyId, 'autonomous_executed');

            return ['mode' => 'auto_resolved', 'message' => 'AsisFly respondio automaticamente y registro auditoria.'];
        }

        Database::connection()->prepare('UPDATE inbox_messages SET status = "failed" WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $draftId,
        ]);
        Database::connection()->prepare('UPDATE inbox_conversations SET status = "pending_approval", updated_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'company_id' => $companyId,
            'id' => $conversationId,
        ]);
        $this->createApprovalIfNeeded($companyId, $conversationId, $account, $message, $draft, $draftId, [
            ...$decision,
            'mode' => 'approval_required',
            'reason' => 'El envio automatico fallo: ' . (string) ($send['message'] ?? 'sin detalle'),
        ]);

        return ['mode' => 'approval_required', 'message' => 'AsisFly preparo respuesta, pero necesita revision porque el envio automatico fallo.'];
    }

    private function automationDecision(array $account, array $message, array $autonomy): array
    {
        $risk = $this->messageRisk((string) ($message['body'] ?? ''), (string) ($message['priority'] ?? 'medium'));
        $confidence = $this->messageConfidence((string) ($message['body'] ?? ''), $risk, $autonomy);
        return (new OmnichannelAutonomyPolicy())->decide(
            (int) ($account['company_id'] ?? 0),
            $account,
            $message,
            $autonomy,
            $risk,
            $confidence,
            $this->needsHuman((string) ($message['body'] ?? ''), $risk)
        );
    }

    private function insertAiDraft(int $companyId, int $conversationId, array $account, string $draft): int
    {
        $existing = Database::connection()->prepare('SELECT id FROM inbox_messages WHERE company_id = :company_id AND conversation_id = :conversation_id AND direction = "outbound" AND ai_generated = 1 AND status IN ("draft", "approved", "failed") ORDER BY id DESC LIMIT 1');
        $existing->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
        $existingId = (int) $existing->fetchColumn();
        if ($existingId > 0) {
            Database::connection()->prepare('UPDATE inbox_messages SET body = :body, status = "draft", sender_name = "AsisFly" WHERE company_id = :company_id AND id = :id')->execute([
                'body' => $draft,
                'company_id' => $companyId,
                'id' => $existingId,
            ]);
            return $existingId;
        }

        Database::connection()->prepare('INSERT INTO inbox_messages (company_id, account_id, conversation_id, provider, direction, sender_name, body, ai_generated, status) VALUES (:company_id, :account_id, :conversation_id, :provider, "outbound", "AsisFly", :body, 1, "draft")')->execute([
            'company_id' => $companyId,
            'account_id' => $account['id'],
            'conversation_id' => $conversationId,
            'provider' => $account['provider'],
            'body' => $draft,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function createApprovalIfNeeded(int $companyId, int $conversationId, array $account, array $message, string $draft, int $draftId, array $decision): void
    {
        if ($this->pendingApprovalExists($companyId, $conversationId)) {
            return;
        }

        (new ActionRepository())->create($companyId, !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : null, [
            'title' => 'Supervisar respuesta de AsisFly',
            'description' => 'AsisFly preparo una respuesta para ' . ($message['customer_name'] ?? 'cliente') . '. Motivo: ' . ($decision['reason'] ?? 'requiere revision.'),
            'module' => 'Omnicanal',
            'brain' => $this->brainLabel((string) ($account['brain_key'] ?? 'commercial')),
            'action_type' => 'send_omnichannel_reply',
            'priority' => $message['priority'] ?? 'medium',
            'risk_level' => $decision['risk'] ?? 'medium',
            'assigned_to' => !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : null,
            'requires_approval' => true,
            'payload' => [
                'conversation_id' => $conversationId,
                'message_id' => $draftId,
                'account_id' => (int) $account['id'],
                'account' => $account['display_name'] ?? null,
                'channel' => $account['channel'],
                'reply' => $draft,
                'body' => $draft,
                'ai_decision' => $decision,
            ],
        ]);
    }

    private function pendingApprovalExists(int $companyId, int $conversationId): bool
    {
        try {
            $statement = Database::connection()->prepare('SELECT id FROM action_center_items WHERE company_id = :company_id AND action_type = "send_omnichannel_reply" AND status = "pending" AND payload_json LIKE :needle LIMIT 1');
            $statement->execute([
                'company_id' => $companyId,
                'needle' => '%"conversation_id":' . $conversationId . '%',
            ]);
            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function conversationHistory(int $companyId, int $conversationId): array
    {
        try {
            $statement = Database::connection()->prepare('SELECT direction, sender_name, body, created_at FROM inbox_messages WHERE company_id = :company_id AND conversation_id = :conversation_id ORDER BY id DESC LIMIT 10');
            $statement->execute(['company_id' => $companyId, 'conversation_id' => $conversationId]);
            return array_reverse($statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable) {
            return [];
        }
    }

    private function draftTrace(array $aiDraft): array
    {
        return [
            'status' => $aiDraft['status'] ?? 'fallback',
            'provider' => $aiDraft['provider'] ?? 'simulated',
            'model' => $aiDraft['model'] ?? 'asisfly-demo-latam',
            'memory_hits' => $aiDraft['memory_hits'] ?? 0,
            'knowledge_hits' => $aiDraft['knowledge_hits'] ?? 0,
            'context_log_id' => $aiDraft['context_log_id'] ?? null,
            'generated_response_id' => $aiDraft['generated_response_id'] ?? null,
            'brand_route' => $aiDraft['brand_route'] ?? null,
            'confidence' => $aiDraft['confidence'] ?? null,
            'error' => $aiDraft['error'] ?? null,
        ];
    }

    private function messageRisk(string $body, string $priority): string
    {
        $text = strtolower($body);
        foreach (['reclamo', 'denuncia', 'legal', 'abogado', 'demanda', 'devolucion', 'reembolso', 'cancelar', 'molesto', 'urgente'] as $word) {
            if (str_contains($text, $word)) {
                return 'high';
            }
        }

        if ($priority === 'high' || str_contains($text, 'cotizacion') || str_contains($text, 'precio') || str_contains($text, 'comprar')) {
            return 'medium';
        }

        return 'low';
    }

    private function messageConfidence(string $body, string $risk, array $autonomy): int
    {
        $progress = (int) ($autonomy['learning_progress'] ?? 0);
        $base = match ($risk) {
            'high' => 45,
            'medium' => 68,
            default => 82,
        };
        $lengthPenalty = strlen($body) > 1200 ? 12 : (strlen($body) < 20 ? 10 : 0);

        return max(20, min(96, $base + (int) floor($progress / 5) - $lengthPenalty));
    }

    private function needsHuman(string $body, string $risk): bool
    {
        if ($risk === 'high') {
            return true;
        }

        $text = strtolower($body);
        foreach (['no entiendo', 'hablar con humano', 'supervisor', 'gerente', 'reclamo formal'] as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    private function brainLabel(string $brainKey): string
    {
        return match ($brainKey) {
            'administrative' => 'Cerebro Administrativo',
            'analytical' => 'Cerebro Analitico',
            'operational' => 'Cerebro Operacional',
            'executive' => 'Cerebro Ejecutivo',
            default => 'Cerebro Comercial',
        };
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

    private function autoBrainKey(string $provider, string $channel, string $displayName, string $externalAccountId): string
    {
        if ($provider === 'obraok' || $channel === 'Operaciones') {
            return 'operational';
        }

        if (in_array($channel, ['WhatsApp', 'Instagram', 'Messenger', 'Telegram'], true)) {
            return 'commercial';
        }

        $text = strtolower($displayName . ' ' . $externalAccountId);
        foreach (['admin', 'contabilidad', 'finanza', 'factura', 'facturacion', 'pago', 'cobranza', 'rrhh', 'personal'] as $word) {
            if (str_contains($text, $word)) {
                return 'administrative';
            }
        }

        foreach (['gerencia', 'gerente', 'direccion', 'director', 'ceo', 'fundador'] as $word) {
            if (str_contains($text, $word)) {
                return 'executive';
            }
        }

        foreach (['analytics', 'analitica', 'reporte', 'reportes', 'datos', 'bi'] as $word) {
            if (str_contains($text, $word)) {
                return 'analytical';
            }
        }

        return 'commercial';
    }

    private function webhookToken(int $companyId, string $provider): string
    {
        return 'acct-' . $companyId . '-' . $provider . '-' . bin2hex(random_bytes(8));
    }

    private function encryption(string $value): string
    {
        return in_array($value, ['ssl', 'tls', 'none'], true) ? $value : 'tls';
    }

    private function graphVersion(string $version): string
    {
        $version = trim($version);
        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v20.0';
    }

    private function logWebhookWarning(string $event, array $payload): void
    {
        $directory = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($directory . '/whatsapp-cloud.log', json_encode([
            'at' => date('c'),
            'event' => $event,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}
