<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
use Marketplace\Bus\Repository\ProjectEvaluationRepository as Evaluation;

$current = require_login(); $repository = new Evaluation(db(), $evaluationType);
$definition = Evaluation::TYPES[$evaluationType]; $title = $definition['title']; $active = 'formularios'; $basePath = '/formularios/' . $evaluationType;
$document = null; $errors = []; $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (in_array($evaluationAction, ['ver', 'editar', 'eliminar'], true)) {
    if ($evaluationAction === 'eliminar') { require_admin(); }
    $document = $id && $id > 0 ? $repository->find($id, $current) : null;
    if (!$document) { abort_page(404, 'Documento no encontrado', 'El documento no está disponible para tu cuenta.'); }
}
$values = Evaluation::blank($evaluationType);
if ($document) {
    $values = $document; $values['filas'] = [];
    foreach ($repository->rows((int) $document['id']) as $row) {
        if ($evaluationType === 'portafolio') { $values['filas'][] = $row; }
        else { $values['filas'][$row[$evaluationType === 'rubrica' ? 'criterio' : 'aspecto']] = $row; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($evaluationAction === 'eliminar') {
        $repository->delete((int) $document['id'], $current); flash('Documento y sus filas eliminados.'); redirect($basePath);
    }
    try {
        $savedId = $repository->save($current, $_POST, $document ? (int) $document['id'] : null);
        flash($document ? 'Cambios guardados.' : 'Documento registrado.'); redirect($basePath . '/ver?id=' . $savedId);
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage(); $values = Evaluation::blank($evaluationType);
        foreach ($definition['fields'] as $field => $label) { $values[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : ''; }
        $posted = is_array($_POST['filas'] ?? null) ? $_POST['filas'] : [];
        if ($evaluationType === 'portafolio') { $values['filas'] = array_fill(0, max(1, min(30, count($posted))), array_fill_keys($definition['row_fields'], '')); $posted = array_values($posted); }
        foreach ($values['filas'] as $key => &$row) {
            foreach ($row as $field => &$value) { $value = is_array($posted[$key] ?? null) && is_string($posted[$key][$field] ?? null) ? $posted[$key][$field] : ''; } unset($value);
        } unset($row);
    }
}
$returnPath = $document ? $basePath . '/ver?id=' . $document['id'] : $basePath;
$editing = in_array($evaluationAction, ['nuevo', 'editar'], true);
require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url(in_array($evaluationAction, ['ver', 'listado'], true) ? ($evaluationAction === 'listado' ? '/formularios' : $basePath) : $returnPath)) ?>">← Volver</a>
<div class="page-heading"><div><span class="eyebrow">FORMULARIO <?= e((string) $definition['number']) ?> · NIVEL PROYECTO</span><h1><?= e($title) ?></h1><p class="muted"><?= e($definition['purpose']) ?></p></div><div class="heading-actions">
<?php if ($evaluationAction === 'listado'): ?><a class="button" href="<?= e(url($basePath . '/nuevo')) ?>">+ Nuevo documento</a>
<?php elseif ($evaluationAction === 'ver'): ?><a class="button" href="<?= e(url($basePath . '/editar?id=' . $document['id'])) ?>">Editar</a><?php if ($current['rol'] === 'admin'): ?><a class="button-secondary danger-link" href="<?= e(url($basePath . '/eliminar?id=' . $document['id'])) ?>">Eliminar</a><?php endif; ?>
<?php endif; ?></div></div>
<?php if ($evaluationAction === 'listado'): $records = $repository->listing($current); ?>
<section class="panel"><div class="table-scroll"><table><caption class="sr-only"><?= e($title) ?>: documentos disponibles</caption><thead><tr><th><?= $evaluationType === 'portafolio' ? 'Título' : 'Persona evaluada' ?></th><?php if ($evaluationType !== 'portafolio'): ?><th>Fecha</th><th>Resultado</th><?php endif; ?><th>Creador</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td class="wrap-cell"><?= e($record[$evaluationType === 'portafolio' ? 'titulo' : 'evaluado']) ?></td><?php if ($evaluationType !== 'portafolio'): ?><td><?= e($record['fecha']) ?></td><td><?= $evaluationType === 'rubrica' ? e((string) $record['total']) . ' / 30' : 'Auto: ' . e((string) $record['promedio_auto']) . ' · Co: ' . e((string) $record['promedio_co']) ?></td><?php endif; ?><td><?= e($record['creador']) ?></td><td><a class="text-link" href="<?= e(url($basePath . '/ver?id=' . $record['id'])) ?>">Ver →</a></td></tr><?php endforeach; ?>
<?php if (!$records): ?><tr><td colspan="<?= $evaluationType === 'portafolio' ? 3 : 5 ?>">Todavía no tienes documentos. Registra el primero para este proyecto.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($evaluationAction === 'eliminar'): ?>
<section class="panel confirmation"><h2>Eliminar este documento</h2><p>Se eliminará <?= e($document['titulo'] ?? $document['evaluado']) ?> y todas sus filas.</p><form method="post"><?php csrf_field(); ?><div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button button-danger" type="submit">Eliminar documento</button></div></form></section>
<?php else: ?>
<?php if ($editing): errors_block($errors); ?><p class="form-note">Los campos con * son obligatorios. La cuenta creadora se registra por separado.</p><form method="post" class="case-form" id="project-evaluation-form" data-type="<?= e($evaluationType) ?>"><?php csrf_field(); ?>
<?php else: ?><p class="muted">Creado por <?= e($document['creador']) ?> · Última actualización: <?= e(date_display($document['actualizado_en'])) ?></p><?php endif; ?>
<section class="panel <?= $editing ? 'form-section' : 'detail-panel' ?>"><h2>Información general</h2>
<?php if ($editing): ?><div class="field-grid"><?php foreach ($definition['fields'] as $field => $label): $optional = in_array($field, ['evaluador', 'observaciones'], true); ?><div class="field"><label for="<?= e($field) ?>"><?= e($label) ?><?= $optional ? ' (opcional)' : ' *' ?></label>
<?php if ($field === 'observaciones'): ?><textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="3" maxlength="5000"><?= e($values[$field]) ?></textarea>
<?php else: ?><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'fecha' ? 'date' : 'text' ?>" <?= $field === 'fecha' ? 'min="1000-01-01" max="9999-12-31"' : 'maxlength="250"' ?> <?= $optional ? '' : 'required' ?> value="<?= e($values[$field]) ?>"><?php endif; ?></div><?php endforeach; ?></div>
<?php else: ?><dl class="case-details"><?php foreach ($definition['fields'] as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= nl2br(e($values[$field] !== '' ? $values[$field] : 'Sin registrar')) ?></dd></div><?php endforeach; ?></dl><?php endif; ?></section>
<?php require TESTING_PATH . '/formularios/evaluacion_filas.php'; ?>
<?php if ($editing): ?><div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button" type="submit"><?= $document ? 'Guardar cambios' : 'Registrar documento' ?></button></div></form><script defer src="<?= e(url('/assets/project-evaluation.js')) ?>"></script><?php endif; ?>
<?php endif; require TESTING_PATH . '/includes/footer.php'; ?>
