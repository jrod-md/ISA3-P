<?php
$formGroups = [
    ['Por caso de prueba', 'Se registran y consultan desde cada caso.', [0, 1, 2, 3, 4]],
    ['Proyecto y evaluación', 'Documentos del proyecto, sin caso asociado.', [5, 6, 7, 8]],
    ['Defectos', 'Incidentes encontrados durante las pruebas.', [9]],
];
$formNotes = ['Documenta cada caso', 'Caja Negra', 'Caja Negra', 'Caja Negra', 'Caja Blanca', 'Versiones y cronograma', 'Evaluación sumativa', 'Evaluación formativa', 'Organización de evidencias', 'Caso relacionado opcional'];
?>
<?php foreach ($formGroups as [$groupName, $groupNote, $indexes]): ?>
<div class="form-group"><div class="form-group-title"><h3><?= e($groupName) ?></h3><p class="muted"><?= e($groupNote) ?></p></div>
<div class="form-group-rows"><?php foreach ($indexes as $index): ?>
<a class="form-summary-row" href="<?= e(url(FORMULARIO_RUTAS[$index])) ?>"><span class="form-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><div><strong><?= e(FORMULARIOS[$index]) ?></strong><small><?= e($formNotes[$index]) ?></small></div><span class="row-arrow" aria-hidden="true">→</span></a>
<?php endforeach; ?></div></div>
<?php endforeach; ?>
