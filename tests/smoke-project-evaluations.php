<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Repository\ProjectEvaluationRepository as Evaluation;
use Marketplace\Shared\Support\Database;
$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) { fwrite(STDERR, "Solo pruebas locales.\n"); exit(1); }
final class EvaluationClient {
    private CurlHandle $handle;
    public function __construct(private string $base) {
        $this->handle = curl_init(); curl_setopt_array($this->handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]);
    }
    public function request(string $path, ?array $data = null, bool $json = false): array {
        $headers = [];
        curl_setopt_array($this->handle, [CURLOPT_URL => $this->base . $path, CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [], CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); } return strlen($line);
        }]);
        if ($data === null) { curl_setopt($this->handle, CURLOPT_HTTPGET, true); }
        else { curl_setopt($this->handle, CURLOPT_POST, true); curl_setopt($this->handle, CURLOPT_POSTFIELDS, $json ? json_encode($data) : http_build_query($data)); }
        $body = curl_exec($this->handle); if ($body === false) { throw new RuntimeException(curl_error($this->handle)); }
        return ['status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $path): string {
        $page = $this->request($path);
        if ($page['status'] !== 200 || !preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $match)) { throw new RuntimeException('CSRF no disponible: ' . $path); } return $match[1];
    }
    public function login(string $name, string $password): array { return $this->request('/api/admin/login', ['username' => $name, 'password' => $password], true); }
}
$pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')); $passed = 0; $failed = 0; $ids = []; $userId = null;
function check(bool $value, string $label): void { global $passed, $failed; if ($value) { $passed++; echo "PASS {$label}\n"; } else { $failed++; echo "FAIL {$label}\n"; } }
function saved_id(array $response, string $type): int {
    global $ids;
    if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $match)) { throw new RuntimeException('Guardado falló: ' . strip_tags($response['body'])); }
    $id = (int) $match[1]; $ids[$type][$id] = $id; return $id;
}
function payload(string $type): array {
    $data = ['titulo' => 'Evaluación del Bus', 'evaluado' => 'Estudiante distinto de la cuenta', 'evaluador' => 'Compañero externo', 'fecha' => '2026-10-04', 'observaciones' => 'Valoración académica', 'filas' => []];
    if ($type === 'rubrica') { foreach (Evaluation::RUBRIC as $key => $reference) { $data['filas'][$key] = ['puntuacion' => '5', 'observacion' => 'Observación de ' . $reference[0]]; } }
    elseif ($type === 'evaluacion') { foreach (Evaluation::ASPECTS as $key => $label) { $data['filas'][$key] = ['autoevaluacion' => '3', 'coevaluacion' => '4', 'comentarios' => 'Reflexión de ' . $label]; } $data['filas']['conceptos']['autoevaluacion'] = '4'; $data['filas']['plazos']['coevaluacion'] = '5'; }
    else { $data['filas'] = [
        ['semana' => '1', 'evidencia' => 'Reporte de filtros del Bus', 'tipo' => 'Bitácora personalizada', 'fecha' => '2026-10-04', 'observaciones' => 'Texto libre'],
        ['semana' => '52', 'evidencia' => 'Análisis propio', 'tipo' => 'Otro tipo libre', 'fecha' => '2026-10-05', 'observaciones' => ''],
        ['semana' => '2', 'evidencia' => 'Pruebas de regresión', 'tipo' => 'Documento', 'fecha' => '2026-10-06', 'observaciones' => 'Sin adjunto'],
    ]; }
    return $data;
}
try {
    $protected = [];
    foreach (['usuarios', 'casos_prueba', 'formularios_prueba', 'equivalencia_filas', 'limite_filas', 'decision_reglas', 'decision_elementos', 'decision_valores', 'cobertura_metricas', 'planes_prueba', 'plan_cronograma', 'rubricas', 'rubrica_criterios', 'evaluaciones_pares', 'evaluacion_aspectos', 'portafolios', 'portafolio_evidencias'] as $table) {
        $protected[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($table === 'decision_valores' ? 'elemento_id, regla_id' : 'id'))->fetchAll();
    }
    $admin = new EvaluationClient($base); $tester = new EvaluationClient($base); $other = new EvaluationClient($base); $anon = new EvaluationClient($base);
    check($admin->login('admin', 'demo-isa3-2026')['status'] === 200, 'Login Admin conservado'); check($tester->login('tester', 'Tester123!')['status'] === 200, 'Login Tester conservado');
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn();
    $nonce = 'eval-' . bin2hex(random_bytes(5));
    $pdo->prepare('INSERT INTO usuarios (username,nombre,correo,password,rol) VALUES (?,?,?,?,?)')->execute([$nonce, 'Tester temporal', $nonce . '@isa3.local', password_hash('Temporal123!', PASSWORD_DEFAULT), 'tester']);
    $userId = (int) $pdo->lastInsertId(); $other->login($nonce, 'Temporal123!');
    foreach (['/dashboard', '/formularios'] as $route) {
        $body = $tester->request($route)['body']; check(str_contains($body, '10 de 10 disponibles') && substr_count($body, '>Pendiente<') === 0, $route . ' diez disponibles y ninguno pendiente');
        foreach (array_keys(Evaluation::TYPES) as $type) { check(str_contains($body, '/formularios/' . $type), 'Catálogo enlaza ' . $type); }
    }
    foreach (Evaluation::TYPES as $type => $definition) {
        $repository = new Evaluation($pdo, $type); $path = '/formularios/' . $type;
        foreach (['', '/nuevo', '/ver?id=1', '/editar?id=1', '/eliminar?id=1'] as $suffix) { check($anon->request($path . $suffix)['status'] === 302, $type . ' protege ' . $suffix); }
        check($tester->request($path, [])['status'] === 405, $type . ' rechaza POST al listado');
        $editor = $tester->request($path . '/nuevo')['body'];
        check(!str_contains($editor, 'name="caso_id"') && str_contains($editor, 'NIVEL PROYECTO'), $type . ' independiente de caso');
        foreach ($definition['fields'] as $field => $label) { check(str_contains($editor, 'name="' . $field . '"'), $type . ' campo ' . $label); }
        if ($type === 'rubrica') {
            check(substr_count($editor, 'data-score="puntuacion"') === 6 && !str_contains($editor, 'value="5" selected'), 'Seis criterios sin puntuación seleccionada por defecto');
            foreach (Evaluation::RUBRIC as $reference) { foreach ($reference as $text) { check(str_contains($editor, htmlspecialchars($text, ENT_QUOTES, 'UTF-8')), 'Referencia visible: ' . $text); } }
        } elseif ($type === 'evaluacion') { check(substr_count($editor, 'data-score=') === 12, 'Seis aspectos con dos valoraciones'); foreach (Evaluation::ASPECTS as $label) { check(str_contains($editor, $label), 'Aspecto visible: ' . $label); } }
        else { check(str_contains($editor, 'list="evidence-types"') && !str_contains($editor, 'type="file"'), 'Portafolio tipo libre con sugerencias y sin archivos obligatorios'); }
        $data = payload($type); $data['usuario_id'] = $userId; $data['caso_id'] = 999999999; $data['total'] = 999; $data['promedio_auto'] = 99; $data['promedio_co'] = -10; $data['csrf'] = $tester->token($path . '/nuevo');
        $id = saved_id($tester->request($path . '/nuevo', $data), $type); $record = $repository->find($id, ['rol' => 'admin']); $rows = $repository->rows($id);
        check((int) $record['usuario_id'] === $testerId && !array_key_exists('caso_id', $record), $type . ' propietario desde sesión, no desde POST');
        foreach ($definition['fields'] as $field => $label) { check($record[$field] === $data[$field], $type . ' persistencia ' . $field); }
        check(count($rows) === ($type === 'portafolio' ? 3 : 6) && array_column($rows, 'orden') === range(0, count($rows) - 1), $type . ' filas relacionales ordenadas');
        if ($type === 'rubrica') { check((int) $record['total'] === 30, 'Total máximo 30 servidor ignora 999 adulterado'); check(array_column($rows, 'criterio') === array_keys(Evaluation::RUBRIC), 'Exactamente seis criterios fijos'); check($rows[0]['observacion'] === $data['filas']['diseno']['observacion'], 'Observación por criterio guardada'); }
        elseif ($type === 'evaluacion') { check((float) $record['promedio_auto'] === 3.17 && (float) $record['promedio_co'] === 4.17, 'Promedios servidor con redondeo, ignora adulteración'); check(array_column($rows, 'aspecto') === array_keys(Evaluation::ASPECTS), 'Exactamente seis aspectos fijos'); check($rows[0]['comentarios'] === $data['filas']['conceptos']['comentarios'], 'Comentarios persistidos'); }
        else { check(array_column($rows, 'evidencia') === array_column($data['filas'], 'evidencia') && $rows[0]['tipo'] === 'Bitácora personalizada', 'Evidencias propias y tipo libre'); check($rows[0]['semana'] === 1 && $rows[1]['semana'] === 52, 'Semanas límites aceptadas'); }
        check($tester->request($path . '/ver?id=' . $id)['status'] === 200 && $admin->request($path . '/ver?id=' . $id)['status'] === 200, $type . ' consulta propia y Admin');
        check(str_contains($tester->request($path)['body'], '/ver?id=' . $id) && str_contains($admin->request($path)['body'], '/ver?id=' . $id), $type . ' listados propio y Admin');
        check(!str_contains($other->request($path)['body'], '/ver?id=' . $id), $type . ' listado no muestra ajenos');
        foreach (['ver', 'editar'] as $action) { check($other->request($path . '/' . $action . '?id=' . $id)['status'] === 404, $type . ' ownership GET ' . $action); }
        check($other->request($path . '/editar?id=' . $id, $data)['status'] === 404, $type . ' ownership POST');
        check($tester->request($path . '/eliminar?id=' . $id)['status'] === 403 && $tester->request($path . '/eliminar?id=' . $id, $data)['status'] === 403, $type . ' Tester no elimina GET/POST');
        check($admin->request($path . '/eliminar?id=' . $id)['status'] === 200 && $repository->find($id, ['rol' => 'admin']) !== null, $type . ' confirmación GET conserva documento');
        foreach (['nuevo', 'editar?id=' . $id, 'eliminar?id=' . $id] as $action) {
            $client = str_starts_with($action, 'eliminar') ? $admin : $tester;
            $bad = $data; unset($bad['csrf']); check($client->request($path . '/' . $action, $bad)['status'] === 403, $type . ' CSRF ausente ' . $action);
            $bad['csrf'] = 'incorrecto'; check($client->request($path . '/' . $action, $bad)['status'] === 403, $type . ' CSRF incorrecto ' . $action);
        }
        $edit = $data; $edit[$type === 'portafolio' ? 'titulo' : 'evaluado'] = '<script>alert(1)</script>';
        if ($type === 'rubrica') { foreach ($edit['filas'] as &$row) { $row['puntuacion'] = '1'; $row['observacion'] = ''; } unset($row); $edit['observaciones'] = ''; }
        elseif ($type === 'evaluacion') { foreach ($edit['filas'] as &$row) { $row['autoevaluacion'] = '5'; $row['coevaluacion'] = '1'; $row['comentarios'] = ''; } unset($row); $edit['evaluador'] = ''; }
        else { $edit['filas'] = [$data['filas'][2], $data['filas'][0]]; $edit['filas'][0]['observaciones'] = '<img src=x onerror=alert(1)>'; }
        check(saved_id($tester->request($path . '/editar?id=' . $id, $edit), $type) === $id, $type . ' Tester edita propio');
        $updated = $repository->find($id, ['rol' => 'admin']); $updatedRows = $repository->rows($id);
        check((int) $updated['usuario_id'] === $testerId, $type . ' creador inmutable al editar');
        if ($type === 'rubrica') { check((int) $updated['total'] === 6 && array_sum(array_column($updatedRows, 'puntuacion')) === 6, 'Edición recalcula total mínimo 6'); }
        elseif ($type === 'evaluacion') { check((float) $updated['promedio_auto'] === 5.0 && (float) $updated['promedio_co'] === 1.0, 'Edición recalcula promedios extremos'); }
        else { check(count($updatedRows) === 2 && array_column($updatedRows, 'evidencia') === array_column($edit['filas'], 'evidencia') && array_column($updatedRows, 'orden') === [0,1], 'Edición elimina y reordena filas'); }
        $body = $tester->request($path . '/ver?id=' . $id)['body']; check(str_contains($body, '&lt;script&gt;') && !str_contains($body, '<script>alert(1)</script>'), $type . ' escapa HTML en consulta');
        if ($type === 'portafolio') { check(str_contains($body, '&lt;img') && !str_contains($body, '<img src=x'), 'Escapa observaciones de evidencia'); }
        $edit['csrf'] = $admin->token($path . '/editar?id=' . $id); check(saved_id($admin->request($path . '/editar?id=' . $id, $edit), $type) === $id && (int) $repository->find($id, ['rol' => 'admin'])['usuario_id'] === $testerId, $type . ' Admin edita ajeno sin transferir propiedad');
        $updated = $repository->find($id, ['rol' => 'admin']); $updatedRows = $repository->rows($id); $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $definition['table'])->fetchColumn();
        $invalid = [];
        foreach ($definition['fields'] as $field => $label) {
            if (!in_array($field, ['observaciones', 'evaluador'], true)) { $bad = $data; $bad[$field] = ''; $invalid['vacío ' . $field] = $bad; }
            $bad = $data; $bad[$field] = ['mal']; $invalid['array ' . $field] = $bad;
            if ($field !== 'fecha') { $bad = $data; $bad[$field] = str_repeat('á', Evaluation::limit($field) + 1); $invalid['largo ' . $field] = $bad; }
        }
        $bad = $data; $bad['filas'] = []; $invalid['sin filas'] = $bad;
        $bad = $data; $bad['filas'] = 'mal'; $invalid['filas no array'] = $bad;
        if ($type !== 'portafolio') {
            $key = array_key_first($data['filas']); $scoreFields = $type === 'rubrica' ? ['puntuacion'] : ['autoevaluacion', 'coevaluacion'];
            foreach ($scoreFields as $field) { foreach (['0', '6', '-1', '2.5', '', '01', '1e0', ['mal']] as $index => $value) { $bad = $data; $bad['filas'][$key][$field] = $value; $invalid[$field . ' fuera de escala ' . $index] = $bad; } }
            $bad = $data; unset($bad['filas'][$key]); $invalid['criterio/aspecto ausente'] = $bad;
            $bad = $data; $bad['filas']['inventado'] = $data['filas'][$key]; $invalid['criterio/aspecto extra'] = $bad;
            $bad = $data; $bad['filas']['inventado'] = $bad['filas'][$key]; unset($bad['filas'][$key]); $invalid['criterio/aspecto sustituido'] = $bad;
            $bad = $data; $bad['filas'][$key] = 'mal'; $invalid['fila mal formada'] = $bad;
            $comment = $type === 'rubrica' ? 'observacion' : 'comentarios';
            foreach ([['mal'], str_repeat('á', 5001)] as $index => $value) { $bad = $data; $bad['filas'][$key][$comment] = $value; $invalid['comentario inválido ' . $index] = $bad; }
            $bad = $data; $bad['fecha'] = '2025-02-29'; $invalid['fecha imposible'] = $bad;
        } else {
            foreach (['0', '-1', '53', '1.5', '1e0', ['mal']] as $index => $value) { $bad = $data; $bad['filas'][0]['semana'] = $value; $invalid['semana inválida ' . $index] = $bad; }
            foreach (['2026-02-30', '04/10/2026', '0999-10-04', ''] as $index => $date) { $bad = $data; $bad['filas'][0]['fecha'] = $date; $invalid['fecha inválida ' . $index] = $bad; }
            foreach (['evidencia', 'tipo'] as $field) { foreach (['', ['mal'], str_repeat('á', 251)] as $index => $value) { $bad = $data; $bad['filas'][0][$field] = $value; $invalid[$field . ' inválido ' . $index] = $bad; } }
            $bad = $data; $bad['filas'][0] = 'mal'; $invalid['fila mal formada'] = $bad;
            $bad = $data; $bad['filas'] = array_fill(0,31,$data['filas'][0]); $invalid['31 filas'] = $bad;
            $bad = $data; $bad['filas'][0]['observaciones'] = ['mal']; $invalid['observaciones array'] = $bad;
        }
        foreach ($invalid as $label => $bad) {
            $bad['csrf'] = $tester->token($path . '/nuevo'); check($tester->request($path . '/nuevo', $bad)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM ' . $definition['table'])->fetchColumn() === $count, $type . ' creación rechaza ' . $label);
            $bad['csrf'] = $tester->token($path . '/editar?id=' . $id); check($tester->request($path . '/editar?id=' . $id, $bad)['status'] === 200 && $repository->find($id, ['rol' => 'admin']) === $updated && $repository->rows($id) === $updatedRows, $type . ' edición inválida preserva todo: ' . $label);
        }
        $second = payload($type); if ($type === 'portafolio') { $second['filas'] = array_fill(0,30,$second['filas'][0]); }
        $second['csrf'] = $data['csrf']; $secondId = saved_id($tester->request($path . '/nuevo', $second), $type);
        check($secondId !== $id && count($repository->rows($secondId)) === ($type === 'portafolio' ? 30 : 6), $type . ' múltiples documentos y límite máximo de filas');
        $adminData = payload($type); $adminData['csrf'] = $admin->token($path . '/nuevo'); $adminId = saved_id($admin->request($path . '/nuevo', $adminData), $type);
        check((int) $repository->find($adminId, ['rol' => 'admin'])['usuario_id'] !== $testerId && $tester->request($path . '/ver?id=' . $adminId)['status'] === 404, $type . ' Admin crea y Tester no accede');
        check(str_contains($admin->request($path)['body'], '/ver?id=' . $secondId) && str_contains($admin->request($path)['body'], '/ver?id=' . $adminId), $type . ' Admin lista todos los creadores');
        $headers = $pdo->query('SELECT * FROM ' . $definition['table'] . ' ORDER BY id')->fetchAll(); $beforeRows = $pdo->query('SELECT * FROM ' . $definition['child'] . ' ORDER BY id')->fetchAll();
        $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formularios_7_9_migration.sql'));
        check($pdo->query('SELECT * FROM ' . $definition['table'] . ' ORDER BY id')->fetchAll() === $headers && $pdo->query('SELECT * FROM ' . $definition['child'] . ' ORDER BY id')->fetchAll() === $beforeRows, $type . ' reimportación conserva cabecera y filas');
        $noCase = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'caso_id'"); $noCase->execute([$definition['table']]); check((int) $noCase->fetchColumn() === 0, $type . ' tabla sin caso_id');
        $delete = $path . '/eliminar?id=' . $id; check($admin->request($delete, ['csrf' => $admin->token($delete)])['status'] === 302 && $repository->find($id, ['rol' => 'admin']) === null && count($repository->rows($id)) === 0, $type . ' Admin elimina POST CSRF con cascada');
        check($repository->find($secondId, ['rol' => 'admin']) !== null && $repository->find($adminId, ['rol' => 'admin']) !== null, $type . ' cascada no afecta otros documentos');
        check($admin->request($path . '/ver?id=999999999')['status'] === 404, $type . ' inexistente 404');
    }
} catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n"); }
finally {
    foreach ($ids as $type => $list) { foreach ($list as $id) { $pdo->prepare('DELETE FROM ' . Evaluation::TYPES[$type]['table'] . ' WHERE id = ?')->execute([$id]); } }
    if ($userId !== null) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$userId]); }
    foreach ($protected ?? [] as $table => $before) { check($pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($table === 'decision_valores' ? 'elemento_id, regla_id' : 'id'))->fetchAll() === $before, 'Datos originales conservados: ' . $table); }
}
echo "Summary project-evaluations: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
