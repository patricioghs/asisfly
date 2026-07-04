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
    <?php if ($type === 'companies'): ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
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
