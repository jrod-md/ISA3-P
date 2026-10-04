<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Repository\BlackBoxRepository;
use Marketplace\Shared\Support\Database;

$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1'], true)) { fwrite(STDERR, "Solo se admiten pruebas locales.\n"); exit(1); }
final class BlackBoxClient {
    private CurlHandle $handle;
    public function __construct(private string $base) {
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]);
    }
    public function request(string $path, ?array $data = null, bool $json = false): array {
        $headers = [];
        curl_setopt_array($this->handle, [CURLOPT_URL => $this->base . $path, CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [], CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $headers[strtolower(trim($name))] = trim($value); } return strlen($line);
        }]);
        if ($data === null) { curl_setopt($this->handle, CURLOPT_HTTPGET, true); }
        else { curl_setopt($this->handle, CURLOPT_POST, true); curl_setopt($this->handle, CURLOPT_POSTFIELDS, $json ? json_encode($data) : http_build_query($data)); }
        $body = curl_exec($this->handle);
        if ($body === false) { throw new RuntimeException(curl_error($this->handle)); }
        return ['status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $path): string {
        $page = $this->request($path);
        if ($page['status'] !== 200 || !preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $matches)) { throw new RuntimeException('CSRF no disponible en ' . $path); }
        return $matches[1];
    }
    public function login(string $name, string $password): array { return $this->request('/api/admin/login', ['username' => $name, 'password' => $password], true); }
}
$passed = 0; $failed = 0; $caseIds = []; $userIds = [];
$pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'));
$repository = new BlackBoxRepository($pdo);
function check(bool $condition, string $name): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS {$name}\n"; } else { $failed++; echo "FAIL {$name}\n"; }
}
function save_id(array $response): int {
    if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $matches)) { throw new RuntimeException('Guardado falló: ' . strip_tags($response['body'])); }
    return (int) $matches[1];
}
function fixture_data(string $type): array {
    if ($type !== 'decision') {
        $rows = [];
        foreach (['Precio mínimo', 'Categoría'] as $label) {
            $row = array_fill_keys(array_keys(BlackBoxRepository::FIELDS[$type]), 'Valores válidos de prueba');
            $row['campo'] = $label; $row['resultado_esperado'] = 'Aceptar valores válidos; rechazar inválidos.';
            if ($type === 'limites') { $row['valor_minimo'] = '0'; $row['valor_maximo'] = '900'; $row['valores_limite'] = '-1, 0, 1, 899, 900, 901'; }
            $rows[] = $row;
        }
        return ['filas' => $rows];
    }
    return ['reglas' => ['Regla 1', 'Regla 2', 'Regla 3', 'Regla 4', 'Regla 5'],
        'condiciones' => [
            ['texto' => 'Precio válido', 'valores' => ['V', 'V', 'F', 'F', '-']],
            ['texto' => 'Proveedor disponible', 'valores' => ['V', 'F', 'V', 'F', '-']],
            ['texto' => 'Categoría válida', 'valores' => ['V', 'V', 'F', 'F', 'V']],
        ], 'acciones' => [
            ['texto' => 'Mostrar resultados', 'valores' => ['X', '', '', '', '']],
            ['texto' => 'Avisar fallo', 'valores' => ['', 'X', 'X', 'X', '']],
            ['texto' => 'Conservar filtros', 'valores' => ['X', 'X', 'X', 'X', 'X']],
        ]];
}
try {
    $beforeCases = $pdo->query('SELECT * FROM casos_prueba ORDER BY id')->fetchAll();
    $beforeUsers = $pdo->query('SELECT * FROM usuarios ORDER BY id')->fetchAll();
    $beforeDocuments = $pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll();
    $admin = new BlackBoxClient($base); $tester = new BlackBoxClient($base); $outsider = new BlackBoxClient($base); $anonymous = new BlackBoxClient($base);
    check($admin->login('admin', 'demo-isa3-2026')['status'] === 200, 'Login Admin conservado');
    check($tester->login('tester', 'Tester123!')['status'] === 200, 'Login Tester conservado');
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn();
    $nonce = bin2hex(random_bytes(5)); $name = 'bb-' . $nonce;
    $pdo->prepare('INSERT INTO usuarios (username,nombre,correo,password,rol) VALUES (?,?,?,?,?)')->execute([$name, 'Tester temporal', $name . '@isa3.local', password_hash('Temporal123!', PASSWORD_DEFAULT), 'tester']);
    $otherId = (int) $pdo->lastInsertId(); $userIds[] = $otherId;
    $outsider->login($name, 'Temporal123!');
    foreach ([$testerId, $otherId] as $owner) {
        $pdo->prepare("INSERT INTO casos_prueba (usuario_id,modulo,tecnica,subtecnica,objetivo,precondiciones,datos_entrada,pasos_ejecucion,resultado_esperado,resultado_obtenido,estado,observaciones) VALUES (?,?,'Caja Negra','Partición de equivalencia','Comprobar filtros','Catálogo demo','Precio','Buscar','Respuesta válida','Respuesta válida','Éxito','Temporal')")
            ->execute([$owner, 'Smoke Caja Negra ' . $nonce]);
        $caseIds[] = (int) $pdo->lastInsertId();
    }
    [$ownCase, $otherCase] = $caseIds;
    check($anonymous->request('/formularios')['status'] === 302, 'Catálogo protegido');
    foreach (['/dashboard', '/formularios'] as $path) {
        $page = $tester->request($path);
        check($page['status'] === 200 && str_contains($page['body'], '10 de 10 disponibles'), $path . ' muestra 10 disponibles');
        check(substr_count($page['body'], '>Pendiente<') === 0 && !str_contains($page['body'], 'Primer avance'), $path . ' sin formularios pendientes');
    }
    $ids = [];
    foreach (array_diff_key(BlackBoxRepository::TYPES, ['cobertura' => true]) as $type => $number) {
        $path = '/formularios/' . $type;
        check($anonymous->request($path . '/nuevo')['status'] === 302, $type . ' creación anónima rechazada');
        $payload = fixture_data($type); $payload['caso_id'] = $ownCase;
        $payload['usuario_id'] = $otherId; $payload['tipo'] = 'inventado';
        $payload['csrf'] = $tester->token($path . '/nuevo');
        $id = save_id($tester->request($path . '/nuevo', $payload)); $ids[$type] = $id;
        $user = ['id' => $testerId, 'rol' => 'tester'];
        $record = $repository->find($id, $type, $user);
        check($record && (int) $record['caso_id'] === $ownCase && (int) $record['usuario_id'] === $testerId, $type . ' persiste caso y autor del servidor');
        $contents = $repository->contents($record);
        check($type === 'decision' ? count($contents['reglas']) === 5 && count($contents['condiciones']) === 3 && count($contents['acciones']) === 3 : count($contents['filas']) === 2, $type . ' múltiples filas / condiciones / reglas');
        check($tester->request($path . '/ver?id=' . $id)['status'] === 200, $type . ' consulta propia');
        check($admin->request($path . '/ver?id=' . $id)['status'] === 200, $type . ' Admin consulta todos');
        check($tester->request($path)['status'] === 200 && str_contains($tester->request($path)['body'], '/ver?id=' . $id), $type . ' listado');
        foreach (['ver', 'editar'] as $action) { check($outsider->request($path . '/' . $action . '?id=' . $id)['status'] === 404, $type . ' impide acceso ajeno ' . $action); }
        check(!str_contains($outsider->request($path)['body'], '/ver?id=' . $id), $type . ' lista solo registros propios');
        check($tester->request($path . '/eliminar?id=' . $id)['status'] === 403, $type . ' Tester no elimina');
        check($admin->request($path . '/eliminar?id=' . $id)['status'] === 200 && $repository->find($id, $type, $user) !== null, $type . ' GET no elimina');
        check($admin->request($path . '/eliminar?id=' . $id, [])['status'] === 403, $type . ' eliminación sin CSRF rechazada');
        $missingToken = fixture_data($type); $missingToken['caso_id'] = $ownCase;
        check($tester->request($path . '/nuevo', $missingToken)['status'] === 403, $type . ' creación sin CSRF rechazada');
        check($tester->request($path . '/editar?id=' . $id, $missingToken)['status'] === 403, $type . ' edición sin CSRF rechazada');
        $foreign = $payload; $foreign['caso_id'] = $otherCase;
        check($tester->request($path . '/nuevo', $foreign)['status'] === 200, $type . ' impide crear sobre caso ajeno');
        $foreign['caso_id'] = 999999999;
        check($tester->request($path . '/nuevo', $foreign)['status'] === 200, $type . ' exige caso existente');
        $edit = fixture_data($type);
        if ($type === 'decision') {
            $edit['reglas'] = ['Nueva regla A', 'Nueva regla B', 'Nueva regla C'];
            $edit['condiciones'] = [['texto' => 'Condición editada', 'valores' => ['V', 'F', '-']], ['texto' => '<script>alert(1)</script>', 'valores' => ['F', 'V', 'V']]];
            $edit['acciones'] = [['texto' => 'Acción editada', 'valores' => ['X', '', 'X']]];
        } else { $edit['filas'][0]['campo'] = '<script>alert(1)</script>'; $edit['filas'][] = $edit['filas'][1]; }
        $edit['csrf'] = $tester->token($path . '/editar?id=' . $id); $edit['caso_id'] = $otherCase; $edit['usuario_id'] = $otherId;
        check(save_id($tester->request($path . '/editar?id=' . $id, $edit)) === $id, $type . ' edición propia');
        $updated = $repository->find($id, $type, $user); $updatedContents = $repository->contents($updated);
        check((int) $updated['caso_id'] === $ownCase && (int) $updated['usuario_id'] === $testerId, $type . ' edición conserva autor y caso');
        check($type === 'decision' ? count($updatedContents['reglas']) === 3 && count($updatedContents['condiciones']) === 2 : count($updatedContents['filas']) === 3, $type . ' edición sustituye dimensiones');
        $view = $tester->request($path . '/ver?id=' . $id);
        check(str_contains($view['body'], '&lt;script&gt;') && !str_contains($view['body'], '<script>alert(1)</script>'), $type . ' salida HTML escapada');
        $edit['csrf'] = $admin->token($path . '/editar?id=' . $id);
        check(save_id($admin->request($path . '/editar?id=' . $id, $edit)) === $id, $type . ' Admin edita ajenos');
        check($repository->find($id, $type, $user)['usuario_id'] === $updated['usuario_id'], $type . ' Admin conserva creador');
        $invalid = fixture_data($type); $invalid['caso_id'] = $ownCase; $invalid['csrf'] = $tester->token($path . '/nuevo');
        if ($type !== 'decision') { $invalid['filas'][0]['campo'] = ['invalido']; } else { $invalid['condiciones'][0]['valores'] = ['V']; }
        $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn();
        check($tester->request($path . '/nuevo', $invalid)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $countBefore, $type . ' inválidos no persisten');
        $invalid = fixture_data($type); $invalid['caso_id'] = $ownCase; $invalid['csrf'] = $tester->token($path . '/nuevo');
        if ($type !== 'decision') { $invalid['filas'] = array_fill(0, 31, $invalid['filas'][0]); } else { $invalid['reglas'] = array_fill(0, 21, 'Regla'); }
        check($tester->request($path . '/nuevo', $invalid)['status'] === 200 && (int) $pdo->query('SELECT COUNT(*) FROM formularios_prueba')->fetchColumn() === $countBefore, $type . ' límites de filas/reglas');
    }
    $linked = $tester->request('/casos/ver?id=' . $ownCase);
    foreach ($ids as $type => $id) { check(str_contains($linked['body'], '/formularios/' . $type . '/ver?id=' . $id), $type . ' accesible desde ficha del caso'); }
    check(substr_count($linked['body'], '✓ Registrado') === 3, 'Ficha indica tres documentos asociados');
    $rules = $pdo->query('SELECT id FROM decision_reglas WHERE formulario_id = ' . $ids['decision'])->fetchAll(PDO::FETCH_COLUMN);
    check(count($rules) === 3 && (int) $pdo->query('SELECT COUNT(*) FROM decision_valores WHERE formulario_id = ' . $ids['decision'])->fetchColumn() === 9, 'Decisión guarda reglas y celdas relacionales');
    $foreignDecision = fixture_data('decision'); $foreignDecision['caso_id'] = $otherCase; $foreignDecision['csrf'] = $admin->token('/formularios/decision/nuevo');
    $foreignDocumentId = save_id($admin->request('/formularios/decision/nuevo', $foreignDecision));
    $element = (int) $pdo->query('SELECT id FROM decision_elementos WHERE formulario_id = ' . $ids['decision'] . ' LIMIT 1')->fetchColumn();
    $foreignRule = (int) $pdo->query('SELECT id FROM decision_reglas WHERE formulario_id = ' . $foreignDocumentId . ' LIMIT 1')->fetchColumn();
    try { $pdo->prepare('INSERT INTO decision_valores (formulario_id, elemento_id, regla_id, valor) VALUES (?,?,?,?)')->execute([$ids['decision'], $element, $foreignRule, 'V']); check(false, 'FK impide cruzar documentos'); }
    catch (PDOException $error) { check($error->getCode() === '23000', 'FK impide cruzar documentos'); }
    $snapshot = $pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll();
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formularios_2_4_migration.sql'));
    check($pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll() === $snapshot, 'Migración aditiva reejecutable conserva documentos');
    $invalid = fixture_data('limites'); $invalid['filas'][0]['valor_minimo'] = '901'; $invalid['caso_id'] = $ownCase; $invalid['csrf'] = $tester->token('/formularios/limites/nuevo');
    check(str_contains($tester->request('/formularios/limites/nuevo', $invalid)['body'], 'mínimo no puede superar'), 'Valor límite mínimo/máximo validado');
    $invalid = fixture_data('decision'); $invalid['condiciones'][0]['valores'][0] = 'Z'; $invalid['caso_id'] = $ownCase; $invalid['csrf'] = $tester->token('/formularios/decision/nuevo');
    check(str_contains($tester->request('/formularios/decision/nuevo', $invalid)['body'], 'Valor de decisión no válido'), 'Valores de decisión validados');
    foreach ($ids as $type => $id) {
        $path = '/formularios/' . $type . '/eliminar?id=' . $id;
        check($admin->request($path, ['csrf' => $admin->token($path)])['status'] === 302 && $repository->find($id, $type, ['rol' => 'admin']) === null, $type . ' Admin elimina documento');
        $table = $type === 'equivalencia' ? 'equivalencia_filas' : ($type === 'limites' ? 'limite_filas' : 'decision_valores');
        check((int) $pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE formulario_id = ' . $id)->fetchColumn() === 0, $type . ' eliminación en cascada de filas');
    }
    check((int) $pdo->query('SELECT COUNT(*) FROM casos_prueba WHERE id = ' . $ownCase)->fetchColumn() === 1, 'Eliminar documentos conserva Formulario 1');
    $deleteCasePath = '/casos/eliminar?id=' . $otherCase;
    check($admin->request($deleteCasePath, ['csrf' => $admin->token($deleteCasePath)])['status'] === 302
        && $repository->find($foreignDocumentId, 'decision', ['rol' => 'admin']) === null, 'Eliminar caso elimina solo su documentación asociada');
    check($tester->request('/formularios/inventado')['status'] === 404, 'Tipo desconocido rechazado');
} catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n"); }
finally {
    foreach ($caseIds as $id) { $pdo->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$id]); }
    foreach ($userIds as $id) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]); }
    if (isset($beforeCases)) {
        check($pdo->query('SELECT * FROM casos_prueba ORDER BY id')->fetchAll() === $beforeCases, 'Casos existentes, incluido CP-024, intactos');
        check($pdo->query('SELECT * FROM usuarios ORDER BY id')->fetchAll() === $beforeUsers, 'Usuarios originales intactos');
        check($pdo->query('SELECT * FROM formularios_prueba ORDER BY id')->fetchAll() === $beforeDocuments, 'Solo documentos temporales eliminados');
    }
}
echo "Summary black-box: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
