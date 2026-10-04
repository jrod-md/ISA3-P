<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · ISA III</title>
    <link rel="stylesheet" href="<?= e(url('/assets/testing.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <a class="brand" href="<?= e(url('/admin')) ?>"><span class="brand-mark">i3</span><span>ISA III<small>Gestión de pruebas</small></span></a>
    <section class="auth-panel" aria-label="<?= e($title) ?>">
    <?php if (isset($_SESSION['flash'])): ?><div class="alert alert-success" role="status"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
