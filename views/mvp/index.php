<div class="mvp-hero panel">
    <div>
        <span class="eyebrow">Preparacion comercial</span>
        <h2>MVP Vendible</h2>
        <p>Estado de las piezas necesarias para comenzar pilotos y primeras ventas con clientes reales.</p>
    </div>
    <div class="mvp-score">
        <span>Readiness</span>
        <strong><?= e((string) $score) ?>%</strong>
        <small>Base suficiente para demos guiadas y pilotos controlados.</small>
    </div>
</div>

<div class="mvp-grid mt-4">
    <?php foreach ($items as $item): ?>
        <article class="panel mvp-card">
            <div class="mvp-card-head">
                <span><?= e($item['area']) ?></span>
                <strong class="mvp-status mvp-<?= e($item['status']) ?>"><?= e($item['status']) ?></strong>
            </div>
            <h2><?= e($item['item']) ?></h2>
            <p><?= e($item['next']) ?></p>
        </article>
    <?php endforeach; ?>
</div>
