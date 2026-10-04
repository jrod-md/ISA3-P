<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Repository\BlackBoxRepository;
use Marketplace\Bus\Repository\CoverageMetrics;
use Marketplace\Shared\Support\Database;

$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) { fwrite(STDERR, "Solo se admiten pruebas locales.\n"); exit(1); }
final class CoverageClient {
    private CurlHandle $handle;
    public function __construct(private string $base) {
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]);
    }
    public function request(string $path, ?array $data = null, bool $json = false): array {
        $headers = [];
        curl_setopt_array($this->handle, [CURLOPT_URL => $this->base . $path, CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [], CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); } return strlen($line);
        }]);
        if ($data === null) { curl_setopt($this->handle, CURLOPT_HTTPGET, true); }
        else { curl_setopt($this->handle, CURLOPT_POST, true); curl_setopt($this->handle, CURLOPT_POSTFIELDS, $json ? json_encode($data) : http_build_query($data)); }
        $body = curl_exec($this->handle);
        if ($body === false) { throw new RuntimeException(curl_error($this->handle)); }
        return ['status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $path): string {
        $page = $this->request($path);
        if ($page['status'] !== 200 || !preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $match)) { throw new RuntimeException('CSRF no disponible: ' . $path); }
        return $match[1];
    }
    public function login(string $name, string $password): array { return $this->request('/api/admin/login', ['username' => $name, 'password' => $password], true); }
}
$pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'));
$repository = new BlackBoxRepository($pdo);
$passed = 0; $failed = 0; $caseIds = []; $userIds = [];
function check(bool $value, string $name): void {
    global $passed, $failed;
    if ($value) { $passed++; echo "PASS {$name}\n"; } else { $failed++; echo "FAIL {$name}\n"; }
}
function saved_id(array $response): int {
    if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $match)) { throw new RuntimeException('Guardado falló: ' . strip_tags($response['body'])); }
    return (int) $match[1];
}
function payload(): array {
    $rows = []; $values = [[0, 0], [3, 1], [3, 2], [100, 100], [4294967295, 2147483647]];
    foreach (array_keys(CoverageMetrics::METRICS) as $order => $metric) {
        $rows[$metric] = ['total' => (string) $values[$order][0], 'cubiertos' => (string) $values[$order][1], 'porcentaje' => '999', 'herramienta' => $order === 4 ? 'Herramienta propia del tester' : 'Manual'];
    }
    return ['metricas' => $rows];
}
try {
    $protected = [];
    foreach (['usuarios', 'casos_prueba', 'formularios_prueba', 'equivalencia_filas', 'limite_filas', 'decision_reglas', 'decision_elementos', 'decision_valores', 'cobertura_metricas'] as $table) {
        $order = $table === 'decision_valores' ? 'elemento_id, regla_id' : 'id';
        $protected[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $order)->fetchAll();
    }
    $admin = new CoverageClient($base); $tester = new CoverageClient($base); $other = new CoverageClient($base); $anon = new CoverageClient($base);
    check($admin->login('admin', 'demo-isa3-2026')['status'] === 200, 'Admin autenticado');
    check($tester->login('tester', 'Tester123!')['status'] === 200, 'Tester autenticado');
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn();
    $nonce = bin2hex(random_bytes(5)); $name = 'coverage-' . $nonce;
    $pdo->prepare('INSERT INTO usuarios (username,nombre,correo,password,rol) VALUES (?,?,?,?,?)')->execute([$name, 'Tester temporal', $name . '@isa3.local', password_hash('Temporal123!', PASSWORD_DEFAULT), 'tester']);
    $otherId = (int) $pdo->lastInsertId(); $userIds[] = $otherId; $other->login($name, 'Temporal123!');
    foreach ([$testerId, $otherId] as $owner) {
        $pdo->prepare("INSERT INTO casos_prueba (usuario_id,modulo,tecnica,subtecnica,objetivo,precondiciones,datos_entrada,pasos_ejecucion,resultado_esperado,resultado_obtenido,estado,observaciones) VALUES (?,?,'Caja Blanca','Cobertura de sentencias','Validar cobertura','Login','Conteos','Registrar','Datos guardados','Datos guardados','Éxito','Temporal')")->execute([$owner, 'Smoke cobertura ' . $nonce]);
        $caseIds[] = (int) $pdo->lastInsertId();
    }
    [$ownCase, $foreignCase] = $caseIds; $path = '/formularios/cobertura';
    check($anon->request($path)['status'] === 302 && $anon->request($path . '/nuevo')['status'] === 302, 'Cobertura requiere sesión');
    foreach (['/dashboard', '/formularios'] as $route) {
        $body = $tester->request($route)['body'];
        check(str_contains($body, '9 de 10 disponibles') && substr_count($body, '>Pendiente<') === 1 && str_contains($body, $path), $route . ' muestra nueve disponibles');
    }
    $editor = $tester->request($path . '/nuevo?caso_id=' . $ownCase)['body'];
    foreach (CoverageMetrics::METRICS as $label) { check(str_contains($editor, $label), 'Métrica fija: ' . $label); }
    $data = payload(); $data['caso_id'] = $ownCase; $data['usuario_id'] = $otherId; $data['tipo'] = 'decision'; $data['csrf'] = $tester->token($path . '/nuevo');
    $id = saved_id($tester->request($path . '/nuevo', $data));
    $record = $repository->find($id, 'cobertura', ['rol' => 'admin']);
    check((int) $record['caso_id'] === $ownCase && (int) $record['usuario_id'] === $testerId && $record['tipo'] === 'cobertura', 'Relación con caso y usuario determinados por servidor');
    $metrics = $repository->contents($record)['metricas'];
    check(count($metrics) === 5 && (int) $pdo->query('SELECT COUNT(*) FROM cobertura_metricas WHERE formulario_id = ' . $id)->fetchColumn() === 5, 'Cinco métricas persistidas relacionalmente');
    foreach (['sentencia' => 0, 'decision' => 33.33, 'condicion' => 66.67, 'caminos' => 100, 'bucles' => 50] as $metric => $expected) { check((float) $metrics[$metric]['porcentaje'] === (float) $expected, 'Porcentaje calculado: ' . $metric); }
    check($metrics['bucles']['herramienta'] === 'Herramienta propia del tester', 'Herramienta libre, sin lista cerrada');
    check($tester->request($path . '/ver?id=' . $id)['status'] === 200 && str_contains($tester->request($path . '/ver?id=' . $id)['body'], '33.33 %'), 'Consulta propia con dos decimales');
    check($admin->request($path . '/ver?id=' . $id)['status'] === 200, 'Admin consulta documentación ajena');
    check(str_contains($tester->request($path)['body'], '/ver?id=' . $id), 'Listado propio');
    check(!str_contains($other->request($path)['body'], '/ver?id=' . $id), 'Listado excluye documentos ajenos');
    foreach (['ver', 'editar'] as $action) { check($other->request($path . '/' . $action . '?id=' . $id)['status'] === 404, 'Ownership de ' . $action); }
    check($other->request($path . '/editar?id=' . $id, $data)['status'] === 404, 'POST de edición ajena rechazado');
    check($tester->request($path . '/eliminar?id=' . $id)['status'] === 403, 'Tester no abre eliminación');
    check($tester->request($path . '/eliminar?id=' . $id, ['csrf' => $data['csrf']])['status'] === 403, 'Tester no elimina con POST');
    check($admin->request($path . '/eliminar?id=' . $id)['status'] === 200 && $repository->find($id, 'cobertura', ['rol' => 'admin']) !== null, 'GET confirma sin eliminar');
    foreach (['nuevo', 'editar?id=' . $id, 'eliminar?id=' . $id] as $action) {
        $client = str_starts_with($action, 'eliminar') ? $admin : $tester; $bad = $data; unset($bad['csrf']);
        check($client->request($path . '/' . $action, $bad)['status'] === 403, 'CSRF obligatorio: ' . $action);
    }
    $invalidCase = $data; $invalidCase['caso_id'] = $foreignCase;
    $beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn();
    check($tester->request($path . '/nuevo', $invalidCase)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $beforeCount, 'Tester no crea sobre caso ajeno');
    $invalidCase['caso_id'] = 999999999;
    $invalidCase['csrf'] = $admin->token($path . '/nuevo');
    check($admin->request($path . '/nuevo', $invalidCase)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $beforeCount, 'Caso inexistente rechazado');
    $edit = $data; $edit['metricas']['sentencia'] = ['total' => '8', 'cubiertos' => '3', 'porcentaje' => ['falso'], 'herramienta' => '<script>alert(1)</script>'];
    $edit['caso_id'] = $foreignCase; $edit['usuario_id'] = $otherId;
    $edit['csrf'] = $tester->token($path . '/editar?id=' . $id);
    check(saved_id($tester->request($path . '/editar?id=' . $id, $edit)) === $id, 'Tester edita documento propio');
    $record = $repository->find($id, 'cobertura', ['rol' => 'admin']); $updated = $repository->contents($record)['metricas'];
    check((int) $record['caso_id'] === $ownCase && (int) $record['usuario_id'] === $testerId, 'Edición conserva caso y propietario');
    check((float) $updated['sentencia']['porcentaje'] === 37.5 && count($updated) === 5, 'Porcentaje adulterado ignorado y recalculado en servidor');
    $body = $tester->request($path . '/ver?id=' . $id)['body'];
    check(str_contains($body, '&lt;script&gt;') && !str_contains($body, '<script>alert(1)</script>'), 'Herramienta escapada en consulta');
    $edit['csrf'] = $admin->token($path . '/editar?id=' . $id); $edit['metricas']['sentencia']['cubiertos'] = '4';
    check(saved_id($admin->request($path . '/editar?id=' . $id, $edit)) === $id, 'Admin edita documento ajeno');
    $record = $repository->find($id, 'cobertura', ['rol' => 'admin']);
    check((int) $record['usuario_id'] === $testerId && (int) $record['caso_id'] === $ownCase && (float) $repository->contents($record)['metricas']['sentencia']['porcentaje'] === 50.0, 'Admin conserva relaciones y persiste conteos');
    $snapshot = $repository->contents($record);
    $invalidValues = ['', '-1', '1.5', '1e3', 'abc', '4294967296', ['invalido']];
    foreach (['total', 'cubiertos'] as $field) {
        foreach ($invalidValues as $index => $value) {
            $bad = $data; $bad['metricas']['decision'][$field] = $value;
            check($tester->request($path . '/nuevo', $bad)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $beforeCount, $field . ' inválido #' . $index . ' no persiste');
        }
    }
    foreach (['excede', 'cero', 'falta', 'extra', 'duplicada', 'herramienta', 'herramienta_array', 'herramienta_larga', 'matriz_array'] as $kind) {
        $bad = $data;
        switch ($kind) {
            case 'excede': $bad['metricas']['decision']['cubiertos'] = '4'; break;
            case 'cero': $bad['metricas']['sentencia']['cubiertos'] = '1'; break;
            case 'falta': unset($bad['metricas']['bucles']); break;
            case 'extra': $bad['metricas']['otra'] = $bad['metricas']['bucles']; break;
            case 'duplicada': $bad['metricas']['otra'] = $bad['metricas']['bucles']; unset($bad['metricas']['bucles']); break;
            case 'herramienta': $bad['metricas']['bucles']['herramienta'] = ' '; break;
            case 'herramienta_array': $bad['metricas']['bucles']['herramienta'] = ['x']; break;
            case 'herramienta_larga': $bad['metricas']['bucles']['herramienta'] = str_repeat('á', 151); break;
            case 'matriz_array': $bad['metricas'] = 'invalido'; break;
        }
        check($tester->request($path . '/nuevo', $bad)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $beforeCount, 'Validación de ' . $kind . ' sin inserción parcial');
        $bad['csrf'] = $tester->token($path . '/editar?id=' . $id);
        check($tester->request($path . '/editar?id=' . $id, $bad)['status'] === 200 && $repository->contents($record) === $snapshot, 'Edición inválida de ' . $kind . ' preserva registro');
    }
    check(str_contains($tester->request('/casos/ver?id=' . $ownCase)['body'], $path . '/ver?id=' . $id), 'Cobertura accesible desde ficha del caso');
    $headersBefore = $pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll();
    $metricsBefore = $pdo->query('SELECT * FROM cobertura_metricas ORDER BY id')->fetchAll();
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_5_migration.sql'));
    check($pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll() === $headersBefore && $pdo->query('SELECT * FROM cobertura_metricas ORDER BY id')->fetchAll() === $metricsBefore, 'Migración reejecutable conserva documentación');
    $deletePath = $path . '/eliminar?id=' . $id;
    check($admin->request($deletePath, ['csrf' => $admin->token($deletePath)])['status'] === 302 && $repository->find($id, 'cobertura', ['rol' => 'admin']) === null, 'Admin elimina con POST y CSRF');
    check((int) $pdo->query('SELECT COUNT(*) FROM cobertura_metricas WHERE formulario_id = ' . $id)->fetchColumn() === 0 && (int) $pdo->query('SELECT COUNT(*) FROM casos_prueba WHERE id = ' . $ownCase)->fetchColumn() === 1, 'Eliminación cascada de métricas conserva caso');
    $adminData = payload(); $adminData['caso_id'] = $foreignCase; $adminData['csrf'] = $admin->token($path . '/nuevo');
    $adminId = saved_id($admin->request($path . '/nuevo', $adminData));
    check($repository->find($adminId, 'cobertura', ['rol' => 'admin'])['usuario_id'] !== $otherId && $other->request($path . '/ver?id=' . $adminId)['status'] === 404, 'Admin crea sobre caso ajeno; propietario del caso no recibe documento ajeno');
    $deleteCase = '/casos/eliminar?id=' . $foreignCase;
    check($admin->request($deleteCase, ['csrf' => $admin->token($deleteCase)])['status'] === 302 && (int) $pdo->query('SELECT COUNT(*) FROM cobertura_metricas WHERE formulario_id = ' . $adminId)->fetchColumn() === 0, 'Eliminar caso elimina sus métricas en cascada');
    check($tester->request('/formularios/cobertura/ver?id=' . $adminId)['status'] === 404, 'Documento eliminado no disponible');
} catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n"); }
finally {
    foreach ($caseIds as $caseId) { $pdo->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$caseId]); }
    foreach ($userIds as $userId) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$userId]); }
    foreach ($protected ?? [] as $table => $rows) {
        $order = $table === 'decision_valores' ? 'elemento_id, regla_id' : 'id';
        check($pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $order)->fetchAll() === $rows, 'Preservación de ' . $table);
    }
}
echo "Summary coverage: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
