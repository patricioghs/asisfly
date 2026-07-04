<div class="auth-panel">
    <div>
        <p class="eyebrow">Acceso seguro</p>
        <h1>Recupera el acceso sin perder el control de tu empresa.</h1>
        <p>Generaremos un enlace temporal de 45 minutos. En produccion se envia por correo; en local se muestra para pruebas.</p>
    </div>
    <form class="auth-card" method="post" action="<?= url('/forgot-password') ?>">
        <?= csrf_field() ?>
        <h2>Recuperar contrasena</h2>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success py-2"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['reset_link'])): ?>
            <div class="alert alert-info py-2"><a href="<?= e($_SESSION['reset_link']) ?>"><?= e($_SESSION['reset_link']); unset($_SESSION['reset_link']); ?></a></div>
        <?php endif; ?>
        <label class="form-label">Correo</label>
        <input class="form-control" type="email" name="email" value="admin@asisfly.ai" required>
        <button class="btn btn-primary w-100 mt-4">Generar enlace</button>
        <a class="btn btn-link w-100" href="<?= url('/login') ?>">Volver al login</a>
    </form>
</div>
