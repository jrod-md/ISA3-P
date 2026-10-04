<?php
require __DIR__ . '/includes/bootstrap.php';
if (user()) { redirect('/dashboard'); }
$errors = []; $nombre = $correo = $username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nombre = post_text('nombre'); $correo = mb_strtolower(post_text('correo')); $username = post_text('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) { $errors[] = 'El nombre debe tener entre 2 y 100 caracteres.'; }
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D', $username)) { $errors[] = 'El usuario debe tener entre 3 y 50 letras, números, puntos, guiones o guiones bajos.'; }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 190) { $errors[] = 'Introduce un correo válido de hasta 190 caracteres.'; }
    if (strlen($password) < 8 || strlen($password) > 72) { $errors[] = 'La contraseña debe tener entre 8 y 72 bytes.'; }
    if ($password !== ($_POST['confirmacion'] ?? null)) { $errors[] = 'Las contraseñas no coinciden.'; }
    if (!$errors) {
        try {
            db()->prepare("INSERT INTO usuarios (username, nombre, correo, password, rol) VALUES (?, ?, ?, ?, 'tester')")
                ->execute([$username, $nombre, $correo, password_hash($password, PASSWORD_DEFAULT)]);
            flash('Cuenta creada. Inicia sesión con tu usuario o correo.'); redirect('/admin');
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') { throw $error; }
            $errors[] = 'Ya existe una cuenta con ese usuario o correo.';
        }
    }
}
$title = 'Crear cuenta de tester'; require __DIR__ . '/includes/auth_header.php';
?>
<span class="eyebrow">TU ESPACIO DE PRUEBAS</span><h1>Crear cuenta de tester</h1>
<p>Tu cuenta permitirá documentar las pruebas de nuestro propio Marketplace Search Bus.</p>
<?php errors_block($errors); ?>
<form method="post" class="registration-form"><?php csrf_field(); ?>
<label for="nombre">Nombre<input id="nombre" name="nombre" value="<?= e($nombre) ?>" minlength="2" maxlength="100" autocomplete="name" required></label>
<label for="username">Usuario<input id="username" name="username" value="<?= e($username) ?>" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.\-]+" autocomplete="username" required></label>
<label for="correo">Correo<input id="correo" name="correo" value="<?= e($correo) ?>" type="email" maxlength="190" autocomplete="email" required></label>
<label for="password">Contraseña<input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<label for="confirmacion">Confirmar contraseña<input id="confirmacion" name="confirmacion" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<button class="button primary" type="submit">Crear cuenta</button>
</form><p class="auth-switch">¿Ya tienes una cuenta? <a href="<?= e(url('/admin')) ?>">Iniciar sesión</a></p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
