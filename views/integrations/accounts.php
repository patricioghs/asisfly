<?php
$providerLabels = ['whatsapp_cloud' => 'WhatsApp Business', 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'imap' => 'Correo IMAP/SMTP', 'meta' => 'Meta', 'telegram' => 'Telegram', 'obraok' => 'ObraOK'];
$channelLabels = ['WhatsApp' => 'WhatsApp', 'Email' => 'Email', 'Instagram' => 'Instagram', 'Messenger' => 'Messenger', 'Telegram' => 'Telegram', 'Operaciones' => 'Operaciones'];
$statusLabels = ['simulated' => 'Demo', 'sandbox' => 'Prueba', 'connected' => 'Conectada', 'disabled' => 'Pausada', 'error' => 'Error'];
$brainLabels = ['commercial' => 'Ventas y clientes', 'administrative' => 'Administracion', 'analytical' => 'Analisis y reportes', 'operational' => 'Operaciones', 'executive' => 'Direccion'];
$channelIcons = ['WhatsApp' => 'bi-whatsapp', 'Email' => 'bi-envelope-at', 'Instagram' => 'bi-instagram', 'Messenger' => 'bi-messenger', 'Telegram' => 'bi-telegram', 'Operaciones' => 'bi-kanban'];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$webhookHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
$whatsAppWebhookUrl = $webhookHost !== '' ? $scheme . '://' . $webhookHost . url('/webhooks/whatsapp-cloud') : url('/webhooks/whatsapp-cloud');
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
            <div class="account-token">
                <span>Uso automatico</span>
                <code>AsisFly asigna esta cuenta segun canal, proveedor y nombre.</code>
            </div>
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
        <?php if (empty($accounts)): ?>
            <article class="panel account-empty-state">
                <div class="account-icon"><i class="bi bi-plug"></i></div>
                <div>
                    <span class="eyebrow">Sin cuentas conectadas</span>
                    <h2>Agrega tu primer canal de trabajo</h2>
                    <p>Crea una cuenta de correo, WhatsApp o red social para que AsisFly pueda recibir mensajes, sugerir respuestas y centralizar la operacion.</p>
                </div>
            </article>
        <?php endif; ?>
        <?php foreach ($accounts as $account): ?>
            <?php
                $credential = $account['credential_summary'] ?? [];
                $hasCredentials = !empty($credential['is_configured']);
                $isEmailProvider = in_array($account['provider'], ['imap', 'gmail', 'outlook'], true);
                $isWhatsAppProvider = $account['provider'] === 'whatsapp_cloud';
            ?>
            <article class="panel account-card">
                <div class="account-card-head">
                    <div class="account-icon"><i class="bi <?= e($channelIcons[$account['channel']] ?? 'bi-plug') ?>"></i></div>
                    <div>
                        <span class="eyebrow"><?= e($providerLabels[$account['provider']] ?? $account['provider']) ?></span>
                        <h2><?= e($account['display_name']) ?></h2>
                        <p><?= e($account['external_account_id'] ?: 'Sin identificador publico') ?></p>
                    </div>
                    <span class="status status-<?= e($account['status']) ?>"><i class="bi bi-plug"></i><?= e($statusLabels[$account['status']] ?? $account['status']) ?></span>
                </div>

                <div class="account-status-strip">
                    <span><i class="bi bi-inbox"></i><?= !empty($account['inbound_enabled']) ? 'Recibe mensajes' : 'Entrada pausada' ?></span>
                    <span><i class="bi bi-send"></i><?= !empty($account['outbound_enabled']) ? 'Envio habilitado' : 'Envio en aprobacion' ?></span>
                    <span><i class="bi bi-shield-check"></i><?= !empty($account['requires_approval']) ? 'Requiere aprobacion' : 'Puede ejecutar' ?></span>
                    <span><i class="bi bi-diagram-3"></i><?= e($brainLabels[$account['brain_key'] ?? ''] ?? 'Uso general') ?></span>
                </div>

                <div class="account-credential-summary <?= $hasCredentials ? 'is-ready' : 'is-pending' ?>">
                    <div>
                        <span class="eyebrow">Credenciales</span>
                        <strong><?= $hasCredentials ? 'Configuradas y cifradas' : 'Pendientes de configurar' ?></strong>
                        <small><?= $hasCredentials ? 'Ultima actualizacion: ' . e((string) ($credential['updated_at'] ?? '-')) : 'Guarda los datos tecnicos para activar pruebas de conexion.' ?></small>
                    </div>
                    <?php if ($isEmailProvider): ?>
                        <dl>
                            <div><dt>IMAP</dt><dd><?= e((string) ($credential['imap_host'] ?? 'No registrado')) ?><?= !empty($credential['imap_port']) ? ':' . e((string) $credential['imap_port']) : '' ?></dd></div>
                            <div><dt>SMTP</dt><dd><?= e((string) ($credential['smtp_host'] ?? 'No registrado')) ?><?= !empty($credential['smtp_port']) ? ':' . e((string) $credential['smtp_port']) : '' ?></dd></div>
                            <div><dt>Usuario</dt><dd><?= e((string) ($credential['username'] ?? $credential['email_address'] ?? 'No registrado')) ?></dd></div>
                        </dl>
                    <?php elseif ($isWhatsAppProvider): ?>
                        <dl>
                            <div><dt>Phone Number ID</dt><dd><?= e((string) ($credential['phone_number_id'] ?? $account['external_account_id'] ?? 'No registrado')) ?></dd></div>
                            <div><dt>Business ID</dt><dd><?= e((string) ($credential['business_account_id'] ?? 'Opcional')) ?></dd></div>
                            <div><dt>Graph API</dt><dd><?= e((string) ($credential['graph_version'] ?? 'v20.0')) ?></dd></div>
                        </dl>
                    <?php elseif ($account['provider'] === 'obraok'): ?>
                        <dl>
                            <div><dt>API</dt><dd><?= e((string) ($credential['api_base_url'] ?? 'No registrada')) ?></dd></div>
                            <div><dt>Workspace</dt><dd><?= e((string) ($credential['workspace_id'] ?? 'No registrado')) ?></dd></div>
                        </dl>
                    <?php else: ?>
                        <dl>
                            <div><dt>Proveedor</dt><dd><?= e($providerLabels[$account['provider']] ?? $account['provider']) ?></dd></div>
                            <div><dt>Cuenta</dt><dd><?= e($account['external_account_id'] ?: 'No registrada') ?></dd></div>
                        </dl>
                    <?php endif; ?>
                </div>

                <div class="account-actions-row">
                    <?php if ($hasCredentials): ?>
                        <form method="post" action="<?= url('/integrations/accounts/test') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                            <button class="btn btn-primary"><i class="bi bi-wifi"></i> Probar conexion</button>
                        </form>
                        <?php if ($isEmailProvider): ?>
                            <form method="post" action="<?= url('/integrations/accounts/sync-email') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                                <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Sincronizar correos</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary" href="<?= url('/inbox') ?>"><i class="bi bi-inboxes"></i> Ver mensajes</a>
                </div>

                <details class="account-details">
                    <summary><span>Configuracion de cuenta</span><i class="bi bi-chevron-down"></i></summary>
                    <form class="account-card-form" method="post" action="<?= url('/integrations/accounts/update') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                        <div class="account-form-row">
                            <label>
                                <span>Nombre</span>
                                <input class="form-control" name="display_name" value="<?= e($account['display_name']) ?>">
                            </label>
                            <label>
                                <span>Identificador</span>
                                <input class="form-control" name="external_account_id" value="<?= e($account['external_account_id'] ?? '') ?>">
                            </label>
                        </div>
                        <div class="account-form-row">
                            <label>
                                <span>Estado</span>
                                <select class="form-select" name="status">
                                    <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $account['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Uso principal</span>
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
                        <button class="btn btn-outline-primary w-100">Guardar cambios</button>
                    </form>
                </details>

                <?php if ($isEmailProvider): ?>
                    <details class="account-details">
                        <summary><span><?= $hasCredentials ? 'Actualizar credenciales de correo' : 'Configurar credenciales de correo' ?></span><i class="bi bi-chevron-down"></i></summary>
                        <form class="account-card-form" method="post" action="<?= url('/integrations/accounts/credentials') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                            <input type="hidden" name="email_address" value="<?= e($account['external_account_id'] ?? '') ?>">
                            <div class="account-form-row">
                                <label>
                                    <span>IMAP host</span>
                                    <input class="form-control" name="imap_host" value="<?= e((string) ($credential['imap_host'] ?? '')) ?>" placeholder="imap.empresa.com">
                                </label>
                                <label>
                                    <span>Puerto IMAP</span>
                                    <select class="form-select" name="imap_port">
                                        <option value="993" <?= (string) ($credential['imap_port'] ?? '993') === '993' ? 'selected' : '' ?>>993 SSL</option>
                                        <option value="143" <?= (string) ($credential['imap_port'] ?? '') === '143' ? 'selected' : '' ?>>143 TLS/None</option>
                                    </select>
                                </label>
                            </div>
                            <div class="account-form-row">
                                <label>
                                    <span>SMTP host</span>
                                    <input class="form-control" name="smtp_host" value="<?= e((string) ($credential['smtp_host'] ?? '')) ?>" placeholder="smtp.empresa.com">
                                </label>
                                <label>
                                    <span>Puerto SMTP</span>
                                    <select class="form-select" name="smtp_port">
                                        <option value="587" <?= (string) ($credential['smtp_port'] ?? '587') === '587' ? 'selected' : '' ?>>587 TLS</option>
                                        <option value="465" <?= (string) ($credential['smtp_port'] ?? '') === '465' ? 'selected' : '' ?>>465 SSL</option>
                                        <option value="25" <?= (string) ($credential['smtp_port'] ?? '') === '25' ? 'selected' : '' ?>>25</option>
                                    </select>
                                </label>
                            </div>
                            <div class="account-form-row">
                                <label>
                                    <span>Usuario</span>
                                    <input class="form-control" name="username" value="<?= e((string) ($credential['username'] ?? '')) ?>" placeholder="correo@empresa.com">
                                </label>
                                <label>
                                    <span>Nueva clave</span>
                                    <input class="form-control" type="password" name="password" placeholder="<?= $hasCredentials ? 'Dejar vacio mantiene la clave actual' : 'Clave o app password' ?>" autocomplete="new-password">
                                </label>
                            </div>
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock"></i> Guardar credenciales correo</button>
                        </form>
                    </details>
                <?php elseif ($isWhatsAppProvider): ?>
                    <details class="account-details">
                        <summary><span><?= $hasCredentials ? 'Actualizar WhatsApp Cloud API' : 'Configurar WhatsApp Cloud API' ?></span><i class="bi bi-chevron-down"></i></summary>
                        <div class="account-token">
                            <span>Webhook Meta</span>
                            <code><?= e($whatsAppWebhookUrl) ?></code>
                        </div>
                        <div class="account-token">
                            <span>Verify token</span>
                            <code><?= e((string) ($account['webhook_token'] ?? '')) ?></code>
                        </div>
                        <form class="account-card-form" method="post" action="<?= url('/integrations/accounts/credentials') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                            <div class="account-form-row">
                                <label>
                                    <span>Phone Number ID</span>
                                    <input class="form-control" name="phone_number_id" value="<?= e((string) ($credential['phone_number_id'] ?? $account['external_account_id'] ?? '')) ?>" placeholder="ID del numero en Meta" required>
                                </label>
                                <label>
                                    <span>Business Account ID</span>
                                    <input class="form-control" name="business_account_id" value="<?= e((string) ($credential['business_account_id'] ?? '')) ?>" placeholder="Opcional">
                                </label>
                            </div>
                            <div class="account-form-row">
                                <label>
                                    <span>Graph API</span>
                                    <select class="form-select" name="graph_version">
                                        <?php foreach (['v20.0', 'v21.0', 'v22.0'] as $version): ?>
                                            <option value="<?= e($version) ?>" <?= (string) ($credential['graph_version'] ?? 'v20.0') === $version ? 'selected' : '' ?>><?= e($version) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Access token</span>
                                    <input class="form-control" type="password" name="access_token" placeholder="<?= $hasCredentials ? 'Dejar vacio mantiene el token actual' : 'Token permanente de Meta' ?>" autocomplete="new-password">
                                </label>
                            </div>
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock"></i> Guardar WhatsApp Cloud</button>
                        </form>
                    </details>
                <?php elseif ($account['provider'] === 'obraok'): ?>
                    <details class="account-details">
                        <summary><span><?= $hasCredentials ? 'Actualizar credenciales ObraOK' : 'Configurar credenciales ObraOK' ?></span><i class="bi bi-chevron-down"></i></summary>
                        <form class="account-card-form" method="post" action="<?= url('/integrations/accounts/credentials') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="account_id" value="<?= e((string) $account['id']) ?>">
                            <label>
                                <span>URL API</span>
                                <input class="form-control" name="api_base_url" value="<?= e((string) ($credential['api_base_url'] ?? '')) ?>" placeholder="https://api.obraok.cl">
                            </label>
                            <label>
                                <span>Token API</span>
                                <input class="form-control" type="password" name="api_token" placeholder="<?= $hasCredentials ? 'Dejar vacio mantiene el token actual' : 'Token o API key' ?>" autocomplete="new-password">
                            </label>
                            <label>
                                <span>Workspace / empresa</span>
                                <input class="form-control" name="workspace_id" value="<?= e((string) ($credential['workspace_id'] ?? '')) ?>" placeholder="ID de cuenta o proyecto">
                            </label>
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock"></i> Guardar credenciales ObraOK</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</div>
