<div class="auth-panel">
    <div>
        <p class="eyebrow">Nueva empresa</p>
        <h1>Configura una organizacion aislada desde el primer minuto.</h1>
        <p>Cada empresa mantiene usuarios, memoria, integraciones, reglas, historial y consumo de IA por separado.</p>
    </div>
    <form class="auth-card" method="post" action="<?= url('/register') ?>">
        <?= csrf_field() ?>
        <h2>Registro</h2>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger py-2"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <input class="form-control mb-3" name="name" placeholder="Tu nombre" required>
        <input class="form-control mb-3" type="email" name="email" placeholder="Correo" required>
        <input class="form-control mb-3" type="password" name="password" placeholder="Contrasena" required minlength="8">
        <input class="form-control mb-3" name="company" placeholder="Empresa" required>
        <div class="row g-2">
            <div class="col"><input class="form-control" name="country" value="Chile"></div>
            <div class="col"><input class="form-control" name="currency" value="CLP"></div>
        </div>
        <input class="form-control mt-3" name="timezone" value="America/Santiago">
        <input class="form-control mt-3" name="locale" value="es_CL">
        <button class="btn btn-primary w-100 mt-4">Crear workspace</button>
    </form>
</div>
