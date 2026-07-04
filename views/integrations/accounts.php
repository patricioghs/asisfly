<?php
$providerLabels = ['whatsapp_cloud' => 'WhatsApp Business', 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'imap' => 'Correo IMAP/SMTP', 'meta' => 'Meta', 'telegram' => 'Telegram', 'obraok' => 'ObraOK'];
$channelLabels = ['WhatsApp' => 'WhatsApp', 'Email' => 'Email', 'Instagram' => 'Instagram', 'Messenger' => 'Messenger', 'Telegram' => 'Telegram', 'Operaciones' => 'Operaciones'];
$statusLabels = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectada', 'disabled' => 'Pausada', 'error' => 'Error'];
$brainLabels = ['commercial' => 'Comercial', 'administrative' => 'Administrativo', 'analytical' => 'Analitico', 'operational' => 'Operacional', 'executive' => 'Ejecutivo'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Email' => 'bi-envelope-at', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Telegram' => 'bi-telegram', 'Operaciones' => 'bi-kanban'];
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
                <input class="form-control" name="external_account_id" placeholder="Email, numero, usuario o ID de cuenta">
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
                        <span>Credenciales</span>
                        <code><?= !empty($account['credentials_updated_at']) ? 'Cifradas: ' . e((string) ($account['credentials_last4'] ?? 'configuradas')) : 'Sin credenciales' ?></code>
                    </div>
                    <button class="btn btn-outline-primary w-100">Guardar cambios</button>
                </form>

                <?php if (in_array($account['provider'], ['imap', 'gmail', 'outlook'], true)): ?>
                    <form class="account-card-form mt-3" method="post" action="<?= url('/integrations/accounts/credentials') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                        <input type="hidden" name="email_address" value="<?= e($account['external_account_id'] ?? '') ?>">
                        <div class="account-form-row">
                            <label>
                                <span>IMAP host</span>
                                <input class="form-control" name="imap_host" placeholder="imap.empresa.com">
                            </label>
                            <label>
                                <span>Puerto</span>
                                <select class="form-select" name="imap_port">
                                    <option value="993">993 SSL</option>
                                    <option value="143">143 TLS/None</option>
                                </select>
                            </label>
                        </div>
                        <div class="account-form-row">
                            <label>
                                <span>SMTP host</span>
                                <input class="form-control" name="smtp_host" placeholder="smtp.empresa.com">
                            </label>
                            <label>
                                <span>Puerto</span>
                                <select class="form-select" name="smtp_port">
                                    <option value="587">587 TLS</option>
                                    <option value="465">465 SSL</option>
                                    <option value="25">25</option>
                                </select>
                            </label>
                        </div>
                        <div class="account-form-row">
                            <label>
                                <span>Usuario</span>
                                <input class="form-control" name="username" placeholder="correo@empresa.com">
                            </label>
                            <label>
                                <span>Clave</span>
                                <input class="form-control" type="password" name="password" placeholder="Clave o app password" autocomplete="new-password">
                            </label>
                        </div>
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock"></i> Guardar credenciales correo</button>
                    </form>
                <?php elseif ($account['provider'] === 'obraok'): ?>
                    <form class="account-card-form mt-3" method="post" action="<?= url('/integrations/accounts/credentials') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                        <label>
                            <span>URL API</span>
                            <input class="form-control" name="api_base_url" placeholder="https://api.obraok.cl">
                        </label>
                        <label>
                            <span>Token API</span>
                            <input class="form-control" type="password" name="api_token" placeholder="Token o API key" autocomplete="new-password">
                        </label>
                        <label>
                            <span>Workspace / empresa</span>
                            <input class="form-control" name="workspace_id" placeholder="ID de cuenta o proyecto">
                        </label>
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock"></i> Guardar credenciales ObraOK</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($account['credentials_updated_at'])): ?>
                    <form class="mt-2" method="post" action="<?= url('/integrations/accounts/test') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                        <button class="btn btn-primary w-100"><i class="bi bi-wifi"></i> Probar conexion</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</div>
