<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\DemoRepository;
use App\Services\DocumentTextExtractor;
use PDO;

final class TenantRepository
{
    public function __construct(private readonly DemoRepository $fallback = new DemoRepository())
    {
    }

    public function databaseReady(): bool
    {
        return Database::available();
    }

    public function dashboard(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return $this->fallback->dashboard();
        }

        $counts = [
            'Clientes contactados' => $this->count('crm_customers', $companyId),
            'Documentos analizados' => $this->count('documents', $companyId),
            'Cotizaciones' => $this->count('quotes', $companyId),
            'Integraciones' => $this->count('integrations', $companyId),
            'Mensajes respondidos' => $this->tableExists('inbox_messages') ? $this->countWhere('inbox_messages', $companyId, "direction = 'out'") : 0,
            'Tareas creadas' => $this->tableExists('crm_tasks') ? $this->countWhere('crm_tasks', $companyId, "status = 'pending'") : 0,
            'Reuniones agendadas' => 0,
        ];
        $tokens = (int) $this->scalar('SELECT COALESCE(SUM(prompt_tokens + completion_tokens), 0) FROM ai_usage_logs WHERE company_id = :company_id', $companyId);

        return [
            ['label' => 'Correos revisados', 'value' => 0, 'trend' => 'Conector simulado'],
            ['label' => 'Mensajes respondidos', 'value' => $counts['Mensajes respondidos'], 'trend' => 'Omnicanal'],
            ['label' => 'Reuniones agendadas', 'value' => $counts['Reuniones agendadas'], 'trend' => 'Calendario'],
            ['label' => 'Tareas creadas', 'value' => $counts['Tareas creadas'], 'trend' => 'Pendientes'],
            ['label' => 'Clientes contactados', 'value' => $counts['Clientes contactados'], 'trend' => 'CRM'],
            ['label' => 'Oportunidades detectadas', 'value' => $counts['Clientes contactados'], 'trend' => 'Pipeline'],
            ['label' => 'Documentos analizados', 'value' => $counts['Documentos analizados'], 'trend' => 'Memoria'],
            ['label' => 'Consumo IA', 'value' => number_format($tokens) . ' tokens', 'trend' => 'Auditado'],
        ];
    }

    public function alerts(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return $this->fallback->alerts();
        }

        $pendingDocs = $this->countWhere('documents', $companyId, "processing_status IN ('pending','processing')");
        $sandbox = $this->countWhere('integrations', $companyId, "status IN ('simulated','sandbox')");

        return array_values(array_filter([
            $pendingDocs > 0 ? "$pendingDocs documentos siguen pendientes de procesamiento." : null,
            $sandbox > 0 ? "$sandbox integraciones estan en modo simulado o sandbox." : null,
            'Las acciones sensibles siguen requiriendo aprobacion humana.',
        ]));
    }

    public function assistant(int $companyId): array
    {
        $defaults = [
            'name' => 'AsisFly',
            'tone' => 'Cercano y ejecutivo',
            'language' => 'Espanol latino',
            'country' => 'Chile',
            'currency' => 'CLP',
            'work_hours' => 'Lunes a viernes, 09:00 a 18:00',
            'signature' => 'Equipo AsisFly',
            'rules' => 'Pedir aprobacion antes de enviar correos, cotizaciones o mensajes comerciales.',
            'forbidden_words' => '',
            'required_phrases' => 'Quedo atento/a',
            'human_escalation' => 'Reclamos, descuentos especiales, riesgos legales o clientes molestos.',
        ];

        if (!$this->databaseReady()) {
            return $_SESSION['assistant'] ?? $defaults;
        }

        $statement = Database::connection()->prepare('SELECT * FROM assistant_settings WHERE company_id = :company_id LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults;
        }

        return [
            'name' => $row['assistant_name'],
            'tone' => $row['tone'],
            'language' => $row['language'],
            'country' => $row['country'],
            'currency' => $row['currency'],
            'work_hours' => $row['work_hours'],
            'signature' => $row['signature'] ?? '',
            'rules' => $row['rules'] ?? '',
            'forbidden_words' => $row['forbidden_words'] ?? '',
            'required_phrases' => $row['required_phrases'] ?? '',
            'human_escalation' => $row['human_escalation'] ?? '',
        ];
    }

    public function saveAssistant(int $companyId, array $input): void
    {
        if (!$this->databaseReady()) {
            $_SESSION['assistant'] = $input;
            return;
        }

        $sql = 'INSERT INTO assistant_settings (company_id, assistant_name, tone, language, country, currency, work_hours, signature, rules, forbidden_words, required_phrases, human_escalation)
                VALUES (:company_id, :name, :tone, :language, :country, :currency, :work_hours, :signature, :rules, :forbidden_words, :required_phrases, :human_escalation)
                ON DUPLICATE KEY UPDATE assistant_name = VALUES(assistant_name), tone = VALUES(tone), language = VALUES(language), country = VALUES(country), currency = VALUES(currency), work_hours = VALUES(work_hours), signature = VALUES(signature), rules = VALUES(rules), forbidden_words = VALUES(forbidden_words), required_phrases = VALUES(required_phrases), human_escalation = VALUES(human_escalation)';
        Database::connection()->prepare($sql)->execute([
            'company_id' => $companyId,
            'name' => $input['name'] ?? 'AsisFly',
            'tone' => $input['tone'] ?? '',
            'language' => $input['language'] ?? '',
            'country' => $input['country'] ?? '',
            'currency' => $input['currency'] ?? '',
            'work_hours' => $input['work_hours'] ?? '',
            'signature' => $input['signature'] ?? '',
            'rules' => $input['rules'] ?? '',
            'forbidden_words' => $input['forbidden_words'] ?? '',
            'required_phrases' => $input['required_phrases'] ?? '',
            'human_escalation' => $input['human_escalation'] ?? '',
        ]);
    }

    public function documents(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return $_SESSION['documents'] ?? [];
        }

        $statement = Database::connection()->prepare('SELECT d.id, d.original_name AS name, d.document_type AS type, d.processing_status AS status, d.summary, COUNT(m.id) AS memory_chunks
            FROM documents d
            LEFT JOIN memory_entries m ON m.company_id = d.company_id AND m.source_type = "document" AND m.source_id = d.id
            WHERE d.company_id = :company_id
            GROUP BY d.id
            ORDER BY d.id DESC');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDocument(int $companyId, int $userId, array $file, string $type): void
    {
        if (!$this->databaseReady()) {
            $_SESSION['documents'][] = [
                'name' => $file['name'],
                'type' => $type,
                'status' => 'Procesado en modo demo',
                'summary' => 'Contenido disponible para memoria empresarial y busqueda semantica en fase siguiente.',
            ];
            return;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
        $relativePath = 'uploads/company_' . $companyId . '/' . date('YmdHis') . '_' . $safeName;
        $absoluteDir = dirname(__DIR__, 2) . '/storage/uploads/company_' . $companyId;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }
        $absolutePath = dirname(__DIR__, 2) . '/storage/' . $relativePath;
        if (is_uploaded_file($file['tmp_name'])) {
            move_uploaded_file($file['tmp_name'], $absolutePath);
        }

        $statement = Database::connection()->prepare('INSERT INTO documents (company_id, uploaded_by, original_name, mime_type, storage_path, document_type, processing_status, summary) VALUES (:company_id, :uploaded_by, :original_name, :mime_type, :storage_path, :document_type, :processing_status, :summary)');
        $statement->execute([
            'company_id' => $companyId,
            'uploaded_by' => $userId,
            'original_name' => $file['name'],
            'mime_type' => $file['type'] ?? null,
            'storage_path' => $relativePath,
            'document_type' => $type,
            'processing_status' => 'processed',
            'summary' => 'Archivo guardado por empresa. Extraccion de texto en proceso.',
        ]);
        $documentId = (int) Database::connection()->lastInsertId();

        $extraction = (new DocumentTextExtractor())->extract($absolutePath, $file['name'], $file['type'] ?? null);
        $chunks = (new MemoryRepository())->indexDocument($companyId, $documentId, $file['name'], $extraction['text']);
        $summary = $extraction['summary'] . ($chunks > 0 ? " Fragmentos indexados: {$chunks}." : ' No se crearon fragmentos de memoria.');

        $update = Database::connection()->prepare('UPDATE documents SET processing_status = :status, summary = :summary WHERE id = :id AND company_id = :company_id');
        $update->execute([
            'status' => $chunks > 0 ? 'processed' : $extraction['status'],
            'summary' => $summary,
            'id' => $documentId,
            'company_id' => $companyId,
        ]);
    }

    public function customers(int $companyId, string $currency): array
    {
        if (!$this->databaseReady()) {
            return $this->fallback->customers();
        }

        $statement = Database::connection()->prepare('SELECT name, contact_name AS contact, stage, estimated_value, currency FROM crm_customers WHERE company_id = :company_id ORDER BY id DESC');
        $statement->execute(['company_id' => $companyId]);
        return array_map(fn (array $row) => [
            'name' => $row['name'],
            'contact' => $row['contact'],
            'stage' => $row['stage'],
            'value' => $this->money((float) $row['estimated_value'], $row['currency'] ?: $currency),
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addCustomer(int $companyId, array $input, string $currency): void
    {
        if (!$this->databaseReady()) {
            $_SESSION['customers'][] = [
                'name' => $input['company'] ?? '',
                'contact' => $input['contact'] ?? '',
                'stage' => $input['stage'] ?? 'Nuevo',
                'value' => $input['value'] ?? '$0',
            ];
            return;
        }

        $value = (float) preg_replace('/[^0-9.]/', '', $input['value'] ?? '0');
        $statement = Database::connection()->prepare('INSERT INTO crm_customers (company_id, name, contact_name, stage, estimated_value, currency) VALUES (:company_id, :name, :contact_name, :stage, :estimated_value, :currency)');
        $statement->execute([
            'company_id' => $companyId,
            'name' => $input['company'] ?? '',
            'contact_name' => $input['contact'] ?? '',
            'stage' => $input['stage'] ?? 'Nuevo',
            'estimated_value' => $value,
            'currency' => $currency,
        ]);
    }

    public function quotes(int $companyId, string $currency): array
    {
        if (!$this->databaseReady()) {
            return $this->fallback->quotes();
        }

        $sql = 'SELECT q.quote_number, q.status, q.total, q.currency, COALESCE(c.name, q.customer_name, "Cliente directo") AS client,
                       COALESCE(GROUP_CONCAT(i.description SEPARATOR ", "), "Servicios") AS items
                FROM quotes q
                LEFT JOIN crm_customers c ON c.id = q.customer_id AND c.company_id = q.company_id
                LEFT JOIN quote_items i ON i.quote_id = q.id
                WHERE q.company_id = :company_id
                GROUP BY q.id
                ORDER BY q.id DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row) => [
            'client' => $row['client'],
            'items' => $row['items'],
            'total' => $this->money((float) $row['total'], $row['currency'] ?: $currency),
            'status' => $row['status'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addQuote(int $companyId, array $input, string $currency): void
    {
        $quantity = (float) ($input['quantity'] ?? 1);
        $price = (float) ($input['price'] ?? 0);
        $discount = (float) ($input['discount'] ?? 0);
        $taxRate = (float) ($input['tax'] ?? 19);
        $subtotal = max(0, $quantity * $price);
        $tax = max(0, ($subtotal - $discount) * ($taxRate / 100));
        $total = max(0, $subtotal - $discount + $tax);

        if (!$this->databaseReady()) {
            $_SESSION['quotes'][] = [
                'client' => $input['client'] ?? '',
                'items' => $input['item'] ?? '',
                'total' => $this->money($total, $currency),
                'status' => 'Borrador',
            ];
            return;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $number = 'Q-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $quote = $pdo->prepare('INSERT INTO quotes (company_id, quote_number, customer_name, status, subtotal, discount, tax, total, currency) VALUES (:company_id, :quote_number, :customer_name, :status, :subtotal, :discount, :tax, :total, :currency)');
        $quote->execute([
            'company_id' => $companyId,
            'quote_number' => $number,
            'customer_name' => $input['client'] ?? 'Cliente directo',
            'status' => 'draft',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,
        ]);
        $quoteId = (int) $pdo->lastInsertId();
        $item = $pdo->prepare('INSERT INTO quote_items (quote_id, description, quantity, unit_price, discount, tax_rate, total) VALUES (:quote_id, :description, :quantity, :unit_price, :discount, :tax_rate, :total)');
        $item->execute([
            'quote_id' => $quoteId,
            'description' => $input['item'] ?? 'Servicio',
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'total' => $total,
        ]);
        $pdo->commit();
    }

    public function integrations(int $companyId): array
    {
        $labels = [
            'gmail' => ['Gmail', 'Correos, borradores, etiquetas'],
            'outlook' => ['Outlook', 'Correos y calendario Microsoft'],
            'google_calendar' => ['Google Calendar', 'Disponibilidad y eventos'],
            'whatsapp_business' => ['WhatsApp Business API', 'Mensajes y derivacion humana'],
            'instagram' => ['Instagram', 'DM, FAQ y oportunidades'],
            'facebook' => ['Facebook Messenger', 'Mensajeria y CRM'],
            'telegram' => ['Telegram', 'Soporte conversacional'],
        ];

        if (!$this->databaseReady()) {
            return $this->fallback->integrations();
        }

        $statement = Database::connection()->prepare('SELECT provider, status FROM integrations WHERE company_id = :company_id ORDER BY provider');
        $statement->execute(['company_id' => $companyId]);

        $statuses = [
            'simulated' => 'Pendiente',
            'sandbox' => 'Prueba',
            'connected' => 'Conectada',
            'disabled' => 'Pausada',
            'error' => 'Error',
        ];

        return array_map(function (array $row) use ($labels, $statuses): array {
            [$name, $scope] = $labels[$row['provider']] ?? [ucfirst($row['provider']), 'Conector externo'];
            return [
                'provider' => $row['provider'],
                'name' => $name,
                'status' => $statuses[$row['status']] ?? 'Pendiente',
                'scope' => $scope,
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function aiUsage(int $companyId): array
    {
        if (!$this->databaseReady()) {
            return $this->fallback->aiUsage();
        }

        $statement = Database::connection()->prepare('SELECT provider, model, module, prompt_tokens + completion_tokens AS tokens, estimated_cost, status, error_message FROM ai_usage_logs WHERE company_id = :company_id ORDER BY id DESC LIMIT 20');
        $statement->execute(['company_id' => $companyId]);

        return array_map(fn (array $row) => [
            'provider' => $row['provider'],
            'model' => $row['model'],
            'tokens' => (int) $row['tokens'],
            'cost' => 'USD ' . number_format((float) $row['estimated_cost'], 4),
            'module' => $row['module'],
            'status' => $row['status'] ?? 'success',
            'error' => $row['error_message'] ?? '',
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function logAiUsage(int $companyId, ?int $userId, string $module, string $provider, string $model, int $promptTokens, int $completionTokens, float $cost, array $trace = []): void
    {
        if (!$this->databaseReady()) {
            $_SESSION['ai_usage'][] = [
                'provider' => $provider,
                'model' => $model,
                'tokens' => $promptTokens + $completionTokens,
                'cost' => 'USD ' . number_format($cost, 4),
                'module' => $module,
                'status' => $trace['status'] ?? 'success',
                'error' => $trace['error_message'] ?? '',
            ];
            return;
        }

        $statement = Database::connection()->prepare('INSERT INTO ai_usage_logs (company_id, user_id, module, prompt_text, response_text, provider, model, prompt_tokens, completion_tokens, estimated_cost, status, error_message, request_id, metadata_json) VALUES (:company_id, :user_id, :module, :prompt_text, :response_text, :provider, :model, :prompt_tokens, :completion_tokens, :estimated_cost, :status, :error_message, :request_id, :metadata_json)');
        $statement->execute([
            'company_id' => $companyId,
            'user_id' => $userId ?: null,
            'module' => $module,
            'prompt_text' => $trace['prompt_text'] ?? null,
            'response_text' => $trace['response_text'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'estimated_cost' => $cost,
            'status' => $trace['status'] ?? 'success',
            'error_message' => $trace['error_message'] ?? null,
            'request_id' => $trace['request_id'] ?? null,
            'metadata_json' => isset($trace['metadata']) ? json_encode($trace['metadata'], JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public function companies(): array
    {
        if (!$this->databaseReady()) {
            return [
                ['name' => 'Andes Demo SpA', 'plan' => 'Business', 'users' => 8, 'tokens' => '82k', 'status' => 'Activo'],
                ['name' => 'Brasil Norte Ltda', 'plan' => 'Pro', 'users' => 4, 'tokens' => '39k', 'status' => 'Activo'],
                ['name' => 'Mexico Retail SA', 'plan' => 'Starter', 'users' => 2, 'tokens' => '11k', 'status' => 'Trial'],
            ];
        }

        $sql = 'SELECT c.name, COALESCE(p.name, "Sin plan") AS plan, c.status,
                       COUNT(DISTINCT u.id) AS users,
                       COALESCE(SUM(a.prompt_tokens + a.completion_tokens), 0) AS tokens
                FROM companies c
                LEFT JOIN plans p ON p.id = c.plan_id
                LEFT JOIN users u ON u.company_id = c.id
                LEFT JOIN ai_usage_logs a ON a.company_id = c.id
                GROUP BY c.id
                ORDER BY c.id DESC';
        return array_map(fn (array $row) => [
            'name' => $row['name'],
            'plan' => $row['plan'],
            'users' => (int) $row['users'],
            'tokens' => number_format((int) $row['tokens']),
            'status' => ucfirst($row['status']),
        ], Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    private function count(string $table, int $companyId): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE company_id = :company_id", $companyId);
    }

    private function countWhere(string $table, int $companyId, string $where): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE company_id = :company_id AND {$where}", $companyId);
    }

    private function scalar(string $sql, int $companyId): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchColumn();
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

    private function money(float $amount, string $currency): string
    {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }
}
