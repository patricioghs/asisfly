<section class="marketplace-hero panel">
    <div>
        <span class="eyebrow">Marketplace interno</span>
        <h2>Habilidades de AsisFly por empresa</h2>
        <p>Activa o pausa modulos sin borrar datos. El cambio impacta el menu dinamico y deja preparadas las herramientas futuras por habilidad.</p>
    </div>
    <div class="marketplace-summary">
        <span><strong><?= e((string) $summary['total']) ?></strong> disponibles</span>
        <span><strong><?= e((string) $summary['active']) ?></strong> activas</span>
        <span><strong><?= e((string) $summary['disabled']) ?></strong> pausadas</span>
    </div>
</section>

<section class="marketplace-grid mt-4">
    <?php foreach ($items as $item): ?>
        <?php
            $tenantStatus = $item['tenant_status'] ?: 'available';
            $isActive = $tenantStatus === 'active';
            $isDisabled = $tenantStatus === 'disabled';
            $isProtected = !empty($item['is_protected']);
            $priceLabel = ($item['pricing_model'] ?? 'included') === 'included'
                ? 'Incluida'
                : 'Addon ' . (string) $item['currency'] . ' ' . number_format((float) $item['monthly_price'], 0) . '/mes';
        ?>
        <article class="marketplace-card panel">
            <div class="marketplace-card-head">
                <div class="marketplace-icon"><i class="bi bi-boxes"></i></div>
                <div>
                    <span class="eyebrow"><?= e((string) $item['category']) ?></span>
                    <h3><?= e((string) $item['title']) ?></h3>
                </div>
                <span class="status status-<?= e($tenantStatus) ?>"><?= e($isActive ? 'Activa' : ($isDisabled ? 'Pausada' : 'Disponible')) ?></span>
            </div>

            <p><?= e((string) ($item['long_description'] ?: $item['short_description'])) ?></p>

            <div class="marketplace-meta">
                <span><i class="bi bi-tag"></i><?= e($priceLabel) ?></span>
                <span><i class="bi bi-code-square"></i><?= e((string) ($item['installed_version'] ?: $item['latest_version'] ?: '1.0.0')) ?></span>
                <span><i class="bi bi-shield-check"></i><?= !empty($item['is_core']) ? 'Core' : 'Modulo' ?></span>
            </div>

            <div class="marketplace-dependencies">
                <strong>Dependencias</strong>
                <?php if (!empty($item['dependencies'])): ?>
                    <div>
                        <?php foreach ($item['dependencies'] as $dependency): ?>
                            <span class="dependency-chip <?= ($dependency['tenant_status'] ?? '') === 'active' ? 'ready' : 'pending' ?>">
                                <?= e((string) $dependency['commercial_name']) ?>
                                <small><?= e((string) $dependency['dependency_type']) ?></small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <small>No requiere otras habilidades.</small>
                <?php endif; ?>
            </div>

            <div class="marketplace-actions">
                <?php if ($isActive): ?>
                    <form method="post" action="<?= url('/marketplace/disable') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ability_key" value="<?= e((string) $item['ability_key']) ?>">
                        <button class="btn btn-outline-danger" <?= $isProtected ? 'disabled' : '' ?>>
                            <i class="bi bi-pause-circle"></i> Desactivar
                        </button>
                    </form>
                    <?php if ($isProtected): ?><small>Critica para operar AsisFly.</small><?php endif; ?>
                <?php else: ?>
                    <form method="post" action="<?= url('/marketplace/activate') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ability_key" value="<?= e((string) $item['ability_key']) ?>">
                        <button class="btn btn-primary"><i class="bi bi-lightning-charge"></i> Activar</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!$items): ?>
    <div class="empty-state">
        <strong>Marketplace sin habilidades cargadas</strong>
        <p>Ejecuta la migracion y el seed de habilidades para habilitar el catalogo interno.</p>
    </div>
<?php endif; ?>
