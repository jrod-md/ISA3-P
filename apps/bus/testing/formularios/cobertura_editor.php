<?php use Marketplace\Bus\Repository\CoverageMetrics; ?>
<p class="muted">Registra Total y Cubiertos como enteros no negativos. Porcentaje = Cubiertos / Total × 100; cuando Total es 0, se muestra 0 %. No se obtienen datos automáticamente de herramientas externas. Herramienta Utilizada documenta la herramienta empleada por el tester.</p>
<datalist id="coverage-tools"><option value="TestCover"><option value="PHPUnit"><option value="Manual"><option value="Otra"></datalist>
<div class="table-scroll"><table class="matrix-editor"><caption class="sr-only">Cinco métricas de cobertura de Caja Blanca</caption><thead><tr><th>Métrica</th><th>Total</th><th>Cubiertos</th><th>Porcentaje</th><th>Herramienta Utilizada</th></tr></thead><tbody>
<?php foreach (CoverageMetrics::METRICS as $metric => $label): $row = $data['metricas'][$metric]; ?>
<tr data-coverage-row><th scope="row"><?= e($label) ?></th>
<?php foreach (['total' => 'Total', 'cubiertos' => 'Cubiertos'] as $field => $name): ?><td><input type="number" name="metricas[<?= e($metric) ?>][<?= e($field) ?>]" data-<?= e($field) ?> aria-label="<?= e($label . ' · ' . $name) ?>" min="0" max="<?= CoverageMetrics::MAX_COUNT ?>" step="1" required value="<?= e((string) $row[$field]) ?>"></td><?php endforeach; ?>
<td><output data-percentage aria-label="<?= e($label . ' · Porcentaje') ?>" aria-live="polite"><?= number_format((float) $row['porcentaje'], 2, '.', '') ?> %</output></td>
<td><input name="metricas[<?= e($metric) ?>][herramienta]" aria-label="<?= e($label . ' · Herramienta Utilizada') ?>" list="coverage-tools" maxlength="150" required value="<?= e($row['herramienta']) ?>"></td></tr>
<?php endforeach; ?></tbody></table></div>
