<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Empresa</span>
        <h2>Roles</h2>
        <p>Roles y permisos base para controlar lo que cada usuario puede ver, aprobar o ejecutar.</p>
    </div>
    <div class="launch-next">
        <span>Seguridad</span>
        <strong><?= e((string) count($roles)) ?> roles</strong>
        <small>Permisos listos para extender.</small>
    </div>
</section>

<section class="knowledge-card-grid mt-4">
    <?php foreach ($roles as $role): ?>
        <article class="panel knowledge-card">
            <div class="kpi-icon tone-primary"><i class="bi bi-shield-lock"></i></div>
            <span><?= e($role['scope'] ?? 'company') ?></span>
            <strong><?= e($role['name'] ?? 'Rol') ?></strong>
            <p><?= e(substr((string) ($role['permissions_json'] ?? '[]'), 0, 120)) ?></p>
        </article>
    <?php endforeach; ?>
</section>
