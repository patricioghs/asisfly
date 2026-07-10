<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class OmnichannelCommercialAutomation
{
    public function handle(int $companyId, array $account, array $message, array $decision): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'reason' => 'CRM no disponible', 'actions' => []];
        }

        try {
            $intent = $this->intent($message);
            $customerId = $this->upsertCustomer($companyId, $account, $message, $intent);
            if ($customerId <= 0) {
                return ['ok' => false, 'reason' => 'No se pudo crear o encontrar cliente', 'actions' => []];
            }

            $actions = ['customer_touched'];
            $this->addActivity($companyId, $customerId, (int) ($account['assigned_user_id'] ?? 0), 'omnichannel_message', 'Mensaje omnicanal recibido: ' . ($message['subject'] ?? 'Sin asunto'), [
                'channel' => $account['channel'] ?? null,
                'account_id' => $account['id'] ?? null,
                'intent' => $intent,
                'decision' => $decision,
            ]);

            if ($intent['commercial']) {
                $opportunityId = $this->ensureOpportunity($companyId, $customerId, $message, $intent);
                if ($opportunityId > 0) {
                    $actions[] = 'opportunity_created';
                }
            }

            if ($intent['task_type'] !== '') {
                $taskId = $this->ensureTask($companyId, $customerId, $account, $message, $intent, $decision);
                if ($taskId > 0) {
                    $actions[] = 'task_created';
                }
            }

            if ($intent['note'] !== '') {
                $this->addNote($companyId, $customerId, (int) ($account['assigned_user_id'] ?? 0), $intent['note']);
                $actions[] = 'note_added';
            }

            return [
                'ok' => true,
                'customer_id' => $customerId,
                'intent' => $intent,
                'actions' => array_values(array_unique($actions)),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'reason' => $exception->getMessage(), 'actions' => []];
        }
    }

    private function upsertCustomer(int $companyId, array $account, array $message, array $intent): int
    {
        $handle = trim((string) ($message['customer_handle'] ?? ''));
        $email = filter_var($handle, FILTER_VALIDATE_EMAIL) ? $handle : null;
        $phone = $email === null ? $handle : null;
        $name = trim((string) ($message['customer_name'] ?? 'Cliente Omnicanal')) ?: 'Cliente Omnicanal';
        $currency = $this->companyCurrency($companyId);
        $nextFollowUpAt = date('Y-m-d H:i:s', strtotime('+' . (int) $intent['followup_days'] . ' days'));

        $customerId = $this->findCustomer($companyId, $email, $phone, $name);
        if ($customerId > 0) {
            Database::connection()->prepare('UPDATE crm_customers
                SET contact_name = COALESCE(NULLIF(contact_name, ""), :contact_name),
                    email = COALESCE(email, :email),
                    phone = COALESCE(phone, :phone),
                    source = COALESCE(source, :source),
                    temperature = :temperature,
                    last_activity_at = CURRENT_TIMESTAMP,
                    next_follow_up_at = :next_follow_up_at
                WHERE company_id = :company_id AND id = :id')->execute([
                'contact_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'source' => 'Omnicanal ' . ($account['channel'] ?? ''),
                'temperature' => $intent['temperature'],
                'next_follow_up_at' => $nextFollowUpAt,
                'company_id' => $companyId,
                'id' => $customerId,
            ]);
            $this->ensureContact($companyId, $customerId, $name, $email, $phone);
            return $customerId;
        }

        Database::connection()->prepare('INSERT INTO crm_customers
            (company_id, owner_id, name, contact_name, email, phone, source, temperature, stage, estimated_value, currency, last_activity_at, next_follow_up_at)
            VALUES (:company_id, :owner_id, :name, :contact_name, :email, :phone, :source, :temperature, :stage, 0, :currency, CURRENT_TIMESTAMP, :next_follow_up_at)')->execute([
            'company_id' => $companyId,
            'owner_id' => !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : null,
            'name' => $name,
            'contact_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'source' => 'Omnicanal ' . ($account['channel'] ?? ''),
            'temperature' => $intent['temperature'],
            'stage' => $intent['commercial'] ? 'Calificado' : 'Nuevo',
            'currency' => $currency,
            'next_follow_up_at' => $nextFollowUpAt,
        ]);

        $customerId = (int) Database::connection()->lastInsertId();
        $this->ensureContact($companyId, $customerId, $name, $email, $phone);
        return $customerId;
    }

    private function ensureOpportunity(int $companyId, int $customerId, array $message, array $intent): int
    {
        $statement = Database::connection()->prepare('SELECT id FROM crm_opportunities WHERE company_id = :company_id AND customer_id = :customer_id AND stage NOT IN ("ganado", "perdido") ORDER BY id DESC LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'customer_id' => $customerId]);
        $existing = (int) $statement->fetchColumn();
        if ($existing > 0) {
            return 0;
        }

        Database::connection()->prepare('INSERT INTO crm_opportunities (company_id, customer_id, title, stage, amount, currency, probability, expected_close_date, source)
            VALUES (:company_id, :customer_id, :title, :stage, 0, :currency, :probability, DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), "Omnicanal")')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'title' => $intent['opportunity_title'] ?: ('Oportunidad desde ' . ($message['subject'] ?? 'Omnicanal')),
            'stage' => $intent['stage'],
            'currency' => $this->companyCurrency($companyId),
            'probability' => $intent['probability'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function ensureTask(int $companyId, int $customerId, array $account, array $message, array $intent, array $decision): int
    {
        $title = $intent['task_title'];
        $dueAt = date('Y-m-d H:i:s', strtotime('+' . (int) $intent['task_hours'] . ' hours'));
        $statement = Database::connection()->prepare('SELECT id FROM crm_tasks WHERE company_id = :company_id AND customer_id = :customer_id AND status = "pending" AND task_type = :task_type AND title = :title LIMIT 1');
        $statement->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'task_type' => $intent['task_type'],
            'title' => $title,
        ]);
        if ((int) $statement->fetchColumn() > 0) {
            return 0;
        }

        Database::connection()->prepare('INSERT INTO crm_tasks (company_id, customer_id, assigned_to, title, task_type, due_at, priority)
            VALUES (:company_id, :customer_id, :assigned_to, :title, :task_type, :due_at, :priority)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'assigned_to' => !empty($account['assigned_user_id']) ? (int) $account['assigned_user_id'] : null,
            'title' => $title,
            'task_type' => $intent['task_type'],
            'due_at' => $dueAt,
            'priority' => $decision['risk'] === 'high' ? 'critical' : $intent['priority'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function intent(array $message): array
    {
        $body = strtolower($this->normalize((string) ($message['body'] ?? '') . ' ' . (string) ($message['subject'] ?? '')));
        $has = fn (array $words): bool => array_reduce($words, fn (bool $carry, string $word): bool => $carry || str_contains($body, $word), false);
        $commercial = $has(['cotizacion', 'cotizar', 'precio', 'valor', 'comprar', 'compra', 'presupuesto', 'promocion', 'producto', 'servicio']);
        $meeting = $has(['reunion', 'agendar', 'agenda', 'llamada', 'visita']);
        $complaint = $has(['reclamo', 'molesto', 'problema', 'devolucion', 'reembolso', 'cancelar', 'urgente']);
        $quote = $has(['cotizacion', 'cotizar', 'presupuesto']);

        if ($complaint) {
            return [
                'commercial' => false,
                'temperature' => 'hot',
                'stage' => 'nuevo',
                'probability' => 10,
                'task_type' => 'call',
                'task_title' => 'Revisar reclamo omnicanal',
                'task_hours' => 2,
                'priority' => 'critical',
                'followup_days' => 1,
                'opportunity_title' => '',
                'note' => 'AsisFly detecto un mensaje sensible o reclamo desde Omnicanal.',
            ];
        }

        return [
            'commercial' => $commercial || $meeting,
            'temperature' => $commercial ? 'hot' : 'warm',
            'stage' => $quote ? 'propuesta' : ($commercial ? 'calificado' : 'nuevo'),
            'probability' => $quote ? 70 : ($commercial ? 45 : 20),
            'task_type' => $meeting ? 'meeting' : ($quote ? 'quote' : ($commercial ? 'follow_up' : 'todo')),
            'task_title' => $meeting ? 'Agendar reunion solicitada por cliente' : ($quote ? 'Preparar cotizacion solicitada' : ($commercial ? 'Hacer seguimiento comercial omnicanal' : 'Revisar conversacion omnicanal')),
            'task_hours' => $commercial || $meeting ? 24 : 48,
            'priority' => $commercial ? 'high' : 'medium',
            'followup_days' => $commercial ? 2 : 3,
            'opportunity_title' => $quote ? 'Cotizacion solicitada desde Omnicanal' : 'Oportunidad detectada desde Omnicanal',
            'note' => $commercial ? 'AsisFly detecto intencion comercial en el mensaje omnicanal.' : '',
        ];
    }

    private function findCustomer(int $companyId, ?string $email, ?string $phone, string $name): int
    {
        $identity = [];
        $params = ['company_id' => $companyId];
        if ($email) {
            $identity[] = 'email = :email';
            $params['email'] = $email;
        }
        if ($phone) {
            $identity[] = 'phone = :phone';
            $params['phone'] = $phone;
        }
        if (!$email && !$phone) {
            $identity[] = 'name = :name';
            $params['name'] = $name;
        }

        $statement = Database::connection()->prepare('SELECT id FROM crm_customers WHERE company_id = :company_id AND (' . implode(' OR ', $identity) . ') LIMIT 1');
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    private function ensureContact(int $companyId, int $customerId, string $name, ?string $email, ?string $phone): void
    {
        $statement = Database::connection()->prepare('SELECT id FROM crm_contacts WHERE company_id = :company_id AND customer_id = :customer_id AND (email <=> :email OR phone <=> :phone) LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'customer_id' => $customerId, 'email' => $email, 'phone' => $phone]);
        if ($statement->fetchColumn()) {
            return;
        }

        Database::connection()->prepare('INSERT INTO crm_contacts (company_id, customer_id, name, email, phone, role, is_primary) VALUES (:company_id, :customer_id, :name, :email, :phone, "Contacto omnicanal", TRUE)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    private function addNote(int $companyId, int $customerId, int $userId, string $note): void
    {
        Database::connection()->prepare('INSERT INTO crm_notes (company_id, customer_id, user_id, note) VALUES (:company_id, :customer_id, :user_id, :note)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'user_id' => $userId ?: null,
            'note' => $note,
        ]);
    }

    private function addActivity(int $companyId, int $customerId, int $userId, string $type, string $summary, array $metadata = []): void
    {
        Database::connection()->prepare('INSERT INTO crm_activities (company_id, customer_id, user_id, activity_type, summary, metadata_json) VALUES (:company_id, :customer_id, :user_id, :activity_type, :summary, :metadata_json)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'user_id' => $userId ?: null,
            'activity_type' => $type,
            'summary' => substr($summary, 0, 220),
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function companyCurrency(int $companyId): string
    {
        try {
            $statement = Database::connection()->prepare('SELECT currency FROM companies WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $companyId]);
            return (string) ($statement->fetchColumn() ?: 'CLP');
        } catch (Throwable) {
            return 'CLP';
        }
    }

    private function ready(): bool
    {
        if (!Database::available()) {
            return false;
        }

        try {
            foreach (['crm_customers', 'crm_contacts', 'crm_opportunities', 'crm_tasks', 'crm_notes', 'crm_activities'] as $table) {
                Database::connection()->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function normalize(string $value): string
    {
        if (!function_exists('iconv')) {
            return $value;
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($ascii) ? $ascii : $value;
    }
}
