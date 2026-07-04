<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/bootstrap.php';

$options = getopt('', [
    'confirm:',
    'company:',
    'name:',
    'email:',
    'password:',
    'country::',
    'currency::',
    'timezone::',
    'locale::',
    'plan::',
]);

if (($options['confirm'] ?? '') !== 'LIMPIAR_ASISFLY') {
    fwrite(STDERR, "Uso: php database/prepare_real_workspace.php --confirm=LIMPIAR_ASISFLY --company=\"Mi Empresa\" --name=\"Admin\" --email=\"admin@empresa.cl\" --password=\"ClaveSegura123\"\n");
    exit(1);
}

foreach (['company', 'name', 'email', 'password'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Falta --{$required}\n");
        exit(1);
    }
}

if (!filter_var($options['email'], FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email invalido.\n");
    exit(1);
}

if (strlen((string) $options['password']) < 8) {
    fwrite(STDERR, "La contrasena debe tener al menos 8 caracteres.\n");
    exit(1);
}

$db = config('database');
$pdo = new PDO(
    "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}",
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$basePlans = [
    ['Starter', 29.00, ['users' => 3, 'documents' => 100, 'ai_messages' => 1000, 'tokens' => 500000, 'integrations' => 2, 'automations' => 5, 'storage_gb' => 5]],
    ['Pro', 79.00, ['users' => 10, 'documents' => 1000, 'ai_messages' => 5000, 'tokens' => 3000000, 'integrations' => 6, 'automations' => 25, 'storage_gb' => 50]],
    ['Business', 199.00, ['users' => 30, 'documents' => 5000, 'ai_messages' => 20000, 'tokens' => 15000000, 'integrations' => 15, 'automations' => 100, 'storage_gb' => 250]],
    ['Enterprise', 0.00, ['users' => -1, 'documents' => -1, 'ai_messages' => -1, 'tokens' => -1, 'integrations' => -1, 'automations' => -1, 'storage_gb' => -1]],
];

$baseRoles = [
    ['Superadmin', ['*']],
    ['Dueno de empresa', ['company.manage', 'users.manage', 'assistant.manage', 'billing.manage', 'crm.manage', 'quotes.manage', 'documents.manage', 'chat.use', 'dashboard.view']],
    ['Administrador', ['users.manage', 'assistant.manage', 'crm.manage', 'quotes.manage', 'documents.manage', 'chat.use', 'dashboard.view']],
    ['Ejecutivo', ['crm.manage', 'quotes.manage', 'chat.use', 'dashboard.view']],
    ['Analista', ['reports.view', 'documents.manage', 'chat.use', 'dashboard.view']],
    ['Solo lectura', ['dashboard.view', 'reports.view']],
];

$preserved = ['plans', 'roles'];
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$pdo->beginTransaction();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) {
    if (!in_array($table, $preserved, true)) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$planStatement = $pdo->prepare('INSERT INTO plans (name, monthly_price, limits_json) VALUES (:name, :price, :limits) ON DUPLICATE KEY UPDATE monthly_price = VALUES(monthly_price), limits_json = VALUES(limits_json)');
foreach ($basePlans as [$name, $price, $limits]) {
    $planStatement->execute([
        'name' => $name,
        'price' => $price,
        'limits' => json_encode($limits, JSON_UNESCAPED_UNICODE),
    ]);
}

$roleStatement = $pdo->prepare('INSERT INTO roles (name, permissions_json) VALUES (:name, :permissions) ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)');
foreach ($baseRoles as [$name, $permissions]) {
    $roleStatement->execute([
        'name' => $name,
        'permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
    ]);
}

$planName = (string) ($options['plan'] ?? 'Business');
$planQuery = $pdo->prepare('SELECT id FROM plans WHERE name = :name LIMIT 1');
$planQuery->execute(['name' => $planName]);
$planId = (int) ($planQuery->fetchColumn() ?: $pdo->query("SELECT id FROM plans WHERE name = 'Business' LIMIT 1")->fetchColumn());

$roleQuery = $pdo->prepare("SELECT id FROM roles WHERE name = 'Superadmin' LIMIT 1");
$roleQuery->execute();
$roleId = (int) $roleQuery->fetchColumn();

$country = (string) ($options['country'] ?? 'Chile');
$currency = strtoupper((string) ($options['currency'] ?? 'CLP'));
$timezone = (string) ($options['timezone'] ?? 'America/Santiago');
$locale = (string) ($options['locale'] ?? 'es_CL');

$company = $pdo->prepare('INSERT INTO companies (name, legal_name, country, currency, timezone, locale, plan_id, status) VALUES (:name, :legal_name, :country, :currency, :timezone, :locale, :plan_id, :status)');
$company->execute([
    'name' => $options['company'],
    'legal_name' => $options['company'],
    'country' => $country,
    'currency' => $currency,
    'timezone' => $timezone,
    'locale' => $locale,
    'plan_id' => $planId,
    'status' => 'active',
]);
$companyId = (int) $pdo->lastInsertId();

$user = $pdo->prepare('INSERT INTO users (company_id, role_id, name, email, password_hash, locale, timezone, status) VALUES (:company_id, :role_id, :name, :email, :password_hash, :locale, :timezone, :status)');
$user->execute([
    'company_id' => $companyId,
    'role_id' => $roleId,
    'name' => $options['name'],
    'email' => $options['email'],
    'password_hash' => password_hash((string) $options['password'], PASSWORD_DEFAULT),
    'locale' => $locale,
    'timezone' => $timezone,
    'status' => 'active',
]);

$assistant = $pdo->prepare('INSERT INTO assistant_settings (company_id, assistant_name, tone, language, country, currency, work_hours, signature, rules, forbidden_words, required_phrases, human_escalation) VALUES (:company_id, :assistant_name, :tone, :language, :country, :currency, :work_hours, :signature, :rules, :forbidden_words, :required_phrases, :human_escalation)');
$assistant->execute([
    'company_id' => $companyId,
    'assistant_name' => 'AsisFly',
    'tone' => 'Cercano, profesional y ejecutivo',
    'language' => $locale === 'pt_BR' ? 'Portugues de Brasil' : ($locale === 'en_US' ? 'Ingles' : 'Espanol latino'),
    'country' => $country,
    'currency' => $currency,
    'work_hours' => 'Lunes a viernes, 09:00 a 18:00',
    'signature' => 'Equipo ' . $options['company'],
    'rules' => 'Pedir aprobacion antes de enviar correos, mensajes, cotizaciones o ejecutar acciones sensibles.',
    'forbidden_words' => '',
    'required_phrases' => 'Quedo atento/a',
    'human_escalation' => 'Reclamos, descuentos especiales, riesgos legales, pagos, clientes molestos o decisiones comerciales sensibles.',
]);

$integration = $pdo->prepare('INSERT INTO integrations (company_id, provider, status, settings_json) VALUES (:company_id, :provider, :status, JSON_OBJECT())');
foreach (['gmail', 'outlook', 'google_calendar', 'whatsapp_business', 'instagram', 'facebook', 'telegram'] as $provider) {
    $integration->execute([
        'company_id' => $companyId,
        'provider' => $provider,
        'status' => $provider === 'whatsapp_business' ? 'sandbox' : 'simulated',
    ]);
}

if (in_array('company_ai_autonomy', $tables, true)) {
    $pdo->prepare('INSERT INTO company_ai_autonomy (company_id, learning_progress, mode, approvals_count, corrections_count, autonomous_actions_count) VALUES (:company_id, 0, :mode, 0, 0, 0)')
        ->execute([
            'company_id' => $companyId,
            'mode' => 'supervised_learning',
        ]);
}

$pdo->commit();

echo "Workspace real creado.\n";
echo "Empresa: {$options['company']} (ID {$companyId})\n";
echo "Usuario: {$options['email']}\n";
echo "Datos demo eliminados. Planes y roles conservados.\n";
