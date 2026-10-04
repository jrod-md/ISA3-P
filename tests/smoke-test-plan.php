<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Repository\TestPlanRepository;
use Marketplace\Shared\Support\Database;
$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost','127.0.0.1','::1'], true)) { fwrite(STDERR, "Solo pruebas locales.\n"); exit(1); }
final class PlanClient {
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
$repository = new TestPlanRepository($pdo);
$passed = 0; $failed = 0; $planIds = []; $userIds = []; $caseIds = [];
function check(bool $value, string $name): void {
    global $passed, $failed;
    if ($value) { $passed++; echo "PASS {$name}\n"; } else { $failed++; echo "FAIL {$name}\n"; }
}
function saved_id(array $response): int {
    global $planIds;
    if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $match)) { throw new RuntimeException('Guardado falló: ' . strip_tags($response['body'])); }
    $id = (int) $match[1]; if (!in_array($id, $planIds, true)) { $planIds[] = $id; } return $id;
}
function payload(string $version = '1.0'): array {
    return ['nombre_proyecto' => 'Marketplace Search Bus', 'version' => $version, 'responsable' => 'Coordinadora externa del equipo', 'fecha' => '2026-10-04',
        'alcance' => 'Bus y tres proveedores', 'objetivos' => 'Verificar filtros y persistencia', 'estrategia' => 'Caja Negra y Caja Blanca',
        'recursos' => 'Tester y entorno local', 'criterios_aceptacion' => 'Pruebas aprobadas', 'riesgos' => 'Proveedor no disponible',
        'cronograma' => [
            ['actividad' => 'Diseño de casos', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-07'],
            ['actividad' => 'Ejecución de pruebas', 'fecha_inicio' => '2026-10-08', 'fecha_fin' => '2026-10-12'],
            ['actividad' => 'Corrección', 'fecha_inicio' => '2026-10-13', 'fecha_fin' => '2026-10-15'],
        ]];
}
try {
    $protected = [];
    foreach (['usuarios','casos_prueba','formularios_prueba','equivalencia_filas','limite_filas','decision_reglas','decision_elementos','decision_valores','cobertura_metricas','planes_prueba','plan_cronograma'] as $table) {
        $order = $table === 'decision_valores' ? 'elemento_id, regla_id' : 'id';
        $protected[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $order)->fetchAll();
    }
    $admin = new PlanClient($base); $tester = new PlanClient($base); $other = new PlanClient($base); $anon = new PlanClient($base);
    check($admin->login('admin', 'demo-isa3-2026')['status'] === 200, 'Login Admin conservado');
    check($tester->login('tester', 'Tester123!')['status'] === 200, 'Login Tester conservado');
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn();
    $nonce = bin2hex(random_bytes(5)); $name = 'plan-' . $nonce;
    $pdo->prepare('INSERT INTO usuarios (username,nombre,correo,password,rol) VALUES (?,?,?,?,?)')->execute([$name, 'Tester temporal', $name . '@isa3.local', password_hash('Temporal123!', PASSWORD_DEFAULT), 'tester']);
    $otherId = (int) $pdo->lastInsertId(); $userIds[] = $otherId; $other->login($name, 'Temporal123!');
    $path = '/formularios/plan';
    foreach ([$path, $path . '/nuevo', $path . '/ver?id=1', $path . '/editar?id=1'] as $route) { check($anon->request($route)['status'] === 302, 'Ruta protegida ' . $route); }
    foreach (['/dashboard','/formularios'] as $route) {
        $body = $tester->request($route)['body'];
        check(str_contains($body, '10 de 10 disponibles') && substr_count($body, '>Pendiente<') === 0 && str_contains($body, $path), $route . ' muestra diez formularios disponibles');
    }
    $editor = $tester->request($path . '/nuevo')['body'];
    check(!str_contains($editor, 'name="caso_id"') && str_contains($editor, 'NIVEL PROYECTO'), 'Editor de plan sin caso obligatorio');
    foreach (TestPlanRepository::FIELDS as $field => $label) { check(str_contains($editor, 'name="' . $field . '"'), 'Campo presente: ' . $label); }
    $data = payload(); $data['usuario_id'] = $otherId; $data['caso_id'] = 999999999; $data['csrf'] = $tester->token($path . '/nuevo');
    $id = saved_id($tester->request($path . '/nuevo', $data));
    $record = $repository->find($id, ['rol' => 'admin']); $schedule = $repository->schedule($id);
    check($record && (int) $record['usuario_id'] === $testerId && $record['responsable'] === $data['responsable'], 'Creador de sesión y responsable independiente');
    check(!array_key_exists('caso_id', $record) && count($schedule) === 3, 'Plan independiente con cronograma relacional múltiple');
    check(array_column($schedule, 'actividad') === array_column($data['cronograma'], 'actividad') && array_column($schedule, 'orden') === [0,1,2], 'Cronograma conserva contenido y orden');
    $second = payload('1.1'); $second['csrf'] = $data['csrf']; $second['cronograma'] = [['actividad' => 'Una jornada', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-05']];
    $secondId = saved_id($tester->request($path . '/nuevo', $second));
    check($secondId !== $id && $repository->find($secondId, ['rol' => 'admin'])['version'] === '1.1', 'Distintas versiones del mismo proyecto permitidas');
    check(count($repository->schedule($secondId)) === 1, 'Inicio y fin iguales aceptados');
    foreach (TestPlanRepository::FIELDS as $field => $label) { check($record[$field] === $data[$field], 'Persistencia: ' . $label); }
    check($tester->request($path . '/ver?id=' . $id)['status'] === 200 && $admin->request($path . '/ver?id=' . $id)['status'] === 200, 'Consulta propia y Admin');
    $list = $tester->request($path)['body'];
    check(str_contains($list, '/ver?id=' . $id) && str_contains($list, '/ver?id=' . $secondId), 'Listado incluye versiones propias');
    check(!str_contains($other->request($path)['body'], '/ver?id=' . $id), 'Listado excluye planes ajenos');
    foreach (['ver','editar'] as $action) { check($other->request($path . '/' . $action . '?id=' . $id)['status'] === 404, 'Ownership ' . $action); }
    check($other->request($path . '/editar?id=' . $id, $data)['status'] === 404, 'POST ajeno no modifica plan');
    check($tester->request($path . '/eliminar?id=' . $id)['status'] === 403 && $tester->request($path . '/eliminar?id=' . $id, $data)['status'] === 403, 'Tester no elimina mediante GET ni POST');
    check($admin->request($path . '/eliminar?id=' . $id)['status'] === 200 && $repository->find($id, ['rol' => 'admin']) !== null, 'Confirmación GET no elimina');
    foreach (['nuevo','editar?id=' . $id,'eliminar?id=' . $id] as $action) {
        $client = str_starts_with($action, 'eliminar') ? $admin : $tester;
        $bad = $data; unset($bad['csrf']); check($client->request($path . '/' . $action, $bad)['status'] === 403, 'CSRF obligatorio: ' . $action);
        $bad['csrf'] = 'incorrecto'; check($client->request($path . '/' . $action, $bad)['status'] === 403, 'CSRF inválido: ' . $action);
    }
    $edit = payload('1.0 revisada'); $edit['csrf'] = $tester->token($path . '/editar?id=' . $id); $edit['usuario_id'] = $otherId;
    $edit['responsable'] = 'Nuevo responsable del proyecto'; $edit['nombre_proyecto'] = '<script>alert(1)</script>';
    $edit['cronograma'] = [$edit['cronograma'][2], $edit['cronograma'][0]];
    check(saved_id($tester->request($path . '/editar?id=' . $id, $edit)) === $id, 'Tester edita plan propio');
    $updated = $repository->find($id, ['rol' => 'admin']); $updatedSchedule = $repository->schedule($id);
    check((int) $updated['usuario_id'] === $testerId && $updated['responsable'] === $edit['responsable'], 'Edición cambia responsable y conserva creador');
    check(count($updatedSchedule) === 2 && $updatedSchedule[0]['actividad'] === 'Corrección' && array_column($updatedSchedule, 'orden') === [0,1], 'Edición sustituye y reordena cronograma');
    $body = $tester->request($path . '/ver?id=' . $id)['body'];
    check(str_contains($body, '&lt;script&gt;') && !str_contains($body, '<script>alert(1)</script>'), 'Texto escapado en consulta');
    $edit['csrf'] = $admin->token($path . '/editar?id=' . $id); $edit['recursos'] = 'Equipo actualizado por Admin';
    check(saved_id($admin->request($path . '/editar?id=' . $id, $edit)) === $id, 'Admin edita plan ajeno');
    $updated = $repository->find($id, ['rol' => 'admin']); $updatedSchedule = $repository->schedule($id);
    check((int) $updated['usuario_id'] === $testerId && $updated['recursos'] === $edit['recursos'], 'Admin conserva creador original');
    $count = (int) $pdo->query('SELECT COUNT(*) FROM planes_prueba')->fetchColumn();
    foreach (TestPlanRepository::FIELDS as $field => $label) {
        foreach (['vacío' => '', 'array' => ['invalido']] as $kind => $value) {
            $bad = $data; $bad[$field] = $value;
            check($tester->request($path . '/nuevo', $bad)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM planes_prueba')->fetchColumn() === $count, $label . ' ' . $kind . ' rechazado sin persistencia');
        }
    }
    foreach (['invertidas','imposible','sin_filas','exceso','campo_faltante','fila_array','actividad_larga','fecha_invalida','version_larga'] as $kind) {
        $bad = $data;
        switch ($kind) {
            case 'invertidas': $bad['cronograma'][0]['fecha_fin'] = '2026-10-04'; break;
            case 'imposible': $bad['cronograma'][0]['fecha_inicio'] = '2026-02-30'; break;
            case 'sin_filas': $bad['cronograma'] = []; break;
            case 'exceso': $bad['cronograma'] = array_fill(0, 31, $data['cronograma'][0]); break;
            case 'campo_faltante': unset($bad['cronograma'][0]['actividad']); break;
            case 'fila_array': $bad['cronograma'][0] = 'no es fila'; break;
            case 'actividad_larga': $bad['cronograma'][0]['actividad'] = str_repeat('á', 251); break;
            case 'fecha_invalida': $bad['fecha'] = '2025-02-29'; break;
            case 'version_larga': $bad['version'] = str_repeat('v', 51); break;
        }
        check($tester->request($path . '/nuevo', $bad)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM planes_prueba')->fetchColumn() === $count, 'Validación de ' . $kind . ' en creación');
        $bad['csrf'] = $tester->token($path . '/editar?id=' . $id);
        check($tester->request($path . '/editar?id=' . $id, $bad)['status'] === 200 && $repository->find($id, ['rol' => 'admin']) === $updated && $repository->schedule($id) === $updatedSchedule, 'Edición inválida de ' . $kind . ' preserva plan y cronograma');
    }
    $pdo->prepare("INSERT INTO casos_prueba (usuario_id,modulo,tecnica,subtecnica,objetivo,precondiciones,datos_entrada,pasos_ejecucion,resultado_esperado,resultado_obtenido,estado,observaciones) VALUES (?,?,'Caja Negra','Partición de equivalencia','Validar independencia','Login','Plan','Consultar','Sin vínculo','Sin vínculo','Éxito','Temporal')")->execute([$testerId, 'Caso temporal plan ' . $nonce]);
    $caseId = (int) $pdo->lastInsertId(); $caseIds[] = $caseId;
    check(!str_contains($admin->request('/casos/ver?id=' . $caseId)['body'], '/formularios/plan'), 'Plan no aparece como documento asociado a un caso');
    $deleteCase = '/casos/eliminar?id=' . $caseId;
    check($admin->request($deleteCase, ['csrf' => $admin->token($deleteCase)])['status'] === 302 && $repository->find($id, ['rol' => 'admin']) !== null && $repository->find($secondId, ['rol' => 'admin']) !== null, 'Eliminar un caso no elimina planes del proyecto');
    $noCase = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'planes_prueba' AND column_name = 'caso_id'")->fetchColumn();
    check((int) $noCase === 0, 'Tabla de planes sin caso_id');
    $headers = $pdo->query('SELECT * FROM planes_prueba ORDER BY id')->fetchAll(); $rows = $pdo->query('SELECT * FROM plan_cronograma ORDER BY id')->fetchAll();
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_6_migration.sql'));
    check($headers === $pdo->query('SELECT * FROM planes_prueba ORDER BY id')->fetchAll() && $rows === $pdo->query('SELECT * FROM plan_cronograma ORDER BY id')->fetchAll(), 'Migración reejecutable conserva planes y cronograma');
    $deletePath = $path . '/eliminar?id=' . $id;
    check($admin->request($deletePath, ['csrf' => $admin->token($deletePath)])['status'] === 302 && $repository->find($id, ['rol' => 'admin']) === null, 'Admin elimina con confirmación POST y CSRF');
    check(count($repository->schedule($id)) === 0 && $repository->find($secondId, ['rol' => 'admin']) !== null, 'Cascada de cronograma conserva otros planes');
    $adminData = payload('2.0'); $adminData['csrf'] = $admin->token($path . '/nuevo'); $adminData['usuario_id'] = $testerId;
    $adminId = saved_id($admin->request($path . '/nuevo', $adminData));
    check((int) $repository->find($adminId, ['rol' => 'admin'])['usuario_id'] !== $testerId && $tester->request($path . '/ver?id=' . $adminId)['status'] === 404, 'Admin crea plan con propiedad propia');
    check(str_contains($admin->request($path)['body'], '/ver?id=' . $adminId) && str_contains($admin->request($path)['body'], '/ver?id=' . $secondId), 'Admin lista planes de todos los creadores');
    check($tester->request($path . '/ver?id=999999999')['status'] === 404, 'Plan inexistente rechazado');
} catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n"); }
finally {
    foreach ($caseIds as $caseId) { $pdo->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$caseId]); }
    foreach ($planIds as $planId) { $pdo->prepare('DELETE FROM planes_prueba WHERE id = ?')->execute([$planId]); }
    foreach ($userIds as $userId) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$userId]); }
    foreach ($protected ?? [] as $table => $rows) {
        $order = $table === 'decision_valores' ? 'elemento_id, regla_id' : 'id';
        check($pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $order)->fetchAll() === $rows, 'Preservación de ' . $table);
    }
}
echo "Summary test-plan: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
