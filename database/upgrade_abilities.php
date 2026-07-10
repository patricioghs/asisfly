<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;

try {
    $pdo = Database::connection();

    foreach ([
        __DIR__ . '/migrations/022_abilities_marketplace_schema.sql',
        __DIR__ . '/migrations/023_ai_tool_audit_schema.sql',
        __DIR__ . '/seeders/014_abilities_compatibility.sql',
        __DIR__ . '/migrations/024_marketplace_installable_defaults.sql',
        __DIR__ . '/migrations/025_marketplace_label_backfill.sql',
        __DIR__ . '/migrations/026_marketplace_hide_invalid_abilities.sql',
        __DIR__ . '/migrations/027_marketplace_canonical_catalog.sql',
        __DIR__ . '/migrations/028_omnichannel_core_default.sql',
        __DIR__ . '/migrations/029_ai_training_schema.sql',
        __DIR__ . '/migrations/030_ai_training_knowledge_schema.sql',
        __DIR__ . '/seeders/015_ai_training_seed.sql',
    ] as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("No se pudo leer {$file}");
        }

        $pdo->exec($sql);
        echo "Ejecutado: {$file}" . PHP_EOL;
    }

    echo "Capa de habilidades instalada sin modificar rutas ni tablas existentes." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo instalar la capa de habilidades: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
