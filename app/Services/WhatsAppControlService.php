<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\BookingRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class WhatsAppControlService
{
    public function controllers(int $companyId): array
    {
        if (!$this->ready() || $companyId <= 0) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT c.*, u.name AS user_name, a.display_name AS account_name, r.name AS resource_name
             FROM whatsapp_control_users c
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN omnichannel_accounts a ON a.id = c.account_id AND a.company_id = c.company_id
             LEFT JOIN booking_resources r ON r.id = c.resource_id AND r.company_id = c.company_id
             WHERE c.company_id = :company_id
             ORDER BY c.is_active DESC, c.control_role, c.display_name'
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function users(int $companyId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $statement = Database::connection()->prepare('SELECT id, name, email FROM users WHERE company_id = :company_id AND status = "active" ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function whatsappAccounts(int $companyId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $statement = Database::connection()->prepare('SELECT id, display_name, external_account_id, status FROM omnichannel_accounts WHERE company_id = :company_id AND provider = "whatsapp_cloud" AND channel = "WhatsApp" ORDER BY display_name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resources(int $companyId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $statement = Database::connection()->prepare('SELECT id, name, resource_type FROM booking_resources WHERE company_id = :company_id AND is_active = TRUE ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function commands(int $companyId, int $limit = 12): array
    {
        if (!$this->ready()) {
            return [];
        }
        $statement = Database::connection()->prepare(
            'SELECT c.*, p.display_name AS controller_name, a.display_name AS account_name
             FROM whatsapp_control_commands c
             INNER JOIN whatsapp_control_users p ON p.id = c.controller_id
             LEFT JOIN omnichannel_accounts a ON a.id = c.account_id
             WHERE c.company_id = :company_id ORDER BY c.id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveController(int $companyId, array $input): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'El Centro de control WhatsApp aun no esta instalado.'];
        }
        $phone = $this->phone((string) ($input['phone_number'] ?? ''));
        $name = trim((string) ($input['display_name'] ?? ''));
        $role = $this->role((string) ($input['control_role'] ?? 'observer'));
        $accountId = (int) ($input['account_id'] ?? 0);
        $resourceId = (int) ($input['resource_id'] ?? 0);
        $userId = (int) ($input['user_id'] ?? 0);

        if ($name === '' || $phone === '') {
            return ['ok' => false, 'message' => 'Indica nombre y número WhatsApp de la persona.'];
        }
        if (!$this->ownsAccount($companyId, $accountId)) {
            return ['ok' => false, 'message' => 'Selecciona el WhatsApp de empresa que recibirá los comandos.'];
        }
        if ($userId > 0 && !$this->ownsUser($companyId, $userId)) {
            return ['ok' => false, 'message' => 'El usuario seleccionado no pertenece a esta empresa.'];
        }
        if (in_array($role, ['owner', 'manager', 'schedule_operator'], true) && !$this->ownsResource($companyId, $resourceId)) {
            return ['ok' => false, 'message' => 'Selecciona la agenda que esta persona puede controlar.'];
        }

        Database::connection()->prepare(
            'INSERT INTO whatsapp_control_users (company_id, user_id, account_id, resource_id, display_name, phone_number, control_role, is_active)
             VALUES (:company_id, :user_id, :account_id, :resource_id, :display_name, :phone_number, :control_role, :is_active)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), account_id = VALUES(account_id), resource_id = VALUES(resource_id), display_name = VALUES(display_name), control_role = VALUES(control_role), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP'
        )->execute([
            'company_id' => $companyId,
            'user_id' => $userId > 0 ? $userId : null,
            'account_id' => $accountId,
            'resource_id' => $resourceId > 0 ? $resourceId : null,
            'display_name' => $name,
            'phone_number' => $phone,
            'control_role' => $role,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ]);

        return ['ok' => true, 'message' => 'Persona autorizada para controlar AsisFly por WhatsApp.'];
    }

    public function disableController(int $companyId, int $controllerId): void
    {
        if (!$this->ready() || $controllerId <= 0) {
            return;
        }
        Database::connection()->prepare('UPDATE whatsapp_control_users SET is_active = FALSE WHERE company_id = :company_id AND id = :id')->execute(['company_id' => $companyId, 'id' => $controllerId]);
    }

    /** Returns null when the sender is a client, not an authorized internal controller. */
    public function handle(int $companyId, int $accountId, array $message, string $timezone): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $phone = $this->phone((string) ($message['customer_handle'] ?? $message['phone'] ?? ''));
        if ($phone === '') {
            return null;
        }

        $controller = $this->controllerFor($companyId, $accountId, $phone);
        if (!$controller) {
            return null;
        }

        $text = trim((string) ($message['body'] ?? ''));
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return $this->reply('Escribe "ayuda" para ver los comandos disponibles.');
        }

        if (preg_match('/^(confirmar|confirmo)\s+([a-z0-9-]{4,20})$/i', $normalized, $match)) {
            return $this->confirm($companyId, $accountId, $controller, strtoupper($match[2]), $timezone);
        }
        if (in_array($normalized, ['ayuda', 'menu', 'comandos', 'hola'], true)) {
            $this->log($companyId, $accountId, (int) $controller['id'], $text, 'help', 'executed', 'Mostró ayuda de comandos.');
            return $this->reply($this->help((string) $controller['control_role']));
        }
        if (str_contains($normalized, 'mi agenda') || str_contains($normalized, 'mis reservas')) {
            return $this->agenda($companyId, $accountId, $controller, $text, $timezone);
        }
        if (str_starts_with($normalized, 'bloquea') || str_starts_with($normalized, 'bloquear')) {
            return $this->requestBlock($companyId, $accountId, $controller, $text, $normalized, $timezone);
        }

        $this->log($companyId, $accountId, (int) $controller['id'], $text, 'unknown', 'received', 'Comando no reconocido.');
        return $this->reply('No reconocí esa orden. ' . $this->help((string) $controller['control_role']));
    }

    private function requestBlock(int $companyId, int $accountId, array $controller, string $original, string $command, string $timezone): array
    {
        if (!$this->canManageAgenda((string) $controller['control_role'])) {
            return $this->reply('Tu acceso no permite bloquear agenda. Pide al dueño que habilite el rol Operador de agenda.');
        }
        $resourceId = (int) ($controller['resource_id'] ?? 0);
        if ($resourceId <= 0) {
            return $this->reply('No tienes una agenda asignada. El dueño debe asignártela en Integraciones > Control por WhatsApp.');
        }
        $range = $this->blockRange($command, $timezone);
        if (!$range) {
            return $this->reply('Usa este formato: Bloquea mi agenda mañana de 10:00 a 13:00. También puedes indicar una fecha: Bloquea agenda 2026-07-15 de 10:00 a 13:00.');
        }

        $code = $this->confirmationCode();
        $payload = ['resource_id' => $resourceId, 'starts_at' => $range['starts_at'], 'ends_at' => $range['ends_at'], 'reason' => 'Bloqueo solicitado por WhatsApp'];
        $this->log($companyId, $accountId, (int) $controller['id'], $original, 'block_agenda', 'pending_confirmation', 'Pendiente de confirmación.', $payload, $code);
        $resource = $this->resourceName($companyId, $resourceId);
        return $this->reply('Voy a bloquear ' . $resource . ' el ' . $range['label'] . '. Responde CONFIRMAR ' . $code . ' para aplicarlo.');
    }

    private function confirm(int $companyId, int $accountId, array $controller, string $code, string $timezone): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM whatsapp_control_commands WHERE company_id = :company_id AND account_id = :account_id AND controller_id = :controller_id AND confirmation_code = :code AND status = "pending_confirmation" ORDER BY id DESC LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'account_id' => $accountId, 'controller_id' => (int) $controller['id'], 'code' => $code]);
        $command = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$command) {
            return $this->reply('No encontré una acción pendiente con ese código. Solicita un nuevo bloqueo o escribe AYUDA.');
        }

        $payload = json_decode((string) ($command['payload_json'] ?? ''), true);
        if (!is_array($payload) || ($command['command_type'] ?? '') !== 'block_agenda') {
            return $this->fail((int) $command['id'], 'La acción pendiente no es válida.');
        }
        $ok = (new BookingRepository())->addBlock($companyId, (int) ($controller['user_id'] ?? 0), $payload, $timezone);
        if (!$ok) {
            return $this->fail((int) $command['id'], 'No se pudo bloquear la agenda. Revisa que el horario sea válido.');
        }

        Database::connection()->prepare('UPDATE whatsapp_control_commands SET status = "executed", result_message = :message, executed_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['message' => 'Agenda bloqueada desde WhatsApp.', 'id' => (int) $command['id']]);
        return $this->reply('Listo. Bloqueé tu agenda. Los clientes ya no verán ese horario como disponible.');
    }

    private function agenda(int $companyId, int $accountId, array $controller, string $original, string $timezone): array
    {
        if (!$this->canManageAgenda((string) $controller['control_role'])) {
            return $this->reply('Tu acceso no incluye agenda.');
        }
        $resourceId = (int) ($controller['resource_id'] ?? 0);
        if ($resourceId <= 0) {
            return $this->reply('No tienes una agenda asignada todavía.');
        }
        $zone = new DateTimeZone($timezone ?: 'America/Santiago');
        $today = new DateTimeImmutable('today', $zone);
        $statement = Database::connection()->prepare('SELECT customer_name, starts_at, status FROM booking_appointments WHERE company_id = :company_id AND resource_id = :resource_id AND starts_at >= :today AND starts_at < :until AND status NOT IN ("cancelled", "no_show") ORDER BY starts_at LIMIT 6');
        $statement->execute(['company_id' => $companyId, 'resource_id' => $resourceId, 'today' => $today->format('Y-m-d H:i:s'), 'until' => $today->add(new DateInterval('P7D'))->format('Y-m-d H:i:s')]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->log($companyId, $accountId, (int) $controller['id'], $original, 'agenda_summary', 'executed', 'Consultó su agenda.');
        if (!$rows) {
            return $this->reply('No tienes reservas en los próximos 7 días.');
        }
        $lines = array_map(fn (array $row): string => (new DateTimeImmutable((string) $row['starts_at'], $zone))->format('D d H:i') . ' - ' . $row['customer_name'] . ' (' . $row['status'] . ')', $rows);
        return $this->reply("Tus próximas reservas:\n" . implode("\n", $lines));
    }

    private function log(int $companyId, int $accountId, int $controllerId, string $text, string $type, string $status, string $result, array $payload = [], ?string $code = null): void
    {
        Database::connection()->prepare('INSERT INTO whatsapp_control_commands (company_id, account_id, controller_id, command_text, command_type, payload_json, confirmation_code, status, result_message) VALUES (:company_id, :account_id, :controller_id, :command_text, :command_type, :payload_json, :confirmation_code, :status, :result_message)')->execute([
            'company_id' => $companyId, 'account_id' => $accountId, 'controller_id' => $controllerId, 'command_text' => $text, 'command_type' => $type,
            'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null, 'confirmation_code' => $code, 'status' => $status, 'result_message' => $result,
        ]);
    }

    private function controllerFor(int $companyId, int $accountId, string $phone): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM whatsapp_control_users WHERE company_id = :company_id AND phone_number = :phone_number AND is_active = TRUE AND (account_id = :account_id OR account_id IS NULL) ORDER BY account_id IS NULL, id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'account_id' => $accountId, 'phone_number' => $phone]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function blockRange(string $command, string $timezone): ?array
    {
        if (!preg_match('/(?:de\s*)?(\d{1,2}:\d{2})\s*(?:a|hasta|-)\s*(\d{1,2}:\d{2})/i', $command, $hours)) {
            return null;
        }
        $zone = new DateTimeZone($timezone ?: 'America/Santiago');
        $date = new DateTimeImmutable('today', $zone);
        if (str_contains($command, 'manana') || str_contains($command, 'mañana')) {
            $date = $date->add(new DateInterval('P1D'));
        } elseif (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $command, $dateMatch)) {
            try { $date = new DateTimeImmutable($dateMatch[1], $zone); } catch (Throwable) { return null; }
        }
        try {
            $starts = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $hours[1], $zone);
            $ends = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $hours[2], $zone);
        } catch (Throwable) {
            return null;
        }
        if ($ends <= $starts) {
            return null;
        }
        return ['starts_at' => $starts->format('Y-m-d H:i:s'), 'ends_at' => $ends->format('Y-m-d H:i:s'), 'label' => $starts->format('d/m H:i') . ' a ' . $ends->format('H:i')];
    }

    private function help(string $role): string
    {
        $base = 'Comandos: MI AGENDA o MIS RESERVAS.';
        return $this->canManageAgenda($role) ? $base . ' Para bloquear: BLOQUEA MI AGENDA MAÑANA DE 10:00 A 13:00.' : $base;
    }

    private function reply(string $message): array { return ['handled' => true, 'message' => $message]; }
    private function fail(int $commandId, string $message): array
    {
        Database::connection()->prepare('UPDATE whatsapp_control_commands SET status = "failed", result_message = :message WHERE id = :id')->execute(['message' => $message, 'id' => $commandId]);
        return $this->reply($message);
    }
    private function canManageAgenda(string $role): bool { return in_array($role, ['owner', 'manager', 'schedule_operator'], true); }
    private function role(string $role): string { return in_array($role, ['owner', 'manager', 'schedule_operator', 'sales', 'observer'], true) ? $role : 'observer'; }
    private function phone(string $value): string { return preg_replace('/\D+/', '', $value) ?: ''; }
    private function normalize(string $value): string { return strtolower(trim(str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $value))); }
    private function confirmationCode(): string { return strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)); }
    private function resourceName(int $companyId, int $resourceId): string { $s = Database::connection()->prepare('SELECT name FROM booking_resources WHERE company_id = :company_id AND id = :id'); $s->execute(['company_id' => $companyId, 'id' => $resourceId]); return (string) ($s->fetchColumn() ?: 'tu agenda'); }
    private function ownsAccount(int $companyId, int $accountId): bool { $s = Database::connection()->prepare('SELECT COUNT(*) FROM omnichannel_accounts WHERE company_id = :company_id AND id = :id AND provider = "whatsapp_cloud"'); $s->execute(['company_id' => $companyId, 'id' => $accountId]); return (int) $s->fetchColumn() > 0; }
    private function ownsUser(int $companyId, int $userId): bool { $s = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE company_id = :company_id AND id = :id AND status = "active"'); $s->execute(['company_id' => $companyId, 'id' => $userId]); return (int) $s->fetchColumn() > 0; }
    private function ownsResource(int $companyId, int $resourceId): bool { $s = Database::connection()->prepare('SELECT COUNT(*) FROM booking_resources WHERE company_id = :company_id AND id = :id AND is_active = TRUE'); $s->execute(['company_id' => $companyId, 'id' => $resourceId]); return (int) $s->fetchColumn() > 0; }
    private function ready(): bool { try { Database::connection()->query('SELECT 1 FROM whatsapp_control_users LIMIT 1'); return true; } catch (Throwable) { return false; } }
}
