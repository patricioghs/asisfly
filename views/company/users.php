<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Empresa</span>
        <h2>Usuarios</h2>
        <p>Usuarios del espacio de trabajo, roles, estado y acceso a la plataforma.</p>
    </div>
    <div class="launch-next">
        <span>Equipo</span>
        <strong><?= e((string) count($users)) ?> usuarios</strong>
        <small>Separados por empresa.</small>
    </div>
</section>

<section class="panel mt-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($users as $item): ?>
                <tr><td><?= e($item['name']) ?></td><td><?= e($item['email']) ?></td><td><?= e($item['role_name']) ?></td><td><?= e($item['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
