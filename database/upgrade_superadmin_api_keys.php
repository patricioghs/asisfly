<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$database = (string) config('database.database');

$columns = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = "ai_provider_settings"');
$columns->execute(['database' => $database]);
$existing = array_flip(array_column($columns->fetchAll(), 'COLUMN_NAME'));

$updates = [
    'encrypted_api_key' => 'ALTER TABLE ai_provider_settings ADD COLUMN encrypted_api_key TEXT NULL AFTER api_key_env',
    'api_key_last4' => 'ALTER TABLE ai_provider_settings ADD COLUMN api_key_last4 VARCHAR(12) NULL AFTER encrypted_api_key',
    'api_key_updated_at' => 'ALTER TABLE ai_provider_settings ADD COLUMN api_key_updated_at TIMESTAMP NULL AFTER api_key_last4',
];

foreach ($updates as $column => $sql) {
    if (!isset($existing[$column])) {
        $pdo->exec($sql);
        echo "Columna agregada: {$column}" . PHP_EOL;
        continue;
    }

    echo "Columna existente: {$column}" . PHP_EOL;
}

echo 'Upgrade de claves API Superadmin listo.' . PHP_EOL;
