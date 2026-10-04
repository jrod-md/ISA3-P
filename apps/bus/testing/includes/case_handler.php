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
    if ($uploaded && ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (!is_int($uploaded['error']) || $uploaded['error'] !== UPLOAD_ERR_OK || !is_string($uploaded['tmp_name']) || !is_uploaded_file($uploaded['tmp_name'])) {
            $errors[] = 'No se pudo subir la evidencia. Elige un archivo válido de hasta 2 MB.';
        } elseif ($uploaded['size'] > 2 * 1024 * 1024) {
            $errors[] = 'La evidencia debe pesar como máximo 2 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($uploaded['tmp_name']);
            $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
            if (!isset($types[$mime])) { $errors[] = 'La evidencia debe ser una imagen PNG, JPG o un documento PDF.'; }
            elseif (!$errors) {
                $directory = ROOT_PATH . '/.runtime/evidence';
                if (!is_dir($directory)) { mkdir($directory, 0700, true); }
                $newFile = bin2hex(random_bytes(20)) . '.' . $types[$mime];
                if (!move_uploaded_file($uploaded['tmp_name'], $directory . '/' . $newFile)) { $errors[] = 'No se pudo guardar la evidencia. Revisa los permisos de la carpeta .runtime.'; $newFile = null; }
                else { $evidenceFile = $newFile; $evidenceName = mb_substr(basename(str_replace('\\', '/', $uploaded['name'])), 0, 255); $evidenceType = $mime; }
            }
        }
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
