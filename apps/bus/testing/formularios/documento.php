<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
use Marketplace\Bus\Repository\BlackBoxRepository;

$current = require_login();
$repository = new BlackBoxRepository(db());
$number = BlackBoxRepository::TYPES[$matrixType];
$title = FORMULARIOS[$number - 1]; $active = 'formularios';
$basePath = '/formularios/' . $matrixType;
$document = null; $errors = [];
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (in_array($matrixAction, ['ver', 'editar', 'eliminar'], true)) {
    if ($matrixAction === 'eliminar') { require_admin(); }
    $document = $documentId && $documentId > 0 ? $repository->find($documentId, $matrixType, $current) : null;
    if (!$document) { abort_page(404, 'Registro no encontrado', 'El formulario no está disponible para tu cuenta.'); }
}
if ($matrixAction === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $repository->delete((int) $document['id'], $matrixType, $current);
    flash('Documentación eliminada. El caso de prueba se conserva.');
    redirect($basePath);
}
$data = $document ? $repository->contents($document) : ($matrixType === 'decision'
    ? ['reglas' => ['Regla 1', 'Regla 2', 'Regla 3', 'Regla 4'], 'condiciones' => [['texto' => '', 'valores' => ['V', 'V', 'F', 'F']]], 'acciones' => [['texto' => '', 'valores' => ['X', '', 'X', '']]]]
    : ['filas' => [array_fill_keys(array_keys(BlackBoxRepository::FIELDS[$matrixType]), '')]]);
$caseId = $document ? (int) $document['caso_id'] : (filter_input(INPUT_GET, 'caso_id', FILTER_VALIDATE_INT) ?: 0);
if (in_array($matrixAction, ['nuevo', 'editar'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $caseId = $document ? (int) $document['caso_id'] : (filter_input(INPUT_POST, 'caso_id', FILTER_VALIDATE_INT) ?: 0);
    try {
        $data = BlackBoxRepository::validate($matrixType, $_POST);
        $savedId = $repository->save($matrixType, $current, $caseId, $data, $document ? (int) $document['id'] : null);
        flash($document ? 'Cambios guardados.' : 'Documentación registrada.');
        redirect($basePath . '/ver?id=' . $savedId);
    } catch (InvalidArgumentException $error) {
        $errors[] = $error->getMessage();
        // Preserve strings safely, with bounded collections even for malformed POSTs.
        if ($matrixType !== 'decision') {
            $data = ['filas' => []];
            foreach (array_slice(is_array($_POST['filas'] ?? null) ? array_values($_POST['filas']) : [[]], 0, 30) as $row) {
                $item = [];
                foreach (BlackBoxRepository::FIELDS[$matrixType] as $field => $label) { $item[$field] = is_array($row) && is_string($row[$field] ?? null) ? $row[$field] : ''; }
                $data['filas'][] = $item;
            }
            if (!$data['filas']) { $data['filas'][] = array_fill_keys(array_keys(BlackBoxRepository::FIELDS[$matrixType]), ''); }
        } else {
            $rawRules = is_array($_POST['reglas'] ?? null) ? array_values($_POST['reglas']) : ['Regla 1'];
            $data = ['reglas' => array_map(fn ($rule) => is_string($rule) ? $rule : '', array_slice($rawRules ?: ['Regla 1'], 0, 20))];
            foreach (['condiciones', 'acciones'] as $kind) {
                $data[$kind] = [];
                $items = is_array($_POST[$kind] ?? null) ? array_values($_POST[$kind]) : [[]];
                foreach (array_slice($items ?: [[]], 0, 20) as $item) {
                    $values = [];
                    for ($r = 0; $r < count($data['reglas']); $r++) { $value = is_array($item) && is_array($item['valores'] ?? null) ? ($item['valores'][$r] ?? '') : ''; $values[] = is_string($value) ? $value : ''; }
                    $data[$kind][] = ['texto' => is_array($item) && is_string($item['texto'] ?? null) ? $item['texto'] : '', 'valores' => $values];
                }
            }
        }
    }
}
$returnPath = $document ? $basePath . '/ver?id=' . $document['id'] : ($caseId ? '/casos/ver?id=' . $caseId : $basePath);
require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url($matrixAction === 'ver' ? '/casos/ver?id=' . $caseId : ($matrixAction === 'listado' ? '/formularios' : $returnPath))) ?>">← Volver</a>
<div class="page-heading"><div><span class="eyebrow">FORMULARIO <?= str_pad((string) $number, 2, '0', STR_PAD_LEFT) ?> · CAJA NEGRA</span><h1><?= e($title) ?></h1><p class="muted"><?= $document ? e(code_case($caseId) . ' · ' . $document['modulo'] . ' · Autor: ' . $document['autor']) : 'Registra entradas, condiciones y resultados del propio proyecto.' ?></p></div><div class="heading-actions">
<?php if ($matrixAction === 'listado'): ?><a class="button" href="<?= e(url($basePath . '/nuevo')) ?>">+ Nuevo registro</a>
<?php elseif ($matrixAction === 'ver'): ?><a class="button" href="<?= e(url($basePath . '/editar?id=' . $document['id'])) ?>">Editar</a><?php if ($current['rol'] === 'admin'): ?><a class="button-secondary danger-link" href="<?= e(url($basePath . '/eliminar?id=' . $document['id'])) ?>">Eliminar</a><?php endif; ?>
<?php endif; ?></div></div>
<?php if ($matrixAction === 'listado'): $records = $repository->listing($matrixType, $current); ?>
<section class="panel"><div class="table-scroll"><table><caption class="sr-only"><?= e($title) ?>: registros disponibles</caption><thead><tr><th>Caso</th><th>Módulo</th><th>Autor</th><th>Actualización</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($records as $record): ?><tr><td><a href="<?= e(url('/casos/ver?id=' . $record['caso_id'])) ?>"><?= e(code_case((int) $record['caso_id'])) ?></a></td><td class="wrap-cell"><?= e($record['modulo']) ?></td><td><?= e($record['autor']) ?></td><td><?= e(date_display($record['actualizado_en'])) ?></td><td><a class="text-link" href="<?= e(url($basePath . '/ver?id=' . $record['id'])) ?>">Ver →</a></td></tr><?php endforeach; ?>
<?php if (!$records): ?><tr><td colspan="5">Todavía no tienes registros de este formulario. Crea primero un caso de prueba y documenta sus entradas.</td></tr><?php endif; ?></tbody></table></div></section>
<?php elseif ($matrixAction === 'eliminar'): ?>
<section class="panel confirmation"><h2>Eliminar este registro de documentación</h2><p>Se eliminarán sus filas, condiciones y reglas asociadas. El caso de prueba y su evidencia se conservan.</p><form method="post"><?php csrf_field(); ?><div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button button-danger" type="submit">Eliminar registro</button></div></form></section>
<?php elseif ($matrixAction === 'ver'): ?>
<section class="panel detail-panel"><?php require TESTING_PATH . '/formularios/tabla.php'; ?><p class="muted last-update">Última actualización: <?= e(date_display($document['actualizado_en'])) ?></p></section>
<?php else: errors_block($errors); ?>
<form method="post" class="case-form" id="black-box-form" data-type="<?= e($matrixType) ?>">
<?php csrf_field(); ?>
<section class="panel form-section"><div class="field"><label for="caso_id">Caso de prueba existente *</label>
<?php if ($document): ?><input id="caso_id" readonly value="<?= e(code_case($caseId) . ' · ' . $document['modulo']) ?>"><small>El caso y el autor originales se conservan al editar.</small>
<?php else: $cases = $repository->cases($current); ?><select id="caso_id" name="caso_id" required><option value="">Selecciona un caso</option><?php foreach ($cases as $option): ?><option value="<?= (int) $option['id'] ?>" <?= $caseId === (int) $option['id'] ? 'selected' : '' ?>><?= e(code_case((int) $option['id']) . ' · ' . $option['modulo']) ?></option><?php endforeach; ?></select><?php if (!$cases): ?><p class="muted">Primero <a href="<?= e(url('/casos/nuevo')) ?>">registra un caso de prueba</a>.</p><?php endif; ?><?php endif; ?></div>
<?php require TESTING_PATH . '/formularios/editor.php'; ?></section>
<div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button" type="submit"><?= $document ? 'Guardar cambios' : 'Guardar formulario' ?></button></div>
</form><script defer src="<?= e(url('/assets/black-box.js')) ?>"></script>
<?php endif; require TESTING_PATH . '/includes/footer.php'; ?>
