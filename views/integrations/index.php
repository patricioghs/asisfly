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
                <?php foreach (['simulated' => 'Simulado', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic Claude', 'gemini' => 'Google Gemini', 'local' => 'Modelo local'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($aiSettings['provider'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Modelo</span>
            <input class="form-control" name="model" value="<?= e($aiSettings['model'] ?? 'gpt-4.1-mini') ?>">
        </label>
        <label>
            <span>Variable API key de respaldo</span>
            <input class="form-control" name="api_key_env" value="<?= e($aiSettings['api_key_env'] ?? 'OPENAI_API_KEY') ?>">
            <small class="text-secondary">Fuente actual: <?= e((string) ($aiSettings['api_key_source'] ?? 'missing')) ?><?= !empty($aiSettings['api_key_last4']) ? ' · ****' . e((string) $aiSettings['api_key_last4']) : '' ?></small>
        </label>
        <label>
            <span>Temperatura</span>
            <input class="form-control" type="number" step="0.1" min="0" max="2" name="temperature" value="<?= e((string) ($aiSettings['temperature'] ?? 0.4)) ?>">
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
            <input class="form-control" name="fallback_model" value="<?= e($aiSettings['fallback_model'] ?? 'asisfly-demo-latam') ?>">
        </label>
        <label>
            <span>Limite tokens mes</span>
            <input class="form-control" type="number" name="monthly_token_limit" value="<?= e((string) ($aiSettings['monthly_token_limit'] ?? 500000)) ?>">
        </label>
        <label>
            <span>Limite costo mes USD</span>
            <input class="form-control" type="number" step="0.01" name="monthly_cost_limit" value="<?= e((string) ($aiSettings['monthly_cost_limit'] ?? 25)) ?>">
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
