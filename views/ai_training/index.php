<?php
$section = trim((string) ($_GET['section'] ?? ($section ?? 'summary')));
$requestValue = (string) ($_SERVER['REQUEST_URI'] ?? '');
if (($section === '' || $section === 'summary') && preg_match('~(?:^|/)ai-training/([a-z-]+)(?:[?#/].*)?$~', $requestValue, $requestMatches)) {
    $section = trim((string) $requestMatches[1]);
}
if (($section === '' || $section === 'summary')) {
    $queryString = (string) parse_url($requestValue, PHP_URL_QUERY);
    parse_str($queryString, $queryParams);
    if (!empty($queryParams['section'])) {
        $section = trim((string) $queryParams['section']);
    }
}
if (($section === '' || $section === 'summary') && !empty($_GET['section'])) {
    $section = trim((string) $_GET['section']);
}
$overview = $overview ?? [];
$session = $overview['session'] ?? [];
$profile = $overview['profile'] ?? [];
$personality = $overview['personality'] ?? [];
$metrics = $overview['metrics'] ?? [];
$products = $overview['products'] ?? [];
$rules = $overview['rules'] ?? [];
$faqs = $overview['faqs'] ?? [];
$examples = $overview['examples'] ?? [];
$autonomy = $overview['autonomy'] ?? ['learning_progress' => 0, 'label' => 'Aprendizaje supervisado'];
$channels = $overview['channels'] ?? [];
$prompt = $overview['prompt'] ?? [];
$knowledgeSources = $overview['knowledgeSources'] ?? [];
$knowledgeStats = $overview['knowledgeStats'] ?? ['sources' => 0, 'ready' => 0, 'failed' => 0, 'chunks' => 0];
$knowledgeQuery = $overview['knowledgeQuery'] ?? '';
$knowledgeResults = $overview['knowledgeResults'] ?? [];
$responseReviews = $overview['responseReviews'] ?? [];
$brandRoutes = $overview['brandRoutes'] ?? [];
$selectedBrandRouteId = (int) ($overview['selectedBrandRouteId'] ?? ($_GET['brand_route_id'] ?? 0));
$selectedBrandRoute = $overview['selectedBrandRoute'] ?? null;
$routingSettings = $overview['routingSettings'] ?? [
    'shared_channels' => 'WhatsApp principal',
    'ambiguous_action' => 'ask_customer',
    'min_confidence' => 70,
    'default_brand_route_id' => null,
    'status' => 'active',
];
$questions = $questions ?? [];
$answersByKey = $answersByKey ?? [];
$simulation = $simulation ?? null;
$tabs = [
    'summary' => ['Resumen', 'bi-speedometer2'],
    'onboarding' => ['Entrevista inicial', 'bi-chat-dots'],
    'company' => ['Empresa', 'bi-building'],
    'products' => ['Productos y servicios', 'bi-box-seam'],
    'personality' => ['Personalidad', 'bi-palette'],
    'rules' => ['Reglas', 'bi-shield-check'],
    'faqs' => ['Preguntas frecuentes', 'bi-question-circle'],
    'documents' => ['Documentos', 'bi-file-earmark-text'],
    'examples' => ['Ejemplos', 'bi-chat-square-quote'],
    'corrections' => ['Correcciones', 'bi-pencil-square'],
    'routing' => ['Marcas y enrutamiento', 'bi-signpost-split'],
    'simulator' => ['Simulador', 'bi-stars'],
    'settings' => ['Configuracion', 'bi-sliders'],
    'versions' => ['Versiones', 'bi-clock-history'],
];
if (!isset($tabs[$section])) {
    $section = 'summary';
}
$activeTab = $tabs[$section];
$scopedSections = ['summary', 'onboarding', 'company', 'products', 'personality', 'rules', 'faqs', 'examples', 'simulator'];
$trainingSectionUrl = static function (string $key, ?int $brandRouteId = null) use (&$selectedBrandRouteId): string {
    $query = ['section' => $key];
    $targetBrandRouteId = $brandRouteId ?? $selectedBrandRouteId;
    if ($targetBrandRouteId > 0) {
        $query['brand_route_id'] = $targetBrandRouteId;
    }
    return url('/ai-training?' . http_build_query($query)) . '#training-content';
};
$scopeLabel = $selectedBrandRouteId > 0 ? (string) ($selectedBrandRoute['brand_name'] ?? 'Marca seleccionada') : 'General';
$statusLabels = ['draft' => 'Borrador', 'review' => 'En revision', 'published' => 'Publicado', 'archived' => 'Archivado'];
$toneLabels = ['formal' => 'Formal', 'professional' => 'Profesional', 'close' => 'Cercano', 'technical' => 'Tecnico', 'commercial' => 'Comercial'];
$lengthLabels = ['short' => 'Breve', 'medium' => 'Media', 'detailed' => 'Detallada'];
$priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Critica'];
$modeLabels = ['manual' => 'Manual', 'assisted' => 'Asistido', 'automatic' => 'Automatico'];
$routeStatusLabels = ['active' => 'Activa', 'inactive' => 'Inactiva'];
$ambiguousActionLabels = ['ask_customer' => 'Preguntar al cliente', 'human_review' => 'Enviar a revision humana', 'default_brand' => 'Usar marca por defecto'];
$progress = (int) ($session['progress_percent'] ?? 0);
?>

<section class="panel controls-hero">
    <div>
        <span class="eyebrow">Centro de Entrenamiento IA</span>
        <h2>Ensena a AsisFly como trabaja tu empresa</h2>
        <p>Construye el perfil, reglas, preguntas frecuentes y ejemplos que el trabajador virtual usara como contexto autorizado antes de responder.</p>
        <div class="controls-hero-actions">
            <a class="btn btn-primary" href="<?= e($trainingSectionUrl('onboarding')) ?>"><i class="bi bi-chat-dots"></i> Continuar entrevista</a>
            <a class="btn btn-outline-secondary" href="<?= e($trainingSectionUrl('simulator')) ?>"><i class="bi bi-stars"></i> Practicar respuesta</a>
        </div>
    </div>
    <div class="controls-command-card">
        <span>Estado del conocimiento</span>
        <strong><?= e($statusLabels[$profile['status'] ?? 'draft'] ?? 'Borrador') ?> / <?= e((string) $progress) ?>%</strong>
        <p>La preparacion se calcula con criterios visibles: perfil, productos, personalidad, reglas, FAQs y ejemplos aprobados.</p>
    </div>
</section>

<nav class="panel controls-filter-bar ai-training-tabs mt-4" aria-label="Secciones del Centro de Entrenamiento IA">
    <?php foreach ($tabs as $key => [$label, $icon]): ?>
        <a class="btn <?= $section === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e($trainingSectionUrl($key)) ?>"><i class="bi <?= e($icon) ?>"></i><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (in_array($section, $scopedSections, true)): ?>
    <nav class="panel controls-filter-bar ai-training-tabs mt-3" aria-label="Alcance del entrenamiento">
        <span class="soft-badge"><i class="bi bi-diagram-3"></i> Entrenando: <?= e($scopeLabel) ?></span>
        <a class="btn <?= $selectedBrandRouteId === 0 ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e($trainingSectionUrl($section, 0)) ?>">
            <i class="bi bi-layers"></i>General
        </a>
        <?php foreach ($brandRoutes as $route): ?>
            <a class="btn <?= $selectedBrandRouteId === (int) $route['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e($trainingSectionUrl($section, (int) $route['id'])) ?>">
                <i class="bi bi-building-check"></i><?= e((string) $route['brand_name']) ?>
            </a>
        <?php endforeach; ?>
        <a class="btn btn-outline-secondary" href="<?= e($trainingSectionUrl('routing', 0)) ?>">
            <i class="bi bi-plus-lg"></i>Agregar empresa/marca
        </a>
    </nav>
<?php endif; ?>

<section id="training-content" class="panel ai-training-active-section mt-4" tabindex="-1">
    <div>
        <span class="eyebrow">Seccion activa</span>
        <h2><i class="bi <?= e($activeTab[1]) ?>"></i><?= e($activeTab[0]) ?></h2>
    </div>
    <small>Completa esta parte para que AsisFly aprenda como vender, responder y operar en <?= e($scopeLabel) ?>.</small>
</section>

<?php if ($section === 'summary'): ?>
    <section class="workbench-metrics mt-4">
        <?php foreach ($metrics as $metric): ?>
            <article class="metric-card">
                <span><?= e((string) $metric['label']) ?></span>
                <strong><?= e((string) $metric['value']) ?></strong>
                <small><?= e((string) $metric['hint']) ?></small>
            </article>
        <?php endforeach; ?>
        <?php if (!$metrics): ?>
            <article class="metric-card"><span>Preparacion</span><strong>0%</strong><small>Ejecuta la migracion de Fase 1.</small></article>
        <?php endif; ?>
    </section>

    <section class="controls-detail-grid mt-4">
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Base empresarial</span><h2>Perfil publicado</h2></div></div>
            <p><?= e((string) ($profile['description'] ?? 'Aun no hay descripcion publicada. Completa la entrevista o el perfil de empresa.')) ?></p>
            <div class="control-rules-grid">
                <span><strong>Empresa</strong><?= e((string) ($profile['company_name'] ?? ($company['name'] ?? 'Empresa'))) ?></span>
                <span><strong>Rubro</strong><?= e((string) ($profile['industry'] ?? 'Sin definir')) ?></span>
                <span><strong>Objetivo IA</strong><?= e((string) ($profile['primary_objective'] ?? 'Sin definir')) ?></span>
            </div>
        </article>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Activacion</span><h2>Publicar conocimiento</h2></div></div>
            <p>Publicar marca el perfil y personalidad como conocimiento autorizado. Las reglas, FAQs y ejemplos deben estar en estado Publicado para entrar al contexto.</p>
            <form method="post" action="<?= url('/ai-training/publish') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
                <button class="btn btn-primary"><i class="bi bi-check2-circle"></i>Confirmar y publicar</button>
            </form>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'onboarding'): ?>
    <section class="panel mt-4">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Entrevista guiada</span>
                <h2>Antes de atender clientes, necesito conocer tu empresa</h2>
            </div>
            <span class="soft-badge"><?= e((string) $progress) ?>%</span>
        </div>
        <div class="progress mb-4" role="progressbar" aria-label="Progreso de entrevista">
            <div class="progress-bar" style="width: <?= e((string) $progress) ?>%"></div>
        </div>
        <p class="text-secondary">Responde una pregunta a la vez. Puedes guardar y continuar despues; las preguntas opcionales se pueden dejar vacias.</p>
        <div class="controls-detail-grid">
            <?php foreach ($questions as $key => $question): ?>
                <?php $answer = $answersByKey[$key]['answer'] ?? ''; ?>
                <form id="question-<?= e($key) ?>" class="panel control-entry-form" method="post" action="<?= url('/ai-training/onboarding') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="question_key" value="<?= e($key) ?>">
                    <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
                    <span class="eyebrow"><?= e((string) ($question['section'] ?? 'Entrevista')) ?><?= !empty($question['optional']) ? ' / opcional' : '' ?></span>
                    <h2><?= e((string) $question['label']) ?></h2>
                    <textarea class="form-control" name="answer" rows="4" placeholder="Escribe la respuesta de la empresa..."><?= e((string) $answer) ?></textarea>
                    <button class="btn btn-primary"><i class="bi bi-save"></i>Guardar respuesta</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($section === 'company'): ?>
    <form class="panel mt-4 control-entry-form" method="post" action="<?= url('/ai-training/profile') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
        <div class="panel-title"><div><span class="eyebrow">Empresa</span><h2>Perfil del trabajador virtual</h2></div></div>
        <div class="controls-detail-grid">
            <input class="form-control" name="company_name" value="<?= e((string) ($profile['company_name'] ?? ($company['name'] ?? ''))) ?>" placeholder="Nombre de empresa">
            <input class="form-control" name="industry" value="<?= e((string) ($profile['industry'] ?? '')) ?>" placeholder="Rubro">
            <textarea class="form-control" name="description" rows="4" placeholder="Descripcion de la empresa"><?= e((string) ($profile['description'] ?? '')) ?></textarea>
            <textarea class="form-control" name="main_offering" rows="4" placeholder="Productos o servicios principales"><?= e((string) ($profile['main_offering'] ?? '')) ?></textarea>
            <textarea class="form-control" name="customer_type" rows="3" placeholder="Tipo de clientes"><?= e((string) ($profile['customer_type'] ?? '')) ?></textarea>
            <textarea class="form-control" name="value_proposition" rows="3" placeholder="Propuesta de valor"><?= e((string) ($profile['value_proposition'] ?? '')) ?></textarea>
            <textarea class="form-control" name="differentiators" rows="3" placeholder="Diferenciadores"><?= e((string) ($profile['differentiators'] ?? '')) ?></textarea>
            <input class="form-control" name="business_hours" value="<?= e((string) ($profile['business_hours'] ?? '')) ?>" placeholder="Horario de atencion">
            <input class="form-control" name="website" value="<?= e((string) ($profile['website'] ?? '')) ?>" placeholder="Sitio web">
            <textarea class="form-control" name="locations" rows="2" placeholder="Ubicaciones"><?= e((string) ($profile['locations'] ?? '')) ?></textarea>
            <textarea class="form-control" name="contact_details" rows="2" placeholder="Datos de contacto"><?= e((string) ($profile['contact_details'] ?? '')) ?></textarea>
            <input class="form-control" name="primary_objective" value="<?= e((string) ($profile['primary_objective'] ?? '')) ?>" placeholder="Objetivo principal de AsisFly">
        </div>
        <select class="form-select" name="status">
            <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($profile['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><i class="bi bi-save"></i>Guardar perfil</button>
    </form>
<?php endif; ?>

<?php if ($section === 'products'): ?>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/product') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
            <div class="panel-title"><div><span class="eyebrow">Catalogo IA</span><h2>Agregar producto o servicio</h2></div></div>
            <input class="form-control" name="name" placeholder="Nombre" required>
            <div class="control-create-grid">
                <select class="form-select" name="item_type"><option value="service">Servicio</option><option value="product">Producto</option></select>
                <input class="form-control" name="category" placeholder="Categoria">
            </div>
            <textarea class="form-control" name="description" rows="4" placeholder="Descripcion"></textarea>
            <div class="control-create-grid">
                <select class="form-select" name="price_type"><option value="quote_required">Sujeto a cotizacion</option><option value="fixed">Precio fijo</option><option value="range">Rango</option></select>
                <input class="form-control" name="currency" value="<?= e($company['currency'] ?? 'CLP') ?>" placeholder="Moneda">
            </div>
            <div class="control-create-grid">
                <input class="form-control" name="price_from" type="number" step="0.01" placeholder="Precio desde">
                <input class="form-control" name="price_to" type="number" step="0.01" placeholder="Precio hasta">
            </div>
            <input class="form-control" name="delivery_time" placeholder="Tiempo de entrega">
            <textarea class="form-control" name="requirements" rows="2" placeholder="Requisitos"></textarea>
            <textarea class="form-control" name="warranty" rows="2" placeholder="Garantia"></textarea>
            <button class="btn btn-primary">Agregar</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Activos</span><h2>Productos y servicios</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($products as $product): ?>
                    <div><strong><?= e((string) $product['name']) ?></strong><span><?= e((string) $product['item_type']) ?> / <?= e((string) ($product['category'] ?? 'Sin categoria')) ?></span><small><?= e((string) ($product['description'] ?? '')) ?></small></div>
                <?php endforeach; ?>
                <?php if (!$products): ?><p class="task-muted">Aun no hay productos o servicios registrados.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'personality'): ?>
    <form class="panel mt-4 control-entry-form" method="post" action="<?= url('/ai-training/personality') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
        <div class="panel-title"><div><span class="eyebrow">Personalidad</span><h2>Como debe hablar AsisFly</h2></div></div>
        <div class="controls-detail-grid">
            <select class="form-select" name="tone"><?php foreach ($toneLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($personality['tone'] ?? 'professional') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <select class="form-select" name="response_length"><?php foreach ($lengthLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($personality['response_length'] ?? 'medium') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            <input class="form-control" name="primary_language" value="<?= e((string) ($personality['primary_language'] ?? 'Espanol latino')) ?>" placeholder="Idioma principal">
            <select class="form-select" name="persuasion_level"><option value="low" <?= ($personality['persuasion_level'] ?? '') === 'low' ? 'selected' : '' ?>>Persuasion baja</option><option value="medium" <?= ($personality['persuasion_level'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Persuasion media</option><option value="high" <?= ($personality['persuasion_level'] ?? '') === 'high' ? 'selected' : '' ?>>Persuasion alta</option></select>
            <input class="form-control" name="greeting_style" value="<?= e((string) ($personality['greeting_style'] ?? '')) ?>" placeholder="Forma de saludo">
            <input class="form-control" name="closing_style" value="<?= e((string) ($personality['closing_style'] ?? '')) ?>" placeholder="Forma de despedida">
        </div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="allow_emojis" value="1" <?= !empty($personality['allow_emojis']) ? 'checked' : '' ?>> <span class="form-check-label">Permitir emojis</span></label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="use_customer_name" value="1" <?= !isset($personality['use_customer_name']) || !empty($personality['use_customer_name']) ? 'checked' : '' ?>> <span class="form-check-label">Usar nombre del cliente</span></label>
        <textarea class="form-control" name="preferred_phrases" rows="2" placeholder="Frases preferidas"><?= e((string) ($personality['preferred_phrases'] ?? '')) ?></textarea>
        <textarea class="form-control" name="forbidden_phrases" rows="2" placeholder="Frases prohibidas"><?= e((string) ($personality['forbidden_phrases'] ?? '')) ?></textarea>
        <textarea class="form-control" name="good_examples" rows="3" placeholder="Ejemplos correctos"><?= e((string) ($personality['good_examples'] ?? '')) ?></textarea>
        <textarea class="form-control" name="bad_examples" rows="3" placeholder="Ejemplos incorrectos"><?= e((string) ($personality['bad_examples'] ?? '')) ?></textarea>
        <select class="form-select" name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($personality['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary">Guardar personalidad</button>
    </form>
<?php endif; ?>

<?php if ($section === 'rules'): ?>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/rule') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
            <div class="panel-title"><div><span class="eyebrow">Regla operativa</span><h2>Nueva regla</h2></div></div>
            <input class="form-control" name="name" placeholder="Ej: No ofrecer descuentos sin autorizacion" required>
            <textarea class="form-control" name="description" rows="2" placeholder="Descripcion"></textarea>
            <textarea class="form-control" name="condition_text" rows="2" placeholder="Condicion"></textarea>
            <textarea class="form-control" name="action_text" rows="2" placeholder="Accion"></textarea>
            <div class="control-create-grid">
                <select class="form-select" name="priority"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                <input class="form-control" name="channel" value="all" placeholder="Canal">
            </div>
            <input class="form-control" name="escalation_role" placeholder="Rol o persona de escalamiento">
            <input class="form-control" name="effective_until" type="date">
            <input type="hidden" name="status" value="published">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <span class="form-check-label">Activa</span></label>
            <button class="btn btn-primary">Agregar regla</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Publicadas</span><h2>Reglas actuales</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($rules as $rule): ?>
                    <div><strong><?= e((string) $rule['name']) ?></strong><span><?= e($priorityLabels[$rule['priority']] ?? $rule['priority']) ?> / <?= e((string) $rule['channel']) ?></span><small><?= e((string) ($rule['action_text'] ?? $rule['description'] ?? '')) ?></small></div>
                <?php endforeach; ?>
                <?php if (!$rules): ?><p class="task-muted">Aun no hay reglas. Parte por reglas criticas de seguridad comercial.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'faqs'): ?>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/faq') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
            <div class="panel-title"><div><span class="eyebrow">FAQ aprobada</span><h2>Nueva pregunta frecuente</h2></div></div>
            <textarea class="form-control" name="question" rows="2" placeholder="Pregunta" required></textarea>
            <textarea class="form-control" name="variants" rows="2" placeholder="Variantes de la pregunta"></textarea>
            <textarea class="form-control" name="approved_answer" rows="4" placeholder="Respuesta aprobada" required></textarea>
            <div class="control-create-grid"><input class="form-control" name="category" placeholder="Categoria"><input class="form-control" name="tags" placeholder="Etiquetas"></div>
            <div class="control-create-grid"><input class="form-control" name="channel" value="all" placeholder="Canal"><select class="form-select" name="priority"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <input type="hidden" name="status" value="published">
            <button class="btn btn-primary">Guardar FAQ</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Contexto directo</span><h2>FAQs disponibles</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($faqs as $faq): ?>
                    <div><strong><?= e((string) $faq['question']) ?></strong><span><?= e((string) ($faq['category'] ?? 'General')) ?> / <?= e($priorityLabels[$faq['priority']] ?? $faq['priority']) ?></span><small><?= e((string) $faq['approved_answer']) ?></small></div>
                <?php endforeach; ?>
                <?php if (!$faqs): ?><p class="task-muted">Aun no hay preguntas frecuentes aprobadas.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'examples'): ?>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/example') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
            <div class="panel-title"><div><span class="eyebrow">Ejemplo aprobado</span><h2>Como deberia responder</h2></div></div>
            <textarea class="form-control" name="customer_message" rows="4" placeholder="Mensaje del cliente" required></textarea>
            <textarea class="form-control" name="ideal_response" rows="5" placeholder="Respuesta ideal" required></textarea>
            <div class="control-create-grid"><input class="form-control" name="channel" value="all" placeholder="Canal"><input class="form-control" name="intent" placeholder="Intencion"></div>
            <div class="control-create-grid"><input class="form-control" name="category" placeholder="Categoria"><input class="form-control" name="product_service" placeholder="Producto o servicio"></div>
            <input class="form-control" name="expected_result" placeholder="Resultado esperado">
            <input type="hidden" name="status" value="published">
            <button class="btn btn-primary">Guardar ejemplo</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Aprendizaje supervisado</span><h2>Ejemplos guardados</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($examples as $example): ?>
                    <div><strong><?= e((string) $example['customer_message']) ?></strong><span><?= e((string) ($example['intent'] ?? 'Sin intencion')) ?> / <?= e((string) $example['channel']) ?></span><small><?= e((string) $example['ideal_response']) ?></small></div>
                <?php endforeach; ?>
                <?php if (!$examples): ?><p class="task-muted">Aun no hay ejemplos aprobados.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'corrections'): ?>
    <section class="panel mt-4">
        <div class="panel-title">
            <div>
                <span class="eyebrow">Aprendizaje desde Omnicanal</span>
                <h2>Correcciones y aprobaciones humanas</h2>
            </div>
            <span class="soft-badge"><?= e((string) count($responseReviews)) ?> registros</span>
        </div>
        <p class="text-secondary">Cada respuesta sugerida por AsisFly que el equipo edita o aprueba queda como senal de entrenamiento para futuras conversaciones de esta empresa.</p>
        <div class="control-entry-list">
            <?php foreach ($responseReviews as $review): ?>
                <div>
                    <strong><?= e((string) ($review['customer_message'] ?: 'Mensaje de cliente')) ?></strong>
                    <span>
                        <?= e((string) ($review['channel'] ?: 'Omnicanal')) ?> /
                        <?= e((string) ($review['result'] === 'edited' ? 'Editada' : ($review['result'] === 'approved' ? 'Aprobada' : 'Rechazada'))) ?> /
                        <?= e((string) ($review['reviewer_name'] ?: 'Equipo')) ?>
                    </span>
                    <small><?= e((string) ($review['difference_summary'] ?: 'Sin resumen de diferencia.')) ?></small>
                    <?php if (!empty($review['final_response'])): ?>
                        <details class="mt-2">
                            <summary>Ver respuesta final</summary>
                            <div class="message-body-card mt-2"><?= nl2br(e((string) $review['final_response'])) ?></div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$responseReviews): ?><p class="task-muted">Aun no hay correcciones. Apareceran cuando edites o apruebes respuestas sugeridas desde el Centro de Supervision.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($section === 'documents'): ?>
    <section class="workbench-metrics mt-4">
        <article class="metric-card"><span>Fuentes</span><strong><?= e((string) ($knowledgeStats['sources'] ?? 0)) ?></strong><small>Cargadas para entrenamiento</small></article>
        <article class="metric-card"><span>Listas</span><strong><?= e((string) ($knowledgeStats['ready'] ?? 0)) ?></strong><small>Disponibles para contexto</small></article>
        <article class="metric-card"><span>Fragmentos</span><strong><?= e((string) ($knowledgeStats['chunks'] ?? 0)) ?></strong><small>Recuperables por busqueda</small></article>
        <article class="metric-card"><span>Errores</span><strong><?= e((string) ($knowledgeStats['failed'] ?? 0)) ?></strong><small>Requieren revision</small></article>
    </section>

    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/document') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="panel-title"><div><span class="eyebrow">Fuente documental</span><h2>Subir documento</h2></div></div>
            <input class="form-control" type="file" name="document" accept=".pdf,.docx,.xlsx,.csv,.txt" required>
            <div class="control-create-grid">
                <input class="form-control" name="category" placeholder="Categoria: catalogo, politica, contrato">
                <input class="form-control" name="tags" placeholder="Etiquetas">
            </div>
            <p class="text-secondary mb-0">Soporta PDF, DOCX, XLSX, CSV y TXT hasta 15 MB. El texto se fragmenta por empresa y no se envia completo a la IA.</p>
            <button class="btn btn-primary"><i class="bi bi-upload"></i>Procesar documento</button>
        </form>

        <form class="panel control-entry-form" method="get" action="<?= url('/ai-training') ?>">
            <input type="hidden" name="section" value="documents">
            <div class="panel-title"><div><span class="eyebrow">Recuperacion</span><h2>Buscar conocimiento</h2></div></div>
            <input class="form-control" name="knowledge_q" value="<?= e((string) $knowledgeQuery) ?>" placeholder="Ej: garantia, despacho, precios, condiciones">
            <button class="btn btn-outline-primary"><i class="bi bi-search"></i>Buscar fragmentos</button>
            <?php if ($knowledgeQuery !== ''): ?>
                <span class="soft-badge"><?= e((string) count($knowledgeResults)) ?> resultados</span>
            <?php endif; ?>
        </form>
    </section>

    <section class="controls-detail-grid mt-4">
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Fuentes</span><h2>Documentos de entrenamiento</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($knowledgeSources as $source): ?>
                    <div>
                        <strong><?= e((string) $source['name']) ?></strong>
                        <span><?= e((string) ($source['category'] ?? 'Sin categoria')) ?> / <?= e((string) $source['status']) ?> / <?= e((string) ($source['chunks'] ?? 0)) ?> fragmentos</span>
                        <small><?= e((string) ($source['summary'] ?? $source['error_message'] ?? '')) ?></small>
                        <?php if (!empty($source['document_id'])): ?>
                            <form method="post" action="<?= url('/ai-training/document/reprocess') ?>" class="mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_id" value="<?= e((string) $source['document_id']) ?>">
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i>Reprocesar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$knowledgeSources): ?><p class="task-muted">Aun no hay fuentes documentales en el Centro de Entrenamiento.</p><?php endif; ?>
            </div>
        </article>

        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Contexto encontrado</span><h2>Fragmentos relevantes</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($knowledgeResults as $result): ?>
                    <div>
                        <strong><?= e((string) $result['title']) ?></strong>
                        <span><?= e((string) ($result['source_name'] ?? 'Fuente')) ?> / score <?= e((string) round((float) ($result['score'] ?? 0), 3)) ?></span>
                        <small><?= e((string) $result['content']) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if ($knowledgeQuery !== '' && !$knowledgeResults): ?><p class="task-muted">No se encontraron fragmentos para esa busqueda.</p><?php endif; ?>
                <?php if ($knowledgeQuery === ''): ?><p class="task-muted">Busca una palabra o tema para verificar que AsisFly recupera el contexto correcto.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'simulator'): ?>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/simulate') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="brand_route_id" value="<?= e((string) $selectedBrandRouteId) ?>">
            <div class="panel-title"><div><span class="eyebrow">Practicar</span><h2>Mensaje ficticio de cliente</h2></div></div>
            <textarea class="form-control" name="customer_message" rows="6" placeholder="Ej: Hola, necesito una cotizacion para..." required><?= e((string) ($simulation['message'] ?? '')) ?></textarea>
            <button class="btn btn-primary"><i class="bi bi-stars"></i>Generar respuesta de prueba</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Vista previa</span><h2>Respuesta simulada</h2></div><?php if ($simulation): ?><span class="soft-badge"><?= e((string) $simulation['confidence']) ?>% confianza</span><?php endif; ?></div>
            <?php if ($simulation): ?>
                <div class="message-body-card"><?= nl2br(e((string) $simulation['answer'])) ?></div>
                <div class="control-rules-grid mt-3">
                    <?php foreach (($simulation['sources'] ?? []) as $key => $value): ?><span><strong><?= e((string) $key) ?></strong><?= e((string) $value) ?></span><?php endforeach; ?>
                </div>
                <details class="mt-3"><summary>Ver contexto usado</summary><pre class="mt-3"><?= e((string) ($simulation['context_preview'] ?? '')) ?></pre></details>
            <?php else: ?>
                <p class="task-muted">El simulador usara perfil publicado, reglas, FAQs, productos, ejemplos y memoria documental de esta empresa.</p>
            <?php endif; ?>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'routing'): ?>
    <section class="workbench-metrics mt-4">
        <article class="metric-card"><span>Marcas activas</span><strong><?= e((string) count(array_filter($brandRoutes, fn (array $route): bool => ($route['status'] ?? '') === 'active'))) ?></strong><small>Disponibles para clasificacion futura</small></article>
        <article class="metric-card"><span>Canales compartidos</span><strong><?= e((string) ($routingSettings['shared_channels'] ?? 'WhatsApp')) ?></strong><small>Donde llega todo junto</small></article>
        <article class="metric-card"><span>Confianza minima</span><strong><?= e((string) ($routingSettings['min_confidence'] ?? 70)) ?>%</strong><small>Para sugerir marca</small></article>
        <article class="metric-card"><span>Si hay duda</span><strong><?= e($ambiguousActionLabels[$routingSettings['ambiguous_action'] ?? 'ask_customer'] ?? 'Preguntar') ?></strong><small>Modo seguro inicial</small></article>
    </section>

    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/brand-route') ?>">
            <?= csrf_field() ?>
            <div class="panel-title"><div><span class="eyebrow">Nueva marca</span><h2>Ensena a AsisFly a reconocer una empresa o linea de negocio</h2></div></div>
            <input class="form-control" name="brand_name" placeholder="Ej: Tilo, ObraOK, AsisFly, Tienda de enmarcaciones" required>
            <input class="form-control" name="target_company_name" placeholder="Empresa o marca destino">
            <textarea class="form-control" name="description" rows="3" placeholder="Que hace esta marca y que tipo de consultas deberia atender"></textarea>
            <textarea class="form-control" name="keywords" rows="3" placeholder="Palabras clave separadas por coma: catalogo, ecommerce, pedido, obra, presupuesto..."></textarea>
            <textarea class="form-control" name="products_services" rows="3" placeholder="Productos o servicios que identifican esta marca"></textarea>
            <textarea class="form-control" name="typical_phrases" rows="3" placeholder="Frases tipicas de clientes: quiero cotizar una obra, necesito catalogo, cuanto sale enmarcar..."></textarea>
            <div class="control-create-grid">
                <input class="form-control" name="channels" value="WhatsApp principal" placeholder="Canales compartidos">
                <input class="form-control" type="number" name="priority" min="1" max="100" value="50" placeholder="Prioridad">
            </div>
            <select class="form-select" name="status">
                <?php foreach ($routeStatusLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary"><i class="bi bi-plus-lg"></i>Agregar marca</button>
        </form>

        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/routing-settings') ?>">
            <?= csrf_field() ?>
            <div class="panel-title"><div><span class="eyebrow">Reglas del enrutador</span><h2>Que debe hacer AsisFly cuando recibe todo junto</h2></div></div>
            <input class="form-control" name="shared_channels" value="<?= e((string) ($routingSettings['shared_channels'] ?? 'WhatsApp principal')) ?>" placeholder="Ej: WhatsApp principal, Gmail ventas">
            <select class="form-select" name="ambiguous_action">
                <?php foreach ($ambiguousActionLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($routingSettings['ambiguous_action'] ?? 'ask_customer') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" name="min_confidence" type="number" min="0" max="100" value="<?= e((string) ($routingSettings['min_confidence'] ?? 70)) ?>" placeholder="Confianza minima">
            <select class="form-select" name="default_brand_route_id">
                <option value="">Sin marca por defecto</option>
                <?php foreach ($brandRoutes as $route): ?>
                    <option value="<?= e((string) $route['id']) ?>" <?= (string) ($routingSettings['default_brand_route_id'] ?? '') === (string) $route['id'] ? 'selected' : '' ?>><?= e((string) $route['brand_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status">
                <?php foreach ($routeStatusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($routingSettings['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <p class="task-muted mb-0">Fase 1: esto solo guarda la configuracion. En la fase pasiva Omnicanal mostrara marca sugerida, confianza y motivo sin cambiar respuestas automaticamente.</p>
            <button class="btn btn-primary"><i class="bi bi-save"></i>Guardar enrutamiento</button>
        </form>
    </section>

    <section class="panel mt-4">
        <div class="panel-title"><div><span class="eyebrow">Marcas configuradas</span><h2>Mapa actual para futuros mensajes omnicanal</h2></div><span class="soft-badge"><?= e((string) count($brandRoutes)) ?> marcas</span></div>
        <div class="control-entry-list">
            <?php foreach ($brandRoutes as $route): ?>
                <div>
                    <strong><?= e((string) $route['brand_name']) ?> <span class="soft-badge"><?= e($routeStatusLabels[$route['status']] ?? $route['status']) ?></span></strong>
                    <span><?= e((string) ($route['target_company_name'] ?: 'Sin empresa destino definida')) ?> · <?= e((string) ($route['channels'] ?: 'Todos los canales')) ?> · prioridad <?= e((string) $route['priority']) ?></span>
                    <small><?= e((string) ($route['description'] ?: 'Sin descripcion')) ?></small>
                    <?php if (!empty($route['keywords'])): ?><small><strong>Claves:</strong> <?= e((string) $route['keywords']) ?></small><?php endif; ?>
                    <?php if (!empty($route['typical_phrases'])): ?><small><strong>Frases:</strong> <?= e((string) $route['typical_phrases']) ?></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$brandRoutes): ?>
                <p class="task-muted">Aun no hay marcas. Agrega Tilo, ObraOK, AsisFly u otra linea de negocio para preparar el enrutamiento inteligente.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($section === 'settings'): ?>
    <section class="workbench-metrics mt-4">
        <article class="metric-card"><span>Autonomia actual</span><strong><?= e((string) ($autonomy['learning_progress'] ?? 0)) ?>%</strong><small><?= e((string) ($autonomy['label'] ?? 'Aprendizaje supervisado')) ?></small></article>
        <article class="metric-card"><span>Modo seguro</span><strong>Manual</strong><small>Solo sugiere y espera aprobacion.</small></article>
        <article class="metric-card"><span>Modo asistido</span><strong>Copiloto</strong><small>Redacta, pero no envia solo.</small></article>
        <article class="metric-card"><span>Modo automatico</span><strong>Controlado</strong><small>Solo envia si cumple confianza, riesgo y permisos.</small></article>
    </section>
    <section class="controls-detail-grid mt-4">
        <form class="panel control-entry-form" method="post" action="<?= url('/ai-training/channel') ?>">
            <?= csrf_field() ?>
            <div class="panel-title"><div><span class="eyebrow">Modo por canal</span><h2>Configurar autonomia</h2></div></div>
            <p class="text-secondary">La autonomia real combina progreso de aprendizaje, modo del canal, confianza minima, riesgo del mensaje y salida activa de la cuenta conectada.</p>
            <select class="form-select" name="channel"><option value="all">Todos los canales</option><option value="whatsapp">WhatsApp</option><option value="email">Correo</option><option value="instagram">Instagram</option><option value="facebook">Facebook / Messenger</option></select>
            <select class="form-select" name="mode">
                <option value="manual">Manual - solo sugerir</option>
                <option value="assisted">Asistido - preparar y pedir aprobacion</option>
                <option value="automatic">Automatico - responder si es seguro</option>
            </select>
            <input class="form-control" name="min_confidence" type="number" min="0" max="100" value="75" placeholder="Confianza minima">
            <input class="form-control" name="auto_reply_schedule" placeholder="Horario de respuesta automatica">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="require_approval_for_sensitive" value="1" checked> <span class="form-check-label">Aprobar casos sensibles</span></label>
            <button class="btn btn-primary">Guardar canal</button>
        </form>
        <article class="panel">
            <div class="panel-title"><div><span class="eyebrow">Canales</span><h2>Configuracion actual</h2></div></div>
            <div class="control-entry-list">
                <?php foreach ($channels as $channel): ?>
                    <div>
                        <strong><?= e((string) $channel['channel']) ?></strong>
                        <span><?= e($modeLabels[$channel['mode']] ?? $channel['mode']) ?> / confianza <?= e((string) $channel['min_confidence']) ?>% / sensibles <?= !empty($channel['require_approval_for_sensitive']) ? 'con aprobacion' : 'permitidos' ?></span>
                        <small><?= e((string) ($channel['auto_reply_schedule'] ?? 'Sin horario especial')) ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$channels): ?><p class="task-muted">Sin configuracion por canal. El modo seguro inicial es manual.</p><?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'versions'): ?>
    <section class="panel mt-4">
        <div class="panel-title"><div><span class="eyebrow">Versionado</span><h2>Prompt base del sistema</h2></div><span class="soft-badge"><?= e((string) ($prompt['version'] ?? '1.0.0')) ?></span></div>
        <p>Esta plantilla no contiene datos especificos de ninguna empresa. El contexto empresarial se inserta al generar respuestas.</p>
        <pre><?= e((string) ($prompt['body'] ?? 'Ejecuta el seed 015_ai_training_seed.sql para crear la plantilla base.')) ?></pre>
    </section>
<?php endif; ?>
