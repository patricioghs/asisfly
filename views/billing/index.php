<section class="billing-hero panel">
    <div>
        <span class="eyebrow">Suscripcion</span>
        <h2>Planes, limites y facturacion por pais</h2>
        <p>Controla usuarios, documentos, mensajes IA, tokens, integraciones y automatizaciones antes de vender a clientes finales.</p>
    </div>
    <div class="billing-current">
        <span>Plan actual</span>
        <strong><?= e($current['plan_name'] ?? 'Starter') ?></strong>
        <small><?= e(($current['subscription_status'] ?? 'trialing') . ' · pago sugerido: ' . ($current['provider_recommended'] ?? 'stripe')) ?></small>
        <?php if (!empty($current['trial_ends_at'])): ?><small>Trial hasta <?= e($current['trial_ends_at']) ?></small><?php endif; ?>
    </div>
</section>

<section class="billing-usage mt-4">
    <?php foreach ($usage as $key => $row): ?>
        <article class="panel">
            <span><?= e($key) ?></span>
            <strong><?= e(number_format((int) $row['used'])) ?> / <?= e((int) $row['limit'] === -1 ? 'Ilimitado' : number_format((int) $row['limit'])) ?></strong>
            <div class="usage-bar"><i style="width: <?= e((string) $row['percent']) ?>%"></i></div>
            <small><?= $row['blocked'] ? 'Limite alcanzado' : 'Disponible' ?></small>
        </article>
    <?php endforeach; ?>
</section>

<section class="plans-grid mt-4">
    <?php foreach ($plans as $plan): ?>
        <article class="panel plan-card <?= ($current['plan_name'] ?? '') === $plan['name'] ? 'active' : '' ?>">
            <div class="plan-head">
                <div>
                    <span class="eyebrow"><?= e($plan['name']) ?></span>
                    <h2>USD <?= e(number_format((float) $plan['monthly_price'], 0)) ?>/mes</h2>
                </div>
                <?php if (($current['plan_name'] ?? '') === $plan['name']): ?><span class="soft-badge">Actual</span><?php endif; ?>
            </div>
            <ul>
                <?php foreach ($plan['limits'] as $limit => $value): ?>
                    <li><?= e($limit) ?>: <strong><?= e((int) $value === -1 ? 'Ilimitado' : (string) $value) ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= url('/billing/plan') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="plan" value="<?= e($plan['name']) ?>">
                <button class="btn <?= ($current['plan_name'] ?? '') === $plan['name'] ? 'btn-outline-secondary' : 'btn-primary' ?> w-100">
                    <?= ($current['plan_name'] ?? '') === $plan['name'] ? 'Mantener plan' : 'Cambiar a ' . e($plan['name']) ?>
                </button>
            </form>
        </article>
    <?php endforeach; ?>
</section>

<section class="billing-grid mt-4">
    <div class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Pasarela</span>
                <h2>Proveedor segun pais</h2>
            </div>
        </div>
        <div class="payment-options">
            <span>Chile <strong>Webpay</strong></span>
            <span>Brasil/Mexico/Argentina/Colombia <strong>MercadoPago</strong></span>
            <span>Otros paises <strong>Stripe</strong></span>
        </div>
        <p class="text-secondary mb-0 mt-3">El checkout queda simulado para demos; el modelo de datos ya guarda proveedor, periodo, estado y eventos.</p>
    </div>

    <div class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Auditoria</span>
                <h2>Eventos de facturacion</h2>
            </div>
        </div>
        <?php foreach ($events as $event): ?>
            <div class="list-row">
                <strong><?= e($event['event_type']) ?> · <?= e($event['provider']) ?></strong>
                <span><?= e(($event['plan_from'] ?? '-') . ' -> ' . ($event['plan_to'] ?? '-')) ?></span>
                <small><?= e($event['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
        <?php if (!$events): ?><div class="empty-state compact"><strong>Sin eventos</strong><p>Los upgrades, downgrades y renovaciones apareceran aqui.</p></div><?php endif; ?>
    </div>
</section>
