<div class="panel">
    <h2>Empresas</h2>
    <div class="table-responsive">
        <table class="table"><thead><tr><th>Empresa</th><th>Plan</th><th>Usuarios</th><th>Tokens</th><th>Estado</th></tr></thead>
            <tbody><?php foreach ($companies as $company): ?><tr><td><?= e($company['name']) ?></td><td><?= e($company['plan']) ?></td><td><?= e((string) $company['users']) ?></td><td><?= e($company['tokens']) ?></td><td><?= e($company['status']) ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div>
<div class="panel mt-4">
    <h2>Auditoria IA</h2>
    <div class="table-responsive">
        <table class="table"><thead><tr><th>Proveedor</th><th>Modelo</th><th>Modulo</th><th>Tokens</th><th>Costo</th><th>Estado</th></tr></thead>
            <tbody><?php foreach ($usage as $row): ?><tr><td><?= e($row['provider']) ?></td><td><?= e($row['model']) ?></td><td><?= e($row['module']) ?></td><td><?= e((string) $row['tokens']) ?></td><td><?= e($row['cost']) ?></td><td><?= e($row['status']) ?><?= !empty($row['error']) ? ' - ' . e($row['error']) : '' ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <?php if (!$usage): ?>
        <div class="empty-state compact">
            <strong>Sin auditoria IA todavia</strong>
            <p>Cuando el chat o los modulos usen el motor IA, aqui veras proveedor, modelo, tokens, costo y estado de fallback.</p>
        </div>
    <?php endif; ?>
</div>
