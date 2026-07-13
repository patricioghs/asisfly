<?php
$dayLabels = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'];
$statusLabels = ['pending' => 'Por confirmar', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'completed' => 'Realizada', 'no_show' => 'No asistio'];
$resourceTypes = ['staff' => 'Trabajador', 'room' => 'Sala o box', 'service' => 'Servicio', 'equipment' => 'Equipo', 'general' => 'Agenda general'];
$selectedResourceId = (int) ($selectedResourceId ?? 0);
?>

<section class="panel booking-hero">
    <div>
        <span class="eyebrow">Agenda inteligente</span>
        <h2>Reservas que AsisFly puede gestionar</h2>
        <p>Define disponibilidad, bloquea horarios y comparte una agenda para que clientes reserven sin esperar respuestas manuales.</p>
    </div>
    <div class="booking-public-link">
        <span>Enlace para clientes</span>
        <div><input class="form-control" value="<?= e((string) $publicUrl) ?>" readonly><a class="btn btn-primary" href="<?= e((string) $publicUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i>Ver</a></div>
        <small>Comparte este enlace por WhatsApp, web, correo o redes sociales.</small>
    </div>
</section>

<section class="booking-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card"><span class="metric-icon"><i class="bi <?= e($metric['icon']) ?>"></i></span><div><span><?= e($metric['label']) ?></span><strong><?= e((string) $metric['value']) ?></strong></div></article>
    <?php endforeach; ?>
</section>

<section class="booking-toolbar mt-4">
    <form method="get" action="<?= url('/bookings') ?>">
        <label>Responsable o recurso</label>
        <select class="form-select" name="resource_id" onchange="this.form.submit()">
            <?php foreach ($resources as $resource): ?><option value="<?= e((string) $resource['id']) ?>" <?= (int) $resource['id'] === $selectedResourceId ? 'selected' : '' ?>><?= e($resource['name']) ?><?= !empty($resource['user_name']) ? ' · ' . e($resource['user_name']) : '' ?></option><?php endforeach; ?>
        </select>
        <input type="hidden" name="date" value="<?= e((string) $selectedDate) ?>">
    </form>
    <div class="booking-days">
        <?php foreach ($days as $day): ?><a class="<?= $selectedDate === $day['date'] ? 'active' : '' ?>" href="<?= url('/bookings?resource_id=' . $selectedResourceId . '&date=' . $day['date']) ?>"><small><?= e($day['day']) ?></small><strong><?= e($day['number']) ?></strong><em><?= e($day['month']) ?></em></a><?php endforeach; ?>
    </div>
</section>

<section class="booking-shell mt-4">
    <main class="booking-main">
        <article class="panel booking-day-panel">
            <div class="panel-title"><div><span class="eyebrow">Agenda del responsable</span><h2><?= e(date('d/m/Y', strtotime((string) $selectedDate))) ?></h2></div><span class="soft-badge"><?= e((string) count($appointments)) ?> reservas</span></div>
            <div class="booking-appointment-list">
                <?php foreach ($appointments as $appointment): ?>
                    <article class="booking-appointment status-<?= e($appointment['status']) ?>">
                        <time><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?><small><?= e(date('H:i', strtotime((string) $appointment['ends_at']))) ?></small></time>
                        <div><strong><?= e($appointment['customer_name']) ?></strong><p><?= e($appointment['service_name'] ?: 'Reserva de atención') ?><?= $appointment['customer_phone'] ? ' · ' . e($appointment['customer_phone']) : '' ?></p><small><?= e($appointment['source']) ?><?= $appointment['notes'] ? ' · ' . e($appointment['notes']) : '' ?></small></div>
                        <form method="post" action="<?= url('/bookings/status') ?>">
                            <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>"><input type="hidden" name="resource_id" value="<?= e((string) $selectedResourceId) ?>">
                            <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $appointment['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if (!$appointments): ?><div class="empty-state compact"><strong>Sin reservas en los próximos días</strong><p>Agrega disponibilidad y comparte el enlace de reservas para comenzar.</p></div><?php endif; ?>
            </div>
        </article>

        <div class="booking-action-grid mt-4">
            <article class="panel">
                <div class="panel-title"><div><span class="eyebrow">Reserva interna</span><h2>Agendar una hora</h2></div></div>
                <form class="booking-form" method="post" action="<?= url('/bookings') ?>">
                    <?= csrf_field() ?><input type="hidden" name="resource_id" value="<?= e((string) $selectedResourceId) ?>">
                    <input class="form-control" name="customer_name" placeholder="Nombre del cliente" required>
                    <div class="form-grid"><input class="form-control" name="customer_phone" placeholder="Teléfono"><input class="form-control" name="customer_email" type="email" placeholder="Correo"></div>
                    <input class="form-control" name="service_name" placeholder="Servicio, motivo o tipo de hora">
                    <div class="form-grid"><input class="form-control" name="starts_at" type="datetime-local" required><textarea class="form-control" name="notes" rows="1" placeholder="Nota interna"></textarea></div>
                    <button class="btn btn-primary"><i class="bi bi-calendar-plus"></i>Crear reserva</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-title"><div><span class="eyebrow">Protege tu agenda</span><h2>Bloquear horario</h2></div></div>
                <form class="booking-form" method="post" action="<?= url('/bookings/block') ?>">
                    <?= csrf_field() ?><input type="hidden" name="resource_id" value="<?= e((string) $selectedResourceId) ?>">
                    <div class="form-grid"><input class="form-control" name="starts_at" type="datetime-local" required><input class="form-control" name="ends_at" type="datetime-local" required></div>
                    <input class="form-control" name="reason" placeholder="Motivo: visita, descanso, ocupado">
                    <button class="btn btn-outline-primary"><i class="bi bi-calendar-x"></i>Bloquear</button>
                </form>
            </article>
        </div>
    </main>

    <aside class="booking-side">
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Disponibilidad</span><h2>Horarios semanales</h2></div></div>
            <div class="booking-rule-list">
                <?php foreach ($rules as $rule): ?><div><strong><?= e($dayLabels[(int) $rule['day_of_week']] ?? 'Dia') ?></strong><span><?= e(substr($rule['starts_at'], 0, 5)) ?> - <?= e(substr($rule['ends_at'], 0, 5)) ?></span><form method="post" action="<?= url('/bookings/rule/delete') ?>"><?= csrf_field() ?><input type="hidden" name="rule_id" value="<?= e((string) $rule['id']) ?>"><input type="hidden" name="resource_id" value="<?= e((string) $selectedResourceId) ?>"><button class="icon-only" title="Eliminar horario"><i class="bi bi-x"></i></button></form></div><?php endforeach; ?>
                <?php if (!$rules): ?><p class="task-muted">Todavía no hay horarios disponibles. Agrega el primero abajo.</p><?php endif; ?>
            </div>
            <form class="booking-rule-form" method="post" action="<?= url('/bookings/rule') ?>">
                <?= csrf_field() ?><input type="hidden" name="resource_id" value="<?= e((string) $selectedResourceId) ?>">
                <select class="form-select" name="day_of_week"><?php foreach ($dayLabels as $value => $label): ?><option value="<?= e((string) $value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                <div class="form-grid"><input class="form-control" type="time" name="starts_at" value="09:00" required><input class="form-control" type="time" name="ends_at" value="18:00" required></div>
                <button class="btn btn-sm btn-outline-primary">Agregar horario</button>
            </form>
        </article>

        <details class="panel booking-details mt-4"><summary><span><i class="bi bi-people"></i>Responsables y recursos</span><i class="bi bi-chevron-down"></i></summary>
            <form class="booking-form mt-3" method="post" action="<?= url('/bookings/resource') ?>"><?= csrf_field() ?><input class="form-control" name="name" placeholder="Ej: Dr. Perez, Sala 1, Técnico Camila" required><div class="form-grid"><select class="form-select" name="resource_type"><?php foreach ($resourceTypes as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select><input class="form-control" name="duration_minutes" type="number" min="10" max="480" placeholder="Duración min."></div><button class="btn btn-sm btn-primary">Agregar</button></form>
        </details>

        <details class="panel booking-details mt-4"><summary><span><i class="bi bi-gear"></i>Configuración pública</span><i class="bi bi-chevron-down"></i></summary>
            <form class="booking-form mt-3" method="post" action="<?= url('/bookings/settings') ?>"><?= csrf_field() ?><input class="form-control" name="public_title" value="<?= e($settings['public_title']) ?>" required><textarea class="form-control" name="public_description" rows="2" placeholder="Mensaje de bienvenida"><?= e((string) $settings['public_description']) ?></textarea><div class="form-grid"><input class="form-control" name="default_duration_minutes" type="number" min="10" max="480" value="<?= e((string) $settings['default_duration_minutes']) ?>"><input class="form-control" name="minimum_notice_hours" type="number" min="0" max="720" value="<?= e((string) $settings['minimum_notice_hours']) ?>"></div><select class="form-select" name="confirmation_mode"><option value="manual" <?= $settings['confirmation_mode'] === 'manual' ? 'selected' : '' ?>>Confirmar antes de avisar al cliente</option><option value="automatic" <?= $settings['confirmation_mode'] === 'automatic' ? 'selected' : '' ?>>Confirmar automáticamente</option></select><label class="booking-check"><input type="checkbox" name="public_enabled" value="1" <?= !empty($settings['public_enabled']) ? 'checked' : '' ?>>Agenda pública activa</label><button class="btn btn-sm btn-primary">Guardar configuración</button></form>
        </details>
    </aside>
</section>
