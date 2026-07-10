<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

$db = config('database');
$database = $db['database'];
$autoCreate = strtolower((string) env('DB_AUTO_CREATE', 'true'));
$shouldCreateDatabase = in_array($autoCreate, ['1', 'true', 'yes', 'on'], true);
$dsn = "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}";

if (!$shouldCreateDatabase) {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$database};charset={$db['charset']}";
}

$pdo = new PDO($dsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if ($shouldCreateDatabase) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$database}`");
}

foreach ([
    __DIR__ . '/migrations/001_initial_schema.sql',
    __DIR__ . '/migrations/002_brains_schema.sql',
    __DIR__ . '/migrations/003_social_schema.sql',
    __DIR__ . '/migrations/004_action_center_schema.sql',
    __DIR__ . '/migrations/005_action_execution_schema.sql',
    __DIR__ . '/migrations/006_inbox_schema.sql',
    __DIR__ . '/migrations/007_password_resets_schema.sql',
    __DIR__ . '/migrations/008_ai_real_provider_schema.sql',
    __DIR__ . '/migrations/009_omnichannel_real_schema.sql',
    __DIR__ . '/migrations/010_crm_vendible_schema.sql',
    __DIR__ . '/migrations/011_quotes_real_schema.sql',
    __DIR__ . '/migrations/012_social_vendible_schema.sql',
    __DIR__ . '/migrations/013_action_center_robust_schema.sql',
    __DIR__ . '/migrations/014_subscriptions_billing_schema.sql',
    __DIR__ . '/migrations/015_company_ai_autonomy_schema.sql',
    __DIR__ . '/migrations/016_multi_account_omnichannel_schema.sql',
    __DIR__ . '/migrations/017_task_collaboration_schema.sql',
    __DIR__ . '/migrations/018_controls_schema.sql',
    __DIR__ . '/migrations/019_superadmin_api_keys_schema.sql',
    __DIR__ . '/migrations/020_platform_api_keys_schema.sql',
    __DIR__ . '/migrations/021_omnichannel_credentials_schema.sql',
    __DIR__ . '/migrations/022_abilities_marketplace_schema.sql',
    __DIR__ . '/migrations/023_ai_tool_audit_schema.sql',
    __DIR__ . '/seeders/002_demo_data.sql',
    __DIR__ . '/seeders/003_brains_data.sql',
    __DIR__ . '/seeders/004_social_data.sql',
    __DIR__ . '/seeders/005_action_center_data.sql',
    __DIR__ . '/seeders/006_inbox_data.sql',
    __DIR__ . '/seeders/007_ai_provider_settings.sql',
    __DIR__ . '/seeders/008_omnichannel_accounts.sql',
    __DIR__ . '/seeders/009_crm_vendible_data.sql',
    __DIR__ . '/seeders/010_quote_products.sql',
    __DIR__ . '/seeders/011_social_templates.sql',
    __DIR__ . '/seeders/012_subscriptions_demo.sql',
    __DIR__ . '/seeders/013_controls_demo.sql',
    __DIR__ . '/seeders/014_abilities_compatibility.sql',
] as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("No se pudo leer {$file}");
    }
    $pdo->exec($sql);
    echo "Ejecutado: {$file}" . PHP_EOL;
}

echo "AsisFly instalado. Usuario demo: admin@asisfly.ai / demo1234" . PHP_EOL;
