<?php
$temperatureLabels = ['hot' => 'Caliente', 'warm' => 'Tibio', 'cold' => 'Frio'];
$temperatureIcons = ['hot' => 'bi-fire', 'warm' => 'bi-thermometer-half', 'cold' => 'bi-snow'];
$temperatureClass = ['hot' => 'danger', 'warm' => 'warning', 'cold' => 'info'];
$taskStatusLabels = ['pending' => 'Pendiente', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];
$taskStatusIcons = ['pending' => 'bi-clock', 'completed' => 'bi-check2-circle', 'cancelled' => 'bi-x-circle'];
$priorityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$priorityIcons = ['critical' => 'bi-exclamation-octagon', 'high' => 'bi-arrow-up-circle', 'medium' => 'bi-dot', 'low' => 'bi-arrow-down-circle'];
$stageIcons = ['Nuevo' => 'bi-sparkles', 'Calificado' => 'bi-patch-check', 'Propuesta' => 'bi-file-earmark-text', 'Negociacion' => 'bi-chat-square-text', 'Ganado' => 'bi-trophy', 'Perdido' => 'bi-x-circle'];
$opportunityStageLabels = ['nuevo' => 'Nuevo', 'calificado' => 'Calificado', 'propuesta' => 'Propuesta', 'negociacion' => 'Negociacion', 'ganado' => 'Ganada', 'perdido' => 'Perdida'];
?>

<div class="crm-hero panel">
    <div>
        <span class="eyebrow">CRM Comercial</span>
        <h2>Clientes, oportunidades y seguimientos en una sola vista</h2>
        <p>Detecta clientes frios, crea tareas automaticas, registra notas y mantiene historial comercial por empresa.</p>
    </div>
    <form method="post" action="<?= url('/crm/followups') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary">Crear seguimientos automaticos</button>
    </form>
</div>

<div class="crm-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card">
            <span><?= e($metric['label']) ?></span>
            <strong><?= e($metric['value']) ?></strong>
            <small><?= e($metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</div>

<div class="crm-shell mt-4">
    <aside class="crm-left">
        <form class="panel crm-form" method="post" action="<?= url('/crm') ?>">
            <?= csrf_field() ?>
            <h2>Nuevo cliente</h2>
            <p class="form-hint">Crea cliente, contacto principal y primera oportunidad en un solo paso.</p>
            <input class="form-control" name="company" placeholder="Empresa" required>
            <input class="form-control" name="contact" placeholder="Contacto" required>
            <input class="form-control" name="email" placeholder="Email">
            <input class="form-control" name="phone" placeholder="Telefono">
            <div class="row g-2">
                <div class="col"><input class="form-control" name="source" value="WhatsApp"></div>
                <div class="col">
                    <select class="form-select" name="temperature">
                        <option value="hot">Caliente</option>
                        <option value="warm" selected>Tibio</option>
                        <option value="cold">Frio</option>
                    </select>
                </div>
            </div>
            <select class="form-select" name="stage">
                <?php foreach ($stages as $stage): ?><option><?= e($stage) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" name="value" placeholder="Valor estimado" value="$0">
            <button class="btn btn-primary w-100">Crear cliente</button>
        </form>

        <form class="panel mt-3 crm-form" method="get" action="<?= url('/crm') ?>">
            <h2>Filtros</h2>
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar cliente, contacto, email o telefono">
            <select class="form-select" name="stage">
                <option value="">Todos los estados</option>
                <?php foreach ($stages as $stage): ?><option value="<?= e($stage) ?>" <?= ($filters['stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e($stage) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="temperature">
                <option value="">Todas las temperaturas</option>
                <option value="hot" <?= ($filters['temperature'] ?? '') === 'hot' ? 'selected' : '' ?>>Calientes</option>
                <option value="warm" <?= ($filters['temperature'] ?? '') === 'warm' ? 'selected' : '' ?>>Tibios</option>
                <option value="cold" <?= ($filters['temperature'] ?? '') === 'cold' ? 'selected' : '' ?>>Frios / sin respuesta</option>
            </select>
            <button class="btn btn-outline-primary w-100">Filtrar</button>
            <a class="btn btn-outline-secondary w-100" href="<?= url('/crm') ?>">Limpiar</a>
        </form>

        <div class="panel mt-3 crm-list">
            <div class="panel-title">
                <div>
                    <span class="eyebrow">Pipeline</span>
                    <h2>Clientes</h2>
                </div>
            </div>
            <?php foreach ($customers as $customer): ?>
                <a class="crm-customer <?= $selected && (int) $selected['id'] === (int) $customer['id'] ? 'active' : '' ?>" href="<?= url('/crm?customer_id=' . (int) $customer['id']) ?>">
                    <div><strong><?= e($customer['name']) ?></strong><span class="status status-<?= e($temperatureClass[$customer['temperature']] ?? 'open') ?>"><i class="bi <?= e($temperatureIcons[$customer['temperature']] ?? 'bi-circle') ?>"></i><?= e($temperatureLabels[$customer['temperature']] ?? $customer['temperature']) ?></span></div>
                    <small><span class="status status-open"><i class="bi <?= e($stageIcons[$customer['stage']] ?? 'bi-kanban') ?>"></i><?= e($customer['stage']) ?></span><span class="value-chip"><?= e($customer['value']) ?></span></small>
                    <small><span class="assignee-chip"><i class="bi bi-list-check"></i><?= e((string) $customer['pending_tasks']) ?> pendientes</span></small>
                </a>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
                <div class="empty-state compact">
                    <strong>No hay clientes para este filtro</strong>
                    <p>Cambia la busqueda o crea un cliente para activar el seguimiento comercial.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <section class="crm-detail">
        <?php if ($selected): ?>
            <div class="panel">
                <div class="crm-detail-head">
                    <div>
                        <span class="eyebrow"><?= e($selected['source']) ?> / <?= e($temperatureLabels[$selected['temperature']] ?? $selected['temperature']) ?></span>
                        <h2><?= e($selected['name']) ?></h2>
                        <p><?= e($selected['contact']) ?> · <?= e($selected['email']) ?> · <?= e($selected['phone']) ?></p>
                    </div>
                    <span class="action-status action-approved"><i class="bi <?= e($stageIcons[$selected['stage']] ?? 'bi-kanban') ?>"></i><?= e($selected['stage']) ?></span>
                </div>
                <div class="crm-detail-grid">
                    <span>Responsable <strong><?= e($selected['owner']) ?></strong></span>
                    <span>Valor abierto <strong><?= e($selected['value']) ?></strong></span>
                    <span>Ultima actividad <strong><?= e($selected['last_activity_at'] ?? 'Sin actividad') ?></strong></span>
                    <span>Proximo seguimiento <strong><?= e($selected['next_follow_up_at'] ?? 'Sin fecha') ?></strong></span>
                </div>
                <form class="status-toolbar mt-3" method="post" action="<?= url('/crm/state') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="customer_id" value="<?= e((string) $selected['id']) ?>">
                    <select class="form-select" name="stage">
                        <?php foreach ($stages as $stage): ?><option value="<?= e($stage) ?>" <?= $selected['stage'] === $stage ? 'selected' : '' ?>><?= e($stage) ?></option><?php endforeach; ?>
                    </select>
                    <select class="form-select" name="temperature">
                        <?php foreach (['hot' => 'Caliente', 'warm' => 'Tibio', 'cold' => 'Frio'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $selected['temperature'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary">Actualizar estado</button>
                </form>
            </div>

            <div class="crm-columns mt-4">
                <div class="panel">
                    <div class="panel-title"><div><span class="eyebrow">Venta</span><h2>Oportunidades</h2></div></div>
                    <?php foreach ($selected['opportunities'] as $opportunity): ?>
                        <article class="crm-mini-card">
                            <strong><?= e($opportunity['title']) ?></strong>
                            <span class="chip-line"><span class="status status-open"><i class="bi bi-kanban"></i><?= e($opportunityStageLabels[$opportunity['stage']] ?? $opportunity['stage']) ?></span><span class="value-chip"><?= e($opportunity['amount_label']) ?></span><span class="assignee-chip"><i class="bi bi-percent"></i><?= e((string) $opportunity['probability']) ?>%</span></span>
                        </article>
                    <?php endforeach; ?>
                    <form class="crm-form mt-3" method="post" action="<?= url('/crm/opportunity') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="customer_id" value="<?= e((string) $selected['id']) ?>">
                        <input class="form-control" name="title" placeholder="Nueva oportunidad">
                        <div class="row g-2">
                            <div class="col"><input class="form-control" type="number" name="amount" placeholder="Monto"></div>
                            <div class="col"><input class="form-control" type="number" name="probability" value="30"></div>
                        </div>
                        <select class="form-select" name="stage">
                            <option value="nuevo">Nuevo</option><option value="calificado">Calificado</option><option value="propuesta">Propuesta</option><option value="negociacion">Negociacion</option><option value="ganado">Ganado</option><option value="perdido">Perdido</option>
                        </select>
                        <button class="btn btn-outline-primary">Agregar oportunidad</button>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-title"><div><span class="eyebrow">Seguimiento</span><h2>Tareas</h2></div></div>
                    <?php foreach ($selected['tasks'] as $task): ?>
                        <article class="crm-mini-card">
                            <strong><?= e($task['title']) ?></strong>
                            <span class="chip-line"><span class="status status-<?= e($task['status'] === 'completed' ? 'answered' : 'pending_approval') ?>"><i class="bi <?= e($taskStatusIcons[$task['status']] ?? 'bi-circle') ?>"></i><?= e($taskStatusLabels[$task['status']] ?? $task['status']) ?></span><span class="priority priority-<?= e($task['priority']) ?>"><i class="bi <?= e($priorityIcons[$task['priority']] ?? 'bi-dot') ?>"></i><?= e($priorityLabels[$task['priority']] ?? $task['priority']) ?></span><span class="assignee-chip"><i class="bi bi-calendar3"></i><?= e($task['due_at'] ?? 'Sin fecha') ?></span></span>
                            <?php if ($task['status'] === 'pending'): ?>
                                <form method="post" action="<?= url('/crm/task/complete') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="customer_id" value="<?= e((string) $selected['id']) ?>">
                                    <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
                                    <button class="btn btn-sm btn-outline-success">Completar</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <form class="crm-form mt-3" method="post" action="<?= url('/crm/task') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="customer_id" value="<?= e((string) $selected['id']) ?>">
                        <input class="form-control" name="title" placeholder="Nueva tarea">
                        <input class="form-control" type="datetime-local" name="due_at">
                        <button class="btn btn-outline-primary">Agregar tarea</button>
                    </form>
                </div>
            </div>

            <div class="crm-columns mt-4">
                <div class="panel">
                    <div class="panel-title"><div><span class="eyebrow">Contexto</span><h2>Notas</h2></div></div>
                    <form class="crm-form" method="post" action="<?= url('/crm/note') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="customer_id" value="<?= e((string) $selected['id']) ?>">
                        <textarea class="form-control" name="note" rows="3" placeholder="Agregar nota comercial"></textarea>
                        <button class="btn btn-outline-primary">Guardar nota</button>
                    </form>
                    <?php foreach ($selected['notes'] as $note): ?>
                        <article class="crm-mini-card"><strong><?= e($note['user_name'] ?? 'Usuario') ?></strong><span><?= e($note['created_at']) ?></span><p><?= e($note['note']) ?></p></article>
                    <?php endforeach; ?>
                </div>

                <div class="panel">
                    <div class="panel-title"><div><span class="eyebrow">Historial</span><h2>Actividad</h2></div></div>
                    <?php foreach ($selected['activities'] as $activity): ?>
                        <article class="crm-activity"><span><?= e($activity['activity_type']) ?></span><strong><?= e($activity['summary']) ?></strong><small><?= e($activity['created_at']) ?></small></article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="panel empty-state">
                <strong>Selecciona o crea un cliente</strong>
                <p>El CRM mostrara contactos, oportunidades, tareas, notas y actividad comercial en esta zona.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
