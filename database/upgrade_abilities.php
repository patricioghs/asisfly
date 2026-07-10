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
