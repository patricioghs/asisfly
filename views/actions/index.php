<?php
$locale = (string) ($company['locale'] ?? 'es_CL');
$lang = str_starts_with($locale, 'pt') ? 'pt' : (str_starts_with($locale, 'en') ? 'en' : 'es');
$texts = [
    'es' => [
        'eyebrow' => 'Control humano',
        'title' => 'Modo de Aprobaciones',
        'subtitle' => 'Revisa lo que AsisFly quiere hacer antes de enviarlo, publicarlo o ejecutarlo. Cada decision entrena la autonomia de la empresa.',
        'policy' => 'Politica de autonomia',
        'search' => 'Buscar accion, cliente, modulo o cerebro',
        'all_status' => 'Todos los estados',
        'all_modules' => 'Todos los modulos',
        'all_risks' => 'Todos los riesgos',
        'filter' => 'Filtrar',
        'clear' => 'Limpiar',
        'queue' => 'Bandeja de decisiones',
        'empty_title' => 'No hay aprobaciones para este filtro',
        'empty_body' => 'Cuando AsisFly prepare respuestas, cotizaciones, publicaciones o automatizaciones sensibles, apareceran aqui.',
        'impact' => 'Impacto esperado',
        'decision' => 'Decision requerida',
        'audit' => 'Auditoria',
        'notifications' => 'Notificaciones',
        'comments' => 'Comentarios internos',
        'comment_placeholder' => 'Agrega contexto, correccion o instruccion para que AsisFly aprenda',
        'comment' => 'Comentar',
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'execute' => 'Ejecutar',
        'fail' => 'Marcar fallida',
        'retry' => 'Reintentar',
        'no_actions' => 'Sin acciones disponibles',
        'result' => 'Resultado',
        'requested_by' => 'Solicitado por',
        'assigned_to' => 'Responsable',
        'due_at' => 'Vence',
        'permission' => 'Permiso',
        'role' => 'Rol',
        'execution' => 'Ejecucion',
        'retries' => 'Reintentos',
        'no_notifications' => 'Sin notificaciones todavia.',
        'no_history' => 'Sin historial todavia.',
    ],
    'pt' => [
        'eyebrow' => 'Controle humano',
        'title' => 'Modo de Aprovacoes',
        'subtitle' => 'Revise o que a AsisFly quer fazer antes de enviar, publicar ou executar. Cada decisao treina a autonomia da empresa.',
        'policy' => 'Politica de autonomia',
        'search' => 'Buscar acao, cliente, modulo ou cerebro',
        'all_status' => 'Todos os status',
        'all_modules' => 'Todos os modulos',
        'all_risks' => 'Todos os riscos',
        'filter' => 'Filtrar',
        'clear' => 'Limpar',
        'queue' => 'Caixa de decisoes',
        'empty_title' => 'Nao ha aprovacoes para este filtro',
        'empty_body' => 'Quando a AsisFly preparar respostas, propostas, publicacoes ou automacoes sensiveis, elas aparecerao aqui.',
        'impact' => 'Impacto esperado',
        'decision' => 'Decisao necessaria',
        'audit' => 'Auditoria',
        'notifications' => 'Notificacoes',
        'comments' => 'Comentarios internos',
        'comment_placeholder' => 'Adicione contexto, correcao ou instrucao para a AsisFly aprender',
        'comment' => 'Comentar',
        'approve' => 'Aprovar',
        'reject' => 'Rejeitar',
        'execute' => 'Executar',
        'fail' => 'Marcar falha',
        'retry' => 'Tentar novamente',
        'no_actions' => 'Sem acoes disponiveis',
        'result' => 'Resultado',
        'requested_by' => 'Solicitado por',
        'assigned_to' => 'Responsavel',
        'due_at' => 'Vence',
        'permission' => 'Permissao',
        'role' => 'Funcao',
        'execution' => 'Execucao',
        'retries' => 'Tentativas',
        'no_notifications' => 'Sem notificacoes ainda.',
        'no_history' => 'Sem historico ainda.',
    ],
    'en' => [
        'eyebrow' => 'Human control',
        'title' => 'Approval Mode',
        'subtitle' => 'Review what AsisFly wants to do before sending, publishing, or executing it. Every decision trains company autonomy.',
        'policy' => 'Autonomy policy',
        'search' => 'Search action, customer, module, or brain',
        'all_status' => 'All statuses',
        'all_modules' => 'All modules',
        'all_risks' => 'All risks',
        'filter' => 'Filter',
        'clear' => 'Clear',
        'queue' => 'Decision inbox',
        'empty_title' => 'No approvals for this filter',
        'empty_body' => 'When AsisFly prepares sensitive replies, quotes, posts, or automations, they will appear here.',
        'impact' => 'Expected impact',
        'decision' => 'Decision required',
        'audit' => 'Audit',
        'notifications' => 'Notifications',
        'comments' => 'Internal comments',
        'comment_placeholder' => 'Add context, correction, or instruction so AsisFly can learn',
        'comment' => 'Comment',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'execute' => 'Execute',
        'fail' => 'Mark failed',
        'retry' => 'Retry',
        'no_actions' => 'No actions available',
        'result' => 'Result',
        'requested_by' => 'Requested by',
        'assigned_to' => 'Owner',
        'due_at' => 'Due',
        'permission' => 'Permission',
        'role' => 'Role',
        'execution' => 'Execution',
        'retries' => 'Retries',
        'no_notifications' => 'No notifications yet.',
        'no_history' => 'No history yet.',
    ],
];
$t = fn (string $key): string => $texts[$lang][$key] ?? $texts['es'][$key] ?? $key;
$statusLabels = [
    'es' => ['pending' => 'Pendiente', 'approved' => 'Aprobada', 'executed' => 'Ejecutada', 'failed' => 'Fallida', 'rejected' => 'Rechazada'],
    'pt' => ['pending' => 'Pendente', 'approved' => 'Aprovada', 'executed' => 'Executada', 'failed' => 'Falhou', 'rejected' => 'Rejeitada'],
    'en' => ['pending' => 'Pending', 'approved' => 'Approved', 'executed' => 'Executed', 'failed' => 'Failed', 'rejected' => 'Rejected'],
];
$priorityLabels = [
    'es' => ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'],
    'pt' => ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baixa'],
    'en' => ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'],
];
$riskLabels = [
    'es' => ['high' => 'Alto riesgo', 'medium' => 'Riesgo medio', 'low' => 'Bajo riesgo'],
    'pt' => ['high' => 'Alto risco', 'medium' => 'Risco medio', 'low' => 'Baixo risco'],
    'en' => ['high' => 'High risk', 'medium' => 'Medium risk', 'low' => 'Low risk'],
];
$executionLabels = [
    'es' => ['not_started' => 'Sin iniciar', 'queued' => 'En cola', 'running' => 'Ejecutando', 'succeeded' => 'Completada', 'failed' => 'Fallida', 'cancelled' => 'Cancelada'],
    'pt' => ['not_started' => 'Nao iniciada', 'queued' => 'Na fila', 'running' => 'Executando', 'succeeded' => 'Concluida', 'failed' => 'Falhou', 'cancelled' => 'Cancelada'],
    'en' => ['not_started' => 'Not started', 'queued' => 'Queued', 'running' => 'Running', 'succeeded' => 'Completed', 'failed' => 'Failed', 'cancelled' => 'Cancelled'],
];
$eventLabels = [
    'created' => ['es' => 'Creada', 'pt' => 'Criada', 'en' => 'Created'],
    'approved' => ['es' => 'Aprobada', 'pt' => 'Aprovada', 'en' => 'Approved'],
    'rejected' => ['es' => 'Rechazada', 'pt' => 'Rejeitada', 'en' => 'Rejected'],
    'executed' => ['es' => 'Ejecutada', 'pt' => 'Executada', 'en' => 'Executed'],
    'failed' => ['es' => 'Fallida', 'pt' => 'Falhou', 'en' => 'Failed'],
    'commented' => ['es' => 'Comentario', 'pt' => 'Comentario', 'en' => 'Comment'],
    'retry_queued' => ['es' => 'Reintento', 'pt' => 'Nova tentativa', 'en' => 'Retry'],
    'permission_denied' => ['es' => 'Permiso denegado', 'pt' => 'Permissao negada', 'en' => 'Permission denied'],
];
$iconByModule = ['Omnicanal' => 'bi-inboxes', 'Asisti Social' => 'bi-megaphone', 'CRM' => 'bi-people', 'Cotizaciones' => 'bi-file-earmark-text', 'General' => 'bi-stars'];
$filterFields = function () use ($filters): void { ?>
    <input type="hidden" name="filter_q" value="<?= e($filters['q'] ?? '') ?>">
    <input type="hidden" name="filter_status" value="<?= e($filters['status'] ?? '') ?>">
    <input type="hidden" name="filter_module" value="<?= e($filters['module'] ?? '') ?>">
    <input type="hidden" name="filter_risk_level" value="<?= e($filters['risk_level'] ?? '') ?>">
<?php };
$label = fn (array $map, string $value): string => $map[$lang][$value] ?? $value;
$eventLabel = fn (string $value): string => $eventLabels[$value][$lang] ?? $value;
?>

<section class="panel approval-hero">
    <div>
        <span class="eyebrow"><?= e($t('eyebrow')) ?></span>
        <h2><?= e($t('title')) ?></h2>
        <p><?= e($t('subtitle')) ?></p>
        <div class="approval-hero-tags">
            <span><i class="bi bi-globe-americas"></i><?= e((string) ($company['country'] ?? 'LatAm')) ?> / <?= e((string) ($company['currency'] ?? 'USD')) ?></span>
            <span><i class="bi bi-translate"></i><?= e($locale) ?></span>
        </div>
    </div>
    <div class="approval-metrics">
        <?php foreach ($metrics as $metric): ?>
            <a class="approval-metric metric-<?= e($metric['status']) ?>" href="<?= url('/actions?status=' . urlencode($metric['status'])) ?>">
                <span><?= e($statusLabels[$lang][$metric['status']] ?? $metric['label']) ?></span>
                <strong><?= e($metric['value']) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel approval-autonomy mt-4">
    <div>
        <span class="eyebrow"><?= e($t('policy')) ?></span>
        <h2><?= e($autonomy['label'] ?? 'Aprendizaje supervisado') ?></h2>
        <p><?= e($autonomy['description'] ?? 'Todo lo que sugiera AsisFly requiere aprobacion humana mientras aprende como opera la empresa.') ?></p>
    </div>
    <div class="autonomy-progress" style="--progress: <?= e((string) ($autonomy['learning_progress'] ?? 18)) ?>%;">
        <div><span></span></div>
        <strong><?= e((string) ($autonomy['learning_progress'] ?? 18)) ?>%</strong>
    </div>
</section>

<form class="panel approval-filter-bar mt-4" method="get" action="<?= url('/actions') ?>">
    <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="<?= e($t('search')) ?>">
    <select class="form-select" name="status">
        <option value=""><?= e($t('all_status')) ?></option>
        <?php foreach ($statusLabels[$lang] as $value => $name): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="module">
        <option value=""><?= e($t('all_modules')) ?></option>
        <?php foreach ($modules as $module): ?>
            <option value="<?= e($module) ?>" <?= ($filters['module'] ?? '') === $module ? 'selected' : '' ?>><?= e($module) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="risk_level">
        <option value=""><?= e($t('all_risks')) ?></option>
        <?php foreach ($riskLabels[$lang] as $value => $name): ?>
            <option value="<?= e($value) ?>" <?= ($filters['risk_level'] ?? '') === $value ? 'selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><i class="bi bi-funnel"></i><?= e($t('filter')) ?></button>
    <a class="btn btn-outline-secondary" href="<?= url('/actions') ?>"><?= e($t('clear')) ?></a>
</form>

<section class="approval-board mt-4">
    <div class="panel-title approval-board-title">
        <div>
            <span class="eyebrow"><?= e($t('decision')) ?></span>
            <h2><?= e($t('queue')) ?></h2>
        </div>
        <span><?= e((string) count($actions)) ?> items</span>
    </div>

    <div class="approval-list">
        <?php foreach ($actions as $action): ?>
            <?php
            $payload = is_array($action['payload'] ?? null) ? $action['payload'] : [];
            $impactParts = array_slice($payload, 0, 4, true);
            $moduleIcon = $iconByModule[$action['module'] ?? ''] ?? 'bi-stars';
            ?>
            <article class="approval-card">
                <div class="approval-card-main">
                    <div class="approval-icon"><i class="bi <?= e($moduleIcon) ?>"></i></div>
                    <div>
                        <div class="approval-card-topline">
                            <span><?= e($action['module']) ?></span>
                            <span><?= e($action['brain']) ?></span>
                        </div>
                        <h3><?= e($action['title']) ?></h3>
                        <p><?= e($action['description']) ?></p>
                    </div>
                </div>

                <div class="approval-decision-panel">
                    <span class="action-status action-<?= e($action['status']) ?>"><i class="bi bi-circle-fill"></i><?= e($label($statusLabels, $action['status'])) ?></span>
                    <span class="priority priority-<?= e($action['priority']) ?>"><i class="bi bi-flag"></i><?= e($label($priorityLabels, $action['priority'])) ?></span>
                    <span class="risk-chip risk-<?= e($action['risk_level']) ?>"><i class="bi bi-shield-check"></i><?= e($label($riskLabels, $action['risk_level'])) ?></span>
                </div>

                <div class="approval-detail-grid">
                    <div>
                        <span><?= e($t('impact')) ?></span>
                        <?php if ($impactParts): ?>
                            <?php foreach ($impactParts as $key => $value): ?>
                                <small><?= e((string) $key) ?>: <?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></small>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <small><?= e($action['action_type']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span><?= e($t('audit')) ?></span>
                        <small><?= e($t('requested_by')) ?>: <?= e($action['requested_name'] ?? 'Sistema') ?></small>
                        <?php if (!empty($action['assigned_name'])): ?><small><?= e($t('assigned_to')) ?>: <?= e($action['assigned_name']) ?></small><?php endif; ?>
                        <?php if (!empty($action['due_at'])): ?><small><?= e($t('due_at')) ?>: <?= e($action['due_at']) ?></small><?php endif; ?>
                    </div>
                    <div>
                        <span><?= e($t('execution')) ?></span>
                        <small><?= e($executionLabels[$lang][$action['execution_status']] ?? $action['execution_status']) ?></small>
                        <small><?= e($t('retries')) ?>: <?= e((string) $action['retry_count']) ?>/<?= e((string) $action['max_retries']) ?></small>
                        <?php if (!empty($action['required_permission'])): ?><small><?= e($t('permission')) ?>: <?= e($action['required_permission']) ?></small><?php endif; ?>
                        <?php if (!empty($action['required_role'])): ?><small><?= e($t('role')) ?>: <?= e($action['required_role']) ?></small><?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($action['result_summary'])): ?>
                    <div class="approval-result">
                        <strong><?= e($t('result')) ?></strong>
                        <span><?= e($action['result_summary']) ?></span>
                        <?php if (!empty($action['last_transition_at'])): ?><small><?= e($action['last_transition_at']) ?></small><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="approval-workspace">
                    <div class="approval-timeline">
                        <strong><?= e($t('audit')) ?></strong>
                        <?php foreach ($action['events'] as $event): ?>
                            <div>
                                <i class="bi bi-activity"></i>
                                <span><?= e($eventLabel($event['event_type'])) ?> / <?= e($event['user_name'] ?? 'Sistema') ?></span>
                                <small><?= e($event['created_at']) ?></small>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$action['events']): ?><small><?= e($t('no_history')) ?></small><?php endif; ?>
                    </div>

                    <div class="approval-comments">
                        <strong><?= e($t('comments')) ?></strong>
                        <?php foreach ($action['comments'] as $comment): ?>
                            <p><span><?= e($comment['user_name'] ?? 'Usuario') ?>:</span> <?= e($comment['comment']) ?></p>
                        <?php endforeach; ?>
                        <form method="post" action="<?= url('/actions/comment') ?>" class="approval-comment-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $action['id']) ?>">
                            <?php $filterFields(); ?>
                            <input class="form-control form-control-sm" name="comment" maxlength="700" placeholder="<?= e($t('comment_placeholder')) ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= e($t('comment')) ?></button>
                        </form>
                    </div>
                </div>

                <div class="approval-actions-row">
                    <?php if ($action['status'] === 'pending'): ?>
                        <form method="post" action="<?= url('/actions/approve') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $action['id']) ?>"><?php $filterFields(); ?><button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i><?= e($t('approve')) ?></button></form>
                        <form method="post" action="<?= url('/actions/reject') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $action['id']) ?>"><?php $filterFields(); ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i><?= e($t('reject')) ?></button></form>
                    <?php elseif ($action['status'] === 'approved'): ?>
                        <form method="post" action="<?= url('/actions/execute') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $action['id']) ?>"><?php $filterFields(); ?><button class="btn btn-sm btn-primary"><i class="bi bi-play-circle"></i><?= e($t('execute')) ?></button></form>
                        <form method="post" action="<?= url('/actions/fail') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $action['id']) ?>"><?php $filterFields(); ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle"></i><?= e($t('fail')) ?></button></form>
                    <?php elseif ($action['status'] === 'failed' && $action['retry_count'] < $action['max_retries']): ?>
                        <form method="post" action="<?= url('/actions/retry') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $action['id']) ?>"><?php $filterFields(); ?><button class="btn btn-sm btn-warning"><i class="bi bi-arrow-clockwise"></i><?= e($t('retry')) ?></button></form>
                    <?php else: ?>
                        <span class="text-secondary"><?= e($t('no_actions')) ?></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$actions): ?>
            <div class="panel empty-state compact approval-empty">
                <strong><?= e($t('empty_title')) ?></strong>
                <p><?= e($t('empty_body')) ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
