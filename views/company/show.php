<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Empresa</span>
        <h2><?= e($heading) ?></h2>
        <p><?= e($description) ?></p>
    </div>
    <div class="launch-next">
        <span>Workspace</span>
        <strong><?= e($_SESSION['company']['name'] ?? 'Empresa') ?></strong>
        <small>Configuracion aislada por empresa.</small>
    </div>
</section>

<section class="knowledge-card-grid mt-4">
    <?php foreach ($cards as [$label, $value]): ?>
        <article class="panel knowledge-card">
            <div class="kpi-icon tone-primary"><i class="bi bi-building"></i></div>
            <span><?= e($label) ?></span>
            <strong><?= e((string) $value) ?></strong>
            <p>Dato de configuracion disponible para el asistente y los modulos.</p>
        </article>
    <?php endforeach; ?>
</section>
