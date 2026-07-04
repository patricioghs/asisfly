<div class="chat-layout">
    <div class="panel chat-window" id="chatWindow" data-chat-window>
        <div class="chat-autonomy-card">
            <div>
                <span class="eyebrow">Modo de IA</span>
                <strong><?= e($autonomy['label'] ?? 'Aprendizaje supervisado') ?> · <?= e((string) ($autonomy['learning_progress'] ?? 18)) ?>%</strong>
                <small><?= e(($autonomy['requires_all_approval'] ?? true) ? 'Las respuestas y acciones quedan como sugerencia hasta que el usuario apruebe o modifique.' : 'AsisFly aplica reglas de aprobacion segun riesgo y permisos.') ?></small>
            </div>
            <a class="soft-badge" href="<?= url('/actions') ?>">Ver aprobaciones <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (!$messages): ?>
            <div class="empty-state">
                <strong>Tu empleado digital espera una tarea</strong>
                <p>Pide algo como "analiza esta planilla de ventas", "haz seguimiento a clientes sin respuesta" o "crea 10 publicaciones para vender mas por WhatsApp".</p>
                <div class="prompt-grid">
                    <span>Cerebro comercial</span>
                    <span>Cerebro administrativo</span>
                    <span>Cerebro analitico</span>
                    <span>Cerebro ejecutivo</span>
                </div>
            </div>
        <?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <div class="message <?= e($message['role']) ?>">
                <div class="message-head">
                    <strong><?= $message['role'] === 'user' ? 'Tu' : 'AsisFly' ?></strong>
                    <?php if (!empty($message['brain'])): ?>
                        <span><?= e($message['brain']) ?> / <?= e($message['module'] ?? 'Modulo') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($message['status'])): ?>
                        <small class="ai-status ai-status-<?= e($message['status']) ?>"><?= e($message['status']) ?></small>
                    <?php endif; ?>
                </div>
                <p><?= e($message['content']) ?></p>
            </div>
        <?php endforeach; ?>
        <div id="chatBottom" data-chat-bottom></div>
    </div>
    <form class="composer" method="post" action="<?= url('/chat') ?>" data-chat-form>
        <?= csrf_field() ?>
        <input type="hidden" name="chat_nonce" value="<?= e((string) ($chatNonce ?? '')) ?>">
        <textarea class="form-control" name="prompt" rows="3" placeholder="Escribe una tarea para tu empleado digital..." required data-chat-prompt></textarea>
        <button class="btn btn-primary" type="submit" data-chat-submit>Enviar</button>
    </form>
</div>
