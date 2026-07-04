<?php
$categoryLabels = ['financial' => 'Financiero', 'commercial' => 'Comercial', 'operations' => 'Operacional', 'administrative' => 'Administrativo', 'inventory' => 'Inventario', 'custom' => 'Personalizado'];
$statusLabels = ['draft' => 'Configurando', 'active' => 'Activo', 'paused' => 'Pausado', 'archived' => 'Archivado'];
$frequencyLabels = ['daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual', 'on_demand' => 'A demanda'];
$sourceLabels = ['manual' => 'Manual', 'spreadsheet' => 'Planilla', 'documents' => 'Documentos', 'email' => 'Correo', 'integration' => 'Integracion', 'mixed' => 'Mixto'];
$severityLabels = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$selected = $selected ?? null;
$selectedId = (int) ($selected['id'] ?? 0);
?>

<section class="panel controls-hero">
    <div>
        <span class="eyebrow">Asistente operativo</span>
        <h2>Controles</h2>
        <p>Convierte instrucciones como "llevemos control de gastos" en procesos vivos con datos, reglas, alertas, responsables y reportes.</p>
        <div class="controls-hero-actions">
            <a class="btn btn-primary" href="<?= url('/chat') ?>"><i class="bi bi-stars"></i> Pedir a AsisFly</a>
            <a class="btn btn-outline-secondary" href="#new-control"><i class="bi bi-plus-circle"></i> Crear control</a>
        </div>
    </div>
    <div class="controls-command-card">
        <span>Ejemplo de instruccion</span>
        <strong>"AsisFly, desde hoy llevaremos el control de gastos"</strong>
        <p>El sistema crea un control, define reglas iniciales y deja tareas/alertas en la bandeja de trabajo.</p>
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

<form class="panel controls-filter-bar mt-4" method="get" action="<?= url('/controls') ?>">
    <input class="form-control" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Buscar control, objetivo o proceso">
    <select class="form-select" name="category">
        <option value="">Todas las categorias</option>
        <?php foreach ($categoryLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['category'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
        <option value="">Todos los estados</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i>Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= url('/controls') ?>">Limpiar</a>
</form>

<section class="controls-shell mt-4">
    <aside class="panel controls-list-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Procesos vivos</span>
                <h2>Controles activos</h2>
            </div>
            <span class="soft-badge"><?= e((string) count($controls)) ?></span>
        </div>
        <div class="controls-list">
            <?php foreach ($controls as $control): ?>
                <?php $isActive = (int) ($control['id'] ?? 0) === $selectedId; ?>
                <a class="control-list-item <?= $isActive ? 'active' : '' ?>" href="<?= url('/controls?control_id=' . (int) ($control['id'] ?? 0)) ?>">
                    <span class="control-list-icon"><i class="bi <?= e((string) ($control['icon'] ?? 'bi-sliders')) ?>"></i></span>
                    <div>
                        <strong><?= e((string) ($control['name'] ?? 'Control')) ?></strong>
                        <small><?= e($categoryLabels[$control['category'] ?? 'custom'] ?? 'Personalizado') ?> / <?= e($frequencyLabels[$control['frequency'] ?? 'weekly'] ?? 'Semanal') ?></small>
                    </div>
                    <em class="status status-<?= e((string) ($control['status'] ?? 'draft')) ?>"><?= e($statusLabels[$control['status'] ?? 'draft'] ?? 'Configurando') ?></em>
                </a>
            <?php endforeach; ?>
        </div>

        <form id="new-control" class="control-create-form" method="post" action="<?= url('/controls') ?>">
            <?= csrf_field() ?>
            <span class="eyebrow">Nuevo control</span>
            <input class="form-control" name="name" placeholder="Ej: Control de gastos" required>
            <textarea class="form-control" name="objective" rows="3" placeholder="Objetivo del control"></textarea>
            <select class="form-select" name="category">
                <?php foreach ($categoryLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="control-create-grid">
                <select class="form-select" name="frequency">
                    <?php foreach ($frequencyLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" name="source_type">
                    <?php foreach ($sourceLabels as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i>Crear control</button>
        </form>
    </aside>

    <main class="controls-detail-panel">
        <?php if ($selected): ?>
            <article class="panel control-detail-hero">
                <div class="control-detail-title">
                    <span class="control-list-icon large"><i class="bi <?= e((string) ($selected['icon'] ?? 'bi-sliders')) ?>"></i></span>
                    <div>
                        <span class="eyebrow"><?= e($categoryLabels[$selected['category'] ?? 'custom'] ?? 'Personalizado') ?></span>
                        <h2><?= e((string) ($selected['name'] ?? 'Control')) ?></h2>
                        <p><?= e((string) ($selected['objective'] ?? 'Control operativo de la empresa.')) ?></p>
                    </div>
                </div>
                <div class="control-detail-actions">
                    <form method="post" action="<?= url('/controls/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="control_id" value="<?= e((string) $selectedId) ?>">
                        <select class="form-select form-select-sm" name="status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($selected['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Actualizar</button>
                    </form>
                </div>
            </article>

            <section class="controls-insight-grid mt-4">
                <article class="panel control-insight-card">
                    <span>Frecuencia</span>
                    <strong><?= e($frequencyLabels[$selected['frequency'] ?? 'weekly'] ?? 'Semanal') ?></strong>
                    <small>Proxima revision: <?= e((string) ($selected['next_review_at'] ?? 'Sin fecha')) ?></small>
                </article>
                <article class="panel control-insight-card">
                    <span>Fuente de datos</span>
                    <strong><?= e($sourceLabels[$selected['source_type'] ?? 'manual'] ?? 'Manual') ?></strong>
                    <small>Puede evolucionar a integracion real.</small>
                </article>
                <article class="panel control-insight-card">
                    <span>Progreso</span>
                    <strong><?= e((string) (($selected['metrics']['progress'] ?? 12))) ?>%</strong>
                    <small>Madurez del control dentro de la empresa.</small>
                </article>
            </section>

            <section class="controls-detail-grid mt-4">
                <article class="panel">
                    <div class="panel-title">
                        <div><span class="eyebrow">Alertas</span><h2>Requieren revision</h2></div>
                    </div>
                    <div class="control-alert-list">
                        <?php foreach ($alerts as $alert): ?>
                            <div class="control-alert severity-<?= e((string) ($alert['severity'] ?? 'medium')) ?>">
                                <div>
                                    <span><?= e($severityLabels[$alert['severity'] ?? 'medium'] ?? 'Media') ?></span>
                                    <strong><?= e((string) ($alert['title'] ?? 'Alerta')) ?></strong>
                                    <p><?= e((string) ($alert['body'] ?? '')) ?></p>
                                </div>
                                <?php if (($alert['status'] ?? '') !== 'resolved'): ?>
                                    <form method="post" action="<?= url('/controls/alert') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="control_id" value="<?= e((string) $selectedId) ?>">
                                        <input type="hidden" name="alert_id" value="<?= e((string) ($alert['id'] ?? 0)) ?>">
                                        <button class="btn btn-sm btn-light"><i class="bi bi-check2"></i>Resolver</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$alerts): ?><p class="task-muted">Sin alertas abiertas para este control.</p><?php endif; ?>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-title">
                        <div><span class="eyebrow">Datos</span><h2>Entradas recientes</h2></div>
                    </div>
                    <form class="control-entry-form" method="post" action="<?= url('/controls/entry') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="control_id" value="<?= e((string) $selectedId) ?>">
                        <input class="form-control" name="title" placeholder="Ej: Factura proveedor X" required>
                        <div class="control-create-grid">
                            <input class="form-control" name="amount" type="number" step="0.01" placeholder="Monto">
                            <input class="form-control" name="period_label" placeholder="Periodo">
                        </div>
                        <input class="form-control" name="note" placeholder="Nota o contexto">
                        <button class="btn btn-outline-primary">Agregar entrada</button>
                    </form>
                    <div class="control-entry-list">
                        <?php foreach ($entries as $entry): ?>
                            <div>
                                <strong><?= e((string) ($entry['title'] ?? 'Entrada')) ?></strong>
                                <span><?= e((string) ($entry['period_label'] ?? 'Sin periodo')) ?> / <?= e((string) ($entry['status'] ?? 'pending')) ?></span>
                                <?php if (!empty($entry['amount'])): ?><small><?= e((string) ($entry['currency'] ?? ($company['currency'] ?? 'CLP'))) ?> <?= e(number_format((float) $entry['amount'], 0, ',', '.')) ?></small><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$entries): ?><p class="task-muted">Aun no hay entradas. Puedes partir con una carga manual.</p><?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="panel mt-4">
                <div class="panel-title"><div><span class="eyebrow">Reglas y memoria operativa</span><h2>Como trabaja este control</h2></div></div>
                <div class="control-rules-grid">
                    <?php foreach (($selected['rules'] ?? []) as $key => $value): ?>
                        <span><strong><?= e((string) $key) ?></strong><?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($selected['rules'])): ?><span><strong>Reglas</strong>Se configuraran al cargar datos.</span><?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="panel empty-state compact"><strong>No hay controles todavia</strong><p>Crea el primer control para que AsisFly empiece a organizar un proceso de la empresa.</p></div>
        <?php endif; ?>
    </main>
</section>
