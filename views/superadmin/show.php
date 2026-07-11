<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Administracion (Superadmin)</span>
        <h2><?= e($title) ?></h2>
        <p><?= e($description) ?></p>
    </div>
    <div class="launch-next">
        <span>Acceso global</span>
        <strong>Superadmin</strong>
        <small>Vista reservada para administracion de la plataforma.</small>
    </div>
</section>

<section class="panel mt-4">
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if ($type === 'companies'): ?>
        <div class="section-heading">
            <div>
                <span class="eyebrow">Alta de cliente</span>
                <h3>Crear empresa y dueno inicial</h3>
            </div>
            <span class="status-pill success"><i class="bi bi-building-add"></i> Multiempresa</span>
        </div>
        <form method="post" action="<?= url('/admin/companies') ?>" class="row g-3 mb-4">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label">Empresa</label>
                <input class="form-control" name="company" placeholder="Ej: Constructora Sur" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Dueno inicial</label>
                <input class="form-control" name="owner_name" placeholder="Nombre y apellido" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Email dueno</label>
                <input class="form-control" type="email" name="owner_email" placeholder="dueno@empresa.cl" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contrasena inicial</label>
                <input class="form-control" type="password" name="password" minlength="8" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Pais</label>
                <input class="form-control" name="country" value="Chile" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Moneda</label>
                <input class="form-control" name="currency" value="CLP" maxlength="3" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Zona horaria</label>
                <input class="form-control" name="timezone" value="America/Santiago" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Idioma</label>
                <select class="form-select" name="locale">
                    <option value="es_CL">Espanol LATAM</option>
                    <option value="pt_BR">Portugues Brasil</option>
                    <option value="en_US">Ingles EEUU</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Plan</label>
                <select class="form-select" name="plan">
                    <?php foreach (($plans ?? []) as $plan): ?>
                        <option value="<?= e($plan['name']) ?>"><?= e($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Empresa</th><th>Plan</th><th>Usuarios</th><th>Tokens</th><th>Estado</th></tr></thead>
                <tbody><?php foreach ($cards as $company): ?><tr><td><?= e($company['name']) ?></td><td><?= e($company['plan']) ?></td><td><?= e((string) $company['users']) ?></td><td><?= e($company['tokens']) ?></td><td><?= e($company['status']) ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php elseif ($type === 'usage'): ?>
        <div class="section-heading">
            <div>
                <span class="eyebrow">Credencial global</span>
                <h3>OpenAI para toda la plataforma</h3>
                <p class="text-secondary mb-0">Guarda una sola API key central. Todas las empresas la usan si no tienen una clave propia.</p>
            </div>
            <?php if (($platformCredential['source'] ?? '') === 'Global'): ?>
                <span class="status-pill success"><i class="bi bi-check2-circle"></i> Global ****<?= e((string) ($platformCredential['last4'] ?? '')) ?></span>
            <?php else: ?>
                <span class="status-pill warning"><i class="bi bi-exclamation-triangle"></i> Sin clave global</span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('/admin/ai-tokens/platform-openai-key') ?>" class="row g-3 mb-4">
            <?= csrf_field() ?>
            <div class="col-md-8">
                <label class="form-label">API key OpenAI global</label>
                <input class="form-control" type="password" name="openai_api_key" placeholder="sk-proj-..." autocomplete="new-password" spellcheck="false" required>
                <small class="text-secondary">Se cifra con APP_KEY. No se muestra nuevamente despues de guardarla.</small>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="bi bi-shield-lock"></i> Guardar</button>
            </div>
        </form>
        <?php if (($platformCredential['source'] ?? '') === 'Global'): ?>
            <form method="post" action="<?= url('/admin/ai-tokens/platform-openai-key/delete') ?>" class="mb-4">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Eliminar clave global</button>
            </form>
        <?php endif; ?>

        <div class="section-heading">
            <div>
                <span class="eyebrow">Credencial omnicanal</span>
                <h3>WhatsApp Business Cloud para toda la plataforma</h3>
                <p class="text-secondary mb-0">Guarda el token de Meta una sola vez. Las empresas solo conectan su numero/cuenta WhatsApp sin ver credenciales tecnicas.</p>
            </div>
            <?php if (($whatsappCredential['source'] ?? '') === 'Global'): ?>
                <span class="status-pill success"><i class="bi bi-whatsapp"></i> Global ****<?= e((string) ($whatsappCredential['last4'] ?? '')) ?></span>
            <?php else: ?>
                <span class="status-pill warning"><i class="bi bi-exclamation-triangle"></i> Sin token global</span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('/admin/ai-tokens/platform-whatsapp-key') ?>" class="row g-3 mb-4">
            <?= csrf_field() ?>
            <div class="col-md-8">
                <label class="form-label">Access token Meta / WhatsApp global</label>
                <input class="form-control" type="password" name="whatsapp_api_key" placeholder="EAAG..." autocomplete="new-password" spellcheck="false" required>
                <small class="text-secondary">Se cifra con APP_KEY. No se muestra nuevamente despues de guardarlo.</small>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="bi bi-shield-lock"></i> Guardar</button>
            </div>
        </form>
        <?php if (($whatsappCredential['source'] ?? '') === 'Global'): ?>
            <form method="post" action="<?= url('/admin/ai-tokens/platform-whatsapp-key/delete') ?>" class="mb-4">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Eliminar token WhatsApp</button>
            </form>
        <?php endif; ?>

        <div class="section-heading">
            <div>
                <span class="eyebrow">Conexion en 2 clics</span>
                <h3>Meta Embedded Signup</h3>
                <p class="text-secondary mb-0">Activa el boton para que cada cliente conecte WhatsApp desde AsisFly sin copiar tokens, webhooks ni IDs tecnicos.</p>
            </div>
            <?php if (!empty($whatsappSignup['configured'])): ?>
                <span class="status-pill success"><i class="bi bi-check2-circle"></i> Boton activo</span>
            <?php else: ?>
                <span class="status-pill warning"><i class="bi bi-exclamation-triangle"></i> Falta configurar</span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= url('/admin/ai-tokens/platform-whatsapp-signup') ?>" class="row g-3 mb-4">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label">Meta App ID</label>
                <input class="form-control" name="meta_app_id" placeholder="ID de la app Meta" autocomplete="off" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Configuration ID</label>
                <input class="form-control" name="meta_config_id" placeholder="Config ID Embedded Signup" autocomplete="off" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">App Secret</label>
                <input class="form-control" type="password" name="meta_app_secret" placeholder="Opcional para intercambio server-side" autocomplete="new-password">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="bi bi-whatsapp"></i> Guardar</button>
            </div>
            <div class="col-12">
                <small class="text-secondary">Estado actual: App <?= e((string) ($whatsappSignup['settings']['app_id'] ?? '')) ?> · Config <?= e((string) ($whatsappSignup['settings']['config_id'] ?? '')) ?> · Secret <?= e((string) ($whatsappSignup['settings']['app_secret'] ?? '')) ?></small>
            </div>
        </form>

        <div class="section-heading">
            <div>
                <span class="eyebrow">Credenciales por empresa</span>
                <h3>Excepciones por cliente</h3>
                <p class="text-secondary mb-0">Opcional: usa esto solo si una empresa Enterprise quiere pagar o administrar su propia API key.</p>
            </div>
            <span class="status-pill"><i class="bi bi-key"></i> Opcional</span>
        </div>
        <form method="post" action="<?= url('/admin/ai-tokens/openai-key') ?>" class="row g-3 mb-4">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Empresa</label>
                <select class="form-select" name="company_id" required>
                    <?php foreach (($credentials ?? []) as $credential): ?>
                        <option value="<?= e((string) $credential['id']) ?>"><?= e($credential['name']) ?> - <?= e($credential['plan']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">API key OpenAI</label>
                <input class="form-control" type="password" name="openai_api_key" placeholder="sk-proj-..." autocomplete="new-password" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="bi bi-shield-lock"></i> Guardar</button>
            </div>
        </form>

        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead><tr><th>Empresa</th><th>Proveedor</th><th>Modelo</th><th>Clave</th><th>Actualizada</th><th></th></tr></thead>
                <tbody>
                    <?php foreach (($credentials ?? []) as $credential): ?>
                        <tr>
                            <td><strong><?= e($credential['name']) ?></strong><br><small class="text-secondary"><?= e($credential['plan']) ?></small></td>
                            <td><?= e($credential['provider']) ?></td>
                            <td><?= e($credential['model']) ?></td>
                            <td>
                                <?php if ($credential['source'] === 'Empresa'): ?>
                                    <span class="status-pill success"><i class="bi bi-check2-circle"></i> Empresa ****<?= e((string) $credential['last4']) ?></span>
                                <?php elseif ($credential['source'] === 'Global'): ?>
                                    <span class="status-pill success"><i class="bi bi-globe2"></i> Global</span>
                                <?php elseif ($credential['source'] === '.env'): ?>
                                    <span class="status-pill"><i class="bi bi-terminal"></i> .env</span>
                                <?php else: ?>
                                    <span class="status-pill warning"><i class="bi bi-exclamation-triangle"></i> Sin clave</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($credential['updated_at'] ?? '-')) ?></td>
                            <td class="text-end">
                                <?php if ($credential['source'] === 'Empresa'): ?>
                                    <form method="post" action="<?= url('/admin/ai-tokens/openai-key/delete') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="company_id" value="<?= e((string) $credential['id']) ?>">
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section-heading">
            <div>
                <span class="eyebrow">Auditoria IA</span>
                <h3>Consumo reciente</h3>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle"><thead><tr><th>Proveedor</th><th>Modelo</th><th>Modulo</th><th>Tokens</th><th>Costo</th><th>Estado</th></tr></thead>
                <tbody><?php foreach ($cards as $row): ?><tr><td><?= e($row['provider'] ?? '-') ?></td><td><?= e($row['model'] ?? '-') ?></td><td><?= e($row['module'] ?? '-') ?></td><td><?= e((string) ($row['tokens'] ?? 0)) ?></td><td><?= e($row['cost'] ?? '-') ?></td><td><?= e($row['status'] ?? '-') ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="knowledge-card-grid">
            <?php foreach ($cards as $card): ?>
                <article class="panel knowledge-card">
                    <div class="kpi-icon tone-primary"><i class="bi bi-shield-check"></i></div>
                    <span><?= e((string) ($card['status'] ?? $card['monthly_price'] ?? $card['module'] ?? 'Estado')) ?></span>
                    <strong><?= e((string) ($card['name'] ?? $card['event'] ?? $card['service'] ?? 'Elemento')) ?></strong>
                    <p><?= e((string) ($card['detail'] ?? $card['limits_json'] ?? $card['type'] ?? 'Registro del sistema')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


