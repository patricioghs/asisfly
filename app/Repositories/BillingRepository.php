<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class BillingRepository
{
    public function overview(int $companyId): array
    {
        return [
            'current' => $this->current($companyId),
            'plans' => $this->plans(),
            'usage' => $this->usage($companyId),
            'events' => $this->events($companyId),
        ];
    }

    public function current(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $sql = 'SELECT c.*, p.name AS plan_name, p.monthly_price, p.limits_json, s.status AS subscription_status_real, s.current_period_end
                FROM companies c
                LEFT JOIN plans p ON p.id = c.plan_id
                LEFT JOIN subscriptions s ON s.company_id = c.id AND s.plan_id = c.plan_id
                WHERE c.id = :company_id
                ORDER BY s.id DESC
                LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $limits = json_decode((string) ($row['limits_json'] ?? '{}'), true) ?: [];

        return [
            ...$row,
            'limits' => $limits,
            'provider_recommended' => $this->providerFor((string) ($row['country'] ?? 'Chile')),
        ];
    }

    public function plans(): array
    {
        if (!Database::available()) {
            return [];
        }

        $rows = Database::connection()->query('SELECT * FROM plans ORDER BY FIELD(name, "Starter", "Pro", "Business", "Enterprise"), monthly_price')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $row): array => [
            ...$row,
            'limits' => json_decode((string) $row['limits_json'], true) ?: [],
        ], $rows);
    }

    public function usage(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $counts = [
            'users' => $this->scalar('SELECT COUNT(*) FROM users WHERE company_id = :company_id', $companyId),
            'documents' => $this->scalar('SELECT COUNT(*) FROM documents WHERE company_id = :company_id', $companyId),
            'integrations' => $this->scalar('SELECT COUNT(*) FROM integrations WHERE company_id = :company_id AND status IN ("sandbox","connected","simulated")', $companyId),
            'ai_messages' => $this->scalar('SELECT COUNT(*) FROM ai_usage_logs WHERE company_id = :company_id AND created_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")', $companyId),
            'tokens' => $this->scalar('SELECT COALESCE(SUM(prompt_tokens + completion_tokens), 0) FROM ai_usage_logs WHERE company_id = :company_id AND created_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")', $companyId),
            'automations' => $this->scalar('SELECT COUNT(*) FROM automation_rules WHERE company_id = :company_id AND is_active = 1', $companyId),
        ];

        $limits = $this->current($companyId)['limits'] ?? [];
        $usage = [];
        foreach ($counts as $key => $value) {
            $limit = (int) ($limits[$key] ?? -1);
            $usage[$key] = [
                'used' => (int) $value,
                'limit' => $limit,
                'percent' => $limit > 0 ? min(100, (int) round(((int) $value / $limit) * 100)) : 0,
                'blocked' => $limit > -1 && (int) $value >= $limit,
            ];
        }

        return $usage;
    }

    public function canUse(int $companyId, string $feature, int $increment = 1): array
    {
        $usage = $this->usage($companyId);
        $row = $usage[$feature] ?? null;
        if (!$row || (int) $row['limit'] === -1) {
            return ['allowed' => true, 'reason' => null];
        }

        if (((int) $row['used'] + $increment) > (int) $row['limit']) {
            return ['allowed' => false, 'reason' => 'Limite del plan alcanzado para ' . $feature . '.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function changePlan(int $companyId, int $userId, string $planName): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $current = $this->current($companyId);
        $statement = $pdo->prepare('SELECT * FROM plans WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $planName]);
        $plan = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            $pdo->rollBack();
            return;
        }

        $provider = $this->providerFor((string) ($current['country'] ?? 'Chile'));

        $pdo->prepare('UPDATE companies SET plan_id = :plan_id, subscription_status = "active", billing_provider = :provider, status = "active" WHERE id = :company_id')->execute([
            'plan_id' => (int) $plan['id'],
            'provider' => $provider,
            'company_id' => $companyId,
        ]);
        $pdo->prepare('INSERT INTO subscriptions (company_id, plan_id, provider, status, current_period_start, current_period_end, trial_ends_at) VALUES (:company_id, :plan_id, :provider, "active", CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), NULL)')->execute([
            'company_id' => $companyId,
            'plan_id' => (int) $plan['id'],
            'provider' => $provider,
        ]);
        $pdo->prepare('INSERT INTO billing_events (company_id, user_id, event_type, provider, plan_from, plan_to, amount, currency, metadata_json) VALUES (:company_id, :user_id, :event_type, :provider, :plan_from, :plan_to, :amount, :currency, :metadata_json)')->execute([
            'company_id' => $companyId,
            'user_id' => $userId ?: null,
            'event_type' => 'plan_changed',
            'provider' => $provider,
            'plan_from' => $current['plan_name'] ?? null,
            'plan_to' => $plan['name'],
            'amount' => (float) $plan['monthly_price'],
            'currency' => $current['currency'] ?? 'USD',
            'metadata_json' => json_encode(['mode' => 'simulated_checkout'], JSON_UNESCAPED_UNICODE),
        ]);

        $pdo->commit();
    }

    public function events(int $companyId): array
    {
        if (!Database::available()) {
            return [];
        }

        $statement = Database::connection()->prepare('SELECT e.*, u.name AS user_name FROM billing_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.company_id = :company_id ORDER BY e.id DESC LIMIT 12');
        $statement->execute(['company_id' => $companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function providerFor(string $country): string
    {
        $country = strtolower($country);
        if (str_contains($country, 'chile')) {
            return 'webpay';
        }
        if (str_contains($country, 'brasil') || str_contains($country, 'mexico') || str_contains($country, 'argentina') || str_contains($country, 'colombia')) {
            return 'mercadopago';
        }

        return 'stripe';
    }

    private function scalar(string $sql, int $companyId): int
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        return (int) $statement->fetchColumn();
    }
}
