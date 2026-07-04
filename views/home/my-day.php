<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Inicio</span>
        <h2>Mi dia</h2>
        <p>Una vista ejecutiva para ordenar la jornada: prioridades, reuniones, clientes por responder y tareas que AsisFly recomienda resolver primero.</p>
    </div>
    <div class="launch-next">
        <span>Prioridad</span>
        <strong>Responder clientes calientes</strong>
        <small>AsisFly detecto conversaciones con alta probabilidad de venta.</small>
    </div>
</section>

<section class="home-command-grid mt-4">
    <article class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Agenda inteligente</span>
                <h2>Plan de hoy</h2>
            </div>
            <span class="soft-badge">America/Santiago</span>
        </div>
        <?php foreach ($agenda as $event): ?>
            <div class="home-list-row">
                <time><?= e($event['time']) ?></time>
                <div>
                    <strong><?= e($event['title']) ?></strong>
                    <span><?= e($event['detail']) ?></span>
                </div>
                <small><?= e($event['status']) ?></small>
            </div>
        <?php endforeach; ?>
    </article>

    <aside class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">AsisFly sugiere</span>
                <h2>Enfoque del dia</h2>
            </div>
        </div>
        <div class="signal-row">
            <strong>Llamar a clientes que pidieron precio y no compraron.</strong>
            <small>Puede recuperar ventas sin crear nuevas campanas.</small>
        </div>
        <div class="signal-row">
            <strong>Revisar margen antes de publicar promociones.</strong>
            <small>Hay productos con alta rotacion y mejor rentabilidad.</small>
        </div>
    </aside>
</section>
