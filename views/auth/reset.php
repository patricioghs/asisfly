<div class="auth-panel">
    <div>
        <p class="eyebrow">Nueva contrasena</p>
        <h1>Define una clave segura para volver a entrar.</h1>
        <p>La clave debe tener al menos 8 caracteres. El token queda inutilizado despues de usarlo.</p>
    </div>
    <form class="auth-card" method="post" action="<?= url('/reset-password') ?>">
        <?= csrf_field() ?>
        <h2>Restablecer contrasena</h2>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger py-2"><?= e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <input type="hidden" name="token" value="<?= e((string) $token) ?>">
        <label class="form-label">Nueva contrasena</label>
        <input class="form-control" type="password" name="password" required minlength="8">
        <button class="btn btn-primary w-100 mt-4">Actualizar contrasena</button>
        <a class="btn btn-link w-100" href="<?= url('/login') ?>">Volver al login</a>
    </form>
</div>
