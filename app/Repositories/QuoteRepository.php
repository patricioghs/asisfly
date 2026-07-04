<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\QuotePdfGenerator;
use PDO;

final class QuoteRepository
{
    public function dashboard(int $companyId, string $currency, array $filters = []): array
    {
        return [
            'metrics' => $this->metrics($companyId, $currency),
            'quotes' => $this->quotes($companyId, $currency, $filters),
            'products' => $this->products($companyId),
            'customers' => $this->customers($companyId),
        ];
    }

    public function quotes(int $companyId, string $currency, array $filters = []): array
    {
        if (!Database::available()) {
            return [];
        }

        $where = ['q.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if (!empty($filters['q'])) {
            $where[] = '(q.quote_number LIKE :q OR q.customer_name LIKE :q OR c.name LIKE :q OR i.description LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['status'])) {
            $where[] = 'q.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql = 'SELECT q.*, COALESCE(c.name, q.customer_name, "Cliente directo") AS client,
                       COALESCE(GROUP_CONCAT(i.description SEPARATOR ", "), "Servicios") AS items
                FROM quotes q
                LEFT JOIN crm_customers c ON c.id = q.customer_id AND c.company_id = q.company_id
                LEFT JOIN quote_items i ON i.quote_id = q.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY q.id
                ORDER BY q.id DESC
                LIMIT 80';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row): array => [
            ...$row,
            'total_label' => $this->money((float) $row['total'], $row['currency'] ?: $currency),
            'pdf_url' => !empty($row['pdf_path']) ? url('/quotes/pdf?id=' . (int) $row['id']) : '',
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function products(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT * FROM quote_products WHERE company_id = :company_id AND is_active = 1 ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function customers(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT id, name, contact_name, email, phone FROM crm_customers WHERE company_id = :company_id ORDER BY name');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProduct(int $companyId, array $input, string $currency): void
    {
        Database::connection()->prepare('INSERT INTO quote_products (company_id, sku, name, description, unit_price, tax_rate, currency) VALUES (:company_id, :sku, :name, :description, :unit_price, :tax_rate, :currency)')->execute([
            'company_id' => $companyId,
            'sku' => trim((string) ($input['sku'] ?? '')) ?: null,
            'name' => trim((string) ($input['name'] ?? 'Servicio')),
            'description' => trim((string) ($input['description'] ?? '')),
            'unit_price' => (float) ($input['unit_price'] ?? 0),
            'tax_rate' => (float) ($input['tax_rate'] ?? 19),
            'currency' => $currency,
        ]);
    }

    public function createQuote(int $companyId, int $userId, array $input, array $company): int
    {
        $currency = $company['currency'] ?? 'CLP';
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $customerId = (int) ($input['customer_id'] ?? 0) ?: null;
        $customerName = trim((string) ($input['client'] ?? 'Cliente directo'));
        $product = $this->product($companyId, (int) ($input['product_id'] ?? 0));
        $description = trim((string) ($input['item'] ?? ($product['name'] ?? 'Servicio')));
        $sku = $product['sku'] ?? null;
        $quantity = max(1, (float) ($input['quantity'] ?? 1));
        $unitPrice = (float) ($input['price'] ?? ($product['unit_price'] ?? 0));
        $discount = max(0, (float) ($input['discount'] ?? 0));
        $taxRate = max(0, (float) ($input['tax'] ?? ($product['tax_rate'] ?? 19)));
        $subtotal = $quantity * $unitPrice;
        $taxable = max(0, $subtotal - $discount);
        $tax = $taxable * ($taxRate / 100);
        $total = $taxable + $tax;
        $number = $this->nextNumber($companyId);
        $validUntil = trim((string) ($input['valid_until'] ?? '')) ?: date('Y-m-d', strtotime('+15 days'));

        $quote = $pdo->prepare('INSERT INTO quotes (company_id, customer_id, quote_number, customer_name, status, subtotal, discount, tax, total, currency, valid_until, notes, terms) VALUES (:company_id, :customer_id, :quote_number, :customer_name, "draft", :subtotal, :discount, :tax, :total, :currency, :valid_until, :notes, :terms)');
        $quote->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'quote_number' => $number,
            'customer_name' => $customerName,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,
            'valid_until' => $validUntil,
            'notes' => trim((string) ($input['notes'] ?? '')),
            'terms' => trim((string) ($input['terms'] ?? 'Pago contra aprobacion. Valores sujetos a disponibilidad.')),
        ]);
        $quoteId = (int) $pdo->lastInsertId();

        $item = $pdo->prepare('INSERT INTO quote_items (quote_id, product_id, sku, description, quantity, unit_price, discount, tax_rate, total) VALUES (:quote_id, :product_id, :sku, :description, :quantity, :unit_price, :discount, :tax_rate, :total)');
        $item->execute([
            'quote_id' => $quoteId,
            'product_id' => $product['id'] ?? null,
            'sku' => $sku,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'total' => $total,
        ]);

        if ($customerId) {
            $opportunityId = $this->ensureOpportunity($companyId, $customerId, $number, $total, $currency);
            $pdo->prepare('UPDATE quotes SET opportunity_id = :opportunity_id WHERE id = :id AND company_id = :company_id')->execute([
                'opportunity_id' => $opportunityId,
                'id' => $quoteId,
                'company_id' => $companyId,
            ]);
            $this->logCrmActivity($companyId, $customerId, $userId, 'quote', 'Cotizacion creada: ' . $number);
        }

        $pdo->commit();
        $this->generatePdf($companyId, $quoteId, $company);

        return $quoteId;
    }

    public function generatePdf(int $companyId, int $quoteId, array $company): ?string
    {
        $quote = $this->quote($companyId, $quoteId);
        if (!$quote) {
            return null;
        }

        $items = $this->items($quoteId);
        $path = (new QuotePdfGenerator())->generate($quote, $items, $company);
        Database::connection()->prepare('UPDATE quotes SET pdf_path = :pdf_path WHERE company_id = :company_id AND id = :id')->execute([
            'pdf_path' => $path,
            'company_id' => $companyId,
            'id' => $quoteId,
        ]);

        return $path;
    }

    public function pdfPath(int $companyId, int $quoteId): ?string
    {
        $quote = $this->quote($companyId, $quoteId);
        if (!$quote || empty($quote['pdf_path'])) {
            return null;
        }

        $path = dirname(__DIR__, 2) . '/storage/' . $quote['pdf_path'];
        return is_file($path) ? $path : null;
    }

    public function requestSend(int $companyId, int $quoteId, int $userId, string $channel): void
    {
        $quote = $this->quote($companyId, $quoteId);
        if (!$quote) {
            return;
        }

        (new ActionRepository())->create($companyId, $userId, [
            'title' => 'Enviar cotizacion ' . $quote['quote_number'],
            'description' => 'Enviar propuesta profesional a ' . ($quote['client'] ?? $quote['customer_name']) . ' por ' . $channel . '.',
            'module' => 'Cotizaciones',
            'brain' => 'Cerebro Comercial',
            'action_type' => 'send_quote',
            'priority' => 'high',
            'risk_level' => 'medium',
            'payload' => ['quote_id' => $quoteId, 'channel' => $channel, 'quote_number' => $quote['quote_number']],
            'requires_approval' => true,
        ]);
    }

    public function markSent(int $companyId, int $quoteId, string $channel): string
    {
        $quote = $this->quote($companyId, $quoteId);
        if (!$quote) {
            return 'Cotizacion no encontrada.';
        }

        Database::connection()->prepare('UPDATE quotes SET status = "sent", sent_channel = :channel, sent_to = :sent_to, sent_at = CURRENT_TIMESTAMP WHERE company_id = :company_id AND id = :id')->execute([
            'channel' => $channel,
            'sent_to' => $quote['customer_email'] ?: $quote['customer_phone'] ?: $quote['customer_name'],
            'company_id' => $companyId,
            'id' => $quoteId,
        ]);

        return 'Cotizacion marcada como enviada por ' . $channel . '.';
    }

    public function transition(int $companyId, int $quoteId, int $userId, string $status): void
    {
        if (!in_array($status, ['accepted', 'rejected', 'expired'], true)) {
            return;
        }

        $acceptedAt = $status === 'accepted' ? ', accepted_at = CURRENT_TIMESTAMP' : '';
        Database::connection()->prepare("UPDATE quotes SET status = :status {$acceptedAt} WHERE company_id = :company_id AND id = :id")->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $quoteId,
        ]);

        $quote = $this->quote($companyId, $quoteId);
        if ($quote && $quote['customer_id']) {
            if ($status === 'accepted' && $quote['opportunity_id']) {
                Database::connection()->prepare('UPDATE crm_opportunities SET stage = "ganado", probability = 100 WHERE company_id = :company_id AND id = :id')->execute([
                    'company_id' => $companyId,
                    'id' => $quote['opportunity_id'],
                ]);
            }
            $this->logCrmActivity($companyId, (int) $quote['customer_id'], $userId, 'quote_' . $status, 'Cotizacion ' . $quote['quote_number'] . ' marcada como ' . $status . '.');
        }
    }

    private function metrics(int $companyId, string $currency): array
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) AS total, SUM(status = "draft") AS drafts, SUM(status = "sent") AS sent, SUM(status = "accepted") AS accepted, COALESCE(SUM(total), 0) AS amount FROM quotes WHERE company_id = :company_id');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            ['label' => 'Cotizaciones', 'value' => (string) (int) ($row['total'] ?? 0), 'hint' => 'Total'],
            ['label' => 'Borradores', 'value' => (string) (int) ($row['drafts'] ?? 0), 'hint' => 'Por revisar'],
            ['label' => 'Enviadas', 'value' => (string) (int) ($row['sent'] ?? 0), 'hint' => 'Seguimiento'],
            ['label' => 'Monto total', 'value' => $this->money((float) ($row['amount'] ?? 0), $currency), 'hint' => 'Pipeline'],
        ];
    }

    private function quote(int $companyId, int $quoteId): ?array
    {
        $sql = 'SELECT q.*, COALESCE(c.name, q.customer_name, "Cliente directo") AS client, c.email AS customer_email, c.phone AS customer_phone
                FROM quotes q
                LEFT JOIN crm_customers c ON c.id = q.customer_id AND c.company_id = q.company_id
                WHERE q.company_id = :company_id AND q.id = :id
                LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId, 'id' => $quoteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function items(int $quoteId): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM quote_items WHERE quote_id = :quote_id ORDER BY id');
        $statement->execute(['quote_id' => $quoteId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function product(int $companyId, int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        $statement = Database::connection()->prepare('SELECT * FROM quote_products WHERE company_id = :company_id AND id = :id LIMIT 1');
        $statement->execute(['company_id' => $companyId, 'id' => $productId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'COT-' . date('Ym') . '-';
        $statement = Database::connection()->prepare('SELECT COUNT(*) + 1 FROM quotes WHERE company_id = :company_id AND quote_number LIKE :prefix');
        $statement->execute(['company_id' => $companyId, 'prefix' => $prefix . '%']);
        return $prefix . str_pad((string) $statement->fetchColumn(), 4, '0', STR_PAD_LEFT);
    }

    private function ensureOpportunity(int $companyId, int $customerId, string $number, float $total, string $currency): int
    {
        $statement = Database::connection()->prepare('INSERT INTO crm_opportunities (company_id, customer_id, title, stage, amount, currency, probability, source) VALUES (:company_id, :customer_id, :title, "propuesta", :amount, :currency, 70, "Cotizacion")');
        $statement->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'title' => 'Cotizacion ' . $number,
            'amount' => $total,
            'currency' => $currency,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    private function logCrmActivity(int $companyId, int $customerId, int $userId, string $type, string $summary): void
    {
        Database::connection()->prepare('INSERT INTO crm_activities (company_id, customer_id, user_id, activity_type, summary) VALUES (:company_id, :customer_id, :user_id, :activity_type, :summary)')->execute([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'user_id' => $userId ?: null,
            'activity_type' => $type,
            'summary' => $summary,
        ]);
    }

    private function money(float $amount, string $currency): string
    {
        return $currency . ' ' . number_format($amount, 0, ',', '.');
    }
}
