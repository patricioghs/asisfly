<?php
$statusOptions = ['new' => 'Nueva', 'open' => 'Abierta', 'pending_approval' => 'Aprobacion', 'answered' => 'Respondida', 'closed' => 'Cerrada'];
$priorityOptions = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$statusIcons = ['new' => 'bi-sparkles', 'open' => 'bi-chat-dots', 'pending_approval' => 'bi-shield-check', 'answered' => 'bi-check2-circle', 'closed' => 'bi-archive'];
$priorityIcons = ['critical' => 'bi-exclamation-octagon', 'high' => 'bi-arrow-up-circle', 'medium' => 'bi-dot', 'low' => 'bi-arrow-down-circle'];
$messageStatusOptions = ['received' => 'Recibido', 'draft' => 'Borrador', 'approved' => 'Aprobado', 'sent' => 'Enviado', 'failed' => 'Fallido'];
$connectorStatusOptions = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectado', 'disabled' => 'Pausado', 'error' => 'Error'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Email' => 'bi-envelope-at'];
$brainLabels = ['commercial' => 'Ventas y clientes', 'administrative' => 'Administracion', 'analytical' => 'Analisis y reportes', 'operational' => 'Operaciones', 'executive' => 'Direccion'];
$aiStateOptions = [
    'approval_required' => 'Requiere aprobacion',
    'human_required' => 'Requiere humano',
    'human_reviewing' => 'En revision humana',
    'ai_resolved' => 'Resuelta por IA',
    'human_answered' => 'Respondida por humano',
    'closed' => 'Cerrada',
];
$routingStatusOptions = [
    'manual' => 'Confirmadas por humano',
    'confirmed' => 'Confirmada',
    'corrected' => 'Corregida',
    'suggested' => 'Sugeridas por IA',
    'uncertain' => 'Dudosas',
    'unrouted' => 'Sin marca',
];
$latestDraft = $selected['latest_draft'] ?? null;
$latestDraftId = (int) ($latestDraft['id'] ?? 0);
$threadMessages = array_values(array_filter((array) ($selected['messages'] ?? []), static function (array $message) use ($latestDraftId): bool {
    // A pending draft is edited in the response workspace below, not duplicated in the timeline.
    return $latestDraftId <= 0 || (int) ($message['id'] ?? 0) !== $latestDraftId;
}));
$supervisionReport = $supervisionReport ?? ['decisionCounts' => [], 'generatedCounts' => [], 'channels' => [], 'recentContexts' => []];
$decisionCounts = $supervisionReport['decisionCounts'] ?? [];
$generatedCounts = $supervisionReport['generatedCounts'] ?? [];
$channelSettings = $supervisionReport['channels'] ?? [];
$recentContexts = $supervisionReport['recentContexts'] ?? [];
$routingSummary = $supervisionReport['routingSummary'] ?? [];
$brandRoutes = $brandRoutes ?? [];
$filterQuery = http_build_query(array_filter($filters ?? [], fn ($value) => $value !== ''));
$quickFilter = fn (array $extra): string => url('/inbox?' . http_build_query(array_filter([...($filters ?? []), ...$extra], fn ($value) => $value !== '')));
$metricMap = [];
foreach (($metrics ?? []) as $metric) {
    $metricMap[$metric['label']] = $metric['value'];
}
?>
<div class="supervision-compact-head">
    <div>
        <span class="eyebrow">Centro de conversaciones IA</span>
        <h2>Centro de Supervision</h2>
        <p>Revisa solo las conversaciones donde AsisFly necesita tu criterio.</p>
    </div>
    <div class="supervision-head-stats">
        <a href="<?= e($quickFilter(['ai_state' => 'approval_required', 'intervention' => ''])) ?>"><strong><?= e((string) ($metricMap['Por aprobar'] ?? 0)) ?></strong><span>Por aprobar</span></a>
        <a href="<?= e($quickFilter(['ai_state' => 'human_required', 'intervention' => ''])) ?>"><strong><?= e((string) ($metricMap['Requieren humano'] ?? 0)) ?></strong><span>Requieren humano</span></a>
        <span><strong><?= e((string) ($metricMap['Autonomia'] ?? '0%')) ?></strong><span>Autonomia IA</span></span>
    </div>
</div>

<details class="panel supervision-insights mt-3">
    <summary><span><i class="bi bi-bar-chart-line"></i> Resumen de autonomia y auditoria</span><i class="bi bi-chevron-down"></i></summary>
    <div class="supervision-insights-body">
        <a class="soft-badge" href="<?= url('/ai-training?section=settings') ?>">Ajustar autonomia <i class="bi bi-arrow-right"></i></a>
        <div class="supervision-audit-grid">
        <article>
            <span>Por aprobar</span>
            <strong><?= e((string) ($decisionCounts['approval_required'] ?? 0)) ?></strong>
            <small>Respuestas listas con revision humana.</small>
        </article>
        <article>
            <span>Derivadas a humano</span>
            <strong><?= e((string) ($decisionCounts['human_required'] ?? 0)) ?></strong>
            <small>Casos sensibles o ambiguos.</small>
        </article>
        <article>
            <span>Automaticas</span>
            <strong><?= e((string) ($decisionCounts['auto_resolved'] ?? 0)) ?></strong>
            <small>Enviadas solo si cumplen politica.</small>
        </article>
        <article>
            <span>Generadas IA</span>
            <strong><?= e((string) array_sum(array_map('intval', $generatedCounts))) ?></strong>
            <small><?= e((string) ($generatedCounts['sent'] ?? 0)) ?> enviadas, <?= e((string) ($generatedCounts['edited'] ?? 0)) ?> editadas.</small>
        </article>
        <article>
            <span>Con marca</span>
            <strong><?= e((string) ($routingSummary['routed'] ?? 0)) ?></strong>
            <small><?= e((string) ($routingSummary['manual'] ?? 0)) ?> confirmadas por humano.</small>
        </article>
        <article>
            <span>Sin marca</span>
            <strong><?= e((string) ($routingSummary['unrouted'] ?? 0)) ?></strong>
            <small><?= e((string) ($routingSummary['uncertain'] ?? 0)) ?> conversaciones dudosas.</small>
        </article>
        </div>
        <div class="supervision-policy-list">
        <?php foreach (array_slice($channelSettings, 0, 5) as $setting): ?>
            <span><i class="bi bi-sliders"></i><?= e((string) $setting['channel']) ?>: <?= e((string) $setting['mode']) ?> / <?= e((string) $setting['min_confidence']) ?>%</span>
        <?php endforeach; ?>
        <?php if (!$channelSettings): ?><span><i class="bi bi-shield-lock"></i>Sin reglas por canal: modo manual seguro.</span><?php endif; ?>
        </div>
        <?php if ($recentContexts): ?>
            <div class="supervision-policy-list">
            <?php foreach ($recentContexts as $context): ?>
                <span><i class="bi bi-database-check"></i>Contexto #<?= e((string) $context['id']) ?> / <?= e((string) $context['token_estimate']) ?> tokens</span>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</details>

<div class="inbox-layout inbox-premium-layout mt-3">
    <aside class="panel inbox-list supervision-list">
        <div class="inbox-list-head"><div><span class="eyebrow">Supervision</span><h3>Conversaciones</h3></div><span class="soft-badge"><?= e((string) count($conversations)) ?></span></div>
        <form class="inbox-compact-filter" method="get" action="<?= url('/inbox') ?>">
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar cliente, asunto o motivo">
            <button class="btn btn-primary icon-button" title="Buscar" aria-label="Buscar"><i class="bi bi-search"></i></button>
            <a class="btn btn-outline-secondary icon-button" href="<?= url('/inbox') ?>" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i></a>
            <details class="inbox-advanced-filters">
                <summary><i class="bi bi-sliders"></i>Filtros</summary>
                <div>
            <select class="form-select" name="channel">
                <option value="">Todos los canales</option>
                <?php foreach (['WhatsApp', 'Instagram', 'Messenger', 'Email'] as $channel): ?>
                    <option value="<?= e($channel) ?>" <?= ($filters['channel'] ?? '') === $channel ? 'selected' : '' ?>><?= e($channel) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="account_id" aria-label="Cuenta conectada">
                <option value="">Cuenta conectada: todas</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?= e((string) $account['id']) ?>" <?= (string) ($filters['account_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>><?= e($account['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="brand_route_id" aria-label="Marca o negocio">
                <option value="">Marca/negocio: todos</option>
                <?php foreach ($brandRoutes as $route): ?>
                    <option value="<?= e((string) $route['id']) ?>" <?= (string) ($filters['brand_route_id'] ?? '') === (string) $route['id'] ? 'selected' : '' ?>>
                        <?= e($route['brand_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status">
                <option value="">Todos los estados</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="priority">
                <option value="">Todas las prioridades</option>
                <?php foreach ($priorityOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="ai_state">
                <option value="">Todos los estados IA</option>
                <?php foreach ($aiStateOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['ai_state'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="routing_status">
                <option value="">Enrutamiento: todos</option>
                <?php foreach ($routingStatusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['routing_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="supervision-intervention-toggle">
                <input type="checkbox" name="intervention" value="1" <?= !empty($filters['intervention']) ? 'checked' : '' ?>>
                <span>Solo intervencion</span>
            </label>
                    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar filtros</button>
                </div>
            </details>
        </form>

        <div class="supervision-quick-filters">
            <a class="<?= empty($filters['intervention']) && ($filters['ai_state'] ?? '') === '' ? 'active' : '' ?>" href="<?= url('/inbox') ?>">Todas</a>
            <a class="<?= ($filters['ai_state'] ?? '') === 'approval_required' ? 'active' : '' ?>" href="<?= e($quickFilter(['ai_state' => 'approval_required', 'intervention' => ''])) ?>">Por aprobar</a>
            <a class="<?= ($filters['ai_state'] ?? '') === 'human_required' ? 'active' : '' ?>" href="<?= e($quickFilter(['ai_state' => 'human_required', 'intervention' => ''])) ?>">Requieren humano</a>
            <a class="<?= !empty($filters['intervention']) ? 'active' : '' ?>" href="<?= e($quickFilter(['intervention' => '1', 'ai_state' => ''])) ?>">Mi intervencion</a>
        </div>

        <div class="supervision-section-label">Ordenadas por prioridad</div>
        <?php foreach ($conversations as $conversation): ?>
            <a class="inbox-row supervision-row <?= $selected && (int) $selected['id'] === (int) $conversation['id'] ? 'active' : '' ?>" href="<?= url('/inbox?id=' . (int) $conversation['id'] . ($filterQuery ? '&' . $filterQuery : '')) ?>">
                <div class="supervision-row-head">
                    <strong><?= e($conversation['customer_name']) ?></strong>
                    <span><i class="bi <?= e($channelIcons[$conversation['channel']] ?? 'bi-inbox') ?>"></i><?= e($conversation['account_name'] ?: $conversation['channel']) ?></span>
                </div>
                <p><?= e($conversation['subject']) ?></p>
                <small>
                    <span class="ai-state-pill state-<?= e($conversation['ai_state'] ?? 'human_required') ?>"><i class="bi bi-cpu"></i><?= e($conversation['ai_state_label'] ?? 'Requiere supervision') ?></span>
                    <?php if (!empty($conversation['brand_detection']['brand_name'])): ?>
                        <span class="assignee-chip"><i class="bi bi-signpost-split"></i><?= e($conversation['brand_detection']['brand_name']) ?></span>
                    <?php endif; ?>
                </small>
                <em><?= e($conversation['last_message'] ?? $conversation['ai_activity'] ?? 'AsisFly reviso la conversacion') ?></em>
            </a>
        <?php endforeach; ?>
        <?php if (!$conversations): ?>
            <div class="empty-state compact">
                <strong>Sin conversaciones para supervisar</strong>
                <p>Cuando AsisFly necesite aprobacion o apoyo humano, aparecera aqui.</p>
            </div>
        <?php endif; ?>
    </aside>

    <section class="panel inbox-detail supervision-detail">
        <?php if ($selected): ?>
            <div class="inbox-detail-head supervision-detail-head">
                <div>
                    <span class="eyebrow"><?= e(($selected['account_name'] ?? $selected['channel']) . ' / ' . ($brainLabels[$selected['account_brain'] ?? ''] ?? 'General')) ?></span>
                    <h2><?= e($selected['customer_name']) ?></h2>
                    <p><?= e($selected['subject']) ?></p>
                    <?php if (!empty($selected['assigned_name'])): ?><small class="text-secondary">Tomada por <?= e($selected['assigned_name']) ?></small><?php endif; ?>
                </div>
                <span class="ai-state-pill state-<?= e($selected['ai_state'] ?? 'human_required') ?>"><i class="bi bi-cpu"></i><?= e($selected['ai_state_label'] ?? 'Requiere supervision') ?></span>
            </div>

            <section class="supervision-ai-panel">
                <div>
                    <span class="eyebrow">Trabajo de AsisFly</span>
                    <h3><?= e($selected['ai_activity'] ?? 'AsisFly reviso la conversacion') ?></h3>
                    <p><?= e($selected['ai_summary'] ?? '') ?></p>
                    <small><?= e($selected['ai_reason'] ?? '') ?></small>
                    <?php if (!empty($selected['brand_detection']['brand_name']) || $brandRoutes || !empty($selected['ai_decision'])): ?>
                    <details class="supervision-context-details compact">
                        <summary>Ver contexto y decision IA</summary>
                        <div>
                    <?php if (!empty($selected['brand_detection']['brand_name'])): ?>
                        <div class="supervision-decision-tags">
                            <span><i class="bi bi-signpost-split"></i> <?= in_array(($selected['brand_detection']['status'] ?? ''), ['confirmed', 'corrected'], true) ? 'Marca confirmada' : 'Marca sugerida' ?>: <?= e($selected['brand_detection']['brand_name']) ?></span>
                            <span><i class="bi bi-speedometer2"></i> <?= e((string) $selected['brand_detection']['confidence']) ?>% confianza</span>
                            <span><i class="bi bi-patch-check"></i><?= e($routingStatusOptions[$selected['brand_detection']['status'] ?? ''] ?? ($selected['brand_detection']['status'] ?? 'Detectada')) ?></span>
                            <?php if (!empty($selected['brand_detection']['target_company_name'])): ?>
                                <span><i class="bi bi-building"></i> <?= e($selected['brand_detection']['target_company_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small><?= e($selected['brand_detection']['reason'] ?? '') ?></small>
                    <?php endif; ?>
                    <?php if ($brandRoutes): ?>
                        <form method="post" action="<?= url('/inbox/brand-route') ?>" class="status-toolbar mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                            <select class="form-select" name="brand_route_id" aria-label="Marca de la conversacion">
                                <option value="">Seleccionar marca/negocio</option>
                                <?php foreach ($brandRoutes as $route): ?>
                                    <option value="<?= e((string) $route['id']) ?>" <?= (int) ($selected['brand_detection']['route_id'] ?? 0) === (int) $route['id'] ? 'selected' : '' ?>>
                                        <?= e($route['brand_name']) ?><?= !empty($route['target_company_name']) ? ' / ' . e($route['target_company_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-primary"><i class="bi bi-check2-circle"></i> Confirmar marca</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($selected['ai_decision'])): ?>
                        <div class="supervision-decision-tags">
                            <span><i class="bi bi-shield-check"></i> Riesgo <?= e((string) ($selected['ai_decision']['risk'] ?? 'medio')) ?></span>
                            <span><i class="bi bi-sliders"></i> <?= e((string) ($selected['ai_decision']['autonomy_mode'] ?? 'supervised_learning')) ?></span>
                        </div>
                    <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <div class="supervision-confidence">
                    <strong><?= e((string) ($selected['ai_confidence'] ?? 0)) ?>%</strong>
                    <span>confianza IA</span>
                </div>
            </section>

            <?php if (!empty($selected['decision_timeline'])): ?>
                <details class="supervision-context-details">
                    <summary>Ver auditoria de decisiones IA</summary>
                    <section class="supervision-decision-timeline">
                    <div>
                        <span class="eyebrow">Historial de decisiones</span>
                        <h3>Que hizo AsisFly</h3>
                    </div>
                    <?php foreach ($selected['decision_timeline'] as $event): ?>
                        <article>
                            <i class="bi bi-cpu"></i>
                            <div>
                                <strong><?= e($aiStateOptions[match ($event['mode'] ?? '') {
                                    'auto_resolved' => 'ai_resolved',
                                    'approval_required' => 'approval_required',
                                    'human_required' => 'human_required',
                                    default => 'human_reviewing',
                                }] ?? 'Decision IA') ?></strong>
                                <p><?= e($event['reason'] ?? '') ?></p>
                                <small>Riesgo <?= e($event['risk'] ?? 'medium') ?> - <?= e((string) ($event['confidence'] ?? 0)) ?>% confianza - <?= e($event['created_at'] ?? '') ?></small>
                                <?php if (!empty($event['draft_status'])): ?>
                                    <small>Respuesta: <?= e($event['draft_status']) ?> / <?= e($event['draft_provider'] ?: 'simulated') ?> <?= e($event['draft_model'] ?? '') ?> - memoria <?= e((string) ($event['memory_hits'] ?? 0)) ?> - conocimiento <?= e((string) ($event['knowledge_hits'] ?? 0)) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($event['brand_route']['brand_name'])): ?>
                                    <small>Marca usada: <?= e($event['brand_route']['brand_name']) ?><?= !empty($event['brand_route']['target_company_name']) ? ' / ' . e($event['brand_route']['target_company_name']) : '' ?> - <?= e((string) ($event['brand_route']['confidence'] ?? 0)) ?>%</small>
                                <?php endif; ?>
                                <?php if (!empty($event['channel_mode'])): ?>
                                    <small>Politica: canal <?= e($event['channel_mode']) ?><?= !empty($event['min_confidence']) ? ' / minimo ' . e((string) $event['min_confidence']) . '%' : '' ?><?= !empty($event['context_log_id']) ? ' / contexto #' . e((string) $event['context_log_id']) : '' ?></small>
                                <?php endif; ?>
                                <?php if (!empty($event['commercial_ok'])): ?>
                                    <small>CRM: <?= e(implode(', ', $event['commercial_actions'] ?? [])) ?><?= !empty($event['commercial_customer_id']) ? ' - cliente #' . e((string) $event['commercial_customer_id']) : '' ?></small>
                                <?php elseif (!empty($event['commercial_reason'])): ?>
                                    <small>CRM: <?= e($event['commercial_reason']) ?></small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </section>
                </details>
            <?php endif; ?>

            <div class="supervision-actions">
                <form method="post" action="<?= url('/inbox/status') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <input type="hidden" name="status" value="open">
                    <button class="btn btn-outline-primary"><i class="bi bi-person-check"></i> Tomar control humano</button>
                </form>
                <form method="post" action="<?= url('/inbox/status') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <input type="hidden" name="status" value="new">
                    <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Devolver a IA</button>
                </form>
                <form method="post" action="<?= url('/inbox/status') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <input type="hidden" name="status" value="closed">
                    <button class="btn btn-outline-secondary"><i class="bi bi-archive"></i> Cerrar conversacion</button>
                </form>
                <a class="btn btn-outline-secondary" href="<?= url('/tasks') ?>"><i class="bi bi-list-check"></i> Crear tarea</a>
                <a class="btn btn-outline-secondary" href="<?= url('/crm') ?>"><i class="bi bi-person-lines-fill"></i> Crear seguimiento</a>
            </div>

            <form method="post" action="<?= url('/inbox/status') ?>" class="status-toolbar">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                <select class="form-select" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selected['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary">Cambiar estado interno</button>
            </form>

            <div class="inbox-thread supervision-thread">
                <?php foreach ($threadMessages as $message): ?>
                    <?php
                        $formattedBody = \App\Services\EmailBodyFormatter::formatEmailBody((string) ($message['body'] ?? ''));
                        $recipientLabel = ($message['direction'] ?? '') === 'outbound'
                            ? ($selected['customer_name'] ?? 'Cliente')
                            : ($selected['account_name'] ?: 'AsisFly');
                    ?>
                    <div class="inbox-message <?= e($message['direction']) ?>">
                        <div class="email-message-header">
                            <div>
                                <span>Remitente</span>
                                <strong><?= e($message['sender_name']) ?></strong>
                            </div>
                            <div>
                                <span>Para</span>
                                <strong><?= e($recipientLabel) ?></strong>
                            </div>
                            <div>
                                <span>Fecha</span>
                                <strong><?= e($message['created_at'] ?? '-') ?></strong>
                            </div>
                            <div>
                                <span>Asunto</span>
                                <strong><?= e($selected['subject'] ?? '-') ?></strong>
                            </div>
                            <div>
                                <span>Canal</span>
                                <strong><?= e($selected['account_name'] ?: $selected['channel']) ?></strong>
                            </div>
                            <div>
                                <span>Estado</span>
                                <strong><?= e($messageStatusOptions[$message['status'] ?? 'sent'] ?? ($message['status'] ?? 'Enviado')) ?></strong>
                            </div>
                        </div>
                        <div class="email-message-body">
                            <?= $formattedBody['body_html'] ?>
                        </div>
                        <?php if ($formattedBody['has_signature']): ?>
                            <details class="email-collapsible">
                                <summary>Firma</summary>
                                <div><?= $formattedBody['signature_html'] ?></div>
                            </details>
                        <?php endif; ?>
                        <?php if ($formattedBody['has_quoted']): ?>
                            <details class="email-collapsible quoted">
                                <summary>Historial citado</summary>
                                <div><?= $formattedBody['quoted_html'] ?></div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <section class="inbox-reply-workspace">
                <div class="reply-workspace-head">
                    <div>
                        <span class="eyebrow">Supervision de respuesta</span>
                        <h2>Borrador editable</h2>
                    </div>
                    <?php if ($latestDraft): ?>
                        <span class="status status-<?= e($latestDraft['status']) ?>"><i class="bi bi-pencil-square"></i><?= e($messageStatusOptions[$latestDraft['status']] ?? 'Borrador') ?></span>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= url('/inbox/suggest') ?>" class="reply-action-row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <button class="btn btn-primary"><i class="bi <?= $latestDraft ? 'bi-arrow-repeat' : 'bi-stars' ?>"></i> <?= $latestDraft ? 'Regenerar con IA' : 'Generar con IA' ?></button>
                    <span><?= $latestDraft ? 'Solo genera una nueva propuesta si la actual no te sirve.' : 'AsisFly redacta una respuesta editable y aprende de tus cambios.' ?></span>
                </form>
                <form method="post" action="<?= url('/inbox/draft') ?>" class="reply-editor-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <textarea class="form-control" name="body" rows="5" placeholder="Escribe tu respuesta o edita la sugerencia de AsisFly..."><?= e($latestDraft['body'] ?? '') ?></textarea>
                    <div class="reply-action-row">
                        <button class="btn btn-outline-primary"><i class="bi bi-save"></i> Guardar borrador</button>
                        <span>La correccion queda como senal de aprendizaje para esta empresa.</span>
                    </div>
                </form>
                <?php if ($latestDraft): ?>
                    <form method="post" action="<?= url('/inbox/send-draft') ?>" class="reply-action-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                        <button class="btn btn-success"><i class="bi bi-send-check"></i> Aprobar y enviar</button>
                        <span>Envia por SMTP si la cuenta tiene salida activa. Si falla, el borrador queda disponible.</span>
                    </form>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="empty-state">No hay conversaciones que requieran supervision.</div>
        <?php endif; ?>
    </section>
</div>

<details class="panel compact-connectors mt-3">
    <summary><span><i class="bi bi-plug"></i> Canales conectados</span><a class="soft-badge" href="<?= url('/integrations') ?>">Configurar <i class="bi bi-arrow-right"></i></a></summary>
    <div class="omni-account-grid compact">
        <?php foreach ($accounts as $account): ?>
            <article>
                <div>
                    <strong><?= e($account['channel']) ?></strong>
                    <span class="status status-<?= e($account['status']) ?>"><i class="bi bi-plug"></i><?= e($connectorStatusOptions[$account['status']] ?? $account['status']) ?></span>
                </div>
                <small><?= e($account['display_name']) ?> - <?= e($brainLabels[$account['brain_key'] ?? ''] ?? 'General') ?> - entrada <?= !empty($account['inbound_enabled']) ? 'activa' : 'pausada' ?> - salida <?= !empty($account['outbound_enabled']) ? 'activa' : 'con aprobacion' ?></small>
            </article>
        <?php endforeach; ?>
    </div>
</details>
