<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$database = (string) config('database.database');

$columns = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = "omnichannel_accounts"');
$columns->execute(['database' => $database]);
$existing = array_flip(array_column($columns->fetchAll(), 'COLUMN_NAME'));

$updates = [
    'encrypted_credentials' => 'ALTER TABLE omnichannel_accounts ADD COLUMN encrypted_credentials TEXT NULL AFTER settings_json',
    'credentials_last4' => 'ALTER TABLE omnichannel_accounts ADD COLUMN credentials_last4 VARCHAR(24) NULL AFTER encrypted_credentials',
    'credentials_updated_at' => 'ALTER TABLE omnichannel_accounts ADD COLUMN credentials_updated_at TIMESTAMP NULL AFTER credentials_last4',
];

foreach ($updates as $column => $sql) {
    if (!isset($existing[$column])) {
        $pdo->exec($sql);
        echo "Columna agregada: {$column}" . PHP_EOL;
        continue;
    }

    echo "Columna existente: {$column}" . PHP_EOL;
}

echo 'Upgrade de credenciales omnicanal listo.' . PHP_EOL;
