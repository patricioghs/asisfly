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
