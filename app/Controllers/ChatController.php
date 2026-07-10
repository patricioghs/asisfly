<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\AutonomyRepository;
use App\Repositories\ControlRepository;
use App\Services\AiToolRegistry;
use App\Services\AiGateway;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class ChatController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('chat.use');
        $_SESSION['chat_nonce'] = bin2hex(random_bytes(16));
        $this->view('chat/index', [
            'title' => 'Chat IA',
            'messages' => $_SESSION['chat'] ?? [],
            'autonomy' => (new AutonomyRepository())->profile($this->companyId()),
            'chatNonce' => $_SESSION['chat_nonce'],
        ]);
    }

    public function ask(): void
    {
        $this->requirePermission('chat.use');
        $nonce = (string) ($_POST['chat_nonce'] ?? '');
        if ($nonce === '' || !hash_equals((string) ($_SESSION['chat_nonce'] ?? ''), $nonce)) {
            $_SESSION['flash_error'] = 'La solicitud ya fue enviada o expiro. Escribe el mensaje nuevamente si necesitas repetirla.';
            $this->redirect('/chat');
        }
        unset($_SESSION['chat_nonce']);

        $prompt = trim($_POST['prompt'] ?? '');
        if (strlen($prompt) > 4000) {
            $_SESSION['chat'][] = ['role' => 'assistant', 'content' => 'La solicitud es demasiado larga para esta fase. Resume la tarea y vuelve a intentarlo.'];
            $this->redirect('/chat');
        }
        if ($prompt !== '') {
            $historyBefore = $_SESSION['chat'] ?? [];
            $previousAssistant = $this->lastAssistantMessage();
            $effectivePrompt = $this->resolveFollowUpPrompt($prompt, $previousAssistant);
            $_SESSION['chat'][] = ['role' => 'user', 'content' => $prompt];
            $control = $this->tryCreateControlFromPrompt($effectivePrompt);
            if ($control) {
                $_SESSION['chat'][] = $control;
            } elseif ($this->isDailyBriefingRequest($effectivePrompt)) {
                $_SESSION['chat'][] = $this->dailyBriefingMessage();
            } else {
                $result = (new AiGateway())->ask($effectivePrompt, $this->currentCompany(), $_SESSION['user'], $this->chatHistoryForAi($historyBefore));
                $_SESSION['chat'][] = [
                    'role' => 'assistant',
                    'content' => $result['answer'],
                    'brain' => $result['brain']['name'] ?? 'Cerebro Ejecutivo',
                    'module' => $result['brain']['module'] ?? 'Direccion',
                    'status' => $result['status'] ?? 'success',
                ];
            }
        }
        $this->redirect('/chat');
    }

    private function lastAssistantMessage(): ?array
    {
        $messages = array_reverse($_SESSION['chat'] ?? []);
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'assistant') {
                return $message;
            }
        }

        return null;
    }

    private function resolveFollowUpPrompt(string $prompt, ?array $previousAssistant): string
    {
        $normalized = $this->normalizeText($prompt);
        if (!in_array($normalized, ['si', 'sí', 'ok', 'dale', 'claro', 'por favor', 'ya'], true)) {
            return $prompt;
        }

        $previous = $this->normalizeText((string) ($previousAssistant['content'] ?? ''));
        if (str_contains($previous, 'quieres que te revise agenda')
            || str_contains($previous, 'reuniones')
            || str_contains($previous, 'tareas pendientes')
            || str_contains($previous, 'que tenemos hoy')
        ) {
            return 'Revisame que tenemos hoy: agenda, tareas, mensajes, aprobaciones, cotizaciones y alertas.';
        }

        return $prompt;
    }

    private function isDailyBriefingRequest(string $prompt): bool
    {
        $normalized = $this->normalizeText($prompt);
        return str_contains($normalized, 'que tenemos hoy')
            || str_contains($normalized, 'qué tenemos hoy')
            || str_contains($normalized, 'revisame que tenemos hoy')
            || str_contains($normalized, 'agenda de hoy')
            || str_contains($normalized, 'plan de hoy')
            || str_contains($normalized, 'pendientes de hoy');
    }

    private function dailyBriefingMessage(): array
    {
        $summary = $this->dailyBriefing();

        return [
            'role' => 'assistant',
            'content' => $summary,
            'brain' => 'Cerebro Ejecutivo',
            'module' => 'Direccion',
            'status' => 'success',
        ];
    }

    private function dailyBriefing(): string
    {
        $companyId = $this->companyId();
        $company = $this->currentCompany();
        $timezone = new DateTimeZone((string) ($company['timezone'] ?? 'America/Santiago'));
        $today = new DateTimeImmutable('now', $timezone);
        $dateLabel = $this->spanishDate($today);

        $messages = $this->dailyInboxItems($companyId);
        $tasks = $this->dailyTaskItems($companyId, (int) ($_SESSION['user']['id'] ?? 0));
        $actions = $this->dailyActionItems($companyId, (int) ($_SESSION['user']['id'] ?? 0));
        $quotes = $this->dailyQuoteItems($companyId);
        $alerts = $this->dailyControlAlerts($companyId);

        $lines = ["Hoy es {$dateLabel}. Esto tienes para revisar en " . ($company['name'] ?? 'tu empresa') . ':'];
        $lines[] = '- Mensajes pendientes: ' . count($messages) . $this->itemPreview($messages, 'subject');
        $lines[] = '- Tareas pendientes: ' . count($tasks) . $this->itemPreview($tasks, 'title');
        $lines[] = '- Aprobaciones o acciones IA: ' . count($actions) . $this->itemPreview($actions, 'title');
        $lines[] = '- Cotizaciones por revisar/vencer: ' . count($quotes) . $this->itemPreview($quotes, 'quote_number');
        $lines[] = '- Alertas operativas: ' . count($alerts) . $this->itemPreview($alerts, 'title');

        $recommendation = $this->dailyRecommendation($messages, $tasks, $actions, $quotes, $alerts);
        $lines[] = '';
        $lines[] = 'Prioridad sugerida: ' . $recommendation;

        return implode("\n", $lines);
    }

    private function dailyInboxItems(int $companyId): array
    {
        if (!Database::available() || !$this->tableExists('inbox_conversations')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT subject, customer_name, channel, priority
             FROM inbox_conversations
             WHERE company_id = :company_id AND status IN ("new", "open", "pending_approval")
             ORDER BY FIELD(priority, "critical", "high", "medium", "low"), updated_at DESC
             LIMIT 5'
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dailyTaskItems(int $companyId, int $userId): array
    {
        if (!Database::available() || !$this->tableExists('crm_tasks')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT title, priority, due_at
             FROM crm_tasks
             WHERE company_id = :company_id
               AND status = "pending"
               AND (assigned_to = :user_id OR assigned_to IS NULL)
             ORDER BY FIELD(priority, "critical", "high", "medium", "low"), due_at IS NULL, due_at
             LIMIT 5'
        );
        $statement->execute(['company_id' => $companyId, 'user_id' => $userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dailyActionItems(int $companyId, int $userId): array
    {
        if (!Database::available() || !$this->tableExists('action_center_items')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT title, priority, risk_level
             FROM action_center_items
             WHERE company_id = :company_id
               AND status IN ("pending", "approved", "failed")
               AND (assigned_to = :user_id OR requested_by = :user_id OR assigned_to IS NULL)
             ORDER BY FIELD(priority, "critical", "high", "medium", "low"), id DESC
             LIMIT 5'
        );
        $statement->execute(['company_id' => $companyId, 'user_id' => $userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dailyQuoteItems(int $companyId): array
    {
        if (!Database::available() || !$this->tableExists('quotes')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT quote_number, customer_name, valid_until
             FROM quotes
             WHERE company_id = :company_id
               AND status IN ("draft", "sent")
               AND (valid_until IS NULL OR valid_until <= DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY))
             ORDER BY valid_until IS NULL, valid_until, id DESC
             LIMIT 5'
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dailyControlAlerts(int $companyId): array
    {
        if (!Database::available() || !$this->tableExists('business_control_alerts')) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT title, severity
             FROM business_control_alerts
             WHERE company_id = :company_id AND status IN ("open", "reviewing")
             ORDER BY FIELD(severity, "critical", "high", "medium", "low"), id DESC
             LIMIT 5'
        );
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dailyRecommendation(array $messages, array $tasks, array $actions, array $quotes, array $alerts): string
    {
        if ($messages) {
            return 'parte por Omnicanal; hay mensajes reales esperando respuesta.';
        }
        if ($actions) {
            return 'revisa Aprobaciones para destrabar acciones preparadas por AsisFly.';
        }
        if ($quotes) {
            return 'revisa Cotizaciones antes de que pierdan vigencia.';
        }
        if ($alerts) {
            return 'entra a Controles para cerrar alertas operativas.';
        }
        if ($tasks) {
            return 'abre Tareas y completa los pendientes asignados.';
        }

        return 'no veo pendientes reales ahora. Puedes cargar documentos, sincronizar correos o crear una tarea nueva.';
    }

    private function itemPreview(array $items, string $field): string
    {
        if (!$items) {
            return '.';
        }

        $labels = array_slice(array_map(fn (array $item): string => (string) ($item[$field] ?? ''), $items), 0, 3);
        $labels = array_values(array_filter($labels));
        return $labels ? ' (' . implode('; ', $labels) . ').' : '.';
    }

    private function spanishDate(DateTimeImmutable $date): string
    {
        $months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
    }

    private function normalizeText(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower(trim($text), 'UTF-8') : strtolower(trim($text));
        return str_replace(['?', '¿', '.', ',', '!', '¡'], '', $text);
    }

    private function chatHistoryForAi(array $history): array
    {
        return array_slice($history, -8);
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
        } catch (Throwable) {
            return $cache[$table] = false;
        }
    }

    private function tryCreateControlFromPrompt(string $prompt): ?array
    {
        $normalized = strtolower($prompt);
        $looksLikeControl = str_contains($normalized, 'control de')
            || str_contains($normalized, 'controlar')
            || str_contains($normalized, 'llevaremos el control')
            || str_contains($normalized, 'llevar el control');

        if (!$looksLikeControl) {
            return null;
        }

        $toolRegistry = new AiToolRegistry();
        $decision = $toolRegistry->authorize($this->companyId(), $_SESSION['user'] ?? [], 'controls.create');
        if (!$decision['allowed']) {
            $reason = (string) ($decision['reason'] ?? 'No autorizado.');
            $toolRegistry->audit($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), 'controls.create', 'blocked', $reason, [
                'prompt_preview' => substr($prompt, 0, 180),
            ]);

            return [
                'role' => 'assistant',
                'content' => 'No tengo autorizacion para crear controles en esta empresa. ' . $reason . ' Pide al Dueno de empresa o Superadmin revisar Marketplace y permisos.',
                'brain' => 'Cerebro Ejecutivo',
                'module' => 'Controles',
                'status' => 'blocked',
            ];
        }

        $category = 'custom';
        $name = 'Control operativo';
        if (str_contains($normalized, 'gasto')) {
            $name = 'Control de gastos';
            $category = 'financial';
        } elseif (str_contains($normalized, 'pago')) {
            $name = 'Control de pagos pendientes';
            $category = 'administrative';
        } elseif (str_contains($normalized, 'inventario') || str_contains($normalized, 'stock')) {
            $name = 'Control de inventario';
            $category = 'inventory';
        } elseif (str_contains($normalized, 'cliente')) {
            $name = 'Control de clientes';
            $category = 'commercial';
        } elseif (str_contains($normalized, 'obra') || str_contains($normalized, 'operacion')) {
            $name = 'Control operacional';
            $category = 'operations';
        }

        $id = (new ControlRepository())->create($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), [
            'name' => $name,
            'category' => $category,
            'frequency' => 'weekly',
            'source_type' => 'mixed',
            'objective' => 'Proceso creado desde el chat para ordenar datos, generar alertas, tareas y reportes sobre: ' . $prompt,
        ], (string) ($this->currentCompany()['currency'] ?? 'CLP'));

        if ($id <= 0) {
            return null;
        }

        $toolRegistry->audit($this->companyId(), (int) ($_SESSION['user']['id'] ?? 0), 'controls.create', 'executed', null, [
            'control_id' => $id,
            'category' => $category,
        ]);

        return [
            'role' => 'assistant',
            'content' => 'Perfecto. Cree "' . $name . '" como un control vivo de la empresa. Ya puedes cargar entradas, revisar reglas y ver alertas en /controls?control_id=' . $id,
            'brain' => 'Cerebro Ejecutivo',
            'module' => 'Controles',
            'status' => 'created',
        ];
    }
}
