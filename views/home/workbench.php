<?php
$priorityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$typeLabels = ['approval' => 'Aprobaciones', 'message' => 'Mensajes', 'task' => 'Tareas', 'quote' => 'Cotizaciones'];
$statusLabels = [
    'pending' => 'Pendiente', 'approved' => 'Aprobada', 'failed' => 'Fallida', 'new' => 'Nuevo', 'open' => 'Abierto', 'pending_approval' => 'Por aprobar',
    'draft' => 'Borrador', 'sent' => 'Enviada', 'done' => 'Lista',
];
$riskLabels = ['high' => 'Riesgo alto', 'medium' => 'Riesgo medio', 'low' => 'Riesgo bajo'];
$filterFields = function () use ($filters): void { ?>
    <input type="hidden" name="filter_view" value="<?= e($filters['view'] ?? '') ?>">
    <input type="hidden" name="filter_type" value="<?= e($filters['type'] ?? '') ?>">
    <input type="hidden" name="filter_priority" value="<?= e($filters['priority'] ?? '') ?>">
    <input type="hidden" name="filter_q" value="<?= e($filters['q'] ?? '') ?>">
<?php };
?>

<section class="panel workbench-hero">
    <div>
        <span class="eyebrow">Inicio</span>
        <h2>Bandeja de trabajo</h2>
        <p>Tu vista diaria para resolver mensajes, aprobaciones, tareas, cotizaciones y seguimientos sin saltar entre modulos.</p>
        <div class="workbench-tabs">
            <a class="<?= ($filters['view'] ?? '') === 'mine' ? 'active' : '' ?>" href="<?= url('/workbench?view=mine') ?>"><i class="bi bi-person-check"></i>Para mi</a>
            <a class="<?= ($filters['view'] ?? '') === 'team' ? 'active' : '' ?>" href="<?= url('/workbench?view=team') ?>"><i class="bi bi-people"></i>Equipo</a>
        </div>
    </div>
    <div class="workbench-command">
        <span>Prioridad sugerida</span>
        <strong><?= e((string) ($metrics[1]['value'] ?? '0')) ?> urgentes</strong>
        <p>AsisFly ordena la bandeja por impacto, riesgo y fecha para que partas por lo importante.</p>
        <a class="btn btn-primary" href="<?= url('/chat') ?>"><i class="bi bi-stars"></i> Preguntar a AsisFly</a>
    </div>
</section>

<section class="workbench-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card">
            <span><?= e($metric['label']) ?></span>
            <strong><?= e($metric['value']) ?></strong>
            <small><?= e($metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<form class="panel workbench-filter-bar mt-4" method="get" action="<?= url('/workbench') ?>">
    <input type="hidden" name="view" value="<?= e($filters['view'] ?? 'mine') ?>">
    <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar cliente, tarea, mensaje o modulo">
    <select class="form-select" name="type">
        <option value="">Todos los tipos</option>
        <?php foreach ($typeLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="priority">
        <option value="">Todas las prioridades</option>
        <?php foreach ($priorityLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i>Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= url('/workbench') ?>">Limpiar</a>
</form>

<section class="workbench-shell mt-4">
    <div class="panel workbench-main-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Operacion diaria</span>
                <h2>Trabajo pendiente</h2>
            </div>
            <span class="soft-badge"><?= e((string) count($items)) ?> items</span>
        </div>

        <div class="workbench-list premium">
            <?php foreach ($items as $item): ?>
                <?php
                $priority = (string) ($item['priority'] ?? 'medium');
                $status = (string) ($item['status'] ?? 'pending');
                $risk = (string) ($item['risk'] ?? 'medium');
                $type = (string) ($item['type'] ?? 'task');
                ?>
                <article class="workbench-card wb-priority-<?= e($priority) ?>">
                    <div class="workbench-card-icon"><i class="bi <?= e((string) ($item['icon'] ?? 'bi-briefcase')) ?>"></i></div>
                    <div class="workbench-card-body">
                        <div class="workbench-card-meta">
                            <span><?= e($typeLabels[$type] ?? $type) ?></span>
                            <span><?= e((string) ($item['module'] ?? 'AsisFly')) ?></span>
                            <?php if (($item['customer'] ?? 'AsisFly') !== ($item['module'] ?? 'AsisFly')): ?>
                                <span><?= e((string) ($item['customer'] ?? 'AsisFly')) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?= e((string) ($item['title'] ?? 'Pendiente')) ?></h3>
                        <p><?= e((string) ($item['detail'] ?? 'Revisar este pendiente en AsisFly.')) ?></p>
                        <div class="chip-line">
                            <span class="priority priority-<?= e($priority) ?>"><i class="bi bi-flag"></i><?= e($priorityLabels[$priority] ?? $priority) ?></span>
                            <span class="status status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                            <span class="risk-chip risk-<?= e($risk) ?>"><?= e($riskLabels[$risk] ?? $risk) ?></span>
                            <span class="assignee-chip"><i class="bi bi-clock"></i><?= e((string) ($item['due_label'] ?? 'Sin fecha')) ?></span>
                        </div>
                    </div>
                    <div class="workbench-card-actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?= url((string) ($item['action_url'] ?? '/workbench')) ?>"><?= e((string) ($item['action_label'] ?? 'Abrir')) ?> <i class="bi bi-arrow-right"></i></a>
                        <?php if ($type === 'task'): ?>
                            <form method="post" action="<?= url('/workbench/task-done') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="task_id" value="<?= e((string) ($item['id'] ?? 0)) ?>">
                                <?php $filterFields(); ?>
                                <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-check2"></i>Listo</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$items): ?>
                <div class="empty-state compact">
                    <strong>No hay pendientes para este filtro</strong>
                    <p>Prueba cambiar filtros o revisa Omnicanal, Tareas, Cotizaciones y Aprobaciones.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="panel workbench-side-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Guia rapida</span>
                <h2>Como usarla</h2>
            </div>
        </div>
        <div class="workbench-rule">
            <i class="bi bi-1-circle"></i>
            <div><strong>Parte por urgentes</strong><p>Mensajes, clientes calientes y aprobaciones de riesgo alto suben primero.</p></div>
        </div>
        <div class="workbench-rule">
            <i class="bi bi-2-circle"></i>
            <div><strong>Resuelve en contexto</strong><p>Cada tarjeta te lleva al modulo exacto para responder, aprobar o revisar.</p></div>
        </div>
        <div class="workbench-rule">
            <i class="bi bi-3-circle"></i>
            <div><strong>Entrena a AsisFly</strong><p>Cuando apruebas, corriges o completas, el sistema aprende como trabaja la empresa.</p></div>
        </div>
        <a class="btn btn-outline-secondary w-100" href="<?= url('/actions') ?>"><i class="bi bi-shield-check"></i> Ver aprobaciones</a>
    </aside>
</section>
