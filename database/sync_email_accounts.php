<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;
use App\Repositories\OmnichannelRepository;

/**
 * Sincroniza cuentas IMAP conectadas. Pensado para cron cada 5 minutos.
 * No recibe ni imprime credenciales; el repositorio las descifra solo en memoria.
 */
function option(string $name, int $default = 0): int
{
    foreach ($_SERVER['argv'] ?? [] as $argument) {
        if (str_starts_with($argument, '--' . $name . '=')) {
            return max(0, (int) substr($argument, strlen($name) + 3));
        }
    }
    return $default;
}

function logSync(string $line): void
{
    $message = '[' . date('c') . '] ' . $line;
    echo $message . PHP_EOL;
    $directory = dirname(__DIR__) . '/logs';
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
    @file_put_contents($directory . '/email-sync.log', $message . PHP_EOL, FILE_APPEND);
}

$companyId = option('company_id');
$accountId = option('account_id');
$limit = min(50, max(1, option('limit', 20)));
$pdo = null;
$lockName = 'asisfly_email_sync_scheduler';

try {
    if (!function_exists('imap_open')) {
        throw new RuntimeException('La extension IMAP no esta habilitada para PHP CLI. Verifica php -m y habilitala antes de activar el cron.');
    }

    $pdo = Database::connection();
    $lock = $pdo->prepare('SELECT GET_LOCK(:name, 0)');
    $lock->execute(['name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        logSync('Otra sincronizacion ya esta en curso. Esta ejecucion se omite.');
        exit(0);
    }

    $where = [
        "channel = 'Email'",
        "provider IN ('imap', 'gmail', 'outlook')",
        'inbound_enabled = TRUE',
        "status = 'connected'",
        'encrypted_credentials IS NOT NULL',
    ];
    $params = [];
    if ($companyId > 0) {
        $where[] = 'company_id = :company_id';
        $params['company_id'] = $companyId;
    }
    if ($accountId > 0) {
        $where[] = 'id = :account_id';
        $params['account_id'] = $accountId;
    }

    $accounts = $pdo->prepare('SELECT id, company_id, display_name FROM omnichannel_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY company_id, id');
    $accounts->execute($params);
    $accounts = $accounts->fetchAll(\PDO::FETCH_ASSOC);
    if (!$accounts) {
        logSync('No hay cuentas de correo conectadas y habilitadas para sincronizar.');
        exit(0);
    }

    $repository = new OmnichannelRepository();
    $success = 0;
    $failed = 0;
    foreach ($accounts as $account) {
        $run = $pdo->prepare('INSERT INTO email_sync_runs (company_id, account_id, started_at, status) VALUES (:company_id, :account_id, NOW(), "running")');
        $run->execute(['company_id' => (int) $account['company_id'], 'account_id' => (int) $account['id']]);
        $runId = (int) $pdo->lastInsertId();

        $result = $repository->syncEmailAccount((int) $account['company_id'], (int) $account['id'], $limit);
        $ok = !empty($result['ok']);
        $message = mb_substr((string) ($result['message'] ?? 'Sin detalle.'), 0, 1000);
        $status = $ok ? ((int) ($result['imported'] ?? 0) > 0 ? 'success' : 'warning') : 'failed';
        $pdo->prepare('UPDATE email_sync_runs SET finished_at = NOW(), status = :status, imported_count = :imported_count, message = :message WHERE id = :id')->execute([
            'status' => $status,
            'imported_count' => max(0, (int) ($result['imported'] ?? 0)),
            'message' => $message,
            'id' => $runId,
        ]);

        logSync(($ok ? 'OK' : 'ERROR') . ' [' . $account['company_id'] . ':' . $account['id'] . ' ' . $account['display_name'] . '] ' . $message);
        $ok ? $success++ : $failed++;
    }

    logSync("Finalizado. Cuentas correctas: {$success}. Con error: {$failed}.");
    exit($failed > 0 ? 1 : 0);
} catch (Throwable $exception) {
    logSync('ERROR GENERAL: ' . $exception->getMessage());
    exit(1);
} finally {
    if ($pdo instanceof \PDO) {
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lockName]);
        } catch (Throwable) {
            // El bloqueo de MySQL expira automaticamente al cerrar la conexion.
        }
    }
}
