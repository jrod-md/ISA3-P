<?php
$current = ($publicPage ?? false) ? user() : require_login();
$active = $active ?? '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · ISA III</title>
    <link rel="stylesheet" href="<?= e(url('/assets/testing.css')) ?>">
    <script>window.marketplaceUrl = path => <?= json_encode(url(''), JSON_HEX_TAG) ?> + path;</script>
    <script defer src="<?= e(url('/assets/testing.js')) ?>"></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<aside class="sidebar" id="navegacion">
    <a class="brand" href="<?= e(url($current ? '/dashboard' : '/admin')) ?>"><span class="brand-mark">i3</span><span>ISA III<small>Gestión de pruebas</small></span></a>
    <span class="nav-label">ESPACIO DE TRABAJO</span>
    <nav aria-label="Navegación principal">
        <a <?= $active === 'dashboard' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/dashboard')) ?>"><span aria-hidden="true">▦</span> Dashboard</a>
        <a <?= $active === 'casos' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/casos')) ?>"><span aria-hidden="true">▤</span> Casos de prueba</a>
        <a <?= $active === 'formularios' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/formularios')) ?>"><span aria-hidden="true">▥</span> Formularios</a>
        <a <?= $active === 'buscador' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/')) ?>"><span aria-hidden="true">⌕</span> Buscador del proyecto</a>
    </nav>
    <?php if ($current && $current['rol'] === 'admin'): ?>
    <span class="nav-label admin-nav-label">ADMINISTRACIÓN</span>
    <nav aria-label="Administración">
        <a <?= $active === 'busquedas' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/busquedas')) ?>"><span aria-hidden="true">◷</span> Búsquedas temporales</a>
        <a <?= $active === 'usuarios' ? 'class="selected" aria-current="page"' : '' ?> href="<?= e(url('/usuarios')) ?>"><span aria-hidden="true">♧</span> Usuarios</a>
    </nav>
    <?php endif; ?>
    <div class="sidebar-note"><span class="tiny-label">GESTIÓN DE PRUEBAS</span><p>Casos y documentación de pruebas</p><span class="badge badge-success">6 de 10 disponibles</span></div>
    <?php if ($current): ?>
    <div class="profile"><span class="avatar"><?= e(mb_strtoupper(mb_substr($current['nombre'], 0, 1))) ?></span><div><strong><?= e($current['nombre']) ?></strong><small><?= $current['rol'] === 'admin' ? 'Administrador' : 'Tester' ?></small></div></div>
    <button class="logout" id="session-logout" type="button">Cerrar sesión <span aria-hidden="true">↗</span></button>
    <?php else: ?>
    <div class="guest-access"><p class="muted">Inicia sesión para documentar las pruebas.</p><a class="button full-width" href="<?= e(url('/admin')) ?>">Iniciar sesión</a></div>
    <?php endif; ?>
</aside>
<div class="workspace">
    <header class="topbar"><button class="menu-toggle button-secondary" type="button" aria-controls="navegacion" aria-expanded="false">Menú</button><span>Ingeniería de Software Aplicada III</span><span class="topbar-tag">Gestión de pruebas</span></header>
    <main id="contenido" class="main-content">
    <div id="session-message" class="alert alert-error" role="alert" hidden></div>
    <?php if (isset($_SESSION['flash'])): ?><div class="alert alert-success" role="status"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
