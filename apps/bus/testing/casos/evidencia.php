<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$case = case_by_id();
if (!Marketplace\Bus\Support\PrivateEvidence::download($case, code_case((int) $case['id']))) { abort_page(404, 'Evidencia no disponible', 'Este caso no tiene un archivo de evidencia disponible.'); }
