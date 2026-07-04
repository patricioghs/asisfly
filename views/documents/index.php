<div class="row g-4">
    <div class="col-lg-4">
        <form class="panel" method="post" action="<?= url('/documents') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <h2>Subir entrenamiento</h2>
            <p class="form-hint">Carga memoria privada de esta empresa: contratos, catalogos, politicas, preguntas frecuentes o planillas.</p>
            <select class="form-select mb-3" name="type">
                <option>Entrenamiento</option><option>Contrato</option><option>Catalogo</option><option>Politica</option><option>Planilla</option>
            </select>
            <input class="form-control" type="file" name="document" required>
            <small class="text-secondary d-block mt-2">Soporta PDF, Word .docx, Excel .xlsx, CSV y TXT. La extraccion se guarda solo para esta empresa.</small>
            <button class="btn btn-primary w-100 mt-3">Procesar</button>
        </form>
        <form class="panel mt-3" method="get" action="<?= url('/documents') ?>">
            <div class="panel-title">
                <div>
                    <span class="eyebrow">Busqueda inicial</span>
                    <h2>Buscar en memoria</h2>
                </div>
            </div>
            <input class="form-control mb-2" name="q" value="<?= e($memoryQuery ?? '') ?>" placeholder="Ej: politica de garantia, precio despacho, contrato marco">
            <button class="btn btn-outline-primary w-100">Buscar contexto</button>
            <div class="memory-stats mt-3">
                <span>Fuentes <strong><?= e((string) ($memoryStats['sources'] ?? 0)) ?></strong></span>
                <span>Fragmentos <strong><?= e((string) ($memoryStats['entries'] ?? 0)) ?></strong></span>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-title">
                <div>
                    <span class="eyebrow">Conocimiento privado</span>
                    <h2>Memoria documental</h2>
                </div>
                <span class="soft-badge">Separado por empresa</span>
            </div>
            <?php foreach ($documents as $doc): ?>
                <div class="list-row">
                    <strong><?= e($doc['name']) ?></strong>
                    <span><?= e($doc['type']) ?> / <?= e($doc['status']) ?> / <?= e((string) ($doc['memory_chunks'] ?? 0)) ?> fragmentos</span>
                    <small><?= e($doc['summary']) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$documents): ?>
                <div class="empty-state compact">
                    <strong>No hay documentos entrenados todavia</strong>
                    <p>Sube un catalogo, una politica comercial o una planilla de ventas para que AsisFly empiece a responder con contexto real del negocio.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($memoryQuery)): ?>
            <div class="panel mt-4">
                <div class="panel-title">
                    <div>
                        <span class="eyebrow">Resultados</span>
                        <h2>Contexto encontrado</h2>
                    </div>
                    <span class="soft-badge"><?= e((string) count($memoryResults ?? [])) ?> coincidencias</span>
                </div>
                <?php foreach (($memoryResults ?? []) as $result): ?>
                    <article class="memory-result">
                        <strong><?= e($result['title']) ?></strong>
                        <p><?= e($result['content']) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($memoryResults)): ?>
                    <div class="empty-state compact">
                        <strong>No se encontro contexto</strong>
                        <p>Prueba con palabras del documento o sube una fuente mas especifica para entrenar la memoria empresarial.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
