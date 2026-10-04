<?php
require __DIR__ . '/includes/bootstrap.php';
$current = require_login();
$where = $current['rol'] === 'admin' ? '' : ' WHERE usuario_id = ?';
$query = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(estado = 'Éxito'), 0) AS exitosos, COALESCE(SUM(estado = 'Fallo'), 0) AS fallidos FROM casos_prueba" . $where);
$query->execute($where ? [$current['id']] : []);
$stats = $query->fetch();
$query = db()->prepare('SELECT c.*, u.nombre AS tester FROM casos_prueba c JOIN usuarios u ON u.id = c.usuario_id' . ($where ? ' WHERE c.usuario_id = ?' : '') . ' ORDER BY c.id DESC LIMIT 5');
$query->execute($where ? [$current['id']] : []);
$recent = $query->fetchAll();
$title = 'Dashboard'; $active = 'dashboard'; require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">VISTA GENERAL</span><h1>Hola, <?= e(explode(' ', $current['nombre'])[0]) ?><span class="greeting-dot">.</span></h1><p class="muted"><?= $current['rol'] === 'admin' ? 'Revisa el trabajo del equipo y el resultado de sus pruebas.' : 'Aquí tienes el estado de tus casos de prueba.' ?></p></div><a class="button" href="<?= e(url('/casos/nuevo')) ?>"><span aria-hidden="true">+</span> Nuevo caso</a></div>
<div class="stats-grid">
    <article class="stat"><span>Total de casos</span><strong><?= (int) $stats['total'] ?></strong><small><?= $current['rol'] === 'admin' ? 'Registrados por el equipo' : 'Registrados por ti' ?></small></article>
    <article class="stat"><span><i class="status-dot success"></i> Casos exitosos</span><strong><?= (int) $stats['exitosos'] ?></strong><small>Resultado esperado alcanzado</small></article>
    <article class="stat"><span><i class="status-dot failure"></i> Casos fallidos</span><strong><?= (int) $stats['fallidos'] ?></strong><small>Requieren revisión</small></article>
</div>
<section class="panel">
    <div class="section-heading"><div><h2>Casos recientes</h2><p class="muted">Las últimas cinco pruebas registradas.</p></div><a class="text-link" href="<?= e(url('/casos')) ?>">Ver todos <span aria-hidden="true">→</span></a></div>
    <?php if (!$recent): ?><div class="empty-state"><span class="empty-symbol" aria-hidden="true">▤</span><h3>Tu primera prueba empieza aquí</h3><p>Documenta el objetivo, la técnica y los resultados de un caso.</p><a class="button-secondary" href="<?= e(url('/casos/nuevo')) ?>">Registrar primer caso</a></div><?php else: ?>
    <div class="table-scroll"><table><caption class="sr-only">Casos de prueba recientes</caption><thead><tr><th>Código</th><th>Módulo / Funcionalidad</th><th>Técnica</th><th>Estado</th><th>Fecha</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody>
    <?php foreach ($recent as $case): ?><tr><td><a class="case-code" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>"><?= e(code_case((int) $case['id'])) ?></a></td><td class="wrap-cell"><?= e($case['modulo']) ?></td><td><?= e($case['tecnica']) ?></td><td><span class="badge <?= $case['estado'] === 'Éxito' ? 'badge-success' : 'badge-failure' ?>"><?= e($case['estado']) ?></span></td><td><?= e(date_display($case['creado_en'])) ?></td><td><a class="text-link" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>">Ver <span class="sr-only"><?= e(code_case((int) $case['id'])) ?></span> ↗</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="forms-section">
    <div class="section-heading"><div><h2>Resumen de Formularios</h2><p class="muted">El recorrido del proyecto, un formulario a la vez.</p></div><a class="text-link" href="<?= e(url('/formularios')) ?>">10 de 10 disponibles →</a></div>
    <div class="form-summary">
    <?php require __DIR__ . '/includes/form_summary.php'; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
