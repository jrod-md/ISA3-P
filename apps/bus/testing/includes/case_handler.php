<?php
// Compartido por crear.php y editar.php; ambas rutas requieren sesión.
$fields = ['modulo', 'tecnica', 'subtecnica', 'objetivo', 'precondiciones', 'datos_entrada', 'pasos_ejecucion', 'resultado_esperado', 'resultado_obtenido', 'estado', 'observaciones'];
$labels = ['modulo' => 'Módulo / Funcionalidad', 'objetivo' => 'Objetivo', 'precondiciones' => 'Precondiciones', 'datos_entrada' => 'Datos de Entrada', 'pasos_ejecucion' => 'Pasos de Ejecución', 'resultado_esperado' => 'Resultado Esperado', 'resultado_obtenido' => 'Resultado Obtenido'];
$values = $case ?? array_fill_keys($fields, '');
// Datos observados en el buscador; el Tester completa y valida el caso antes de guardar.
if (!$case && $_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['origen'] ?? '') === 'buscador') {
    $values['modulo'] = 'Bus / búsqueda unificada de productos';
    foreach (['datos_entrada', 'resultado_obtenido'] as $field) {
        if (is_string($_GET[$field] ?? null)) { $values[$field] = mb_substr($_GET[$field], 0, 5000); }
    }
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($fields as $field) { $values[$field] = post_text($field); }
    foreach ($labels as $field => $label) {
        $max = $field === 'modulo' ? 150 : 5000;
        if ($values[$field] === '') { $errors[] = $label . ' es obligatorio.'; }
        elseif (mb_strlen($values[$field]) > $max) { $errors[] = $label . ' admite hasta ' . $max . ' caracteres.'; }
    }
    if (mb_strlen($values['observaciones']) > 5000) { $errors[] = 'Observaciones admite hasta 5000 caracteres.'; }
    if (!array_key_exists($values['tecnica'], TECNICAS)) { $errors[] = 'Selecciona Caja Negra o Caja Blanca.'; }
    elseif (!in_array($values['subtecnica'], TECNICAS[$values['tecnica']], true)) { $errors[] = 'La subtécnica no corresponde a la técnica seleccionada.'; }
    if (!in_array($values['estado'], ['Éxito', 'Fallo'], true)) { $errors[] = 'Selecciona un estado válido: Éxito o Fallo.'; }

    $uploaded = $_FILES['evidencia'] ?? null;
    $newFile = null;
    $evidenceName = $case['evidencia_nombre'] ?? null;
    $evidenceType = $case['evidencia_tipo'] ?? null;
    $evidenceFile = $case['evidencia_archivo'] ?? null;
    $remove = post_text('quitar_evidencia') === '1';
    if (!$errors) {
        try {
            $stored = Marketplace\Bus\Support\PrivateEvidence::store($uploaded);
            if ($stored) { $newFile = $evidenceFile = $stored['evidencia_archivo']; $evidenceName = $stored['evidencia_nombre']; $evidenceType = $stored['evidencia_tipo']; }
        } catch (InvalidArgumentException $error) { $errors[] = $error->getMessage(); }
    }
    if (!$errors) {
        if ($remove && !$newFile) { $evidenceFile = $evidenceName = $evidenceType = null; }
        $params = array_map(fn ($field) => $values[$field], $fields);
        array_push($params, $evidenceFile, $evidenceName, $evidenceType);
        try {
            if ($case) {
                $sql = 'UPDATE casos_prueba SET ' . implode(', ', array_map(fn ($field) => $field . ' = ?', $fields)) . ', evidencia_archivo = ?, evidencia_nombre = ?, evidencia_tipo = ? WHERE id = ?';
                $params[] = $case['id'];
                if ($current['rol'] !== 'admin') { $sql .= ' AND usuario_id = ?'; $params[] = $current['id']; }
                db()->prepare($sql)->execute($params);
                $id = (int) $case['id'];
            } else {
                $sql = 'INSERT INTO casos_prueba (' . implode(', ', $fields) . ', evidencia_archivo, evidencia_nombre, evidencia_tipo, usuario_id) VALUES (' . implode(', ', array_fill(0, count($fields) + 4, '?')) . ')';
                $params[] = $current['id'];
                db()->prepare($sql)->execute($params);
                $id = (int) db()->lastInsertId();
            }
        } catch (Throwable $error) { delete_evidence($newFile); throw $error; }
        if ($case && ($newFile || $remove)) { delete_evidence($case['evidencia_archivo']); }
        flash($case ? 'Caso actualizado correctamente.' : 'Caso ' . code_case($id) . ' registrado correctamente.');
        redirect('/casos/ver?id=' . $id);
    }
}
