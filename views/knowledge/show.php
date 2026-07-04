<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Conocimiento</span>
        <h2><?= e($title) ?></h2>
        <p><?= e($description) ?></p>
    </div>
    <div class="launch-next">
        <span>Memoria empresarial</span>
        <strong>Por empresa</strong>
        <small>Separada por company_id para no mezclar informacion entre clientes.</small>
    </div>
</section>

<section class="knowledge-card-grid mt-4">
    <?php foreach ($cards as [$name, $detail, $status]): ?>
        <article class="panel knowledge-card">
            <div class="kpi-icon tone-success"><i class="bi bi-book"></i></div>
            <span><?= e($status) ?></span>
            <strong><?= e($name) ?></strong>
            <p><?= e($detail) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel mt-4">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Entrenamiento</span>
            <h2>Fuentes recomendadas</h2>
        </div>
        <a href="<?= url('/documents') ?>">Subir documentos <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="prompt-grid">
        <span>Catalogos PDF</span>
        <span>Politicas comerciales</span>
        <span>Manuales de atencion</span>
        <span>Preguntas frecuentes</span>
    </div>
</section>
