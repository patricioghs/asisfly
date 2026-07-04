<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\SecretVault;
use PDO;
use Throwable;

final class AiProviderRepository
{
    public function settings(int $companyId): array
    {
        $defaults = [
            'provider' => env('AI_DEFAULT_PROVIDER', 'simulated'),
            'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
            'fallback_provider' => 'simulated',
            'fallback_model' => 'asisfly-demo-latam',
            'api_key_env' => 'OPENAI_API_KEY',
            'encrypted_api_key' => null,
            'api_key_last4' => null,
            'api_key_updated_at' => null,
            'has_managed_api_key' => false,
            'api_key_source' => trim((string) env('OPENAI_API_KEY', '')) !== '' ? 'env' : 'missing',
            'temperature' => 0.4,
            'monthly_token_limit' => 500000,
            'monthly_cost_limit' => 25.0,
            'is_enabled' => true,
            'monthly_tokens_used' => 0,
            'monthly_cost_used' => 0.0,
        ];

        if (!Database::available()) {
            return $_SESSION['ai_settings'] ?? $defaults;
        }

        $statement = Database::connection()->prepare('SELECT * FROM ai_provider_settings WHERE company_id = :company_id LIMIT 1');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults + $this->usageThisMonth($companyId);
        }

        return [
            'provider' => $row['provider'],
            'model' => $row['model'],
            'fallback_provider' => $row['fallback_provider'],
            'fallback_model' => $row['fallback_model'],
            'api_key_env' => $row['api_key_env'] ?: 'OPENAI_API_KEY',
            'encrypted_api_key' => $row['encrypted_api_key'] ?? null,
            'api_key_last4' => $row['api_key_last4'] ?? null,
            'api_key_updated_at' => $row['api_key_updated_at'] ?? null,
            'has_managed_api_key' => !empty($row['encrypted_api_key']),
            'api_key_source' => !empty($row['encrypted_api_key']) ? 'managed' : (trim((string) env($row['api_key_env'] ?: 'OPENAI_API_KEY', '')) !== '' ? 'env' : 'missing'),
            'temperature' => (float) $row['temperature'],
            'monthly_token_limit' => (int) $row['monthly_token_limit'],
            'monthly_cost_limit' => (float) $row['monthly_cost_limit'],
            'is_enabled' => (bool) $row['is_enabled'],
            ...$this->usageThisMonth($companyId),
        ];
    }

    public function save(int $companyId, array $input): void
    {
        $settings = [
            'provider' => $this->cleanProvider($input['provider'] ?? 'simulated'),
            'model' => trim((string) ($input['model'] ?? 'gpt-4.1-mini')),
            'fallback_provider' => $this->cleanProvider($input['fallback_provider'] ?? 'simulated'),
            'fallback_model' => trim((string) ($input['fallback_model'] ?? 'asisfly-demo-latam')),
            'api_key_env' => preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) ($input['api_key_env'] ?? 'OPENAI_API_KEY'))),
            'temperature' => max(0, min(2, (float) ($input['temperature'] ?? 0.4))),
            'monthly_token_limit' => (int) ($input['monthly_token_limit'] ?? 500000),
            'monthly_cost_limit' => (float) ($input['monthly_cost_limit'] ?? 25),
            'is_enabled' => !empty($input['is_enabled']),
        ];

        if (!Database::available()) {
            $_SESSION['ai_settings'] = $settings;
            return;
        }

        $sql = 'INSERT INTO ai_provider_settings
                (company_id, provider, model, fallback_provider, fallback_model, api_key_env, temperature, monthly_token_limit, monthly_cost_limit, is_enabled)
                VALUES (:company_id, :provider, :model, :fallback_provider, :fallback_model, :api_key_env, :temperature, :monthly_token_limit, :monthly_cost_limit, :is_enabled)
                ON DUPLICATE KEY UPDATE provider = VALUES(provider), model = VALUES(model), fallback_provider = VALUES(fallback_provider), fallback_model = VALUES(fallback_model), api_key_env = VALUES(api_key_env), temperature = VALUES(temperature), monthly_token_limit = VALUES(monthly_token_limit), monthly_cost_limit = VALUES(monthly_cost_limit), is_enabled = VALUES(is_enabled)';

        Database::connection()->prepare($sql)->execute([
            'company_id' => $companyId,
            ...$settings,
            'is_enabled' => $settings['is_enabled'] ? 1 : 0,
        ]);
    }

    public function saveManagedApiKey(int $companyId, string $apiKey): void
    {
        if (!Database::available()) {
            return;
        }

        $vault = new SecretVault();
        $encrypted = $vault->encrypt($apiKey);

        $sql = "INSERT INTO ai_provider_settings
                (company_id, provider, model, fallback_provider, fallback_model, api_key_env, encrypted_api_key, api_key_last4, api_key_updated_at, temperature, monthly_token_limit, monthly_cost_limit, is_enabled)
                VALUES (:company_id, 'openai', :model, 'simulated', 'asisfly-demo-latam', 'OPENAI_API_KEY', :encrypted_api_key, :api_key_last4, CURRENT_TIMESTAMP, 0.40, 500000, 25.00, 1)
                ON DUPLICATE KEY UPDATE encrypted_api_key = VALUES(encrypted_api_key), api_key_last4 = VALUES(api_key_last4), api_key_updated_at = CURRENT_TIMESTAMP";

        Database::connection()->prepare($sql)->execute([
            'company_id' => $companyId,
            'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
            'encrypted_api_key' => $encrypted,
            'api_key_last4' => $vault->last4($apiKey),
        ]);
    }

    public function companyCredentialStatus(): array
    {
        if (!Database::available()) {
            return [];
        }

        try {
            $sql = "SELECT c.id, c.name, COALESCE(p.name, '-') AS plan, s.provider, s.model, s.api_key_last4, s.api_key_updated_at, s.encrypted_api_key
                    FROM companies c
                    LEFT JOIN plans p ON p.id = c.plan_id
                    LEFT JOIN ai_provider_settings s ON s.company_id = c.id
                    ORDER BY c.name";
            $rows = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $sql = "SELECT c.id, c.name, COALESCE(p.name, '-') AS plan, s.provider, s.model
                    FROM companies c
                    LEFT JOIN plans p ON p.id = c.plan_id
                    LEFT JOIN ai_provider_settings s ON s.company_id = c.id
                    ORDER BY c.name";
            $rows = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_map(function (array $row): array {
            $envKey = trim((string) env('OPENAI_API_KEY', ''));
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'plan' => $row['plan'] ?? '-',
                'provider' => $row['provider'] ?: 'simulated',
                'model' => $row['model'] ?: env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
                'source' => !empty($row['encrypted_api_key']) ? 'Plataforma' : ($envKey !== '' ? '.env' : 'Sin clave'),
                'last4' => $row['api_key_last4'] ?? null,
                'updated_at' => $row['api_key_updated_at'] ?? null,
            ];
        }, $rows);
    }

    public function forgetManagedApiKey(int $companyId): void
    {
        if (!Database::available()) {
            return;
        }

        Database::connection()
            ->prepare('UPDATE ai_provider_settings SET encrypted_api_key = NULL, api_key_last4 = NULL, api_key_updated_at = NULL WHERE company_id = :company_id')
            ->execute(['company_id' => $companyId]);
    }

    public function resolvedApiKey(array $settings): string
    {
        $managed = (new SecretVault())->decrypt($settings['encrypted_api_key'] ?? null);
        if ($managed) {
            return $managed;
        }

        return trim((string) env($settings['api_key_env'] ?? 'OPENAI_API_KEY', ''));
    }

    public function planLimits(int $companyId): array
    {
        if (!Database::available()) {
            return ['tokens' => -1, 'ai_messages' => -1];
        }

        $sql = 'SELECT p.limits_json
                FROM companies c
                LEFT JOIN plans p ON p.id = c.plan_id
                WHERE c.id = :company_id
                LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute(['company_id' => $companyId]);
        $limits = json_decode((string) $statement->fetchColumn(), true);

        return is_array($limits) ? $limits : ['tokens' => -1, 'ai_messages' => -1];
    }

    public function canUseAi(int $companyId, int $estimatedTokens, array $settings): array
    {
        $usage = $this->usageThisMonth($companyId);
        $planLimits = $this->planLimits($companyId);
        $settingTokenLimit = (int) ($settings['monthly_token_limit'] ?? -1);
        $planTokenLimit = (int) ($planLimits['tokens'] ?? -1);
        $effectiveTokenLimit = $this->lowestPositiveLimit($settingTokenLimit, $planTokenLimit);
        $costLimit = (float) ($settings['monthly_cost_limit'] ?? -1);

        if (empty($settings['is_enabled'])) {
            return ['allowed' => false, 'reason' => 'El motor IA esta desactivado para esta empresa.'];
        }

        if ($effectiveTokenLimit > -1 && ($usage['monthly_tokens_used'] + $estimatedTokens) > $effectiveTokenLimit) {
            return ['allowed' => false, 'reason' => 'La empresa superaria el limite mensual de tokens del plan.'];
        }

        if ($costLimit > -1 && $usage['monthly_cost_used'] >= $costLimit) {
            return ['allowed' => false, 'reason' => 'La empresa alcanzo el limite mensual de costo IA configurado.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private function usageThisMonth(int $companyId): array
    {
        if (!Database::available()) {
            $logs = $_SESSION['ai_usage'] ?? [];
            return [
                'monthly_tokens_used' => array_sum(array_map(fn (array $row) => (int) ($row['tokens'] ?? 0), $logs)),
                'monthly_cost_used' => 0.0,
            ];
        }

        $statement = Database::connection()->prepare('SELECT COALESCE(SUM(prompt_tokens + completion_tokens), 0) AS tokens, COALESCE(SUM(estimated_cost), 0) AS cost FROM ai_usage_logs WHERE company_id = :company_id AND created_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")');
        $statement->execute(['company_id' => $companyId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: ['tokens' => 0, 'cost' => 0];

        return [
            'monthly_tokens_used' => (int) $row['tokens'],
            'monthly_cost_used' => (float) $row['cost'],
        ];
    }

    private function cleanProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return in_array($provider, ['openai', 'anthropic', 'gemini', 'local', 'simulated'], true) ? $provider : 'simulated';
    }

    private function lowestPositiveLimit(int $a, int $b): int
    {
        $limits = array_values(array_filter([$a, $b], fn (int $limit) => $limit > -1));
        return $limits ? min($limits) : -1;
    }
}
