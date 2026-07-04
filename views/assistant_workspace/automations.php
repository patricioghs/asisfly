<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Asistente</span>
        <h2>Automatizaciones</h2>
        <p>Motor de reglas para que AsisFly ejecute procesos con control humano cuando corresponda: seguimiento, respuestas, tareas, reportes y acciones comerciales.</p>
    </div>
    <div class="launch-next">
        <span>Activas</span>
        <strong><?= e((string) count(array_filter($rules, fn (array $rule): bool => (bool) ($rule['is_active'] ?? false)))) ?> reglas</strong>
        <small>Preparadas para conectar eventos reales por canal.</small>
    </div>
</section>

<section class="automation-rules-grid mt-4">
    <?php foreach ($rules as $rule): ?>
        <article class="panel automation-rule-card">
            <div class="panel-title">
                <div>
                    <span class="eyebrow"><?= !empty($rule['is_active']) ? 'Activa' : 'Pausada' ?></span>
                    <h2><?= e($rule['name'] ?? 'Automatizacion') ?></h2>
                </div>
                <span class="soft-badge"><?= !empty($rule['requires_approval']) ? 'Requiere aprobacion' : 'Automatica' ?></span>
            </div>
            <div class="automation-flow">
                <span>Si ocurre</span>
                <strong><?= e($rule['trigger'] ?? 'Evento no definido') ?></strong>
            </div>
            <div class="automation-flow">
                <span>Entonces</span>
                <strong><?= e($rule['action'] ?? 'Accion no definida') ?></strong>
            </div>
        </article>
    <?php endforeach; ?>
</section>
