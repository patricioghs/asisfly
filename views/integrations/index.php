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
?>
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

<div class="row g-3 mt-3">
    <?php foreach ($integrations as $integration): ?>
        <div class="col-md-6 col-xl-4">
            <article class="panel integration-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h2><?= e($integration['name']) ?></h2>
                    <span class="badge text-bg-secondary"><?= e($integration['status']) ?></span>
                </div>
                <p><?= e($integration['scope']) ?></p>
                <button class="btn btn-outline-primary btn-sm">Configurar</button>
            </article>
        </div>
    <?php endforeach; ?>
</div>
