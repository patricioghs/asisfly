<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Integraciones</span>
        <h2>APIs</h2>
        <p>Centro tecnico para endpoints, webhooks, claves API y conexiones futuras con sistemas externos.</p>
    </div>
    <div class="launch-next">
        <span>Modo actual</span>
        <strong>Sandbox</strong>
        <small>Preparado para conectar proveedores reales.</small>
    </div>
</section>

<section class="intelligence-card-grid mt-4">
    <?php foreach ($apis as [$name, $detail, $status]): ?>
        <article class="panel intelligence-card">
            <div class="kpi-icon tone-primary"><i class="bi bi-code-slash"></i></div>
            <span><?= e($status) ?></span>
            <strong><?= e($name) ?></strong>
            <p><?= e($detail) ?></p>
        </article>
    <?php endforeach; ?>
</section>
