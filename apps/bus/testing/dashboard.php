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
$query = db()->prepare('SELECT id, modulo, estado FROM casos_prueba' . $where . ' ORDER BY id DESC LIMIT 80');
$query->execute($where ? [$current['id']] : []);
$run = array_reverse($query->fetchAll());
$openIncidents = count((new Marketplace\Bus\Repository\IncidentRepository(db()))->listing($current, ['estado' => 'Abierto']));
$passRate = (int) $stats['total'] > 0 ? (int) round((int) $stats['exitosos'] * 100 / (int) $stats['total']) : 0;
$title = 'Dashboard'; $active = 'dashboard'; require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">VISTA GENERAL</span><h1 data-testid="dashboard-title">Hola, <?= e(explode(' ', $current['nombre'])[0]) ?><span class="greeting-dot">.</span></h1><p class="muted"><?= $current['rol'] === 'admin' ? 'Revisa el trabajo del equipo y el resultado de sus pruebas.' : 'Aquí tienes el estado de tus casos de prueba.' ?></p></div><a class="button" href="<?= e(url('/casos/nuevo')) ?>"><span aria-hidden="true">+</span> Nuevo caso</a></div>
<section class="panel run-panel" aria-label="Resultado de los casos">
    <div class="run-figures">
        <div class="figure"><span>Total de casos</span><strong><?= (int) $stats['total'] ?></strong><small><?= $current['rol'] === 'admin' ? 'Registrados por el equipo' : 'Registrados por ti' ?></small></div>
        <div class="figure"><span><i class="status-dot success"></i> Casos exitosos</span><strong><?= (int) $stats['exitosos'] ?></strong><small><?= $passRate ?> % del total</small></div>
        <div class="figure is-fail"><span><i class="status-dot failure"></i> Casos fallidos</span><strong class="<?= (int) $stats['fallidos'] > 0 ? 'has-value' : '' ?>"><?= (int) $stats['fallidos'] ?></strong><small>Requieren revisión</small></div>
        <div class="figure"><span>Incidentes abiertos</span><strong><?= $openIncidents ?></strong><small><a class="text-link" href="<?= e(url('/formularios/incidentes?estado=Abierto')) ?>">Ver incidentes</a></small></div>
    </div>
    <?php if ($run): ?><div class="run-strip"><?php foreach ($run as $item): ?><a class="<?= $item['estado'] === 'Éxito' ? 'pass' : 'fail' ?>" href="<?= e(url('/casos/ver?id=' . $item['id'])) ?>" title="<?= e(code_case((int) $item['id']) . ' · ' . $item['modulo'] . ' · ' . $item['estado']) ?>"><span class="sr-only"><?= e(code_case((int) $item['id']) . ': ' . $item['estado']) ?></span></a><?php endforeach; ?></div>
    <p class="run-caption"><span>Cada segmento es un caso, del más antiguo al más reciente<?= (int) $stats['total'] > count($run) ? ' (últimos ' . count($run) . ')' : '' ?>.</span><span><span class="legend"><i></i> Éxito</span><span class="legend fail"><i></i> Fallo</span></span></p><?php endif; ?>
</section>
<section class="panel">
    <div class="section-heading"><div><h2>Casos recientes</h2><p class="muted">Las últimas cinco pruebas registradas.</p></div><a class="text-link" href="<?= e(url('/casos')) ?>">Ver todos <span aria-hidden="true">→</span></a></div>
    <?php if (!$recent): ?><div class="empty-state"><span class="empty-symbol" aria-hidden="true">▤</span><h3>Tu primera prueba empieza aquí</h3><p>Documenta el objetivo, la técnica y los resultados de un caso.</p><a class="button-secondary" href="<?= e(url('/casos/nuevo')) ?>">Registrar primer caso</a></div><?php else: ?>
    <div class="table-scroll"><table><caption class="sr-only">Casos de prueba recientes</caption><thead><tr><th>Código</th><th>Módulo / Funcionalidad</th><th>Técnica</th><th>Estado</th><th>Fecha</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody>
    <?php foreach ($recent as $case): ?><tr><td><a class="case-code" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>"><?= e(code_case((int) $case['id'])) ?></a></td><td class="wrap-cell"><?= e($case['modulo']) ?></td><td><?= e($case['tecnica']) ?></td><td><span class="badge <?= $case['estado'] === 'Éxito' ? 'badge-success' : 'badge-failure' ?>"><?= e($case['estado']) ?></span></td><td><?= e(date_display($case['creado_en'])) ?></td><td><a class="text-link" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>">Ver <span class="sr-only"><?= e(code_case((int) $case['id'])) ?></span> ↗</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<section class="forms-section">
    <div class="section-heading"><div><h2>Resumen de Formularios</h2><p class="muted">Acceso directo a los diez formularios del proyecto.</p></div><a class="text-link" href="<?= e(url('/formularios')) ?>">10 de 10 disponibles →</a></div>
    <div class="form-summary is-compact">
    <?php require __DIR__ . '/includes/form_summary.php'; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
