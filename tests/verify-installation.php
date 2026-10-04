<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;

// Bases temporales propias: nunca utiliza las bases de la aplicación.
$prefix = 'p2_verify_' . bin2hex(random_bytes(4)) . '_';
$schemas = [];
foreach (['bus_meta', 'market_alpha', 'market_beta', 'market_gamma'] as $name) { $schemas[$name] = $prefix . $name; }
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::string('DB_HOST', '127.0.0.1'), Config::int('DB_PORT', 3306)), Config::string('DB_USER', 'root'), Config::string('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$owned = [];
$failed = false;
try {
    foreach ($schemas as $schema) {
        // CREATE sin IF NOT EXISTS impide apropiarse de una base ajena.
        $pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $owned[] = $schema;
    }
    $sql = strtr(file_get_contents(PROJECT_ROOT . '/sql/instalacion_completa.sql'), $schemas);
    $pdo->exec($sql);
    foreach (['market_alpha' => 13, 'market_beta' => 12, 'market_gamma' => 12] as $name => $expected) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $schemas[$name] . '`.products')->fetchColumn() !== $expected) { throw new RuntimeException('Catálogo incorrecto: ' . $name); }
    }
    $pdo->exec('USE `' . $schemas['bus_meta'] . '`');
    foreach (['formularios_prueba', 'equivalencia_filas', 'limite_filas', 'decision_reglas', 'decision_elementos', 'decision_valores', 'cobertura_metricas', 'planes_prueba', 'plan_cronograma', 'rubricas', 'rubrica_criterios', 'evaluaciones_pares', 'evaluacion_aspectos', 'portafolios', 'portafolio_evidencias', 'incidentes'] as $table) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
        $check->execute([$schemas['bus_meta'], $table]);
        if ((int) $check->fetchColumn() !== 1) { throw new RuntimeException('Falta tabla de Formularios 2–10: ' . $table); }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() !== 2 || (int) $pdo->query('SELECT COUNT(*) FROM casos_prueba')->fetchColumn() !== 0) { throw new RuntimeException('Instalación inicial incorrecta'); }
    foreach (['admin' => 'demo-isa3-2026', 'tester' => 'Tester123!'] as $username => $password) {
        $query = $pdo->prepare('SELECT password FROM usuarios WHERE username = ?'); $query->execute([$username]);
        if (!password_verify($password, (string) $query->fetchColumn())) { throw new RuntimeException('Credencial incorrecta: ' . $username); }
    }
    $pdo->exec("INSERT INTO search_sessions (id, criteria_json, created_at, expires_at, ttl_seconds, requested_providers, result_count) VALUES ('sql-installation-sentinel', '{}', UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR), 3600, 'alpha', 0)");
    $pdo->exec("UPDATE usuarios SET nombre = 'Nombre conservado' WHERE username = 'tester'");
    $pdo->exec("INSERT INTO casos_prueba (usuario_id, modulo, tecnica, subtecnica, objetivo, precondiciones, datos_entrada, pasos_ejecucion, resultado_esperado, resultado_obtenido, estado, observaciones) SELECT id, 'Caso de instalación', 'Caja Negra', 'Partición de equivalencia', 'Validar', 'Sistema listo', 'Precio', 'Buscar', 'Respuesta', 'Respuesta', 'Éxito', '' FROM usuarios WHERE username = 'tester'");
    $caseId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO formularios_prueba (caso_id, usuario_id, tipo) SELECT {$caseId}, id, 'equivalencia' FROM usuarios WHERE username = 'tester'");
    $documentId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO equivalencia_filas (formulario_id, orden, campo, clase_valida, clases_invalidas, valores_representativos, resultado_esperado) VALUES ({$documentId}, 0, 'Precio', '0–900', 'Menor que cero', '0, 900', 'Aceptar')");
    $pdo->exec("INSERT INTO formularios_prueba (caso_id, usuario_id, tipo) SELECT {$caseId}, id, 'cobertura' FROM usuarios WHERE username = 'tester'");
    $coverageId = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO cobertura_metricas (formulario_id,metrica,total,cubiertos,porcentaje,herramienta,orden) VALUES (?,?,3,1,33.33,?,?)');
    foreach (array_keys(Marketplace\Bus\Repository\CoverageMetrics::METRICS) as $order => $metric) { $insert->execute([$coverageId, $metric, 'Manual', $order]); }
    $coverageBefore = $pdo->query('SELECT * FROM cobertura_metricas ORDER BY id')->fetchAll();
    $pdo->exec("INSERT INTO planes_prueba (usuario_id,nombre_proyecto,version,responsable,fecha,alcance,objetivos,estrategia,recursos,criterios_aceptacion,riesgos) SELECT id,'Marketplace Search Bus','1.0','Responsable académico','2026-10-04','Alcance','Objetivos','Estrategia','Recursos','Aceptación','Riesgos' FROM usuarios WHERE username = 'tester'");
    $planId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO plan_cronograma (plan_id,actividad,fecha_inicio,fecha_fin,orden) VALUES ({$planId},'Diseño','2026-10-05','2026-10-07',0)");
    $planBefore = $pdo->query('SELECT * FROM planes_prueba ORDER BY id')->fetchAll();
    $scheduleBefore = $pdo->query('SELECT * FROM plan_cronograma ORDER BY id')->fetchAll();
    $evaluationBefore = [];
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn();
    foreach (Marketplace\Bus\Repository\ProjectEvaluationRepository::TYPES as $type => $definition) {
        $repository = new Marketplace\Bus\Repository\ProjectEvaluationRepository($pdo, $type);
        $data = ['titulo' => 'Instalación', 'evaluado' => 'Estudiante', 'evaluador' => 'Par', 'fecha' => '2026-10-04', 'observaciones' => '', 'filas' => []];
        if ($type === 'rubrica') { foreach (Marketplace\Bus\Repository\ProjectEvaluationRepository::RUBRIC as $key => $reference) { $data['filas'][$key] = ['puntuacion' => '5', 'observacion' => 'Referencia']; } }
        elseif ($type === 'evaluacion') { foreach (Marketplace\Bus\Repository\ProjectEvaluationRepository::ASPECTS as $key => $label) { $data['filas'][$key] = ['autoevaluacion' => '3', 'coevaluacion' => '4', 'comentarios' => 'Reflexión']; } }
        else { $data['filas'] = [['semana' => '1', 'evidencia' => 'Validación de instalación', 'tipo' => 'Tipo libre', 'fecha' => '2026-10-04', 'observaciones' => '']]; }
        $repository->save(['id' => $testerId, 'rol' => 'tester'], $data);
        foreach ([$definition['table'], $definition['child']] as $table) { $evaluationBefore[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll(); }
    }
    $incidentRepository = new Marketplace\Bus\Repository\IncidentRepository($pdo);
    $incidentId = $incidentRepository->save(['id' => $testerId, 'rol' => 'tester'], ['titulo' => 'Defecto de instalación', 'modulo' => 'Bus', 'severidad' => 'Media', 'prioridad' => 'Alta', 'descripcion' => 'Descripción', 'pasos_reproducir' => 'Buscar', 'resultado_esperado' => 'Respuesta', 'resultado_obtenido' => 'Error', 'estado' => 'Abierto', 'asignado_a' => 'Equipo Backend', 'caso_id' => $caseId]);
    if (Marketplace\Bus\Repository\IncidentRepository::code($incidentId) !== 'BUG-001') { throw new RuntimeException('Código inicial incorrecto'); }
    $incidentsBefore = $pdo->query('SELECT * FROM incidentes ORDER BY id')->fetchAll();
    $pdo->exec($sql);
    $pdo->exec('USE `' . $schemas['bus_meta'] . '`');
    if ($pdo->query('SELECT * FROM incidentes ORDER BY id')->fetchAll() !== $incidentsBefore) { throw new RuntimeException('Reimportación alteró incidentes'); }
    if ((int) $pdo->query('SELECT COUNT(*) FROM equivalencia_filas WHERE formulario_id = ' . $documentId)->fetchColumn() !== 1) { throw new RuntimeException('Reimportación alteró documentación de Caja Negra'); }
    if ($pdo->query('SELECT * FROM cobertura_metricas ORDER BY id')->fetchAll() !== $coverageBefore) { throw new RuntimeException('Reimportación alteró métricas de Caja Blanca'); }
    if ($pdo->query('SELECT * FROM planes_prueba ORDER BY id')->fetchAll() !== $planBefore || $pdo->query('SELECT * FROM plan_cronograma ORDER BY id')->fetchAll() !== $scheduleBefore) { throw new RuntimeException('Reimportación alteró planes o cronograma'); }
    foreach ($evaluationBefore as $table => $before) { if ($pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll() !== $before) { throw new RuntimeException('Reimportación alteró evaluación/evidencia: ' . $table); } }
    if ((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() !== 2 || (int) $pdo->query('SELECT COUNT(*) FROM search_sessions')->fetchColumn() !== 1 || $pdo->query("SELECT nombre FROM usuarios WHERE username = 'tester'")->fetchColumn() !== 'Nombre conservado') { throw new RuntimeException('La reimportación alteró datos existentes'); }
    $constraints = $pdo->prepare("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = ? AND constraint_name IN ('fk_search_cache_session', 'fk_casos_usuario')");
    $constraints->execute([$schemas['bus_meta']]);
    if ((int) $constraints->fetchColumn() !== 2) { throw new RuntimeException('Faltan las claves foráneas'); }
    echo "SQL completo verificado: instalación nueva, 37 productos, cuentas con hash, claves foráneas y reimportación sin pérdida de datos.\n";
} catch (Throwable $error) {
    $failed = true; fwrite(STDERR, 'Verificación SQL fallida: ' . $error->getMessage() . "\n");
} finally {
    foreach (array_reverse($owned) as $schema) { $pdo->exec('DROP DATABASE `' . $schema . '`'); }
    echo "Bases temporales de verificación eliminadas. Bases de la aplicación intactas.\n";
}
exit($failed ? 1 : 0);
