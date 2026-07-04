<?php
$quoteStatusLabels = ['draft' => 'Borrador', 'sent' => 'Enviada', 'accepted' => 'Ganada', 'rejected' => 'Perdida', 'expired' => 'Vencida'];
$quoteStatusIcons = ['draft' => 'bi-pencil-square', 'sent' => 'bi-send-check', 'accepted' => 'bi-trophy', 'rejected' => 'bi-x-circle', 'expired' => 'bi-hourglass-split'];
?>

<div class="quotes-hero panel">
    <div>
        <span class="eyebrow">Cotizaciones Reales</span>
        <h2>Propuestas comerciales con PDF, impuestos y aprobacion de envio</h2>
        <p>Crea cotizaciones numeradas, conectadas al CRM, listas para enviar por correo o WhatsApp con control humano.</p>
    </div>
</div>

<div class="quote-metrics mt-4">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card">
            <span><?= e($metric['label']) ?></span>
            <strong><?= e($metric['value']) ?></strong>
            <small><?= e($metric['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</div>

<div class="quote-shell mt-4">
    <aside class="quote-left">
        <form class="panel quote-form" method="post" action="<?= url('/quotes') ?>">
            <?= csrf_field() ?>
            <h2>Nueva cotizacion</h2>
            <p class="form-hint">Selecciona cliente CRM o cliente directo, producto, impuestos y descuento. El PDF se genera al guardar.</p>
            <select class="form-select" name="customer_id">
                <option value="">Cliente directo</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>"><?= e($customer['name']) ?> / <?= e($customer['contact_name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" name="client" placeholder="Cliente directo">
            <select class="form-select" name="product_id">
                <option value="">Producto/servicio manual</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product['id']) ?>"><?= e($product['name']) ?> / <?= e($product['currency'] . ' ' . number_format((float) $product['unit_price'], 0, ',', '.')) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" name="item" placeholder="Producto o servicio manual">
            <div class="row g-2">
                <div class="col"><input class="form-control" name="quantity" type="number" step="0.01" value="1"></div>
                <div class="col"><input class="form-control" name="price" type="number" step="0.01" placeholder="Precio"></div>
            </div>
            <div class="row g-2">
                <div class="col"><input class="form-control" name="discount" type="number" step="0.01" value="0" placeholder="Descuento"></div>
                <div class="col"><input class="form-control" name="tax" type="number" step="0.01" value="19" placeholder="IVA"></div>
            </div>
            <input class="form-control" type="date" name="valid_until" value="<?= e(date('Y-m-d', strtotime('+15 days'))) ?>">
            <textarea class="form-control" name="notes" rows="2" placeholder="Notas comerciales"></textarea>
            <button class="btn btn-primary w-100">Generar cotizacion + PDF</button>
        </form>

        <form class="panel quote-form mt-3" method="post" action="<?= url('/quotes/product') ?>">
            <?= csrf_field() ?>
            <h2>Catalogo</h2>
            <input class="form-control" name="sku" placeholder="SKU">
            <input class="form-control" name="name" placeholder="Producto/servicio" required>
            <input class="form-control" name="unit_price" type="number" step="0.01" placeholder="Precio" required>
            <input class="form-control" name="tax_rate" type="number" step="0.01" value="19">
            <button class="btn btn-outline-primary w-100">Agregar producto</button>
        </form>
    </aside>

    <section class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Cierre de ventas</span>
                <h2>Cotizaciones</h2>
            </div>
            <span class="soft-badge">PDF + aprobacion</span>
        </div>
        <form class="module-filter-bar mb-3" method="get" action="<?= url('/quotes') ?>">
            <input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar numero, cliente o producto">
            <select class="form-select" name="status">
                <option value="">Todos los estados</option>
                <?php foreach ($quoteStatusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-outline-secondary" href="<?= url('/quotes') ?>">Limpiar</a>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Numero</th><th>Cliente</th><th>Detalle</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><strong><?= e($quote['quote_number']) ?></strong><br><small><?= e($quote['valid_until'] ?? '') ?></small></td>
                        <td><?= e($quote['client']) ?></td>
                        <td><?= e($quote['items']) ?></td>
                        <td><?= e($quote['total_label']) ?></td>
                        <td><span class="status status-<?= e($quote['status']) ?>"><i class="bi <?= e($quoteStatusIcons[$quote['status']] ?? 'bi-circle') ?>"></i><?= e($quoteStatusLabels[$quote['status']] ?? $quote['status']) ?></span></td>
                        <td>
                            <div class="quote-actions">
                                <?php if (!empty($quote['pdf_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($quote['pdf_url']) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a><?php endif; ?>
                                <form method="post" action="<?= url('/quotes/send') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="quote_id" value="<?= e((string) $quote['id']) ?>">
                                    <input type="hidden" name="channel" value="Email">
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope"></i> Email</button>
                                </form>
                                <form method="post" action="<?= url('/quotes/send') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="quote_id" value="<?= e((string) $quote['id']) ?>">
                                    <input type="hidden" name="channel" value="WhatsApp">
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                                </form>
                                <?php if ($quote['status'] !== 'accepted'): ?>
                                    <form method="post" action="<?= url('/quotes/status') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quote_id" value="<?= e((string) $quote['id']) ?>">
                                        <input type="hidden" name="status" value="accepted">
                                        <button class="btn btn-sm btn-primary"><i class="bi bi-trophy"></i> Ganada</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!in_array($quote['status'], ['rejected', 'accepted'], true)): ?>
                                    <form method="post" action="<?= url('/quotes/status') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quote_id" value="<?= e((string) $quote['id']) ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Perdida</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!in_array($quote['status'], ['expired', 'accepted'], true)): ?>
                                    <form method="post" action="<?= url('/quotes/status') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quote_id" value="<?= e((string) $quote['id']) ?>">
                                        <input type="hidden" name="status" value="expired">
                                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-hourglass-split"></i> Vencida</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$quotes): ?>
            <div class="empty-state compact">
                <strong>No hay cotizaciones creadas</strong>
                <p>Crea una cotizacion para mostrar PDF profesional, envio aprobado y conversion a oportunidad.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
