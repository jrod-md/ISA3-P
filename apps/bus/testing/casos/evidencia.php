<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$case = case_by_id();
$file = $case['evidencia_archivo'];
if (!$file || basename($file) !== $file || !is_file(ROOT_PATH . '/.runtime/evidence/' . $file)) { abort_page(404, 'Evidencia no disponible', 'Este caso no tiene un archivo de evidencia disponible.'); }
header('Content-Type: ' . $case['evidencia_tipo']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Disposition: attachment; filename=\"evidencia-" . code_case((int) $case['id']) . '.' . pathinfo($file, PATHINFO_EXTENSION) . "\"; filename*=UTF-8''" . rawurlencode($case['evidencia_nombre']));
header('Content-Length: ' . filesize(ROOT_PATH . '/.runtime/evidence/' . $file));
readfile(ROOT_PATH . '/.runtime/evidence/' . $file);
