<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Empresa</span>
        <h2>Usuarios</h2>
        <p>Crea usuarios reales para tu empresa, asigna roles y controla quien puede operar cada modulo.</p>
    </div>
    <div class="launch-next">
        <span>Equipo</span>
        <strong><?= e((string) count($users)) ?> usuarios</strong>
        <small>Separados por empresa.</small>
    </div>
</section>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger mt-4"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success mt-4"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<section class="panel mt-4">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Nuevo acceso</span>
            <h3>Crear usuario</h3>
        </div>
        <span class="status-pill success"><i class="bi bi-shield-check"></i> Activo por empresa</span>
    </div>
    <form method="post" action="<?= url('/users') ?>" class="row g-3 mt-1">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="name" placeholder="Ej: Camila Rojas" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" placeholder="usuario@empresa.cl" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Rol</label>
            <select class="form-select" name="role_id" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e((string) $role['id']) ?>"><?= e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select class="form-select" name="status">
                <option value="active">Activo</option>
                <option value="invited">Invitado</option>
                <option value="disabled">Deshabilitado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Contrasena inicial</label>
            <input class="form-control" type="password" name="password" minlength="8" required>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary"><i class="bi bi-person-plus"></i> Crear usuario</button>
        </div>
    </form>
</section>

<section class="panel mt-4">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Equipo actual</span>
            <h3>Usuarios de <?= e($_SESSION['company']['name'] ?? 'la empresa') ?></h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Creado</th></tr></thead>
            <tbody>
            <?php foreach ($users as $item): ?>
                <tr>
                    <td><?= e($item['name']) ?></td>
                    <td><?= e($item['email']) ?></td>
                    <td><?= e($item['role_name']) ?></td>
                    <td><span class="status-pill"><?= e($item['status']) ?></span></td>
                    <td><?= e(substr((string) ($item['created_at'] ?? ''), 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="text-muted">Aun no hay usuarios creados para esta empresa.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
