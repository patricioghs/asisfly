<?php
$statusOptions = ['new' => 'Nueva', 'open' => 'Abierta', 'pending_approval' => 'Aprobacion', 'answered' => 'Respondida', 'closed' => 'Cerrada'];
$priorityOptions = ['critical' => 'Critica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
$statusIcons = ['new' => 'bi-sparkles', 'open' => 'bi-chat-dots', 'pending_approval' => 'bi-shield-check', 'answered' => 'bi-check2-circle', 'closed' => 'bi-archive'];
$priorityIcons = ['critical' => 'bi-exclamation-octagon', 'high' => 'bi-arrow-up-circle', 'medium' => 'bi-dot', 'low' => 'bi-arrow-down-circle'];
$messageStatusOptions = ['received' => 'Recibido', 'draft' => 'Borrador', 'approved' => 'Aprobado', 'sent' => 'Enviado', 'failed' => 'Fallido'];
$connectorStatusOptions = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectado', 'disabled' => 'Pausado', 'error' => 'Error'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Email' => 'bi-envelope-at'];
$brainLabels = ['commercial' => 'Ventas y clientes', 'administrative' => 'Administracion', 'analytical' => 'Analisis y reportes', 'operational' => 'Operaciones', 'executive' => 'Direccion'];
$latestDraft = $selected['latest_draft'] ?? null;
?>
<div class="inbox-hero panel">
    <div>
        <span class="eyebrow">Conversaciones</span>
        <h2>Bandeja Omnicanal</h2>
        <p>WhatsApp, Instagram, Messenger y correo en una sola bandeja, con respuestas IA sujetas a aprobacion humana.</p>
    </div>
    <div class="inbox-metrics">
        <?php foreach ($metrics as $metric): ?>
            <div><span><?= e($metric['label']) ?></span><strong><?= e($metric['value']) ?></strong></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="inbox-layout mt-4">
    <aside class="panel inbox-list">
        <form class="module-filter-bar" method="get" action="<?= url('/inbox') ?>">
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar cliente o asunto">
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
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-outline-secondary" href="<?= url('/inbox') ?>">Limpiar</a>
        </form>
        <?php foreach ($conversations as $conversation): ?>
            <?php $filterQuery = http_build_query(array_filter($filters ?? [], fn ($value) => $value !== '')); ?>
            <a class="inbox-row <?= $selected && (int) $selected['id'] === (int) $conversation['id'] ? 'active' : '' ?>" href="<?= url('/inbox?id=' . (int) $conversation['id'] . ($filterQuery ? '&' . $filterQuery : '')) ?>">
                <div><strong><?= e($conversation['customer_name']) ?></strong><span><i class="bi <?= e($channelIcons[$conversation['channel']] ?? 'bi-inbox') ?>"></i><?= e($conversation['account_name'] ?: $conversation['channel']) ?></span></div>
                <p><?= e($conversation['subject']) ?></p>
                <small>
                    <span class="status status-<?= e($conversation['status']) ?>"><i class="bi <?= e($statusIcons[$conversation['status']] ?? 'bi-circle') ?>"></i><?= e($statusOptions[$conversation['status']] ?? $conversation['status']) ?></span>
                    <span class="priority priority-<?= e($conversation['priority']) ?>"><i class="bi <?= e($priorityIcons[$conversation['priority']] ?? 'bi-dot') ?>"></i><?= e($priorityOptions[$conversation['priority']] ?? $conversation['priority']) ?></span>
                    <span class="assignee-chip"><i class="bi bi-diagram-3"></i><?= e($brainLabels[$conversation['account_brain'] ?? ''] ?? 'General') ?></span>
                    <?= !empty($conversation['assigned_name']) ? '<span class="assignee-chip"><i class="bi bi-person"></i>' . e($conversation['assigned_name']) . '</span>' : '' ?>
                </small>
            </a>
        <?php endforeach; ?>
        <?php if (!$conversations): ?>
            <div class="empty-state compact">
                <strong>Sin conversaciones para este filtro</strong>
                <p>Cambia los filtros o espera nuevos mensajes desde los conectores.</p>
            </div>
        <?php endif; ?>
    </aside>

    <section class="panel inbox-detail">
        <?php if ($selected): ?>
            <div class="inbox-detail-head">
                <div>
                    <span class="eyebrow"><?= e(($selected['account_name'] ?? $selected['channel']) . ' / ' . ($brainLabels[$selected['account_brain'] ?? ''] ?? 'General')) ?></span>
                    <h2><?= e($selected['customer_name']) ?></h2>
                    <p><?= e($selected['subject']) ?></p>
                    <?php if (!empty($selected['assigned_name'])): ?><small class="text-secondary">Derivado a <?= e($selected['assigned_name']) ?></small><?php endif; ?>
                </div>
                <span class="action-status action-<?= e($selected['status']) ?>"><i class="bi <?= e($statusIcons[$selected['status']] ?? 'bi-circle') ?>"></i><?= e($statusOptions[$selected['status']] ?? $selected['status']) ?></span>
            </div>

            <form method="post" action="<?= url('/inbox/status') ?>" class="status-toolbar">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                <select class="form-select" name="status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selected['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary">Cambiar estado</button>
            </form>

            <div class="inbox-thread">
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
                        <span class="eyebrow">Responder desde Omnicanal</span>
                        <h2>Borrador de respuesta</h2>
                    </div>
                    <?php if ($latestDraft): ?>
                        <span class="status status-<?= e($latestDraft['status']) ?>"><i class="bi bi-pencil-square"></i><?= e($messageStatusOptions[$latestDraft['status']] ?? 'Borrador') ?></span>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= url('/inbox/suggest') ?>" class="reply-action-row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <button class="btn btn-primary"><i class="bi bi-stars"></i> Sugerir con IA</button>
                    <span>AsisFly crea un borrador editable. Si lo corriges, aprende del cambio.</span>
                </form>
                <form method="post" action="<?= url('/inbox/draft') ?>" class="reply-editor-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                    <textarea class="form-control" name="body" rows="5" placeholder="Escribe tu respuesta o edita la sugerencia de AsisFly..."><?= e($latestDraft['body'] ?? '') ?></textarea>
                    <div class="reply-action-row">
                        <button class="btn btn-outline-primary"><i class="bi bi-save"></i> Guardar borrador</button>
                        <span>La edicion queda asociada a <?= e($selected['account_name'] ?: $selected['channel']) ?>.</span>
                    </div>
                </form>
                <?php if ($latestDraft): ?>
                    <form method="post" action="<?= url('/inbox/send-draft') ?>" class="reply-action-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                        <button class="btn btn-success"><i class="bi bi-send-check"></i> Aprobar y marcar enviada</button>
                        <span>En esta fase queda registrada como enviada y auditada por cuenta.</span>
                    </form>
                <?php endif; ?>
            </section>
            <form method="post" action="<?= url('/inbox/assign-human') ?>" class="inbox-suggest">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= e((string) $selected['id']) ?>">
                <button class="btn btn-outline-secondary">Derivar a humano</button>
                <span>Marca la conversacion para seguimiento manual y mantiene el historial del cliente.</span>
            </form>
        <?php else: ?>
            <div class="empty-state">No hay conversaciones todavia.</div>
        <?php endif; ?>
    </section>
</div>

<div class="panel mt-4 compact-connectors">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Canales</span>
            <h2>Conectores comerciales</h2>
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



