<?php
$providerLabels = ['whatsapp_cloud' => 'WhatsApp Business', 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'imap' => 'Correo corporativo', 'meta' => 'Meta', 'telegram' => 'Telegram'];
$channelLabels = ['WhatsApp' => 'WhatsApp', 'Email' => 'Email', 'Instagram' => 'Instagram', 'Messenger' => 'Messenger', 'Telegram' => 'Telegram'];
$statusLabels = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectada', 'disabled' => 'Pausada', 'error' => 'Error'];
$brainLabels = ['commercial' => 'Comercial', 'administrative' => 'Administrativo', 'analytical' => 'Analitico', 'operational' => 'Operacional', 'executive' => 'Ejecutivo'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Email' => 'bi-envelope-at', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Telegram' => 'bi-telegram'];
?>

<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Omnicanal multi-cuenta</span>
        <h2>Cuentas conectadas</h2>
        <p>Administra todos los WhatsApp, correos, Gmail, Outlook, Instagram y Messenger de una empresa desde una sola plataforma.</p>
    </div>
    <a class="btn btn-primary" href="<?= url('/inbox') ?>"><i class="bi bi-inboxes"></i> Ver bandeja</a>
</section>

<section class="crm-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card">
            <span><?= e($metric['label']) ?></span>
            <strong><?= e($metric['value']) ?></strong>
            <small><?= e($metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<div class="connected-accounts-shell mt-4">
    <aside class="panel account-create-panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Nueva cuenta</span>
                <h2>Agregar canal</h2>
            </div>
        </div>
        <form class="account-form" method="post" action="<?= url('/integrations/accounts') ?>">
            <?= csrf_field() ?>
            <label>
                <span>Nombre interno</span>
                <input class="form-control" name="display_name" placeholder="Ej: WhatsApp Ventas" required>
            </label>
            <label>
                <span>Canal</span>
                <select class="form-select" name="channel">
                    <?php foreach ($channelLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Proveedor</span>
                <select class="form-select" name="provider">
                    <?php foreach ($providerLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Identificador</span>
                <input class="form-control" name="external_account_id" placeholder="Numero, email, usuario o cuenta">
            </label>
            <label>
                <span>Cerebro asignado</span>
                <select class="form-select" name="brain_key">
                    <?php foreach ($brainLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Responsable</span>
                <select class="form-select" name="assigned_user_id">
                    <option value="">Sin responsable fijo</option>
                    <?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <div class="account-toggle-grid">
                <label><input type="checkbox" name="inbound_enabled" value="1" checked><span>Recibir</span></label>
                <label><input type="checkbox" name="outbound_enabled" value="1"><span>Enviar</span></label>
                <label><input type="checkbox" name="requires_approval" value="1" checked><span>Aprobar</span></label>
            </div>
            <button class="btn btn-primary w-100">Crear cuenta</button>
        </form>
    </aside>

    <section class="account-grid">
        <?php foreach ($accounts as $account): ?>
            <article class="panel account-card">
                <div class="account-card-head">
                    <div class="account-icon"><i class="bi <?= e($channelIcons[$account['channel']] ?? 'bi-plug') ?>"></i></div>
                    <div>
                        <span class="eyebrow"><?= e($providerLabels[$account['provider']] ?? $account['provider']) ?></span>
                        <h2><?= e($account['display_name']) ?></h2>
                    </div>
                    <span class="status status-<?= e($account['status']) ?>"><i class="bi bi-plug"></i><?= e($statusLabels[$account['status']] ?? $account['status']) ?></span>
                </div>
                <form class="account-card-form" method="post" action="<?= url('/integrations/accounts/update') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                    <label>
                        <span>Nombre</span>
                        <input class="form-control" name="display_name" value="<?= e($account['display_name']) ?>">
                    </label>
                    <label>
                        <span>Identificador</span>
                        <input class="form-control" name="external_account_id" value="<?= e($account['external_account_id'] ?? '') ?>">
                    </label>
                    <div class="account-form-row">
                        <label>
                            <span>Estado</span>
                            <select class="form-select" name="status">
                                <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $account['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Cerebro</span>
                            <select class="form-select" name="brain_key">
                                <?php foreach ($brainLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($account['brain_key'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span>Responsable</span>
                        <select class="form-select" name="assigned_user_id">
                            <option value="">Sin responsable fijo</option>
                            <?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= (string) ($account['assigned_user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <div class="account-toggle-grid">
                        <label><input type="checkbox" name="inbound_enabled" value="1" <?= !empty($account['inbound_enabled']) ? 'checked' : '' ?>><span>Recibir</span></label>
                        <label><input type="checkbox" name="outbound_enabled" value="1" <?= !empty($account['outbound_enabled']) ? 'checked' : '' ?>><span>Enviar</span></label>
                        <label><input type="checkbox" name="requires_approval" value="1" <?= !empty($account['requires_approval']) ? 'checked' : '' ?>><span>Aprobar</span></label>
                    </div>
                    <div class="account-token">
                        <span>Webhook</span>
                        <code><?= e($account['webhook_token']) ?></code>
                    </div>
                    <button class="btn btn-outline-primary w-100">Guardar cambios</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</div>
