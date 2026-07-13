<?php
$statusLabels = ['pending' => 'Pendiente', 'done' => 'Terminada', 'cancelled' => 'Cancelada'];
$statusIcons = ['pending' => 'bi-clock', 'done' => 'bi-check2-circle', 'cancelled' => 'bi-x-circle'];
$priorityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$priorityIcons = ['critical' => 'bi-exclamation-octagon', 'high' => 'bi-arrow-up-circle', 'medium' => 'bi-dot', 'low' => 'bi-arrow-down-circle'];
$typeLabels = ['follow_up' => 'Seguimiento', 'call' => 'Llamada', 'email' => 'Email', 'meeting' => 'Reunion', 'quote' => 'Cotizacion', 'todo' => 'Tarea'];
$typeIcons = ['follow_up' => 'bi-arrow-repeat', 'call' => 'bi-telephone', 'email' => 'bi-envelope', 'meeting' => 'bi-calendar-event', 'quote' => 'bi-file-earmark-text', 'todo' => 'bi-list-check'];
$metricByStatus = [];
foreach ($metrics as $metric) {
    $metricByStatus[$metric['status']] = (int) $metric['value'];
}
$quickTask = fn (array $extra): string => url('/tasks?' . http_build_query(array_filter([...$filters, ...$extra], fn ($value) => $value !== '')));
$taskSource = static function (array $task): array {
    if (($task['source_type'] ?? '') === 'omnichannel' && (int) ($task['source_id'] ?? 0) > 0) {
        return [
            'label' => (string) ($task['source_label'] ?? 'Conversacion omnicanal'),
            'href' => url('/inbox?id=' . (int) $task['source_id']),
            'action' => 'Abrir conversacion',
            'icon' => 'bi-chat-dots',
        ];
    }

    if (in_array($task['task_type'] ?? '', ['meeting', 'follow_up', 'quote', 'call', 'todo'], true) && !empty($task['customer_name'])) {
        return [
            'label' => 'Origen sin vinculo (tarea anterior)',
            'href' => url('/inbox?q=' . rawurlencode((string) $task['customer_name'])),
            'action' => 'Buscar conversacion',
            'icon' => 'bi-search',
        ];
    }

    return ['label' => 'Tarea creada manualmente', 'href' => '', 'action' => '', 'icon' => 'bi-list-check'];
};
?>

<section class="task-compact-head">
    <div>
        <span class="eyebrow">Asistente</span>
        <h2>Tareas</h2>
        <p>Prioriza, asigna y completa el trabajo de tu equipo y de AsisFly.</p>
    </div>
    <div class="task-head-stats">
        <a class="<?= ($filters['status'] ?? '') === 'pending' ? 'active' : '' ?>" href="<?= e($quickTask(['status' => 'pending'])) ?>"><strong><?= e((string) ($metricByStatus['pending'] ?? 0)) ?></strong><span>Pendientes</span></a>
        <a class="<?= ($filters['status'] ?? '') === 'done' ? 'active' : '' ?>" href="<?= e($quickTask(['status' => 'done'])) ?>"><strong><?= e((string) ($metricByStatus['done'] ?? 0)) ?></strong><span>Terminadas</span></a>
        <a class="<?= ($filters['status'] ?? '') === 'cancelled' ? 'active' : '' ?>" href="<?= e($quickTask(['status' => 'cancelled'])) ?>"><strong><?= e((string) ($metricByStatus['cancelled'] ?? 0)) ?></strong><span>Canceladas</span></a>
    </div>
</section>

<form class="panel task-compact-filter mt-3" method="get" action="<?= url('/tasks') ?>">
    <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar tarea, cliente o contacto">
    <select class="form-select" name="status">
        <option value="">Todos los estados</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary icon-button" title="Buscar" aria-label="Buscar"><i class="bi bi-search"></i></button>
    <a class="btn btn-outline-secondary icon-button" href="<?= url('/tasks') ?>" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i></a>
    <details class="task-advanced-filters">
        <summary><i class="bi bi-sliders"></i>Filtros</summary>
        <div>
            <select class="form-select" name="assigned_to">
                <option value="">Todos los responsables</option>
                <option value="none" <?= ($filters['assigned_to'] ?? '') === 'none' ? 'selected' : '' ?>>Sin responsable</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (string) ($filters['assigned_to'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="priority">
                <option value="">Todas las prioridades</option>
                <?php foreach ($priorityLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar filtros</button>
        </div>
    </details>
</form>

<section class="panel task-list-panel mt-3">
    <div class="task-list-head">
        <div>
            <span class="eyebrow">Trabajo activo</span>
            <h2><?= e((string) count($tasks)) ?> tareas</h2>
        </div>
        <a class="soft-badge" href="<?= url('/crm') ?>">Ver CRM <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="task-board">
        <?php foreach ($tasks as $task): ?>
            <?php $source = $taskSource($task); ?>
            <article class="task-card">
                <div class="task-main">
                    <span class="account-icon"><i class="bi <?= e($typeIcons[$task['task_type'] ?? 'todo'] ?? 'bi-list-check') ?>"></i></span>
                    <div>
                        <span class="task-kicker"><?= e($typeLabels[$task['task_type'] ?? 'todo'] ?? 'Tarea') ?> / <?= e($task['customer_name'] ?? 'Sin cliente') ?></span>
                        <strong><?= e($task['title'] ?? 'Tarea pendiente') ?></strong>
                        <div class="chip-line">
                            <span class="status status-<?= e($task['status'] ?? 'pending') ?>"><i class="bi <?= e($statusIcons[$task['status'] ?? 'pending'] ?? 'bi-circle') ?>"></i><?= e($statusLabels[$task['status'] ?? 'pending'] ?? 'Pendiente') ?></span>
                            <span class="priority priority-<?= e($task['priority'] ?? 'medium') ?>"><i class="bi <?= e($priorityIcons[$task['priority'] ?? 'medium'] ?? 'bi-dot') ?>"></i><?= e($priorityLabels[$task['priority'] ?? 'medium'] ?? 'Media') ?></span>
                            <span class="assignee-chip"><i class="bi bi-person"></i><?= e($task['assigned_name'] ?? 'Sin responsable') ?></span>
                            <span class="assignee-chip"><i class="bi bi-calendar3"></i><?= e($task['due_at'] ?? 'Sin fecha') ?></span>
                        </div>
                        <div class="task-origin">
                            <span><i class="bi <?= e($source['icon']) ?>"></i><?= e($source['label']) ?></span>
                            <?php if ($source['href'] !== ''): ?><a href="<?= e($source['href']) ?>"><?= e($source['action']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                        </div>
                    </div>
                </div>
                <form class="task-update-form" method="post" action="<?= url('/tasks/update') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="task_id" value="<?= e((string) ($task['id'] ?? 0)) ?>">
                    <input type="hidden" name="filter_q" value="<?= e($filters['q'] ?? '') ?>">
                    <input type="hidden" name="filter_status" value="<?= e($filters['status'] ?? '') ?>">
                    <input type="hidden" name="filter_assigned_to" value="<?= e($filters['assigned_to'] ?? '') ?>">
                    <input type="hidden" name="filter_priority" value="<?= e($filters['priority'] ?? '') ?>">
                    <select class="form-select form-select-sm" name="status" aria-label="Estado de tarea">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($task['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" name="assigned_to" aria-label="Responsable">
                        <option value="none">Sin responsable</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>" <?= (string) ($task['assigned_to'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" name="priority" aria-label="Prioridad">
                        <?php foreach ($priorityLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($task['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary">Actualizar</button>
                </form>
                <details class="task-collab">
                    <summary>
                        <span><i class="bi bi-chat-left-text"></i> Colaboracion e historial</span>
                        <small><?= e((string) (count($task['comments'] ?? []) + count($task['events'] ?? []))) ?> movimientos</small>
                    </summary>
                    <div class="task-collab-body">
                        <div class="task-collab-head">
                            <div>
                                <span class="eyebrow">Colaboracion interna</span>
                                <strong>Comentarios e historial</strong>
                            </div>
                            <form method="post" action="<?= url('/tasks/request-update') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="task_id" value="<?= e((string) ($task['id'] ?? 0)) ?>">
                                <input type="hidden" name="filter_q" value="<?= e($filters['q'] ?? '') ?>">
                                <input type="hidden" name="filter_status" value="<?= e($filters['status'] ?? '') ?>">
                                <input type="hidden" name="filter_assigned_to" value="<?= e($filters['assigned_to'] ?? '') ?>">
                                <input type="hidden" name="filter_priority" value="<?= e($filters['priority'] ?? '') ?>">
                                <button class="btn btn-sm btn-light" type="submit"><i class="bi bi-send"></i> Pedir actualizacion</button>
                            </form>
                        </div>

                    <div class="task-collab-grid">
                        <div class="task-comment-panel">
                            <form class="task-comment-form" method="post" action="<?= url('/tasks/comment') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="task_id" value="<?= e((string) ($task['id'] ?? 0)) ?>">
                                <input type="hidden" name="filter_q" value="<?= e($filters['q'] ?? '') ?>">
                                <input type="hidden" name="filter_status" value="<?= e($filters['status'] ?? '') ?>">
                                <input type="hidden" name="filter_assigned_to" value="<?= e($filters['assigned_to'] ?? '') ?>">
                                <input type="hidden" name="filter_priority" value="<?= e($filters['priority'] ?? '') ?>">
                                <textarea class="form-control form-control-sm" name="comment" rows="2" maxlength="800" placeholder="Escribe un comentario interno o menciona @usuario"></textarea>
                                <button class="btn btn-sm btn-primary" type="submit">Comentar</button>
                            </form>

                            <div class="task-comment-list">
                                <?php foreach (($task['comments'] ?? []) as $comment): ?>
                                    <div class="task-comment-row">
                                        <span><i class="bi bi-chat-left-text"></i><?= e($comment['user_name'] ?? 'Usuario') ?></span>
                                        <p><?= e($comment['comment'] ?? '') ?></p>
                                        <small><?= e($comment['created_at'] ?? '') ?></small>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($task['comments'])): ?>
                                    <p class="task-muted">Sin comentarios internos todavia.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="task-history">
                            <?php foreach (($task['events'] ?? []) as $event): ?>
                                <div class="task-event-row">
                                    <span><i class="bi bi-activity"></i><?= e($event['user_name'] ?? 'Sistema') ?></span>
                                    <p><?= e($event['summary'] ?? '') ?></p>
                                    <small><?= e($event['created_at'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($task['events'])): ?>
                                <p class="task-muted">La bitacora aparecera cuando se actualice o comente la tarea.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </details>
            </article>
        <?php endforeach; ?>
        <?php if (!$tasks): ?>
            <div class="empty-state compact">
                <strong>No hay tareas para este filtro</strong>
                <p>Cambia los filtros o crea tareas desde CRM, Omnicanal o recomendaciones de AsisFly.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
