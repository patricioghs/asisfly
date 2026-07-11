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
$latestDraft = $selected['latest_draft'] ?? null;
$supervisionReport = $supervisionReport ?? ['decisionCounts' => [], 'generatedCounts' => [], 'channels' => [], 'recentContexts' => []];
$decisionCounts = $supervisionReport['decisionCounts'] ?? [];
$generatedCounts = $supervisionReport['generatedCounts'] ?? [];
$channelSettings = $supervisionReport['channels'] ?? [];
$recentContexts = $supervisionReport['recentContexts'] ?? [];
$filterQuery = http_build_query(array_filter($filters ?? [], fn ($value) => $value !== ''));
$quickFilter = fn (array $extra): string => url('/inbox?' . http_build_query(array_filter([...($filters ?? []), ...$extra], fn ($value) => $value !== '')));
?>
<div class="inbox-hero panel supervision-hero">
    <div>
        <span class="eyebrow">Centro de conversaciones IA</span>
        <h2>Centro de Supervision</h2>
        <p>AsisFly trabaja primero. Aqui revisas excepciones, apruebas respuestas y tomas control solo cuando el empleado digital necesita apoyo humano.</p>
    </div>
    <div class="inbox-metrics supervision-metrics">
        <?php foreach ($metrics as $metric): ?>
            <div><span><?= e($metric['label']) ?></span><strong><?= e($metric['value']) ?></strong></div>
        <?php endforeach; ?>
    </div>
</div>

<section class="panel mt-4 supervision-audit-panel">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Auditoria del trabajador virtual</span>
            <h2>Decisiones, autonomia y contexto usado</h2>
        </div>
        <a class="soft-badge" href="<?= url('/ai-training?section=settings') ?>">Ajustar autonomia <i class="bi bi-arrow-right"></i></a>
    </div>
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
</section>

<div class="inbox-layout mt-4">
    <aside class="panel inbox-list supervision-list">
        <form class="module-filter-bar" method="get" action="<?= url('/inbox') ?>">
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar cliente, asunto o motivo">
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
            <label class="supervision-intervention-toggle">
                <input type="checkbox" name="intervention" value="1" <?= !empty($filters['intervention']) ? 'checked' : '' ?>>
                <span>Solo intervencion</span>
            </label>
            <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
            <a class="btn btn-outline-secondary" href="<?= url('/inbox') ?>">Limpiar</a>
        </form>

        <div class="supervision-quick-filters">
            <a class="<?= !empty($filters['intervention']) ? 'active' : '' ?>" href="<?= e($quickFilter(['intervention' => '1', 'ai_state' => ''])) ?>"><i class="bi bi-person-exclamation"></i> Necesitan mi intervencion</a>
            <a class="<?= ($filters['ai_state'] ?? '') === 'approval_required' ? 'active' : '' ?>" href="<?= e($quickFilter(['ai_state' => 'approval_required', 'intervention' => ''])) ?>"><i class="bi bi-shield-check"></i> Por aprobar</a>
            <a class="<?= ($filters['ai_state'] ?? '') === 'human_required' ? 'active' : '' ?>" href="<?= e($quickFilter(['ai_state' => 'human_required', 'intervention' => ''])) ?>"><i class="bi bi-person-raised-hand"></i> Requieren humano</a>
            <a class="<?= ($filters['ai_state'] ?? '') === 'ai_resolved' ? 'active' : '' ?>" href="<?= e($quickFilter(['ai_state' => 'ai_resolved', 'intervention' => ''])) ?>"><i class="bi bi-check2-circle"></i> Resueltas por IA</a>
        </div>

        <div class="supervision-section-label">Prioridad humana</div>
        <?php foreach ($conversations as $conversation): ?>
            <a class="inbox-row supervision-row <?= $selected && (int) $selected['id'] === (int) $conversation['id'] ? 'active' : '' ?>" href="<?= url('/inbox?id=' . (int) $conversation['id'] . ($filterQuery ? '&' . $filterQuery : '')) ?>">
                <div class="supervision-row-head">
                    <strong><?= e($conversation['customer_name']) ?></strong>
                    <span><i class="bi <?= e($channelIcons[$conversation['channel']] ?? 'bi-inbox') ?>"></i><?= e($conversation['account_name'] ?: $conversation['channel']) ?></span>
                </div>
                <p><?= e($conversation['subject']) ?></p>
                <small>
                    <span class="ai-state-pill state-<?= e($conversation['ai_state'] ?? 'human_required') ?>"><i class="bi bi-cpu"></i><?= e($conversation['ai_state_label'] ?? 'Requiere supervision') ?></span>
                    <span class="priority priority-<?= e($conversation['priority']) ?>"><i class="bi <?= e($priorityIcons[$conversation['priority']] ?? 'bi-dot') ?>"></i><?= e($priorityOptions[$conversation['priority']] ?? $conversation['priority']) ?></span>
                    <span class="assignee-chip"><i class="bi bi-diagram-3"></i><?= e($brainLabels[$conversation['account_brain'] ?? ''] ?? 'General') ?></span>
                    <?php if (!empty($conversation['brand_detection']['brand_name'])): ?>
                        <span class="assignee-chip"><i class="bi bi-signpost-split"></i><?= e($conversation['brand_detection']['brand_name']) ?> · <?= e((string) $conversation['brand_detection']['confidence']) ?>%</span>
                    <?php endif; ?>
                    <?= !empty($conversation['assigned_name']) ? '<span class="assignee-chip"><i class="bi bi-person"></i>' . e($conversation['assigned_name']) . '</span>' : '' ?>
                </small>
                <em><?= e($conversation['ai_activity'] ?? 'AsisFly reviso la conversacion') ?> - <?= e((string) ($conversation['ai_confidence'] ?? 0)) ?>% confianza</em>
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
                    <?php if (!empty($selected['brand_detection']['brand_name'])): ?>
                        <div class="supervision-decision-tags">
                            <span><i class="bi bi-signpost-split"></i> Marca sugerida: <?= e($selected['brand_detection']['brand_name']) ?></span>
                            <span><i class="bi bi-speedometer2"></i> <?= e((string) $selected['brand_detection']['confidence']) ?>% confianza</span>
                            <?php if (!empty($selected['brand_detection']['target_company_name'])): ?>
                                <span><i class="bi bi-building"></i> <?= e($selected['brand_detection']['target_company_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small><?= e($selected['brand_detection']['reason'] ?? '') ?></small>
                    <?php endif; ?>
                    <?php if (!empty($selected['ai_decision'])): ?>
                        <div class="supervision-decision-tags">
                            <span><i class="bi bi-shield-check"></i> Riesgo <?= e((string) ($selected['ai_decision']['risk'] ?? 'medio')) ?></span>
                            <span><i class="bi bi-sliders"></i> <?= e((string) ($selected['ai_decision']['autonomy_mode'] ?? 'supervised_learning')) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="supervision-confidence">
                    <strong><?= e((string) ($selected['ai_confidence'] ?? 0)) ?>%</strong>
                    <span>confianza IA</span>
                </div>
            </section>

            <?php if (!empty($selected['decision_timeline'])): ?>
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
                <?php foreach ($selected['messages'] as $message): ?>
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
                    <button class="btn btn-primary"><i class="bi bi-stars"></i> Sugerir con IA</button>
                    <span>AsisFly redacta una respuesta editable y aprende de tus cambios.</span>
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

<div class="panel mt-4 compact-connectors">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Canales conectados</span>
            <h2>Fuentes que alimentan al trabajador virtual</h2>
        </div>
        <a class="soft-badge" href="<?= url('/integrations') ?>">Configurar canales <i class="bi bi-arrow-right"></i></a>
    </div>
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
</div>
