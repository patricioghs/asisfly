<?php
$roleLabels = ['owner' => 'Dueño', 'manager' => 'Administrador', 'schedule_operator' => 'Operador de agenda', 'sales' => 'Ejecutivo comercial', 'observer' => 'Solo consulta'];
$statusLabels = ['received' => 'Recibido', 'pending_confirmation' => 'Por confirmar', 'executed' => 'Ejecutado', 'cancelled' => 'Cancelado', 'failed' => 'Fallido'];
?>

<section class="panel launch-hero">
    <div>
        <span class="eyebrow">WhatsApp de equipo</span>
        <h2>Control por WhatsApp</h2>
        <p>Autoriza a dueños y trabajadores para consultar o manejar su agenda desde su propio WhatsApp, sin darles acceso a información que no les corresponde.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= url('/integrations/accounts') ?>"><i class="bi bi-plug"></i> Cuentas conectadas</a>
</section>

<section class="control-whatsapp-layout mt-4">
    <aside class="panel control-whatsapp-form">
        <span class="eyebrow">Nuevo acceso</span>
        <h2>Persona autorizada</h2>
        <?php if (!$accounts): ?>
            <div class="empty-state compact"><strong>Conecta WhatsApp primero</strong><p>Debes tener una cuenta de WhatsApp Business Cloud conectada para recibir comandos internos.</p><a class="btn btn-sm btn-primary" href="<?= url('/integrations/whatsapp/connect') ?>">Conectar WhatsApp</a></div>
        <?php elseif (!$resources): ?>
            <div class="empty-state compact"><strong>Configura una agenda primero</strong><p>Abre Agenda y reservas una vez para crear la agenda principal o agrega responsables.</p><a class="btn btn-sm btn-primary" href="<?= url('/bookings') ?>">Abrir agenda</a></div>
        <?php else: ?>
            <form class="account-form mt-3" method="post" action="<?= url('/integrations/whatsapp-control') ?>">
                <?= csrf_field() ?>
                <label><span>Nombre de la persona</span><input class="form-control" name="display_name" placeholder="Ej: Juan, taller" required></label>
                <label><span>Número WhatsApp personal</span><input class="form-control" name="phone_number" inputmode="tel" placeholder="Ej: +56 9 1234 5678" required></label>
                <label><span>Usuario de AsisFly</span><select class="form-select" name="user_id"><option value="">Sin usuario web asociado</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?> · <?= e($user['email']) ?></option><?php endforeach; ?></select></label>
                <label><span>WhatsApp empresarial que recibirá órdenes</span><select class="form-select" name="account_id" required><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['id']) ?>"><?= e($account['display_name']) ?><?= $account['external_account_id'] ? ' · ' . e($account['external_account_id']) : '' ?></option><?php endforeach; ?></select></label>
                <label><span>Rol de control</span><select class="form-select" name="control_role"><?php foreach ($roleLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label><span>Agenda permitida</span><select class="form-select" name="resource_id" required><?php foreach ($resources as $resource): ?><option value="<?= e((string) $resource['id']) ?>"><?= e($resource['name']) ?></option><?php endforeach; ?></select></label>
                <label class="account-check"><input type="checkbox" name="is_active" value="1" checked><span>Acceso activo</span></label>
                <button class="btn btn-primary w-100"><i class="bi bi-person-check"></i> Autorizar persona</button>
            </form>
        <?php endif; ?>
    </aside>

    <section class="control-whatsapp-main">
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Equipo autorizado</span><h2><?= e((string) count($controllers)) ?> personas</h2></div><span class="soft-badge"><i class="bi bi-shield-check"></i> Números verificados</span></div>
            <div class="control-person-list">
                <?php foreach ($controllers as $controller): ?>
                    <article class="control-person-row">
                        <span class="account-icon"><i class="bi bi-person"></i></span>
                        <div><strong><?= e($controller['display_name']) ?></strong><p><?= e('+' . $controller['phone_number']) ?> · <?= e($roleLabels[$controller['control_role']] ?? $controller['control_role']) ?></p><small><i class="bi bi-whatsapp"></i><?= e($controller['account_name'] ?? 'Cuenta conectada') ?> · <i class="bi bi-calendar3"></i><?= e($controller['resource_name'] ?? 'Sin agenda') ?></small></div>
                        <div class="control-person-actions"><span class="status status-<?= !empty($controller['is_active']) ? 'connected' : 'disabled' ?>"><?= !empty($controller['is_active']) ? 'Activo' : 'Pausado' ?></span><?php if (!empty($controller['is_active'])): ?><form method="post" action="<?= url('/integrations/whatsapp-control/disable') ?>"><?= csrf_field() ?><input type="hidden" name="controller_id" value="<?= e((string) $controller['id']) ?>"><button class="icon-only" title="Desactivar acceso"><i class="bi bi-person-x"></i></button></form><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$controllers): ?><div class="empty-state compact"><strong>Aún no hay personas autorizadas</strong><p>Agrega al dueño y luego a cada trabajador con su agenda correspondiente.</p></div><?php endif; ?>
            </div>
        </article>

        <article class="panel mt-3 control-command-guide">
            <div class="panel-title"><div><span class="eyebrow">Cómo usarlo</span><h2>Comandos iniciales</h2></div></div>
            <div><code>AYUDA</code><span>Ve los comandos permitidos para tu rol.</span></div>
            <div><code>MI AGENDA</code><span>Consulta las próximas reservas de tu agenda.</span></div>
            <div><code>BLOQUEA MI AGENDA MAÑANA DE 10:00 A 13:00</code><span>AsisFly pedirá un código de confirmación antes de bloquear.</span></div>
        </article>

        <article class="panel mt-3">
            <div class="panel-title"><div><span class="eyebrow">Auditoría</span><h2>Últimos comandos</h2></div></div>
            <div class="control-command-list">
                <?php foreach ($commands as $command): ?><div><span class="status status-<?= e($command['status']) ?>"><?= e($statusLabels[$command['status']] ?? $command['status']) ?></span><strong><?= e($command['controller_name']) ?></strong><p><?= e($command['command_text']) ?></p><small><?= e($command['result_message'] ?? '') ?> · <?= e($command['created_at']) ?></small></div><?php endforeach; ?>
                <?php if (!$commands): ?><p class="task-muted">Los comandos enviados desde WhatsApp aparecerán aquí.</p><?php endif; ?>
            </div>
        </article>
    </section>
</section>
