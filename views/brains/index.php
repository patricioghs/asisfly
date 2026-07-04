<?php
$statusLabels = ['active' => 'Activo', 'sandbox' => 'Prueba', 'paused' => 'Pausado'];
$statusIcons = ['active' => 'bi-check2-circle', 'sandbox' => 'bi-flask', 'paused' => 'bi-pause-circle'];
$brainIcons = ['commercial' => 'bi-graph-up-arrow', 'administrative' => 'bi-kanban', 'analytical' => 'bi-bar-chart-line', 'operational' => 'bi-hdd-network', 'executive' => 'bi-compass'];
$riskRules = [
    'commercial' => 'Pide aprobacion antes de enviar mensajes, descuentos, cotizaciones o promesas comerciales.',
    'administrative' => 'Pide aprobacion antes de mover documentos, responder correos sensibles o modificar agenda critica.',
    'analytical' => 'No modifica datos; entrega diagnosticos, alertas e informes para revision humana.',
    'operational' => 'Pide aprobacion antes de confirmar despachos, compras, pedidos u operaciones conectadas.',
    'executive' => 'Recomienda prioridades y riesgos; no ejecuta acciones sin regla explicita.',
];
$escalationRules = [
    'commercial' => 'Cliente molesto, negociacion delicada, reclamo, garantia, descuento alto o oportunidad importante.',
    'administrative' => 'Correo legal, pago urgente, agenda de gerencia, documento confidencial o solicitud ambigua.',
    'analytical' => 'Dato inconsistente, anomalia grave, margen negativo o conclusion con baja confianza.',
    'operational' => 'Pedido bloqueado, costo excedido, falta de materiales, atraso o proveedor critico.',
    'executive' => 'Riesgo financiero, cliente clave esperando, margen cayendo o decision estrategica.',
];
?>

<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Modos de trabajo IA</span>
        <h2>Cerebros IA</h2>
        <p>Define como trabaja AsisFly segun objetivo, cuentas conectadas, acciones permitidas, autonomia y criterios de escalamiento. El conocimiento se carga en Memoria.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= url('/documents') ?>"><i class="bi bi-database-check"></i> Ir a Memoria</a>
</section>

<section class="brain-brief panel mt-4">
    <div>
        <span class="eyebrow">Cerebro Ejecutivo</span>
        <h2>Resumen de gerente diario</h2>
    </div>
    <div class="brief-grid">
        <?php foreach ($morningBrief as $item): ?>
            <div><?= e($item) ?></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel autonomy-panel mt-4">
    <div>
        <span class="eyebrow">Autonomia por empresa</span>
        <h2><?= e($autonomy['label'] ?? 'Aprendizaje supervisado') ?></h2>
        <p><?= e($autonomy['description'] ?? 'AsisFly aprende con aprobaciones, modificaciones y comentarios del equipo.') ?></p>
    </div>
    <div class="autonomy-progress" style="--progress: <?= e((string) ($autonomy['learning_progress'] ?? 18)) ?>%;">
        <div><span></span></div>
        <strong><?= e((string) ($autonomy['learning_progress'] ?? 18)) ?>%</strong>
    </div>
    <div class="autonomy-stage-list">
        <span class="<?= (($autonomy['learning_progress'] ?? 18) <= 25) ? 'active' : '' ?>">0-25 Supervisado</span>
        <span class="<?= (($autonomy['learning_progress'] ?? 18) > 25 && ($autonomy['learning_progress'] ?? 18) <= 60) ? 'active' : '' ?>">26-60 Copiloto</span>
        <span class="<?= (($autonomy['learning_progress'] ?? 18) > 60 && ($autonomy['learning_progress'] ?? 18) <= 85) ? 'active' : '' ?>">61-85 Controlado</span>
        <span class="<?= (($autonomy['learning_progress'] ?? 18) > 85) ? 'active' : '' ?>">86-100 Autonomo</span>
    </div>
</section>

<form class="panel brain-filter-bar mt-4" method="get" action="<?= url('/brains') ?>">
    <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar objetivo, accion o senal">
    <select class="form-select" name="status">
        <option value="">Todos los estados</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="channel">
        <option value="">Todos los canales</option>
        <?php foreach ($channels as $channel): ?>
            <option value="<?= e($channel) ?>" <?= ($filters['channel'] ?? '') === $channel ? 'selected' : '' ?>><?= e($channel) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="account_id">
        <option value="">Todas las cuentas</option>
        <?php foreach ($accounts as $account): ?>
            <option value="<?= e((string) $account['id']) ?>" <?= (string) ($filters['account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>><?= e($account['display_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= url('/brains') ?>">Limpiar</a>
</form>

<section class="brain-grid mt-4">
    <?php foreach ($brains as $brain): ?>
        <?php $assignedAccounts = $accountsByBrain[$brain['key']] ?? []; ?>
        <article class="panel brain-card brain-<?= e($brain['key']) ?>">
            <div class="brain-head">
                <div class="brain-title-row">
                    <span class="account-icon"><i class="bi <?= e($brainIcons[$brain['key']] ?? 'bi-diagram-3') ?>"></i></span>
                    <div>
                        <span class="brain-kicker"><?= e($brain['primary_metric']) ?></span>
                        <h2><?= e($brain['name']) ?></h2>
                    </div>
                </div>
                <span class="status status-<?= e($brain['status']) ?>"><i class="bi <?= e($statusIcons[$brain['status']] ?? 'bi-circle') ?>"></i><?= e($statusLabels[$brain['status']] ?? $brain['status']) ?></span>
            </div>
            <p class="brain-mission"><?= e($brain['mission']) ?></p>

            <div class="brain-config-grid">
                <div class="brain-section">
                    <strong>Objetivo</strong>
                    <p><?= e($brain['primary_metric']) ?></p>
                </div>
                <div class="brain-section">
                    <strong>Cuentas asignadas</strong>
                    <div class="chip-row">
                        <?php foreach ($assignedAccounts as $account): ?>
                            <span><?= e($account['display_name']) ?></span>
                        <?php endforeach; ?>
                        <?php if (!$assignedAccounts): ?><span>Sin cuentas directas</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="brain-section">
                <strong>Puede hacer</strong>
                <ul>
                    <?php foreach ($brain['actions'] as $action): ?><li><?= e($action) ?></li><?php endforeach; ?>
                </ul>
            </div>

            <div class="brain-section">
                <strong>Detecta</strong>
                <div class="signal-list">
                    <?php foreach ($brain['signals'] as $signal): ?><span><?= e($signal) ?></span><?php endforeach; ?>
                </div>
            </div>

            <div class="brain-rules">
                <div>
                    <strong>Regla de aprobacion</strong>
                    <span><?= e($riskRules[$brain['key']] ?? 'Pide aprobacion para acciones sensibles.') ?></span>
                </div>
                <div>
                    <strong>Escala a humano si</strong>
                    <span><?= e($escalationRules[$brain['key']] ?? 'Hay riesgo, baja confianza o impacto sensible.') ?></span>
                </div>
            </div>

            <div class="brain-example">
                <?= e($brain['examples'][0] ?? 'Listo para trabajar segun las reglas de la empresa.') ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!$brains): ?>
    <section class="panel empty-state mt-4">
        <strong>No hay cerebros para este filtro</strong>
        <p>Cambia los filtros o revisa las cuentas conectadas asignadas a cada modo de trabajo.</p>
    </section>
<?php endif; ?>
