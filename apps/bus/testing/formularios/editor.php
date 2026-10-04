<?php
use Marketplace\Bus\Repository\BlackBoxRepository;
function matrix_cell(string $kind, string $value): void {
    echo '<select data-cell aria-label="Valor por regla">';
    foreach ($kind === 'condiciones' ? ['V' => 'V', 'F' => 'F', '-' => '–'] : ['' => 'Sin acción', 'X' => 'X'] as $option => $label) {
        echo '<option value="' . e($option) . '"' . ($value === (string) $option ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select>';
}
?>
<?php if ($matrixType !== 'decision'): ?>
<p class="muted">Agrega una fila por campo o clase que quieras documentar. Todos los campos son obligatorios; máximo 30 filas.</p>
<div class="table-scroll"><table class="matrix-editor"><caption class="sr-only">Filas del formulario</caption><thead><tr><?php foreach (BlackBoxRepository::FIELDS[$matrixType] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th>Fila</th></tr></thead><tbody data-rows>
<?php foreach ($data['filas'] as $row): ?><tr><?php foreach (BlackBoxRepository::FIELDS[$matrixType] as $field => $label): ?><td><textarea data-field="<?= e($field) ?>" aria-label="<?= e($label) ?>" rows="3" maxlength="<?= in_array($field, ['campo', 'valor_minimo', 'valor_maximo'], true) ? 150 : 2000 ?>" required><?= e($row[$field]) ?></textarea></td><?php endforeach; ?><td><button class="button-secondary" type="button" data-remove-row>Quitar</button></td></tr><?php endforeach; ?>
</tbody></table></div><button class="button-secondary" type="button" data-add-row>+ Agregar fila</button>
<template id="matrix-row"><tr><?php foreach (BlackBoxRepository::FIELDS[$matrixType] as $field => $label): ?><td><textarea data-field="<?= e($field) ?>" aria-label="<?= e($label) ?>" rows="3" maxlength="<?= in_array($field, ['campo', 'valor_minimo', 'valor_maximo'], true) ? 150 : 2000 ?>" required></textarea></td><?php endforeach; ?><td><button class="button-secondary" type="button" data-remove-row>Quitar</button></td></tr></template>
<?php else: ?>
<p class="muted">Condiciones: V = verdadero, F = falso, – = indiferente. Acciones: X = ejecutar, vacío = no ejecutar. Máximo 20 reglas, 20 condiciones y 20 acciones.</p>
<div class="table-scroll"><table class="matrix-editor decision-editor"><caption class="sr-only">Condiciones, acciones y valores por regla</caption><thead><tr><th>Condición / Acción</th><?php foreach ($data['reglas'] as $rule): ?><th data-rule><input data-rule-name aria-label="Nombre de regla" maxlength="100" required value="<?= e($rule) ?>"><button class="button-secondary" type="button" data-remove-rule>Quitar regla</button></th><?php endforeach; ?><th>Fila</th></tr></thead>
<?php foreach (['condiciones' => 'Condición', 'acciones' => 'Acción'] as $kind => $label): ?><tbody data-group="<?= e($kind) ?>"><?php foreach ($data[$kind] as $element): ?><tr><td><label><?= e($label) ?><input data-description aria-label="<?= e($label) ?>" maxlength="250" required value="<?= e($element['texto']) ?>"></label></td><?php foreach ($element['valores'] as $value): ?><td data-value><?php matrix_cell($kind, $value); ?></td><?php endforeach; ?><td><button class="button-secondary" type="button" data-remove-row>Quitar</button></td></tr><?php endforeach; ?></tbody><?php endforeach; ?>
</table></div>
<div class="button-group"><button class="button-secondary" type="button" data-add-rule>+ Agregar regla</button><button class="button-secondary" type="button" data-add-element="condiciones">+ Agregar condición</button><button class="button-secondary" type="button" data-add-element="acciones">+ Agregar acción</button></div>
<?php foreach (['condiciones' => 'Condición', 'acciones' => 'Acción'] as $kind => $label): ?><template id="decision-<?= e($kind) ?>"><tr><td><label><?= e($label) ?><input data-description aria-label="<?= e($label) ?>" maxlength="250" required></label></td><td><button class="button-secondary" type="button" data-remove-row>Quitar</button></td></tr></template><template id="cell-<?= e($kind) ?>"><td data-value><?php matrix_cell($kind, $kind === 'condiciones' ? 'V' : ''); ?></td></template><?php endforeach; ?>
<template id="decision-rule"><th data-rule><input data-rule-name aria-label="Nombre de regla" maxlength="100" required><button class="button-secondary" type="button" data-remove-rule>Quitar regla</button></th></template>
<?php endif; ?>
<p class="muted" id="matrix-message" role="status" aria-live="polite"></p>
<noscript><p class="alert alert-error">Activa JavaScript para agregar filas y reglas y guardar este formulario.</p></noscript>
