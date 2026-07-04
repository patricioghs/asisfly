<?php
$user = $_SESSION['user'] ?? null;
$company = $_SESSION['company'] ?? null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navigation = [
    'Inicio' => [
        ['/dashboard', 'Dashboard', 'bi-grid-1x2', null],
        ['/my-day', 'Mi dia', 'bi-calendar2-check', null],
        ['/workbench', 'Bandeja de trabajo', 'bi-briefcase', '5'],
        ['/notifications', 'Notificaciones', 'bi-bell', '8'],
    ],
    'Asistente' => [
        ['/chat', 'Chat IA', 'bi-stars', null],
        ['/tasks', 'Tareas', 'bi-list-task', '4'],
        ['/automations', 'Automatizaciones', 'bi-magic', null],
        ['/actions', 'Aprobaciones', 'bi-check2-square', '3'],
        ['/controls', 'Controles', 'bi-sliders', null],
        ['/documents', 'Memoria', 'bi-database-check', null],
        ['/assistant', 'Reglas', 'bi-sliders2', null],
    ],
    'Comunicacion' => [
        ['/inbox', 'Omnicanal', 'bi-inboxes', '8'],
        ['/social', 'Asisti Social', 'bi-megaphone', null],
    ],
    'Comercial' => [
        ['/crm', 'CRM', 'bi-people', null],
        ['/quotes', 'Cotizaciones', 'bi-file-earmark-text', null],
    ],
    'Inteligencia' => [
        ['/reports', 'Reportes', 'bi-clipboard-data', null],
        ['/analytics', 'Analytics', 'bi-graph-up-arrow', null],
        ['/dashboards', 'Dashboards', 'bi-columns-gap', null],
        ['/intelligence-documents', 'Documentos', 'bi-file-earmark-text', null],
        ['/excel', 'Excel', 'bi-file-earmark-spreadsheet', null],
        ['/indicators', 'Indicadores', 'bi-bullseye', null],
        ['/kpis', 'KPIs', 'bi-speedometer2', null],
        ['/brains', 'Cerebros IA', 'bi-diagram-3', null],
    ],
    'Conocimiento' => [
        ['/knowledge-base', 'Base de conocimiento', 'bi-book', null],
        ['/documents', 'Documentos', 'bi-file-earmark', null],
        ['/catalogs', 'Catalogos', 'bi-folder2-open', null],
        ['/manuals', 'Manuales', 'bi-journal-text', null],
        ['/training', 'Entrenamiento', 'bi-mortarboard', null],
    ],
    'Integraciones' => [
        ['/integrations', 'Integraciones', 'bi-diagram-3', null],
        ['/integrations/accounts', 'Cuentas conectadas', 'bi-plug', null],
        ['/apis', 'APIs', 'bi-code-slash', null],
    ],
    'Empresa' => [
        ['/company', 'Empresa', 'bi-building', null],
        ['/users', 'Usuarios', 'bi-people', null],
        ['/roles', 'Roles', 'bi-shield-lock', null],
        ['/billing', 'Plan y facturacion', 'bi-credit-card', null],
        ['/company-settings', 'Configuracion', 'bi-gear', null],
    ],
    'Administracion (Superadmin)' => [
        ['/admin/companies', 'Empresas', 'bi-buildings', null],
        ['/admin/plans', 'Planes', 'bi-check2-square', null],
        ['/admin/ai-tokens', 'IA y tokens', 'bi-cpu', null],
        ['/admin/audit-logs', 'Auditoria y logs', 'bi-file-lock', null],
        ['/admin/system-status', 'Estado del sistema', 'bi-record-circle', null],
    ],
];
?>
<!doctype html>
<html lang="es" data-bs-theme="<?= e($_COOKIE['AsisFly_theme'] ?? 'light') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'AsisFly') . ' | AsisFly') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/theme.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="sidebar">
            <div class="sidebar-head">
                <a class="brand" href="<?= url('/dashboard') ?>"><span>A</span><strong>AsisFly</strong></a>
                <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Cerrar menu"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="company-pill">
                <i class="bi bi-building-check"></i>
                <div>
                    <strong><?= e($company['name'] ?? 'Empresa') ?></strong>
                    <small><?= e(($company['plan'] ?? 'Starter') . ' - ' . ($company['country'] ?? 'LatAm')) ?></small>
                </div>
                <i class="bi bi-chevron-down"></i>
            </div>
            <nav class="nav flex-column">
                <?php foreach ($navigation as $section => $items): ?>
                    <?php
                    $visibleItems = array_filter($items, fn (array $item): bool => !str_starts_with($item[0], '/admin') || in_array('*', $user['permissions'] ?? [], true));
                    if (!$visibleItems) { continue; }
                    ?>
                    <span class="nav-section"><?= e($section) ?></span>
                    <?php foreach ($visibleItems as [$href, $label, $icon, $badge]): ?>
                        <?php $active = str_ends_with($currentPath, $href) || ($href === '/dashboard' && ($currentPath === '/' || str_ends_with($currentPath, '/public'))); ?>
                        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= url($href) ?>">
                            <i class="bi <?= e($icon) ?>"></i>
                            <span><?= e($label) ?></span>
                            <?php if ($badge): ?><small class="nav-badge"><?= e($badge) ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <span>Demo LatAm</span>
                <strong>Lista para pilotos</strong>
            </div>
        </aside>
        <div class="sidebar-scrim" id="sidebarScrim"></div>
    <?php endif; ?>

    <main class="main">
        <?php if ($user): ?>
            <header class="topbar">
                <button class="mobile-menu" id="sidebarOpen" type="button" aria-label="Abrir menu"><i class="bi bi-list"></i></button>
                <div class="topbar-copy">
                    <small class="text-secondary">Empleado digital multiempresa</small>
                    <h1><?= e($title ?? 'AsisFly') ?></h1>
                </div>
                <div class="topbar-actions">
                    <span class="workspace-badge"><i class="bi bi-globe-americas"></i><?= e($company['country'] ?? 'LatAm') ?> / <?= e($company['currency'] ?? 'CLP') ?></span>
                    <button class="btn btn-outline-secondary btn-sm icon-button" id="themeToggle" type="button" title="Cambiar tema"><i class="bi bi-sun"></i><span>Tema</span></button>
                    <span class="badge text-bg-primary"><i class="bi bi-person-badge"></i><?= e($user['role']) ?></span>
                    <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i><span>Salir</span></button></form>
                </div>
            </header>
        <?php endif; ?>

        <section class="content">
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><?= e((string) $_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?= e((string) $_SESSION['flash_success']) ?></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php require $view; ?>
        </section>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
