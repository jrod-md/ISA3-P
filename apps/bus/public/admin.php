<?php
require_once dirname(__DIR__) . '/testing/includes/bootstrap.php';
if (user()) { redirect('/dashboard'); }
$title = 'Iniciar sesión';
require TESTING_PATH . '/includes/auth_header.php';
?>
<span class="eyebrow">TU ESPACIO DE PRUEBAS</span>
<h1>Bienvenido de nuevo</h1>
<p class="muted">Registra tus pruebas, documenta resultados y revisa cada caso.</p>
<div id="login-message" class="alert alert-error" role="alert" hidden></div>
<form id="login-form" class="auth-form" method="post">
    <label for="username">Usuario o correo</label><input id="username" name="username" autocomplete="username" maxlength="190" required autofocus>
    <label for="password">Contraseña</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">Mostrar</button></div>
    <button id="login-submit" class="button full-width" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
</form>
<noscript><p class="alert alert-error">Activa JavaScript para iniciar sesión.</p></noscript>
<p class="auth-switch">¿Primera vez aquí? <a href="<?= e(url('/registro')) ?>">Crear cuenta de tester</a></p>
<script>window.marketplaceUrl = path => <?= json_encode(url(''), JSON_HEX_TAG) ?> + path;</script>
<script src="<?= e(url('/assets/login.js')) ?>" defer></script>
<?php require TESTING_PATH . '/includes/auth_footer.php'; ?>
