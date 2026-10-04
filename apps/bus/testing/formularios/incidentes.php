<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
use Marketplace\Bus\Repository\IncidentRepository as Incidents;
use Marketplace\Bus\Support\PrivateEvidence;

$current = require_login(); $repository = new Incidents(db()); $basePath = '/formularios/incidentes';
$active = 'formularios'; $title = 'Registro de Incidentes'; $incident = null; $errors = [];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (in_array($incidentAction, ['ver', 'editar', 'eliminar', 'evidencia'], true)) {
    if ($incidentAction === 'eliminar') { require_admin(); }
    $incident = $id && $id > 0 ? $repository->find($id, $current) : null;
    if (!$incident) { abort_page(404, 'Incidente no encontrado', 'El incidente no está disponible para tu cuenta.'); }
}
if ($incidentAction === 'evidencia') {
    if (!PrivateEvidence::download($incident, Incidents::code((int) $incident['id']))) { abort_page(404, 'Evidencia no disponible', 'Este incidente no tiene un archivo disponible.'); } exit;
}
$values = $incident ?? Incidents::blank();
$cases = $repository->accessibleCases($current);
if ($incidentAction === 'nuevo' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['caso_id'])) {
    try { $selected = Incidents::caseId($_GET['caso_id']); } catch (InvalidArgumentException) { abort_page(404, 'Caso no encontrado', 'Selecciona un caso disponible para tu cuenta.'); }
    if ($selected !== null && !$repository->canAccessCase($selected, $current)) { abort_page(404, 'Caso no encontrado', 'El caso no está disponible para tu cuenta.'); }
    $values['caso_id'] = $selected;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($incidentAction === 'eliminar') { $repository->delete((int) $incident['id'], $current); flash('Incidente eliminado. Su caso relacionado se conserva.'); redirect($basePath); }
    try {
        $savedId = $repository->save($current, $_POST, $incident ? (int) $incident['id'] : null, $_FILES['evidencia'] ?? null, post_text('quitar_evidencia') === '1');
        flash($incident ? 'Incidente actualizado correctamente.' : 'Incidente ' . Incidents::code($savedId) . ' registrado.'); redirect($basePath . '/ver?id=' . $savedId);
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage(); $values = Incidents::blank();
        foreach (Incidents::FIELDS as $field => $label) { $values[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : ''; }
        try { $values['caso_id'] = Incidents::caseId($_POST['caso_id'] ?? null); } catch (InvalidArgumentException) { $values['caso_id'] = null; }
    }
}
$returnPath = $incident ? $basePath . '/ver?id=' . $incident['id'] : $basePath;
$filters = []; $records = [];
if ($incidentAction === 'listado') {
    foreach (['estado', 'severidad', 'prioridad'] as $field) { $filters[$field] = $_GET[$field] ?? ''; }
    try { $records = $repository->listing($current, $filters); } catch (InvalidArgumentException $error) { abort_page(400, 'Filtro no válido', $error->getMessage()); }
}
require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url(in_array($incidentAction, ['ver', 'listado'], true) ? ($incidentAction === 'listado' ? '/formularios' : $basePath) : $returnPath)) ?>">← Volver</a>
<div class="page-heading"><div><span class="eyebrow">FORMULARIO 10 · <?= $incident ? e(Incidents::code((int) $incident['id'])) : 'GESTIÓN DE DEFECTOS' ?></span><h1><?= e($title) ?></h1><p class="muted">Documenta defectos encontrados durante las pruebas del proyecto.</p></div><div class="heading-actions">
<?php if ($incidentAction === 'listado'): ?><a class="button" href="<?= e(url($basePath . '/nuevo')) ?>">+ Nuevo incidente</a>
<?php elseif ($incidentAction === 'ver'): ?><a class="button" href="<?= e(url($basePath . '/editar?id=' . $incident['id'])) ?>">Editar</a><?php if ($current['rol'] === 'admin'): ?><a class="button-secondary danger-link" href="<?= e(url($basePath . '/eliminar?id=' . $incident['id'])) ?>">Eliminar</a><?php endif; ?>
<?php endif; ?></div></div>
<?php if ($incidentAction === 'listado'): ?>
<form method="get" class="panel"><div class="field-grid"><?php foreach (['estado' => 'Estado', 'severidad' => 'Severidad', 'prioridad' => 'Prioridad'] as $field => $label): ?><div class="field"><label for="filter-<?= e($field) ?>"><?= e($label) ?></label><select id="filter-<?= e($field) ?>" name="<?= e($field) ?>"><option value="">Todos</option><?php foreach (Incidents::choices($field) as $choice): ?><option value="<?= e($choice) ?>" <?= $filters[$field] === $choice ? 'selected' : '' ?>><?= e($choice) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div><div class="form-actions"><button class="button-secondary" type="submit">Filtrar</button><a class="text-link" href="<?= e(url($basePath)) ?>">Limpiar filtros</a></div></form>
<section class="panel"><div class="table-scroll"><table><caption class="sr-only">Incidentes disponibles para tu cuenta</caption><thead><tr><?php foreach (['Código','Título','Módulo','Severidad','Prioridad','Estado','Asignado a','Fecha','Acciones'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($records as $record): ?><tr><td><?= e(Incidents::code((int) $record['id'])) ?></td><?php foreach (['titulo','modulo','severidad','prioridad','estado','asignado_a'] as $field): ?><td class="wrap-cell"><?= e($record[$field]) ?></td><?php endforeach; ?><td><?= e(date_display($record['creado_en'])) ?></td><td><a class="text-link" href="<?= e(url($basePath . '/ver?id=' . $record['id'])) ?>">Ver →</a></td></tr><?php endforeach; ?><?php if (!$records): ?><tr><td colspan="9">No hay incidentes para estos filtros. Puedes registrar uno con o sin caso asociado.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($incidentAction === 'eliminar'): ?>
<section class="panel confirmation"><h2>Eliminar <?= e(Incidents::code((int) $incident['id'])) ?></h2><p><?= e($incident['titulo']) ?>. Se eliminarán el incidente y su evidencia. El caso relacionado se conserva.</p><form method="post"><?php csrf_field(); ?><div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button button-danger" type="submit">Eliminar incidente</button></div></form></section>
<?php elseif ($incidentAction === 'ver'): ?>
<section class="panel detail-panel"><h2><?= e(Incidents::code((int) $incident['id']) . ' · ' . $incident['titulo']) ?></h2><p class="muted">Creado por <?= e($incident['creador']) ?> · Última actualización: <?= e(date_display($incident['actualizado_en'])) ?></p><dl class="case-details"><?php foreach (Incidents::FIELDS as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= nl2br(e($incident[$field])) ?></dd></div><?php endforeach; ?><div><dt>Caso relacionado</dt><dd><?php if ($incident['caso_id'] !== null && $repository->canAccessCase((int) $incident['caso_id'], $current)): ?><a class="text-link" href="<?= e(url('/casos/ver?id=' . $incident['caso_id'])) ?>"><?= e(code_case((int) $incident['caso_id'])) ?> →</a><?php elseif ($incident['caso_id'] !== null): ?><?= e(code_case((int) $incident['caso_id'])) ?> · No disponible para tu cuenta<?php else: ?>Sin caso asociado<?php endif; ?></dd></div><div><dt>Evidencia</dt><dd><?php if ($incident['evidencia_archivo']): ?><a class="text-link" href="<?= e(url($basePath . '/evidencia?id=' . $incident['id'])) ?>">Descargar <?= e($incident['evidencia_nombre']) ?> ↗</a><span class="muted"> · <?= e((string) $incident['evidencia_tamano']) ?> bytes</span><?php else: ?>Sin archivo adjunto<?php endif; ?></dd></div></dl></section>
<?php else: errors_block($errors); require TESTING_PATH . '/formularios/incidente_editor.php'; endif; ?>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
