<?php
$metricMap = [];
foreach ($metrics as $metric) {
    $metricMap[$metric['label']] = $metric;
}
$kpis = [
    ['Ventas del dia', '$2.580.000', '18% vs ayer', 'bi-currency-dollar', 'success'],
    ['Mensajes respondidos', $metricMap['Mensajes respondidos']['value'] ?? 86, '12% vs ayer', 'bi-chat-dots', 'info'],
    ['Nuevos clientes', $metricMap['Clientes contactados']['value'] ?? 14, '7% vs ayer', 'bi-person-plus', 'primary'],
    ['Reuniones hoy', $metricMap['Reuniones agendadas']['value'] ?? 3, 'Hoy 2 pendientes', 'bi-calendar-event', 'warning'],
    ['Tareas pendientes', $metricMap['Tareas creadas']['value'] ?? 12, '6 urgentes', 'bi-list-check', 'dark'],
];
$activity = [
    ['bi-whatsapp', 'Nuevo mensaje de Maria Gonzalez', 'Hola! Quisiera mas informacion...', '10:24', 'success'],
    ['bi-envelope-at', 'Correo de Proveedor ABC', 'Nueva orden de compra #1258', '09:15', 'danger'],
    ['bi-calendar2-check', 'Reunion con Constructora Norte', 'Hoy 15:00 - 16:00', '08:45', 'primary'],
    ['bi-file-earmark-spreadsheet', 'Reporte de ventas generado', 'Venta_Mayo_2026.pdf', '08:30', 'success'],
    ['bi-receipt', 'Cotizacion enviada a Cliente XYZ', 'Cotizacion #COT-00045', '08:12', 'primary'],
];
$recommendations = [
    ['bi-exclamation-circle', 'Hay 8 clientes que pidieron informacion y no han respondido.', 'warning'],
    ['bi-calendar-check', 'Tienes 2 cotizaciones por vencer hoy.', 'primary'],
    ['bi-instagram', 'Publica en Instagram: es el mejor momento para generar alcance.', 'danger'],
    ['bi-graph-down-arrow', 'Las ventas de este producto bajaron 18% respecto al mes pasado.', 'info'],
];
$integrations = [
    ['Gmail', 'bi-google', 'Conectado'],
    ['Calendar', 'bi-calendar3', 'Conectado'],
    ['WhatsApp', 'bi-whatsapp', 'Conectado'],
    ['Instagram', 'bi-instagram', 'Conectado'],
    ['Facebook', 'bi-facebook', 'Conectado'],
];
$autonomy = $autonomy ?? [
    'learning_progress' => 18,
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
        <span><small>Ventas</small><strong>$18.650.000</strong><em>16%</em></span>
        <span><small>Mensajes</small><strong>432</strong><em>11%</em></span>
        <span><small>Nuevos clientes</small><strong>37</strong><em>15%</em></span>
        <span><small>Conversion</small><strong>8,6%</strong><em>6%</em></span>
    </div>
    <div class="chart-card" aria-label="Grafico de rendimiento general">
        <div class="chart-grid"></div>
        <svg viewBox="0 0 900 230" role="img" aria-label="Ventas y mensajes ultimos 7 dias">
            <polyline class="chart-line chart-line-blue" points="0,170 120,150 240,135 360,95 480,70 600,88 720,142 900,92"></polyline>
            <polyline class="chart-line chart-line-green" points="0,190 120,160 240,178 360,142 480,135 600,130 720,72 900,104"></polyline>
        </svg>
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
        <?php foreach ($integrations as [$name, $icon, $status]): ?>
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
