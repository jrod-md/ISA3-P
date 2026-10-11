<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
use Marketplace\Bus\Repository\BlackBoxRepository;
use Marketplace\Bus\Repository\IncidentRepository as Incidents;

$case = case_by_id(); $current = require_login();
$documents = new BlackBoxRepository(db()); $associated = []; $documentCount = 0;
foreach (BlackBoxRepository::TYPES as $type => $number) { $associated[$type] = $documents->listing($type, $current, (int) $case['id']); $documentCount += count($associated[$type]); }
$incidents = (new Incidents(db()))->listing($current, [], (int) $case['id']);
// Formulario de diseño que corresponde a la técnica del caso.
$recommended = $case['tecnica'] === 'Caja Blanca' ? 'cobertura' : (['Partición de equivalencia' => 'equivalencia', 'Análisis de valores límite' => 'limites', 'Tabla de decisiones' => 'decision'][$case['subtecnica']] ?? null);
$title = code_case((int) $case['id']); $active = 'casos'; require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url('/casos')) ?>">← Volver a los casos</a>
<div class="page-heading"><div><span class="eyebrow"><?= e(code_case((int) $case['id'])) ?> · FORMULARIO 01</span><h1><?= e($case['modulo']) ?></h1><p class="muted">Registrado por <?= e($case['tester']) ?> · <?= e(date_display($case['creado_en'])) ?></p></div><div class="heading-actions"><a class="button" href="<?= e(url('/casos/editar?id=' . $case['id'])) ?>">Editar caso</a><?php if ($current['rol'] === 'admin'): ?><a class="button-secondary danger-link" href="<?= e(url('/casos/eliminar?id=' . $case['id'])) ?>">Eliminar</a><?php endif; ?></div></div>
<div class="case-strip"><div><span class="tiny-label">ID DEL CASO</span><strong class="case-code"><?= e(code_case((int) $case['id'])) ?></strong></div><div><span class="tiny-label">TÉCNICA UTILIZADA</span><strong><?= e($case['tecnica']) ?></strong><span><?= e($case['subtecnica']) ?></span></div><div><span class="tiny-label">ESTADO</span><span class="badge <?= $case['estado'] === 'Éxito' ? 'badge-success' : 'badge-failure' ?>"><?= e($case['estado']) ?></span></div><div><span class="tiny-label">DOCUMENTACIÓN</span><strong><?= $documentCount ?> <?= $documentCount === 1 ? 'documento' : 'documentos' ?></strong><span><?= count($incidents) ?> <?= count($incidents) === 1 ? 'incidente' : 'incidentes' ?></span></div></div>
<?php if ($recommended && !$associated[$recommended]): ?>
<div class="next-step"><div><span class="tiny-label">SIGUIENTE PASO</span><p>Este caso aplica <strong><?= e($case['tecnica'] === 'Caja Blanca' ? 'Caja Blanca' : $case['subtecnica']) ?></strong>. Documenta su diseño en el Formulario <?= str_pad((string) BlackBoxRepository::TYPES[$recommended], 2, '0', STR_PAD_LEFT) ?>.</p></div><a class="button" href="<?= e(url('/formularios/' . $recommended . '/nuevo?caso_id=' . $case['id'])) ?>">Crear <?= e(FORMULARIOS[BlackBoxRepository::TYPES[$recommended] - 1]) ?> <span aria-hidden="true">→</span></a></div>
<?php endif; ?>
<div class="tabs" data-tabs><a href="#detalle">Detalle</a><a href="#diseno">Diseño de la prueba <span class="tab-count"><?= $documentCount ?></span></a><a href="#incidentes">Incidentes <span class="tab-count"><?= count($incidents) ?></span></a></div>
<section class="panel detail-panel" id="detalle" data-tab-panel>
<dl class="case-details"><?php foreach (['objetivo' => 'Objetivo', 'precondiciones' => 'Precondiciones', 'datos_entrada' => 'Datos de Entrada', 'pasos_ejecucion' => 'Pasos de Ejecución'] as $key => $label): ?><div><dt><?= e($label) ?></dt><dd><?= nl2br(e($case[$key])) ?></dd></div><?php endforeach; ?></dl>
<div class="result-compare"><div><span class="tiny-label">RESULTADO ESPERADO</span><p><?= nl2br(e($case['resultado_esperado'])) ?></p></div><div class="<?= $case['estado'] === 'Éxito' ? 'is-success' : 'is-failure' ?>"><span class="tiny-label">RESULTADO OBTENIDO · <?= e(mb_strtoupper($case['estado'])) ?></span><p><?= nl2br(e($case['resultado_obtenido'])) ?></p></div></div>
<dl class="case-details"><div><dt>Observaciones</dt><dd><?= $case['observaciones'] === '' ? '<span class="muted">Sin observaciones.</span>' : nl2br(e($case['observaciones'])) ?></dd></div><div><dt>Evidencia</dt><dd><?php if ($case['evidencia_archivo']): ?><a class="text-link" href="<?= e(url('/casos/evidencia?id=' . $case['id'])) ?>">Descargar <?= e($case['evidencia_nombre']) ?> ↗</a><?php else: ?><span class="muted">Sin archivo adjunto.</span><?php endif; ?></dd></div></dl>
<p class="muted last-update">Última actualización: <?= e(date_display($case['actualizado_en'])) ?></p></section>
<section class="panel detail-panel" id="diseno" data-tab-panel><div class="section-heading"><div><h2>Documentación asociada</h2><p class="muted">Caja Negra y Caja Blanca · Formularios disponibles para tu cuenta.</p></div></div>
<?php foreach (BlackBoxRepository::TYPES as $type => $number): ?>
<div class="associated-document<?= $type === $recommended ? ' is-recommended' : '' ?>"><span class="form-number"><?= str_pad((string) $number, 2, '0', STR_PAD_LEFT) ?></span><div class="associated-title"><strong><?= e(FORMULARIOS[$number - 1]) ?></strong><?php if ($type === $recommended): ?><small>Corresponde a la técnica de este caso</small><?php endif; ?></div><span class="badge <?= $associated[$type] ? 'badge-success' : 'badge-pending' ?>"><?= $associated[$type] ? '✓ Registrado' : 'Sin registro' ?></span>
<div class="associated-links"><?php foreach ($associated[$type] as $item): ?><a class="text-link" href="<?= e(url('/formularios/' . $type . '/ver?id=' . $item['id'])) ?>">Consultar #<?= (int) $item['id'] ?> · <?= e($item['autor']) ?></a><?php endforeach; ?><a class="text-link <?= $associated[$type] ? 'quiet-link' : '' ?>" href="<?= e(url('/formularios/' . $type . '/nuevo?caso_id=' . $case['id'])) ?>"><?= $associated[$type] ? 'Añadir otro' : 'Crear' ?> →</a></div></div>
<?php endforeach; ?></section>
<div id="incidentes" data-tab-panel><?php require TESTING_PATH . '/casos/incidentes_relacionados.php'; ?></div>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
