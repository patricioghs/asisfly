<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

use App\Core\Database;

$options = getopt('', ['confirm:', 'dry-run']);
if (($options['confirm'] ?? '') !== 'QUITAR_DEMO_OPERACIONAL') {
    fwrite(STDERR, "Uso: php database/cleanup_demo_operational_data.php --confirm=QUITAR_DEMO_OPERACIONAL\n");
    fwrite(STDERR, "Vista previa: php database/cleanup_demo_operational_data.php --confirm=QUITAR_DEMO_OPERACIONAL --dry-run\n");
    exit(1);
}

$pdo = Database::connection();
$dryRun = array_key_exists('dry-run', $options);

$tableExists = static function (string $table) use ($pdo): bool {
    try {
        $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return true;
    } catch (Throwable) {
        return false;
    }
};

$execute = static function (string $label, string $sql, array $params = []) use ($pdo, $dryRun): int {
    if ($dryRun) {
        $countSql = preg_replace('/^DELETE\s+.+?\s+FROM\s+/i', 'SELECT COUNT(*) FROM ', $sql);
        $countSql = preg_replace('/^DELETE\s+FROM\s+/i', 'SELECT COUNT(*) FROM ', (string) $countSql);
        $statement = $pdo->prepare((string) $countSql);
        $statement->execute($params);
        $count = (int) $statement->fetchColumn();
        echo "[dry-run] {$label}: {$count}\n";
        return $count;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $count = $statement->rowCount();
    echo "{$label}: {$count}\n";
    return $count;
};

$demoExternalIds = ['wa_1001', 'ig_2002', 'fb_3003', 'mail_4004'];
$demoActionTitles = [
    'Aprobar campana social de la semana',
    'Enviar seguimiento a clientes frios',
    'Preparar resumen ejecutivo de manana',
];
$demoCustomerEmails = ['valentina@pacifico.test', 'diego@gruponorte.test'];
$demoProductSkus = ['AS-SOCIAL-PRO', 'AS-OMNI-SETUP', 'AS-CRM-BIZ', 'AS-TRAINING'];

$in = static fn (array $values): string => implode(',', array_fill(0, count($values), '?'));

if (!$dryRun) {
    $pdo->beginTransaction();
}

try {
    if ($tableExists('inbox_messages') && $tableExists('inbox_conversations')) {
        $execute(
            'Mensajes omnicanal demo',
            'DELETE FROM inbox_messages WHERE conversation_id IN (SELECT id FROM inbox_conversations WHERE external_id IN (' . $in($demoExternalIds) . '))',
            $demoExternalIds
        );
    }

    if ($tableExists('inbox_conversations')) {
        $execute(
            'Conversaciones omnicanal demo',
            'DELETE FROM inbox_conversations WHERE external_id IN (' . $in($demoExternalIds) . ')',
            $demoExternalIds
        );
    }

    if ($tableExists('action_center_notifications') && $tableExists('action_center_items')) {
        $execute(
            'Notificaciones de acciones demo',
            'DELETE FROM action_center_notifications WHERE action_id IN (SELECT id FROM action_center_items WHERE title IN (' . $in($demoActionTitles) . '))',
            $demoActionTitles
        );
    }

    if ($tableExists('action_center_items')) {
        $execute(
            'Acciones/aprobaciones demo',
            'DELETE FROM action_center_items WHERE title IN (' . $in($demoActionTitles) . ')',
            $demoActionTitles
        );
    }

    if ($tableExists('crm_tasks') && $tableExists('crm_customers')) {
        $execute(
            'Tareas CRM demo',
            'DELETE FROM crm_tasks WHERE customer_id IN (SELECT id FROM crm_customers WHERE email IN (' . $in($demoCustomerEmails) . ')) OR title LIKE "Hacer seguimiento a Comercial Pacifico" OR title LIKE "Hacer seguimiento a Grupo Norte"',
            $demoCustomerEmails
        );
    }

    foreach (['crm_activities', 'crm_notes', 'crm_opportunities', 'crm_contacts'] as $table) {
        if ($tableExists($table) && $tableExists('crm_customers')) {
            $execute(
                "{$table} demo",
                "DELETE FROM {$table} WHERE customer_id IN (SELECT id FROM crm_customers WHERE email IN (" . $in($demoCustomerEmails) . '))',
                $demoCustomerEmails
            );
        }
    }

    if ($tableExists('crm_customers')) {
        $execute(
            'Clientes CRM demo',
            'DELETE FROM crm_customers WHERE email IN (' . $in($demoCustomerEmails) . ')',
            $demoCustomerEmails
        );
    }

    if ($tableExists('quote_products')) {
        $execute(
            'Productos de cotizacion demo',
            'DELETE FROM quote_products WHERE sku IN (' . $in($demoProductSkus) . ')',
            $demoProductSkus
        );
    }

    if ($tableExists('omnichannel_events') && $tableExists('omnichannel_accounts')) {
        $execute(
            'Eventos de cuentas conectadas demo',
            "DELETE FROM omnichannel_events WHERE account_id IN (SELECT id FROM omnichannel_accounts WHERE status IN ('sandbox', 'simulated') AND (webhook_token LIKE 'demo-%' OR external_account_id LIKE '%demo%' OR external_account_id IN ('wa_sales_001', 'wa_support_001', 'ig_main_001', 'fb_main_001', 'gmail_sales_001', 'gmail_admin_001', 'ventas@empresa.demo', 'gerencia@empresa.demo', 'outlook_finance_001')))"
        );
    }

    if ($tableExists('omnichannel_accounts')) {
        $execute(
            'Cuentas conectadas demo',
            "DELETE FROM omnichannel_accounts WHERE status IN ('sandbox', 'simulated') AND (webhook_token LIKE 'demo-%' OR external_account_id LIKE '%demo%' OR external_account_id IN ('wa_sales_001', 'wa_support_001', 'ig_main_001', 'fb_main_001', 'gmail_sales_001', 'gmail_admin_001', 'ventas@empresa.demo', 'gerencia@empresa.demo', 'outlook_finance_001'))"
        );
    }

    if (!$dryRun) {
        $pdo->commit();
    }

    echo $dryRun ? "Vista previa completada. No se modificaron datos.\n" : "Limpieza demo operacional completada.\n";
} catch (Throwable $exception) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'No se pudo limpiar demo operacional: ' . $exception->getMessage() . "\n");
    exit(1);
}
