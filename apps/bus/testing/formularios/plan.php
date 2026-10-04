<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
use Marketplace\Bus\Repository\TestPlanRepository;

$current = require_login(); $repository = new TestPlanRepository(db());
$title = 'Plan de Pruebas del Proyecto'; $active = 'formularios'; $basePath = '/formularios/plan';
$plan = null; $errors = []; $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (in_array($planAction, ['ver', 'editar', 'eliminar'], true)) {
    if ($planAction === 'eliminar') { require_admin(); }
    $plan = $id && $id > 0 ? $repository->find($id, $current) : null;
    if (!$plan) { abort_page(404, 'Plan no encontrado', 'El plan no está disponible para tu cuenta.'); }
}
$values = $plan ? $plan + ['cronograma' => $repository->schedule((int) $plan['id'])] : TestPlanRepository::blank();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($planAction === 'eliminar') {
        $repository->delete((int) $plan['id'], $current); flash('Plan y cronograma eliminados.'); redirect($basePath);
    }
    try {
        $savedId = $repository->save($current, $_POST, $plan ? (int) $plan['id'] : null);
        flash($plan ? 'Cambios del plan guardados.' : 'Plan de pruebas registrado.'); redirect($basePath . '/ver?id=' . $savedId);
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage(); $values = TestPlanRepository::blank();
        foreach (TestPlanRepository::FIELDS as $field => $label) { $values[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : ''; }
        $values['cronograma'] = [];
        foreach (array_slice(is_array($_POST['cronograma'] ?? null) ? array_values($_POST['cronograma']) : [[]], 0, 30) as $row) {
            $item = []; foreach (['actividad', 'fecha_inicio', 'fecha_fin'] as $field) { $item[$field] = is_array($row) && is_string($row[$field] ?? null) ? $row[$field] : ''; }
            $values['cronograma'][] = $item;
        }
        if (!$values['cronograma']) { $values['cronograma'] = TestPlanRepository::blank()['cronograma']; }
    }
}
$returnPath = $plan ? $basePath . '/ver?id=' . $plan['id'] : $basePath;
require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url(in_array($planAction, ['ver', 'listado'], true) ? ($planAction === 'listado' ? '/formularios' : $basePath) : $returnPath)) ?>">← Volver</a>
<div class="page-heading"><div><span class="eyebrow">FORMULARIO 06 · NIVEL PROYECTO</span><h1><?= e($title) ?></h1><p class="muted">Define las pruebas del proyecto y su versión. Este plan no depende de un caso individual.</p></div><div class="heading-actions">
<?php if ($planAction === 'listado'): ?><a class="button" href="<?= e(url($basePath . '/nuevo')) ?>">+ Nuevo plan</a>
<?php elseif ($planAction === 'ver'): ?><a class="button" href="<?= e(url($basePath . '/editar?id=' . $plan['id'])) ?>">Editar</a><?php if ($current['rol'] === 'admin'): ?><a class="button-secondary danger-link" href="<?= e(url($basePath . '/eliminar?id=' . $plan['id'])) ?>">Eliminar</a><?php endif; ?>
<?php endif; ?></div></div>
<?php if ($planAction === 'listado'): $records = $repository->listing($current); ?>
<section class="panel"><div class="table-scroll"><table><caption class="sr-only">Planes de pruebas disponibles</caption><thead><tr><th>Proyecto</th><th>Versión</th><th>Responsable</th><th>Fecha</th><th>Creador</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td class="wrap-cell"><?= e($record['nombre_proyecto']) ?></td><td><?= e($record['version']) ?></td><td class="wrap-cell"><?= e($record['responsable']) ?></td><td><?= e((new DateTimeImmutable($record['fecha']))->format('d/m/Y')) ?></td><td><?= e($record['creador']) ?></td><td><a class="text-link" href="<?= e(url($basePath . '/ver?id=' . $record['id'])) ?>">Ver →</a></td></tr><?php endforeach; ?>
<?php if (!$records): ?><tr><td colspan="6">Todavía no tienes planes de pruebas. Registra el proyecto y la versión que vas a evaluar.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($planAction === 'eliminar'): ?>
<section class="panel confirmation"><h2>Eliminar este plan de pruebas</h2><p>Se eliminará el plan de <?= e($plan['nombre_proyecto']) ?>, versión <?= e($plan['version']) ?>, y sus actividades del cronograma.</p><form method="post"><?php csrf_field(); ?><div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button button-danger" type="submit">Eliminar plan</button></div></form></section>
<?php elseif ($planAction === 'ver'): ?>
<p class="muted">Creado por <?= e($plan['creador']) ?> · Última actualización: <?= e(date_display($plan['actualizado_en'])) ?></p>
<?php foreach (TestPlanRepository::SECTIONS as $section => $fields): ?>
<?php if ($section === 'Cierre'): require TESTING_PATH . '/formularios/plan_cronograma.php'; endif; ?>
<section class="panel detail-panel"><h2><?= e($section) ?></h2><dl class="case-details"><?php foreach ($fields as $field): ?><div><dt><?= e(TestPlanRepository::FIELDS[$field]) ?></dt><dd><?= $field === 'fecha' ? e((new DateTimeImmutable($values[$field]))->format('d/m/Y')) : nl2br(e($values[$field])) ?></dd></div><?php endforeach; ?></dl></section>
<?php endforeach; ?>
<?php else: errors_block($errors); require TESTING_PATH . '/formularios/plan_editor.php'; endif; ?>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
