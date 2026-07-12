<?php
$providerOptions = [
    'simulated' => 'Simulado seguro',
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic Claude (preparado)',
    'gemini' => 'Google Gemini (preparado)',
    'local' => 'Modelo local (preparado)',
];
$modelOptions = [
    'gpt-4.1-mini' => 'OpenAI GPT-4.1 mini - recomendado',
    'gpt-4.1' => 'OpenAI GPT-4.1 - mayor capacidad',
    'gpt-4o-mini' => 'OpenAI GPT-4o mini - economico',
    'gpt-4o' => 'OpenAI GPT-4o - multimodal',
    'asisfly-demo-latam' => 'AsisFly simulado - sin costo',
];
$temperatureOptions = [
    '0.2' => 'Preciso y conservador',
    '0.4' => 'Equilibrado - recomendado',
    '0.7' => 'Creativo comercial',
    '1.0' => 'Muy creativo',
];
$tokenLimitOptions = [
    '100000' => '100.000 tokens - prueba',
    '500000' => '500.000 tokens - starter',
    '3000000' => '3.000.000 tokens - pro',
    '15000000' => '15.000.000 tokens - business',
    '-1' => 'Sin limite manual',
];
$costLimitOptions = [
    '10' => 'USD 10 / mes',
    '25' => 'USD 25 / mes',
    '100' => 'USD 100 / mes',
    '500' => 'USD 500 / mes',
    '-1' => 'Sin limite manual',
];
$currentTemperature = number_format((float) ($aiSettings['temperature'] ?? 0.4), 1, '.', '');
$currentTokenLimit = (string) ($aiSettings['monthly_token_limit'] ?? 500000);
$currentCostLimit = (string) (float) ($aiSettings['monthly_cost_limit'] ?? 25);
$keySourceLabels = ['managed' => 'Clave propia de empresa', 'global' => 'Clave global de plataforma', 'env' => 'Respaldo tecnico .env', 'missing' => 'Sin clave'];
$canManageAiEngine = !empty($canManageAiEngine);
$accounts = $accounts ?? [];
$accountMetrics = $accountMetrics ?? [];
$whatsappSignup = $whatsappSignup ?? ['configured' => false];
$providerLabels = ['whatsapp_cloud' => 'WhatsApp Business', 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'imap' => 'Correo IMAP/SMTP', 'meta' => 'Meta', 'telegram' => 'Telegram', 'obraok' => 'ObraOK'];
$statusLabels = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectada', 'disabled' => 'Pausada', 'error' => 'Error'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Email' => 'bi-envelope-at', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Telegram' => 'bi-telegram', 'Operaciones' => 'bi-kanban'];
?>
<?php if (!$canManageAiEngine): ?>
<section class="panel launch-hero">
    <div>
        <span class="eyebrow">IA empresarial</span>
        <h2>AsisFly IA esta <?= !empty($aiSettings['is_enabled']) ? 'activa' : 'pausada' ?></h2>
        <p>Tu empresa usa el motor IA administrado por AsisFly. Aqui puedes revisar tu uso mensual y conectar tus canales de trabajo.</p>
    </div>
    <a class="btn btn-primary" href="<?= url('/chat') ?>"><i class="bi bi-stars"></i> Usar Chat IA</a>
</section>

<section class="crm-metrics mt-4">
    <article class="metric-card">
        <span>Tokens usados</span>
        <strong><?= e(number_format((int) ($aiSettings['monthly_tokens_used'] ?? 0))) ?></strong>
        <small>Consumo del mes</small>
    </article>
    <article class="metric-card">
        <span>Bolsa incluida</span>
        <strong><?= ((int) ($aiSettings['monthly_token_limit'] ?? 0)) > 0 ? e(number_format((int) $aiSettings['monthly_token_limit'])) : 'Sin limite' ?></strong>
        <small>Segun plan contratado</small>
    </article>
    <article class="metric-card">
        <span>Servicio IA</span>
        <strong>Administrado</strong>
        <small>Incluido en tu mensualidad AsisFly</small>
    </article>
</section>
<?php else: ?>
<section class="panel ai-config">
    <div>
        <span class="eyebrow">Motor IA</span>
        <h2>Proveedor y modelo por empresa</h2>
        <p>AsisFly puede usar OpenAI real por empresa y mantener fallback simulado si la API falla, no hay clave o se alcanza un limite de consumo.</p>
        <div class="ai-usage-strip">
            <span>Tokens mes: <strong><?= e(number_format((int) ($aiSettings['monthly_tokens_used'] ?? 0))) ?></strong></span>
            <span>Costo mes: <strong>USD <?= e(number_format((float) ($aiSettings['monthly_cost_used'] ?? 0), 4)) ?></strong></span>
            <span>Estado: <strong><?= !empty($aiSettings['is_enabled']) ? 'Activo' : 'Pausado' ?></strong></span>
        </div>
        <p class="text-secondary mt-3 mb-0">Para OpenAI real, el Superadmin debe cargar la API key cifrada en <strong>Administracion &gt; IA y tokens</strong>. El <code>.env</code> queda solo como respaldo tecnico.</p>
    </div>
    <form method="post" action="<?= url('/integrations/ai') ?>" class="ai-config-form">
        <?= csrf_field() ?>
        <label>
            <span>Proveedor principal</span>
            <select class="form-select" name="provider">
                <?php foreach ($providerOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($aiSettings['provider'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Modelo</span>
            <select class="form-select" name="model">
                <?php foreach ($modelOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($aiSettings['model'] ?? 'gpt-4.1-mini') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Clave OpenAI</span>
            <input type="hidden" name="api_key_env" value="OPENAI_API_KEY">
            <div class="form-control d-flex align-items-center justify-content-between">
                <span><?= e($keySourceLabels[$aiSettings['api_key_source'] ?? 'missing'] ?? 'Sin clave') ?></span>
                <?php if (!empty($aiSettings['api_key_last4'])): ?>
                    <strong>****<?= e((string) $aiSettings['api_key_last4']) ?></strong>
                <?php endif; ?>
            </div>
            <small class="text-secondary">La clave real se administra desde Superadmin &gt; IA y tokens.</small>
        </label>
        <label>
            <span>Estilo de respuesta</span>
            <select class="form-select" name="temperature">
                <?php foreach ($temperatureOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $currentTemperature === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Fallback</span>
            <select class="form-select" name="fallback_provider">
                <?php foreach (['simulated' => 'Simulado seguro', 'openai' => 'OpenAI alternativo'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($aiSettings['fallback_provider'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Modelo fallback</span>
            <select class="form-select" name="fallback_model">
                <?php foreach (['asisfly-demo-latam' => 'AsisFly simulado - recomendado', 'gpt-4.1-mini' => 'OpenAI GPT-4.1 mini'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($aiSettings['fallback_model'] ?? 'asisfly-demo-latam') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Limite tokens mes</span>
            <select class="form-select" name="monthly_token_limit">
                <?php foreach ($tokenLimitOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $currentTokenLimit === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Limite costo mes USD</span>
            <select class="form-select" name="monthly_cost_limit">
                <?php foreach ($costLimitOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $currentCostLimit === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="toggle-line">
            <input type="checkbox" name="is_enabled" value="1" <?= !empty($aiSettings['is_enabled']) ? 'checked' : '' ?>>
            <span>Motor IA activo para esta empresa</span>
        </label>
        <button class="btn btn-primary">Guardar motor IA</button>
    </form>
</section>

<section class="panel mt-3">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Validacion beta</span>
            <h3>Probar conexion OpenAI</h3>
        </div>
        <span class="status-pill"><i class="bi bi-cpu"></i><?= e($aiSettings['provider'] ?? 'simulated') ?> / <?= e($aiSettings['model'] ?? '-') ?></span>
    </div>
    <p class="text-secondary mb-3">Ejecuta una llamada real corta, registra tokens y costo estimado en el consumo IA de esta empresa.</p>
    <form method="post" action="<?= url('/integrations/ai/test') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline-primary"><i class="bi bi-lightning-charge"></i> Probar OpenAI</button>
    </form>
</section>
<?php endif; ?>

<section class="panel launch-hero mt-4">
    <div>
        <span class="eyebrow">Canales de trabajo</span>
        <h2>Cuentas conectadas</h2>
        <p>Conecta WhatsApp, correos, Gmail, Outlook, Instagram, Messenger u otros canales desde un solo lugar. AsisFly usa estas cuentas para recibir mensajes, sugerir respuestas y operar con supervision.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?= url('/integrations/accounts') ?>"><i class="bi bi-plug"></i> Gestionar cuentas</a>
        <a class="btn btn-outline-primary" href="<?= url('/integrations/whatsapp/connect') ?>"><i class="bi bi-whatsapp"></i> Conectar WhatsApp</a>
        <a class="btn btn-outline-secondary" href="<?= url('/inbox') ?>"><i class="bi bi-inboxes"></i> Ver mensajes</a>
    </div>
</section>

<?php if (empty($whatsappSignup['configured'])): ?>
    <section class="panel mt-3">
        <div class="section-heading mb-0">
            <div>
                <span class="eyebrow">WhatsApp Business</span>
                <h3>Conexion asistida pendiente de configuracion global</h3>
                <p class="text-secondary mb-0">Cuando el Superadmin configure Meta Embedded Signup, las empresas podran conectar WhatsApp desde el boton sin ingresar datos tecnicos.</p>
            </div>
            <span class="status-pill warning"><i class="bi bi-exclamation-triangle"></i> Pendiente</span>
        </div>
    </section>
<?php endif; ?>

<section class="crm-metrics mt-4">
    <?php foreach ($accountMetrics as $metric): ?>
        <article class="metric-card">
            <span><?= e((string) $metric['label']) ?></span>
            <strong><?= e((string) $metric['value']) ?></strong>
            <small><?= e((string) $metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
    <?php if (!$accountMetrics): ?>
        <article class="metric-card"><span>Cuentas</span><strong>0</strong><small>Sin canales conectados aun</small></article>
    <?php endif; ?>
</section>

<section class="panel mt-4">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Resumen operativo</span>
            <h3>Canales configurados</h3>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="<?= url('/integrations/accounts') ?>"><i class="bi bi-sliders"></i> Abrir configuracion</a>
    </div>
    <div class="control-entry-list">
        <?php foreach (array_slice($accounts, 0, 6) as $account): ?>
            <?php $credential = $account['credential_summary'] ?? []; ?>
            <div>
                <strong><i class="bi <?= e($channelIcons[$account['channel']] ?? 'bi-plug') ?>"></i> <?= e((string) $account['display_name']) ?></strong>
                <span>
                    <?= e((string) ($providerLabels[$account['provider']] ?? $account['provider'])) ?> /
                    <?= e((string) ($statusLabels[$account['status']] ?? $account['status'])) ?> /
                    <?= !empty($credential['is_configured']) ? 'credenciales listas' : 'credenciales pendientes' ?>
                </span>
                <small><?= e((string) ($account['external_account_id'] ?: 'Sin identificador publico')) ?></small>
            </div>
        <?php endforeach; ?>
        <?php if (!$accounts): ?>
            <p class="task-muted">Aun no hay cuentas conectadas. Agrega el primer canal para que AsisFly pueda recibir mensajes y trabajar desde Omnicanal.</p>
        <?php endif; ?>
    </div>
</section>
