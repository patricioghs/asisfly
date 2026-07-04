<div class="social-hero panel">
    <div>
        <span class="eyebrow">Asisti Social</span>
        <h2>Centro de crecimiento social</h2>
        <p>Planifica contenido, campanas y respuestas para convertir conversaciones en ventas por WhatsApp, Instagram, Facebook, LinkedIn y TikTok.</p>
    </div>
    <div class="social-metrics">
        <?php foreach ($metrics as $metric): ?>
            <div>
                <span><?= e($metric['label']) ?></span>
                <strong><?= e($metric['value']) ?></strong>
                <small><?= e($metric['hint']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="social-command mt-4">
    <form class="panel social-generator" method="post" action="<?= url('/social/generate') ?>">
        <?= csrf_field() ?>
        <div>
            <span class="eyebrow">Planner IA</span>
            <h2>Generador de calendario editorial mensual</h2>
            <p class="text-secondary mb-0">Crea campanas por rubro con captions, hashtags, CTA, imagen sugerida, canal, horario y estado editorial.</p>
        </div>
        <textarea class="form-control" name="brief" rows="3">Crea una campana mensual con foco en vender mas pedidos por WhatsApp.</textarea>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="product_focus" value="Producto con buen margen"></div>
            <div class="col-md-4"><input class="form-control" name="season" value="Temporada actual"></div>
            <div class="col-md-4"><input class="form-control" name="monthly_goal" value="Vender por WhatsApp"></div>
        </div>
        <div class="row g-2">
            <div class="col-md-8">
                <select class="form-select" name="industry">
                    <option>Retail / Ecommerce</option>
                    <option>Construccion</option>
                    <option>Servicios profesionales</option>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" type="number" min="1" max="31" name="quantity" value="20"></div>
        </div>
        <div class="social-channel-picker">
            <?php foreach ($channels as $channel): ?>
                <label><input type="checkbox" name="channels[]" value="<?= e($channel) ?>" checked> <?= e($channel) ?></label>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary">Generar borradores</button>
    </form>

    <aside class="panel social-insights">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Decisiones de marketing</span>
                <h2>Recomendaciones</h2>
            </div>
            <span class="soft-badge">Margen + stock</span>
        </div>
        <?php foreach ($insights as $insight): ?>
            <div class="alert alert-info py-2 mb-2"><?= e($insight) ?></div>
        <?php endforeach; ?>
    </aside>
</div>

<div class="social-planner-grid mt-4">
    <section class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Plantillas por rubro</span>
                <h2>Biblioteca comercial</h2>
            </div>
            <span class="soft-badge"><?= e((string) count($templates)) ?> plantillas</span>
        </div>
        <div class="template-list">
            <?php foreach (array_slice($templates, 0, 6) as $template): ?>
                <article>
                    <strong><?= e($template['name']) ?></strong>
                    <span><?= e($template['industry']) ?> / <?= e($template['channel']) ?></span>
                    <small><?= e($template['content_pillar']) ?> · <?= e($template['objective']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Performance mensual</span>
                <h2>Metricas de publicaciones</h2>
            </div>
            <span class="soft-badge"><?= e($month) ?></span>
        </div>
        <div class="social-performance">
            <span>Impresiones <strong><?= e(number_format((int) $performance['impressions'])) ?></strong></span>
            <span>Clicks <strong><?= e(number_format((int) $performance['clicks'])) ?></strong></span>
            <span>Mensajes <strong><?= e(number_format((int) $performance['messages'])) ?></strong></span>
            <span>Ventas <strong>CLP <?= e(number_format((float) $performance['sales'], 0, ',', '.')) ?></strong></span>
        </div>
    </section>
</div>

<div class="panel mt-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <span class="eyebrow">Calendario mensual</span>
            <h2 class="mb-0">Calendario editorial <?= e($month) ?></h2>
        </div>
        <form class="d-flex gap-2" method="get" action="<?= url('/social') ?>">
            <input class="form-control" type="month" name="month" value="<?= e($month) ?>">
            <button class="btn btn-outline-primary">Ver</button>
        </form>
        <div class="chip-row">
            <span>Borrador</span><span>Aprobado</span><span>Programado</span><span>Publicado</span>
        </div>
    </div>
    <div class="social-calendar mt-3">
        <?php foreach ($posts as $post): ?>
            <article class="social-post">
                <div class="social-post-head">
                    <strong><?= e($post['channel']) ?></strong>
                    <span class="status status-<?= e($post['status']) ?>"><?= e($post['status']) ?></span>
                </div>
                <p><?= e($post['post_text']) ?></p>
                <div class="social-meta">
                    <span><?= e($post['objective']) ?></span>
                    <span><?= e($post['content_pillar'] ?: 'Venta') ?></span>
                    <span><?= e($post['recommended_time']) ?></span>
                    <span><?= e($post['product_focus']) ?></span>
                </div>
                <div class="image-idea"><strong>Imagen sugerida</strong><?= e($post['image_idea']) ?></div>
                <div class="hashtags"><strong>Hashtags</strong><?= e($post['hashtags']) ?></div>
                <div class="cta"><?= e($post['cta']) ?></div>
                <div class="social-post-actions">
                    <?php foreach ([['approved', 'Aprobar'], ['scheduled', 'Programar'], ['published', 'Publicar']] as [$status, $label]): ?>
                        <?php if ($post['status'] !== $status): ?>
                            <form method="post" action="<?= url('/social/status') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="post_id" value="<?= e((string) $post['id']) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <button class="btn btn-sm btn-outline-primary"><?= e($label) ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($post['impressions'] || $post['messages'] || $post['sales_amount']): ?>
                    <div class="social-post-metrics">
                        <span><?= e((string) $post['impressions']) ?> imp.</span>
                        <span><?= e((string) $post['messages']) ?> mensajes</span>
                        <span>CLP <?= e(number_format((float) $post['sales_amount'], 0, ',', '.')) ?></span>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$posts): ?>
        <div class="empty-state compact">
            <strong>No hay publicaciones en calendario</strong>
            <p>Genera una semana de contenido para mostrar textos, hashtags, canales, horarios y estados de aprobacion.</p>
        </div>
    <?php endif; ?>
</div>

<div class="panel mt-4">
    <div class="panel-title">
        <div>
            <span class="eyebrow">Campanas</span>
            <h2>Campanas generadas</h2>
        </div>
        <span class="soft-badge">Meta/TikTok/LinkedIn preparados</span>
    </div>
    <div class="campaign-list">
        <?php foreach ($campaigns as $campaign): ?>
            <article>
                <strong><?= e($campaign['name']) ?></strong>
                <span><?= e($campaign['goal']) ?> / <?= e($campaign['status']) ?></span>
                <small><?= e((string) $campaign['posts']) ?> publicaciones · <?= e($campaign['season'] ?? '') ?></small>
            </article>
        <?php endforeach; ?>
    </div>
</div>

