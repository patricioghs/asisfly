<?php
$metricMap = [];
foreach ($metrics as $metric) {
    $metricMap[$metric['label']] = $metric;
}
$kpis = [
    ['Ventas del dia', '$0', 'Sin ventas registradas hoy', 'bi-currency-dollar', 'success'],
    ['Mensajes respondidos', $metricMap['Mensajes respondidos']['value'] ?? 0, 'Desde omnicanal', 'bi-chat-dots', 'info'],
    ['Nuevos clientes', $metricMap['Clientes contactados']['value'] ?? 0, 'Desde CRM', 'bi-person-plus', 'primary'],
    ['Reuniones hoy', $metricMap['Reuniones agendadas']['value'] ?? 0, 'Desde calendario', 'bi-calendar-event', 'warning'],
    ['Tareas pendientes', $metricMap['Tareas creadas']['value'] ?? 0, 'Tareas abiertas', 'bi-list-check', 'dark'],
];
$activity = [];
$recommendations = array_values(array_filter([
    ($metricMap['Documentos analizados']['value'] ?? 0) == 0 ? ['bi-database-add', 'Carga documentos reales para que AsisFly empiece a aprender de la empresa.', 'primary'] : null,
    ($metricMap['Clientes contactados']['value'] ?? 0) == 0 ? ['bi-people', 'Agrega clientes al CRM para activar seguimiento comercial.', 'info'] : null,
    ($metricMap['Mensajes respondidos']['value'] ?? 0) == 0 ? ['bi-inboxes', 'Conecta o registra canales para centralizar mensajes reales.', 'warning'] : null,
]));
$integrations = $integrations ?? [];
$autonomy = $autonomy ?? [
    'learning_progress' => 0,
    'label' => 'Aprendizaje supervisado',
    'range' => '0-25%',
    'description' => 'AsisFly aprende como funciona la empresa. Todo lo que sugiera requiere aprobacion humana.',
    'approvals_count' => 0,
    'corrections_count' => 0,
    'requires_all_approval' => true,
];
?>

<section class="dashboard-hero">
    <div>
        <span class="eyebrow">Resumen general de tu empresa</span>
        <h2>Dashboard</h2>
        <p>AsisFly monitorea ventas, mensajes, clientes, documentos y tareas para ayudarte a decidir mejor cada dia.</p>
    </div>
    <div class="hero-status autonomy-mini">
        <span><?= e($autonomy['label']) ?></span>
        <strong><?= e((string) $autonomy['learning_progress']) ?>%</strong>
        <small><?= e($autonomy['range']) ?> · <?= !empty($autonomy['requires_all_approval']) ? 'Aprobacion obligatoria' : 'Reglas activas' ?></small>
    </div>
</section>

<section class="panel autonomy-panel">
    <div>
        <span class="eyebrow">Entrenamiento empresarial</span>
        <h2>Nivel de autonomia de AsisFly</h2>
        <p><?= e($autonomy['description']) ?></p>
    </div>
    <div class="autonomy-progress" style="--progress: <?= e((string) $autonomy['learning_progress']) ?>%;">
        <div><span></span></div>
        <strong><?= e((string) $autonomy['learning_progress']) ?>%</strong>
    </div>
    <div class="autonomy-stats">
        <span><strong><?= e((string) $autonomy['approvals_count']) ?></strong>Aprobaciones</span>
        <span><strong><?= e((string) $autonomy['corrections_count']) ?></strong>Correcciones</span>
        <span><strong><?= e((string) $autonomy['autonomous_actions_count']) ?></strong>Autonomas</span>
    </div>
</section>

<section class="command-bar">
    <i class="bi bi-stars"></i>
    <input type="text" value="" placeholder="¿Que quieres hacer hoy?" aria-label="¿Que quieres hacer hoy?">
    <a class="btn btn-primary" href="<?= url('/chat') ?>">Preguntar a AsisFly <i class="bi bi-send"></i></a>
</section>

<section class="dashboard-kpis">
    <?php foreach ($kpis as [$label, $value, $trend, $icon, $tone]): ?>
        <article class="kpi-card">
            <div class="kpi-icon tone-<?= e($tone) ?>"><i class="bi <?= e($icon) ?>"></i></div>
            <span><?= e((string) $label) ?></span>
            <strong><?= e((string) $value) ?></strong>
            <small><?= e((string) $trend) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <article class="panel activity-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Operacion diaria</span>
                <h2>Actividad reciente</h2>
            </div>
            <a href="<?= url('/actions') ?>">Ver todo <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php foreach ($activity as [$icon, $title, $text, $time, $tone]): ?>
            <div class="activity-row">
                <i class="bi <?= e($icon) ?> tone-<?= e($tone) ?>"></i>
                <div>
                    <strong><?= e($title) ?></strong>
                    <span><?= e($text) ?></span>
                </div>
                <time><?= e($time) ?></time>
            </div>
        <?php endforeach; ?>
        <?php if (empty($activity)): ?>
            <div class="empty-state">
                <i class="bi bi-clock-history"></i>
                <strong>Sin actividad reciente</strong>
                <span>Cuando existan mensajes, tareas, documentos o aprobaciones reales, apareceran aqui.</span>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel recommendation-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Cerebro ejecutivo</span>
                <h2>AsisFly te recomienda</h2>
            </div>
            <a href="<?= url('/chat') ?>">Abrir IA <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php foreach ($recommendations as [$icon, $text, $tone]): ?>
            <div class="recommendation-row">
                <i class="bi <?= e($icon) ?> tone-<?= e($tone) ?>"></i>
                <strong><?= e($text) ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if (empty($recommendations)): ?>
            <div class="empty-state">
                <i class="bi bi-stars"></i>
                <strong>Sin recomendaciones aun</strong>
                <span>AsisFly generara recomendaciones cuando tenga actividad y datos de la empresa.</span>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="panel performance-panel">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Inteligencia comercial</span>
            <h2>Rendimiento general</h2>
        </div>
        <span class="soft-badge">Ultimos 7 dias</span>
    </div>
    <div class="performance-summary">
        <span><small>Ventas</small><strong>$0</strong><em>Sin datos</em></span>
        <span><small>Mensajes</small><strong><?= e((string) ($metricMap['Mensajes respondidos']['value'] ?? 0)) ?></strong><em>Real</em></span>
        <span><small>Nuevos clientes</small><strong><?= e((string) ($metricMap['Clientes contactados']['value'] ?? 0)) ?></strong><em>Real</em></span>
        <span><small>Conversion</small><strong>0%</strong><em>Sin datos</em></span>
    </div>
    <div class="chart-card" aria-label="Grafico de rendimiento general">
        <div class="chart-grid"></div>
        <div class="empty-state compact">
            <strong>Sin datos para graficar</strong>
            <p>Cuando registres ventas, mensajes o clientes, el rendimiento aparecera aqui.</p>
        </div>
    </div>
</section>

<section class="panel integrations-panel">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Canales conectados</span>
            <h2>Integraciones</h2>
        </div>
        <a href="<?= url('/integrations') ?>">Ver todas <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="integration-strip">
        <?php foreach ($integrations as $integration): ?>
            <?php
                $name = $integration['name'] ?? 'Integracion';
                $status = $integration['status'] ?? 'Pendiente';
                $icon = match (true) {
                    str_contains(strtolower((string) $name), 'whatsapp') => 'bi-whatsapp',
                    str_contains(strtolower((string) $name), 'instagram') => 'bi-instagram',
                    str_contains(strtolower((string) $name), 'facebook') => 'bi-facebook',
                    str_contains(strtolower((string) $name), 'calendar') => 'bi-calendar3',
                    str_contains(strtolower((string) $name), 'gmail') => 'bi-google',
                    default => 'bi-plug',
                };
            ?>
            <span><i class="bi <?= e($icon) ?>"></i><strong><?= e($name) ?></strong><small><?= e($status) ?></small></span>
        <?php endforeach; ?>
        <a href="<?= url('/integrations') ?>"><i class="bi bi-plus-lg"></i><strong>Agregar</strong><small>Nuevo canal</small></a>
    </div>
</section>

<section class="always-on-banner">
    <div class="bot-avatar"><i class="bi bi-robot"></i></div>
    <div>
        <strong>AsisFly esta trabajando para ti 24/7</strong>
        <span>Tu asistente digital analiza, responde, vende y te ayuda a crecer cada dia.</span>
    </div>
    <a class="btn btn-primary" href="<?= url('/chat') ?>">Hablar con AsisFly</a>
</section>
