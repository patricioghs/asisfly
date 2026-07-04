<div class="auth-panel">
    <div>
        <p class="eyebrow">AsisFly SaaS</p>
        <h1>Tu empleado digital para vender, responder y operar mejor.</h1>
        <p>Base multiempresa para Latinoamerica, preparada para IA, CRM, documentos, correos, calendario y automatizaciones con aprobacion humana.</p>
    </div>
    <form class="auth-card" method="post" action="<?= url('/login') ?>">
        <?= csrf_field() ?>
        <h2>Ingresar</h2>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger py-2"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success py-2"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <label class="form-label">Correo</label>
        <input class="form-control" type="email" name="email" value="admin@asisfly.ai" required>
        <label class="form-label mt-3">Contraseña</label>
        <input class="form-control" type="password" name="password" value="demo1234" required>
        <button class="btn btn-primary w-100 mt-4">Entrar al demo</button>
        <a class="btn btn-link w-100" href="<?= url('/forgot-password') ?>">Recuperar contrasena</a>
        <a class="btn btn-link w-100" href="<?= url('/register') ?>">Crear empresa</a>
    </form>
</div>
