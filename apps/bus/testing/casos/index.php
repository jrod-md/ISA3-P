<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$current = require_login();
$query = db()->prepare('SELECT c.*, u.nombre AS tester FROM casos_prueba c JOIN usuarios u ON u.id = c.usuario_id' . ($current['rol'] === 'admin' ? '' : ' WHERE c.usuario_id = ?') . ' ORDER BY c.id DESC');
$query->execute($current['rol'] === 'admin' ? [] : [$current['id']]);
$cases = $query->fetchAll();
$title = 'Casos de prueba'; $active = 'casos'; require TESTING_PATH . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">FORMULARIO 01</span><h1>Casos de prueba</h1><p class="muted"><?= $current['rol'] === 'admin' ? 'Todos los casos registrados por el equipo.' : 'Consulta y actualiza tus pruebas registradas.' ?></p></div><a class="button" href="<?= e(url('/casos/nuevo')) ?>">+ Nuevo caso</a></div>
<section class="panel">
<div class="section-heading"><h2>Registro de casos <span class="count-label"><?= count($cases) ?></span></h2><span class="muted"><?= $current['rol'] === 'admin' ? 'Vista de administrador' : 'Mis casos' ?></span></div>
<?php if (!$cases): ?><div class="empty-state"><span class="empty-symbol" aria-hidden="true">▤</span><h3>Aún no hay casos registrados</h3><p>Crea un caso para guardar el procedimiento y sus resultados.</p><a class="button-secondary" href="<?= e(url('/casos/nuevo')) ?>">Registrar primer caso</a></div><?php else: ?>
<div class="table-scroll"><table><caption class="sr-only">Registro de casos de prueba</caption><thead><tr><th>ID</th><th>Código</th><th>Módulo</th><th>Técnica / Subtécnica</th><th>Estado</th><th>Tester</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($cases as $case): ?><tr><td><?= (int) $case['id'] ?></td><td><a class="case-code" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>"><?= e(code_case((int) $case['id'])) ?></a></td><td class="wrap-cell"><?= e($case['modulo']) ?></td><td class="wrap-cell"><?= e($case['tecnica']) ?><small class="cell-detail"><?= e($case['subtecnica']) ?></small></td><td><span class="badge <?= $case['estado'] === 'Éxito' ? 'badge-success' : 'badge-failure' ?>"><?= e($case['estado']) ?></span></td><td><?= e($case['tester']) ?></td><td><?= e(date_display($case['creado_en'])) ?></td><td><div class="table-actions"><a href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>">Ver</a><a href="<?= e(url('/casos/editar?id=' . $case['id'])) ?>">Editar</a><?php if ($current['rol'] === 'admin'): ?><a class="danger-link" href="<?= e(url('/casos/eliminar?id=' . $case['id'])) ?>">Eliminar</a><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
