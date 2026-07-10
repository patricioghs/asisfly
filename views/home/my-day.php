<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Inicio</span>
        <h2>Mi dia</h2>
        <p>Una vista ejecutiva para ordenar la jornada: prioridades, reuniones, clientes por responder y tareas que AsisFly recomienda resolver primero.</p>
    </div>
    <div class="launch-next">
        <?php if (!empty($priorityItem)): ?>
            <span>Prioridad real</span>
            <strong><?= e((string) ($priorityItem['title'] ?? 'Pendiente')) ?></strong>
            <small><?= e((string) ($priorityItem['detail'] ?? 'Revisar este pendiente.')) ?></small>
        <?php else: ?>
            <span>Sin pendientes</span>
            <strong>Tu dia esta limpio</strong>
            <small>Cuando entren mensajes, tareas o aprobaciones reales apareceran aqui.</small>
        <?php endif; ?>
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
        <?php if (empty($agenda)): ?>
            <div class="empty-state compact">
                <strong>No hay actividades reales para hoy</strong>
                <p>Los correos, tareas, cotizaciones y aprobaciones apareceran aqui cuando existan datos de tu empresa.</p>
            </div>
        <?php endif; ?>
    </article>

    <aside class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">AsisFly sugiere</span>
                <h2>Enfoque del dia</h2>
            </div>
        </div>
        <?php foreach (($suggestions ?? []) as $suggestion): ?>
            <div class="signal-row">
                <strong><?= e((string) ($suggestion['title'] ?? 'Revisar pendiente')) ?></strong>
                <small><?= e((string) ($suggestion['detail'] ?? 'Pendiente detectado en tu empresa.')) ?></small>
            </div>
        <?php endforeach; ?>
        <?php if (empty($suggestions)): ?>
            <div class="empty-state compact">
                <strong>Sin recomendaciones por ahora</strong>
                <p>AsisFly mostrara sugerencias cuando detecte pendientes reales de alta prioridad.</p>
            </div>
        <?php endif; ?>
    </aside>
</section>
