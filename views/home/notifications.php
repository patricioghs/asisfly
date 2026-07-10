<?php
$typeLabels = ['approval' => 'Aprobaciones', 'task' => 'Tareas', 'control' => 'Controles', 'message' => 'Omnicanal', 'quote' => 'Cotizaciones', 'system' => 'Sistema'];
$statusLabels = ['unread' => 'No leida', 'pending' => 'Pendiente', 'read' => 'Leida', 'resolved' => 'Resuelta'];
$priorityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$riskLabels = ['high' => 'Riesgo alto', 'medium' => 'Riesgo medio', 'low' => 'Riesgo bajo'];
$filterFields = function () use ($filters): void { ?>
    <input type="hidden" name="filter_type" value="<?= e((string) ($filters['type'] ?? '')) ?>">
    <input type="hidden" name="filter_status" value="<?= e((string) ($filters['status'] ?? '')) ?>">
    <input type="hidden" name="filter_q" value="<?= e((string) ($filters['q'] ?? '')) ?>">
<?php };
?>

<section class="panel notifications-hero">
    <div>
        <span class="eyebrow">Centro de alertas</span>
        <h2>Notificaciones</h2>
        <p>Alertas conectadas a Omnicanal, Aprobaciones, Tareas, Controles y Cotizaciones para que nada importante quede sin respuesta.</p>
    </div>
    <div class="notifications-live-card">
        <span>Conectado a modulos</span>
        <strong><?= e((string) count($notifications)) ?> novedades</strong>
        <p>Ordenadas por prioridad, riesgo y fecha de actualizacion.</p>
        <?php if ($notifications): ?>
            <form method="post" action="<?= url('/notifications/all-reviewed') ?>" class="workbench-inline-form">
                <?= csrf_field() ?>
                <?php $filterFields(); ?>
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-check2-all"></i> Marcar todas revisadas</button>
                <small>Oculta estas alertas del centro sin borrar su origen.</small>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="workbench-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card">
            <span><?= e((string) $metric['label']) ?></span>
            <strong><?= e((string) $metric['value']) ?></strong>
            <small><?= e((string) $metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<form class="panel notifications-filter-bar mt-4" method="get" action="<?= url('/notifications') ?>">
    <input class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Buscar notificacion, cliente, modulo o alerta">
    <select class="form-select" name="type">
        <option value="">Todos los modulos</option>
        <?php foreach ($typeLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
        <option value="">Pendientes / no revisadas</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i>Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= url('/notifications') ?>">Limpiar</a>
</form>

<section class="notifications-shell mt-4">
    <article class="panel notifications-main-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Actividad conectada</span>
                <h2>Ultimas notificaciones</h2>
            </div>
            <span class="soft-badge">Tiempo real</span>
        </div>
        <div class="notification-feed">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $priority = (string) ($notification['priority'] ?? 'medium');
                $status = (string) ($notification['status'] ?? 'unread');
                $risk = (string) ($notification['risk'] ?? 'medium');
                $type = (string) ($notification['type'] ?? 'system');
                ?>
                <article class="notification-card priority-<?= e($priority) ?> <?= $status === 'unread' ? 'is-unread' : '' ?>">
                    <div class="notification-icon"><i class="bi <?= e((string) ($notification['icon'] ?? 'bi-bell')) ?>"></i></div>
                    <div class="notification-body">
                        <div class="notification-meta">
                            <span><?= e($typeLabels[$type] ?? (string) ($notification['type_label'] ?? 'Sistema')) ?></span>
                            <span><?= e((string) ($notification['module'] ?? 'AsisFly')) ?></span>
                            <time><?= e((string) ($notification['time'] ?? 'Ahora')) ?></time>
                        </div>
                        <h3><?= e((string) ($notification['title'] ?? 'Notificacion')) ?></h3>
                        <p><?= e((string) ($notification['body'] ?? 'Hay una novedad pendiente de revision.')) ?></p>
                        <div class="chip-line">
                            <span class="status status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                            <span class="priority priority-<?= e($priority) ?>"><i class="bi bi-flag"></i><?= e($priorityLabels[$priority] ?? $priority) ?></span>
                            <span class="risk-chip risk-<?= e($risk) ?>"><?= e($riskLabels[$risk] ?? $risk) ?></span>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?= url((string) ($notification['action_url'] ?? '/notifications')) ?>"><?= e((string) ($notification['action_label'] ?? 'Abrir')) ?> <i class="bi bi-arrow-right"></i></a>
                        <?php if ($status !== 'read'): ?>
                            <form method="post" action="<?= url('/notifications/reviewed') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="notification_id" value="<?= e((string) ($notification['id'] ?? '')) ?>">
                                <?php $filterFields(); ?>
                                <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-check2"></i>Revisada</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$notifications): ?>
                <div class="empty-state compact">
                    <strong>No hay notificaciones para este filtro</strong>
                    <p>Cuando existan mensajes, alertas de controles, tareas o aprobaciones, apareceran aqui.</p>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <aside class="panel notifications-side-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Criterios</span>
                <h2>Prioridad</h2>
            </div>
        </div>
        <div class="notification-rule high">
            <i class="bi bi-exclamation-octagon"></i>
            <div><strong>Alta</strong><p>Alertas reales que requieren atencion prioritaria por riesgo, fecha o impacto operativo.</p></div>
        </div>
        <div class="notification-rule medium">
            <i class="bi bi-activity"></i>
            <div><strong>Media</strong><p>Alertas de controles, tareas internas y oportunidades que necesitan seguimiento.</p></div>
        </div>
        <div class="notification-rule low">
            <i class="bi bi-check2-circle"></i>
            <div><strong>Baja</strong><p>Actualizaciones informativas, eventos registrados y actividad sin riesgo inmediato.</p></div>
        </div>
        <a class="btn btn-outline-secondary w-100" href="<?= url('/workbench') ?>"><i class="bi bi-briefcase"></i> Ir a Bandeja de trabajo</a>
    </aside>
</section>
