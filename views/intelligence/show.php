<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Inteligencia</span>
        <h2><?= e($title) ?></h2>
        <p><?= e($description) ?></p>
    </div>
    <div class="launch-next">
        <span>Motor analitico</span>
        <strong>IA + datos</strong>
        <small>Preparado para Excel, CSV, CRM, ventas, gastos e inventario.</small>
    </div>
</section>

<section class="intelligence-card-grid mt-4">
    <?php foreach ($cards as [$name, $detail, $status]): ?>
        <article class="panel intelligence-card">
            <div class="kpi-icon tone-primary"><i class="bi bi-graph-up-arrow"></i></div>
            <span><?= e($status) ?></span>
            <strong><?= e($name) ?></strong>
            <p><?= e($detail) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel mt-4">
    <div class="panel-title">
        <div>
            <span class="eyebrow">AsisFly recomienda</span>
            <h2>Siguiente analisis</h2>
        </div>
        <a href="<?= url('/chat') ?>">Pedir a AsisFly <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="signal-row">
        <strong>Subir una planilla de ventas y pedir comparacion contra el mes anterior.</strong>
        <small>Esto permite detectar bajas, productos con perdida, clientes de mayor valor y oportunidades por zona.</small>
    </div>
</section>
