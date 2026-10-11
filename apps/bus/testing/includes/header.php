<?php
$current = ($publicPage ?? false) ? user() : require_login();
$active = $active ?? '';
// Trazos SVG de la navegación (24×24, sin dependencias externas).
$navIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'casos' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
    'incidentes' => '<path d="M8 6a4 4 0 0 1 8 0"/><rect x="7" y="6" width="10" height="14" rx="5"/><path d="M12 11v9M3 13h4M17 13h4M4 7l3.5 2.5M20 7l-3.5 2.5M4 20l3.5-3M20 20l-3.5-3"/>',
    'plan' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18M8 15h5"/>',
    'formularios' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
    'buscador' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'busquedas' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>',
];
$navLink = static function (string $key, string $path, string $label) use ($active, $navIcons): string {
    return '<a ' . ($active === $key ? 'class="selected" aria-current="page" ' : '') . 'href="' . e(url($path)) . '"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $navIcons[$key] . '</svg> ' . e($label) . '</a>';
};
$navSections = ['dashboard' => 'Inicio', 'casos' => 'Casos de prueba', 'incidentes' => 'Incidentes', 'plan' => 'Plan de pruebas', 'formularios' => 'Formularios', 'buscador' => 'Sistema bajo prueba', 'busquedas' => 'Administración', 'usuarios' => 'Administración'];
// Tono visual de severidad, prioridad y estado de un incidente.
if (!function_exists('incident_tone')) {
    function incident_tone(string $field, string $value): string
    {
        $tones = [
            'severidad' => ['Crítica' => 'badge-critical', 'Alta' => 'badge-high', 'Media' => 'badge-mid', 'Baja' => 'badge-pending'],
            'prioridad' => ['Alta' => 'badge-high', 'Media' => 'badge-mid', 'Baja' => 'badge-pending'],
            'estado' => ['Abierto' => 'badge-open', 'En progreso' => 'badge-progress', 'Cerrado' => 'badge-success'],
        ];
        return $tones[$field][$value] ?? 'badge-pending';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · ISA III</title>
    <link rel="stylesheet" href="<?= e(url('/assets/testing.css')) ?>?v=<?= (int) @filemtime(dirname(TESTING_PATH) . '/public/assets/testing.css') ?>">
    <script>window.marketplaceUrl = path => <?= json_encode(url(''), JSON_HEX_TAG) ?> + path;</script>
    <script defer src="<?= e(url('/assets/testing.js')) ?>?v=<?= (int) @filemtime(dirname(TESTING_PATH) . '/public/assets/testing.js') ?>"></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<aside class="sidebar" id="navegacion">
    <a class="brand" href="<?= e(url($current ? '/dashboard' : '/admin')) ?>"><span class="brand-mark">i3</span><span>ISA III<small>Gestión de pruebas</small></span></a>
    <span class="nav-label">PRUEBAS</span>
    <nav aria-label="Pruebas">
        <?= $navLink('dashboard', '/dashboard', 'Dashboard') ?>
        <?= $navLink('casos', '/casos', 'Casos de prueba') ?>
        <?= $navLink('incidentes', '/formularios/incidentes', 'Incidentes') ?>
    </nav>
    <span class="nav-label nav-label-spaced">PROYECTO</span>
    <nav aria-label="Proyecto">
        <?= $navLink('formularios', '/formularios', 'Formularios 1–10') ?>
    </nav>
    <span class="nav-label nav-label-spaced">SISTEMA BAJO PRUEBA</span>
    <nav aria-label="Sistema bajo prueba">
        <?= $navLink('buscador', '/', 'Buscador del proyecto') ?>
    </nav>
    <?php if ($current && $current['rol'] === 'admin'): ?>
    <span class="nav-label nav-label-spaced">ADMINISTRACIÓN</span>
    <nav aria-label="Administración">
        <?= $navLink('busquedas', '/busquedas', 'Búsquedas temporales') ?>
        <?= $navLink('usuarios', '/usuarios', 'Usuarios') ?>
    </nav>
    <?php endif; ?>
    <?php if ($current): ?>
    <div class="profile"><span class="avatar"><?= e(mb_strtoupper(mb_substr($current['nombre'], 0, 1))) ?></span><div><strong><?= e($current['nombre']) ?></strong><small><?= $current['rol'] === 'admin' ? 'Administrador' : 'Tester' ?></small></div></div>
    <button class="logout" id="session-logout" type="button">Cerrar sesión <span aria-hidden="true">↗</span></button>
    <?php else: ?>
    <div class="guest-access"><p class="muted">Inicia sesión para documentar las pruebas.</p><a class="button full-width" href="<?= e(url('/admin')) ?>">Iniciar sesión</a></div>
    <?php endif; ?>
</aside>
<div class="workspace">
    <header class="topbar"><button class="menu-toggle button-secondary" type="button" aria-controls="navegacion" aria-expanded="false">Menú</button><span class="crumbs"><span><?= e($navSections[$active] ?? 'Gestión de pruebas') ?></span><?php if (($navSections[$active] ?? '') !== $title && !in_array($title, ['Dashboard', 'Casos de prueba', 'Formularios'], true)): ?><span aria-hidden="true">/</span><strong><?= e($title) ?></strong><?php endif; ?></span><span class="topbar-end"><span class="health" id="bus-health" data-state="unknown" title="Estado del Bus y su base de datos"><i aria-hidden="true"></i><span>Bus</span><span class="sr-only" id="bus-health-text">comprobando</span></span><span class="topbar-tag">Ingeniería de Software Aplicada III</span></span></header>
    <main id="contenido" class="main-content">
    <div id="session-message" class="alert alert-error" role="alert" hidden></div>
    <?php if (isset($_SESSION['flash'])): ?><div class="alert alert-success" role="status"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
