<section class="panel assistant-setup">
    <div>
        <span class="eyebrow">Personalidad por empresa</span>
        <h2>Configura como trabaja AsisFly para este negocio</h2>
        <p>Define tono, idioma, moneda, reglas comerciales y criterios de escalamiento humano. Esta configuracion nunca se mezcla con otras empresas.</p>
    </div>
    <div class="setup-card">
        <strong>Postura recomendada para demo</strong>
        <small>Formal cercano, respuestas breves, aprobacion humana para envios sensibles y seguimiento comercial proactivo.</small>
    </div>
</section>

<form class="panel form-grid mt-4" method="post" action="<?= url('/assistant') ?>">
    <?= csrf_field() ?>
    <?php foreach ([
        'name' => 'Nombre del asistente',
        'tone' => 'Tono',
        'language' => 'Idioma principal',
        'country' => 'Pais',
        'currency' => 'Moneda',
        'work_hours' => 'Horario laboral',
        'signature' => 'Firma',
        'rules' => 'Reglas de atencion',
        'forbidden_words' => 'Palabras prohibidas',
        'required_phrases' => 'Frases obligatorias',
        'human_escalation' => 'Cuando escalar a humano',
    ] as $field => $label): ?>
        <label>
            <span><?= e($label) ?></span>
            <textarea class="form-control" name="<?= e($field) ?>" rows="<?= in_array($field, ['rules', 'human_escalation'], true) ? 3 : 1 ?>"><?= e($assistant[$field] ?? '') ?></textarea>
        </label>
    <?php endforeach; ?>
    <button class="btn btn-primary">Guardar configuracion</button>
</form>
