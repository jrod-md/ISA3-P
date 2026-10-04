<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;
function db(): PDO { static $pdo = null; return $pdo ??= Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')); }
require dirname(__DIR__) . '/apps/bus/testing/includes/catalogo.php';

$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Esta comprobación solo admite una aplicación local.\n"); exit(1);
}
if (!extension_loaded('curl')) { fwrite(STDERR, "Activa la extensión cURL de PHP para ejecutar el smoke.\n"); exit(1); }

final class Client {
    private ?CurlHandle $handle;
    public function __construct(private string $base) {
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
    }
    public function request(string $path, ?array $data = null, bool $multipart = false, bool $json = false): array {
        $headers = [];
        curl_setopt($this->handle, CURLOPT_HTTPHEADER, $json ? ['Content-Type: application/json'] : []);
        curl_setopt_array($this->handle, [CURLOPT_URL => $this->base . $path, CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$headers): int {
            if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $headers[strtolower(trim($name))] = trim($value); }
            return strlen($line);
        }]);
        if ($data === null) { curl_setopt($this->handle, CURLOPT_HTTPGET, true); }
        else { curl_setopt($this->handle, CURLOPT_POST, true); curl_setopt($this->handle, CURLOPT_POSTFIELDS, $json ? json_encode($data) : ($multipart ? $data : http_build_query($data))); }
        $body = curl_exec($this->handle);
        if ($body === false) { throw new RuntimeException('HTTP: ' . curl_error($this->handle)); }
        return ['status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $path): string {
        $page = $this->request($path);
        if ($page['status'] !== 200 || !preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $matches)) { throw new RuntimeException('No se pudo obtener CSRF de ' . $path); }
        return $matches[1];
    }
    public function login(string $email, string $password): array {
        return $this->request('/api/admin/login', ['username' => $email, 'password' => $password], false, true);
    }
    public function close(): void { $this->handle = null; }
}

$passed = 0; $failed = 0; $createdIds = []; $createdUsers = []; $fixtureFiles = [];
$runId = bin2hex(random_bytes(5));
$fixtures = dirname(__DIR__) . '/.runtime/test-' . $runId;
function check(bool $ok, string $name): void {
    global $passed, $failed;
    if ($ok) { $passed++; echo '[OK] ' . $name . "\n"; }
    else { $failed++; echo '[ERROR] ' . $name . "\n"; }
}
function case_data(string $technique, string $subtechnique, string $module, string $state = 'Éxito'): array {
    return ['modulo' => $module, 'tecnica' => $technique, 'subtecnica' => $subtechnique, 'objetivo' => 'Comprobar el comportamiento esperado.', 'precondiciones' => 'Usuario registrado y sistema disponible.', 'datos_entrada' => 'Correo válido y contraseña correcta.', 'pasos_ejecucion' => "1. Abrir el formulario.\n2. Completar los datos.\n3. Enviar.", 'resultado_esperado' => 'Se guarda el caso.', 'resultado_obtenido' => 'El caso queda registrado.', 'estado' => $state, 'observaciones' => ''];
}
function saved_id(array $response): int {
    if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $matches)) { throw new RuntimeException('El caso no se guardó: ' . strip_tags($response['body'])); }
    return (int) $matches[1];
}
function row_case(int $id): array {
    $query = db()->prepare('SELECT * FROM casos_prueba WHERE id = ?'); $query->execute([$id]); return $query->fetch() ?: [];
}

try {
    $before = (int) db()->query('SELECT COUNT(*) FROM casos_prueba')->fetchColumn();
    $beforeUsers = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $adminUserId = (int) db()->query("SELECT id FROM usuarios WHERE correo = 'admin@isa3.local'")->fetchColumn();
    $catalogBefore = [];
    foreach (['ALPHA_DB_NAME' => 'market_alpha', 'BETA_DB_NAME' => 'market_beta', 'GAMMA_DB_NAME' => 'market_gamma'] as $key => $schema) {
        $catalogBefore[$key] = hash('sha256', serialize(Database::connect(Config::string($key, $schema))->query('SELECT * FROM products ORDER BY id')->fetchAll()));
    }
    $sentinel = Marketplace\Shared\Support\Uuid::v4();
    db()->prepare('INSERT INTO search_sessions (id, criteria_json, created_at, expires_at, ttl_seconds, requested_providers, result_count) VALUES (?, ?, UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR), 3600, ?, 0)')->execute([$sentinel, '{"q":"Sentinela de migración"}', 'alpha']);
    db()->prepare('INSERT INTO search_cache (search_id, results_json, providers_json, warnings_json, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(6))')->execute([$sentinel, '[]', '{}', '[]']);
    $sentinelQuery = db()->prepare('SELECT s.*, c.results_json, c.providers_json, c.warnings_json FROM search_sessions s JOIN search_cache c ON c.search_id = s.id WHERE s.id = ?');
    $sentinelQuery->execute([$sentinel]); $sentinelBefore = $sentinelQuery->fetch();
    db()->exec(file_get_contents(dirname(__DIR__) . '/sql/proyecto2_migration.sql'));
    $sentinelQuery->execute([$sentinel]);
    check($sentinelQuery->fetch() === $sentinelBefore, 'Migración conserva SearchSession y caché del Proyecto 1');
    db()->prepare('DELETE FROM search_sessions WHERE id = ?')->execute([$sentinel]);
    foreach (['ALPHA_DB_NAME' => 'market_alpha', 'BETA_DB_NAME' => 'market_beta', 'GAMMA_DB_NAME' => 'market_gamma'] as $key => $schema) {
        check($catalogBefore[$key] === hash('sha256', serialize(Database::connect(Config::string($key, $schema))->query('SELECT * FROM products ORDER BY id')->fetchAll())), 'Migración conserva catálogo original: ' . $schema);
    }
    check((int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === $beforeUsers && (int) db()->query('SELECT COUNT(*) FROM casos_prueba')->fetchColumn() === $before, 'SQL reimportable sin duplicar cuentas ni borrar casos');
    foreach (['admin@isa3.local' => 'demo-isa3-2026', 'tester@isa3.local' => 'Tester123!'] as $email => $password) {
        $query = db()->prepare('SELECT password FROM usuarios WHERE correo = ?'); $query->execute([$email]); $hash = (string) $query->fetchColumn();
        check($hash !== $password && password_verify($password, $hash), 'Hash y credenciales demo: ' . $email);
    }
    $anonymous = new Client($base);
    foreach (['/dashboard', '/usuarios', '/busquedas', '/casos', '/casos/nuevo', '/casos/ver?id=1', '/casos/editar?id=1', '/casos/eliminar?id=1', '/casos/evidencia?id=1'] as $path) {
        $response = $anonymous->request($path);
        check($response['status'] === 302 && str_ends_with($response['headers']['location'] ?? '', '/admin'), 'Ruta protegida: ' . $path);
    }
    foreach (['/bootstrap.php', '/.env', '/apps/bus/src/Support/AdminAuth.php', '/sql/proyecto2_migration.sql', '/.runtime/sessions/', '/tests/smoke-proyecto2.php', '/scripts/migrate-proyecto2.php'] as $path) {
        check($anonymous->request($path)['status'] === 403, 'Apache bloquea acceso directo: ' . $path);
    }
    check($anonymous->login('admin@isa3.local', 'incorrecta')['status'] === 401, 'Login rechaza contraseña incorrecta');
    check($anonymous->login("' OR 1=1 --", 'incorrecta')['status'] === 401, 'Login rechaza entrada SQL');
    $registration = $anonymous->request('/registro', ['csrf' => $anonymous->token('/registro'), 'nombre' => '', 'correo' => 'invalido', 'password' => '123', 'confirmacion' => '456']);
    check($registration['status'] === 200 && str_contains($registration['body'], 'Las contraseñas no coinciden'), 'Registro valida nombre, correo y contraseña');
    $testers = [];
    for ($index = 0; $index < 2; $index++) {
        $email = 'smoke-' . $runId . '-' . $index . '@isa3.local';
        $response = $anonymous->request('/registro', ['csrf' => $anonymous->token('/registro'), 'nombre' => 'Prueba temporal ' . $index, 'username' => 'smoke-' . $runId . '-' . $index, 'correo' => $email, 'password' => 'Temporal123!', 'confirmacion' => 'Temporal123!', 'rol' => 'admin']);
        $query = db()->prepare('SELECT * FROM usuarios WHERE correo = ?'); $query->execute([$email]); $account = $query->fetch();
        if (!$account) { throw new RuntimeException('No se creó la cuenta temporal'); }
        $createdUsers[] = (int) $account['id'];
        check($response['status'] === 302 && $account['rol'] === 'tester' && password_verify('Temporal123!', $account['password']), 'Registro persistente y rol tester inalterable ' . $index);
        $testers[$index] = new Client($base);
        check($testers[$index]->login($email, 'Temporal123!')['status'] === 200, 'Login tester temporal ' . $index);
    }
    $duplicate = $anonymous->request('/registro', ['csrf' => $anonymous->token('/registro'), 'nombre' => 'Duplicado', 'username' => 'duplicate-' . $runId, 'correo' => 'smoke-' . $runId . '-0@isa3.local', 'password' => 'Temporal123!', 'confirmacion' => 'Temporal123!']);
    check(str_contains($duplicate['body'], 'Ya existe una cuenta'), 'Correo duplicado rechazado');
    $admin = new Client($base);
    check($admin->login('admin@isa3.local', 'demo-isa3-2026')['status'] === 200, 'Login administrador');
    $demoTester = new Client($base);
    check($demoTester->login('tester@isa3.local', 'Tester123!')['status'] === 200, 'Login tester demo');
    check(str_ends_with($admin->request('/admin')['headers']['location'] ?? '', '/dashboard'), 'Administrador con sesión vuelve al dashboard desde login');
    check(str_ends_with($demoTester->request('/login')['headers']['location'] ?? '', '/dashboard'), 'Tester con sesión vuelve al dashboard desde login');
    $loginPage = $anonymous->request('/login');
    check($loginPage['status'] === 200 && str_contains($loginPage['body'], 'id="login-form"') && str_contains($loginPage['body'], 'auth-panel') && !str_contains($loginPage['body'], 'id="admin-panel"'), 'Login visual separado del panel de búsquedas, misma autenticación');
    check($demoTester->request('/busquedas')['status'] === 403, 'Tester sin acceso al panel de búsquedas temporales');
    check($demoTester->request('/api/admin/searches')['status'] === 403, 'Tester sin acceso a API administrativa original');
    $adminSearches = $admin->request('/busquedas');
    check($adminSearches['status'] === 200 && str_contains($adminSearches['body'], 'aria-current="page"') && str_contains($adminSearches['body'], 'id="searches-body"'), 'Panel original integrado en navegación común');
    foreach (['/', '/dashboard', '/casos', '/usuarios', '/busquedas'] as $path) {
        $page = $admin->request($path)['body'];
        check(str_contains($page, 'class="sidebar"') && str_contains($page, 'id="session-logout"') && str_contains($page, 'href="' . (parse_url($base, PHP_URL_PATH) ?: '') . '/busquedas"'), 'Navegación y cierre de sesión comunes: ' . $path);
    }
    $prefillInputs = 'q=laptop&max_price=900';
    $prefillObserved = '<script>alert(1)</script> Seis resultados';
    $prefill = $demoTester->request('/casos/nuevo?' . http_build_query(['origen' => 'buscador', 'datos_entrada' => $prefillInputs, 'resultado_obtenido' => $prefillObserved]))['body'];
    check(str_contains($prefill, 'value="Bus / búsqueda unificada de productos"') && str_contains($prefill, htmlspecialchars($prefillInputs, ENT_QUOTES, 'UTF-8')) && str_contains($prefill, htmlspecialchars($prefillObserved, ENT_QUOTES, 'UTF-8')), 'Traspaso al caso precarga datos observados y escapa HTML');
    check(str_contains($prefill, '← Volver al buscador') && str_contains($prefill, '/?q=laptop&amp;max_price=900'), 'Cancelar caso originado en buscador conserva filtros');
    check(!str_contains($prefill, '<option selected value="Éxito">') && !str_contains($prefill, '<option selected value="Caja Negra">'), 'Traspaso no inventa técnica ni resultado de evaluación');
    check($admin->request('/usuarios')['status'] === 200, 'Administrador consulta usuarios');
    check($testers[0]->request('/usuarios')['status'] === 403, 'Tester no administra usuarios');
    check(!str_contains($testers[0]->request('/dashboard')['body'], '/usuarios'), 'Navegación de tester sin administración');
    $dashboard = $admin->request('/dashboard')['body'];
    check(count(FORMULARIOS) === 10 && substr_count($dashboard, 'badge-pending">Pendiente') === 1 && str_contains($dashboard, '9 de 10 disponibles'), 'Dashboard muestra nueve formularios disponibles y uno pendiente');
    $token = $testers[0]->token('/casos/nuevo');
    $firstId = null;
    foreach (TECNICAS as $technique => $items) {
        check(count($items) === 10, 'Catálogo exacto de diez subtécnicas: ' . $technique);
        foreach ($items as $index => $subtechnique) {
            $module = $firstId === null ? '<script>alert(1)</script> ' . $runId : 'Prueba ' . $runId . ' ' . $technique . ' ' . $index;
            $data = case_data($technique, $subtechnique, $module, $index % 2 === 0 ? 'Éxito' : 'Fallo'); $data['csrf'] = $token; $data['usuario_id'] = 1;
            $id = saved_id($testers[0]->request('/casos/nuevo', $data)); $createdIds[] = $id; $firstId ??= $id;
            $stored = row_case($id);
            check($stored['tecnica'] === $technique && $stored['subtecnica'] === $subtechnique && (int) $stored['usuario_id'] === $createdUsers[0] && $stored['pasos_ejecucion'] === $data['pasos_ejecucion'], 'Persistencia y propietario: ' . $subtechnique);
            $detail = $testers[0]->request('/casos/ver?id=' . $id);
            check($detail['status'] === 200 && str_contains($detail['body'], htmlspecialchars($subtechnique, ENT_QUOTES, 'UTF-8')), 'Visualización: ' . $subtechnique);
        }
    }
    $detail = $testers[0]->request('/casos/ver?id=' . $firstId)['body'];
    check(str_contains($detail, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($detail, '<script>alert(1)</script>'), 'Salida HTML escapa contenido del usuario');
    foreach (['/casos/ver?id=', '/casos/editar?id=', '/casos/evidencia?id='] as $path) {
        check($testers[1]->request($path . $firstId)['status'] === 404, 'Segundo tester sin acceso al caso ajeno: ' . $path);
    }
    check($testers[0]->request('/casos/eliminar?id=' . $firstId)['status'] === 403, 'Tester no elimina ni sus propios casos');
    check(!str_contains($testers[1]->request('/casos')['body'], 'CP-' . str_pad((string) $firstId, 3, '0', STR_PAD_LEFT)), 'Listado aislado por propietario');
    foreach (['0', '-1', 'abc', '999999999'] as $invalidId) { check($admin->request('/casos/ver?id=' . $invalidId)['status'] === 404, 'ID inválido: ' . $invalidId); }
    $valid = case_data('Caja Negra', TECNICAS['Caja Negra'][0], 'Prueba validación'); $valid['csrf'] = $token;
    foreach (['campos vacíos' => ['objetivo' => ''], 'módulo largo' => ['modulo' => str_repeat('á', 151)], 'texto largo' => ['resultado_obtenido' => str_repeat('á', 5001)], 'subtécnica incompatible' => ['subtecnica' => TECNICAS['Caja Blanca'][0]], 'estado inválido' => ['estado' => 'Pendiente'], 'técnica inválida' => ['tecnica' => 'Caja Gris'], 'entrada array' => ['modulo' => ['dato']]] as $name => $override) {
        $response = $testers[0]->request('/casos/nuevo', array_replace($valid, $override));
        check($response['status'] === 200 && str_contains($response['body'], 'Revisa los siguientes datos'), 'Validación servidor: ' . $name);
    }
    check((int) db()->query('SELECT COUNT(*) FROM casos_prueba')->fetchColumn() === $before + count($createdIds), 'Entradas inválidas no insertan casos');
    $boundary = $valid;
    $boundary['modulo'] = str_repeat('á', 150);
    foreach (['objetivo', 'precondiciones', 'datos_entrada', 'pasos_ejecucion', 'resultado_esperado', 'resultado_obtenido', 'observaciones'] as $field) { $boundary[$field] = str_repeat('á', 5000); }
    $boundaryId = saved_id($testers[0]->request('/casos/nuevo', $boundary)); $createdIds[] = $boundaryId;
    check(row_case($boundaryId)['modulo'] === $boundary['modulo'] && row_case($boundaryId)['observaciones'] === $boundary['observaciones'], 'Límites válidos de 150 y 5000 caracteres UTF-8');
    $noCsrf = $valid; unset($noCsrf['csrf']);
    check($testers[0]->request('/casos/nuevo', $noCsrf)['status'] === 403, 'Creación sin CSRF rechazada');
    check($testers[0]->request('/casos/editar?id=' . $firstId, $noCsrf)['status'] === 403, 'Edición sin CSRF rechazada');
    $edit = case_data('Caja Blanca', TECNICAS['Caja Blanca'][6], 'Edición ' . $runId, 'Fallo'); $edit['csrf'] = $token;
    check($testers[0]->request('/casos/editar?id=' . $firstId, $edit)['status'] === 302 && row_case($firstId)['subtecnica'] === TECNICAS['Caja Blanca'][6], 'Tester edita y cambia técnica de su caso');
    $unauthorized = $edit; $unauthorized['csrf'] = $testers[1]->token('/casos/nuevo'); $unauthorized['modulo'] = 'Cambio ajeno';
    check($testers[1]->request('/casos/editar?id=' . $firstId, $unauthorized)['status'] === 404 && row_case($firstId)['modulo'] === $edit['modulo'], 'POST directo no modifica caso ajeno');
    $edit['csrf'] = $admin->token('/casos/editar?id=' . $firstId); $edit['modulo'] = 'Edición admin ' . $runId;
    check($admin->request('/casos/editar?id=' . $firstId, $edit)['status'] === 302 && row_case($firstId)['modulo'] === $edit['modulo'] && (int) row_case($firstId)['usuario_id'] === $createdUsers[0], 'Administrador edita caso ajeno conservando autor');
    $adminData = case_data('Caja Negra', TECNICAS['Caja Negra'][0], 'Creación admin ' . $runId); $adminData['csrf'] = $edit['csrf'];
    $adminId = saved_id($admin->request('/casos/nuevo', $adminData)); $createdIds[] = $adminId;
    check((int) row_case($adminId)['usuario_id'] === $adminUserId, 'Administrador crea caso propio');

    mkdir($fixtures, 0700, true);
    $png = $fixtures . '/captura.png'; $jpg = $fixtures . '/captura.jpg'; $pdf = $fixtures . '/informe.pdf'; $bad = $fixtures . '/archivo.php'; $large = $fixtures . '/grande.png';
    $fixtureFiles = [$png, $jpg, $pdf, $bad, $large];
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZJcAAAAASUVORK5CYII='));
    file_put_contents($jpg, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3qiiigD//2Q=='));
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    file_put_contents($bad, '<?php echo "ejecución";'); file_put_contents($large, str_repeat('x', 2 * 1024 * 1024 + 1));
    $uploadData = $valid; $uploadData['evidencia'] = new CURLFile($png, 'image/png', 'captura.png');
    $evidenceId = saved_id($testers[0]->request('/casos/nuevo', $uploadData, true)); $createdIds[] = $evidenceId;
    $download = $testers[0]->request('/casos/evidencia?id=' . $evidenceId);
    check($download['status'] === 200 && $download['body'] === file_get_contents($png) && ($download['headers']['content-type'] ?? '') === 'image/png', 'Evidencia PNG guardada y descargada byte a byte');
    check($admin->request('/casos/evidencia?id=' . $evidenceId)['status'] === 200 && $testers[1]->request('/casos/evidencia?id=' . $evidenceId)['status'] === 404, 'Permisos en descarga de evidencia');
    $oldFile = row_case($evidenceId)['evidencia_archivo'];
    $uploadData['evidencia'] = new CURLFile($pdf, 'application/pdf', 'informe.pdf');
    check($testers[0]->request('/casos/editar?id=' . $evidenceId, $uploadData, true)['status'] === 302 && !is_file(dirname(__DIR__) . '/.runtime/evidence/' . $oldFile), 'Reemplazar evidencia elimina archivo anterior');
    check($testers[0]->request('/casos/evidencia?id=' . $evidenceId)['body'] === file_get_contents($pdf), 'Evidencia PDF descargada byte a byte');
    check($testers[0]->request('/casos/editar?id=' . $evidenceId, $valid)['status'] === 302 && row_case($evidenceId)['evidencia_tipo'] === 'application/pdf', 'Editar sin archivo conserva evidencia');
    $uploadData['evidencia'] = new CURLFile($jpg, 'image/jpeg', 'captura.jpg');
    check($testers[0]->request('/casos/editar?id=' . $evidenceId, $uploadData, true)['status'] === 302 && $testers[0]->request('/casos/evidencia?id=' . $evidenceId)['body'] === file_get_contents($jpg), 'Evidencia JPG guardada y descargada byte a byte');
    $uploadData['evidencia'] = new CURLFile($bad, 'image/png', 'falso.png');
    check(str_contains($testers[0]->request('/casos/nuevo', $uploadData, true)['body'], 'La evidencia debe ser una imagen'), 'Contenido ejecutable rechazado aunque declare MIME imagen');
    $uploadData['evidencia'] = new CURLFile($large, 'image/png', 'grande.png');
    check(str_contains($testers[0]->request('/casos/nuevo', $uploadData, true)['body'], 'La evidencia debe pesar'), 'Evidencia mayor a 2 MB rechazada');
    $remove = $valid; $remove['quitar_evidencia'] = '1';
    $oldFile = row_case($evidenceId)['evidencia_archivo'];
    check($testers[0]->request('/casos/editar?id=' . $evidenceId, $remove)['status'] === 302 && row_case($evidenceId)['evidencia_archivo'] === null && !is_file(dirname(__DIR__) . '/.runtime/evidence/' . $oldFile), 'Quitar evidencia actualiza MySQL y disco');
    check($testers[0]->request('/casos/evidencia?id=' . $evidenceId)['status'] === 404, 'Caso sin evidencia devuelve 404');
    $personalDash = $testers[0]->request('/dashboard')['body'];
    $query = db()->prepare("SELECT COUNT(*) AS total, SUM(estado = 'Éxito') AS exitosos, SUM(estado = 'Fallo') AS fallidos FROM casos_prueba WHERE usuario_id = ?"); $query->execute([$createdUsers[0]]); $stats = $query->fetch();
    check(str_contains($personalDash, '<strong>' . $stats['total'] . '</strong>') && str_contains($personalDash, '<strong>' . $stats['exitosos'] . '</strong>') && str_contains($personalDash, '<strong>' . $stats['fallidos'] . '</strong>'), 'Contadores personales coinciden con MySQL');
    $links = [];
    foreach (['/dashboard', '/casos', '/casos/nuevo', '/casos/ver?id=' . $firstId, '/casos/editar?id=' . $firstId, '/casos/eliminar?id=' . $firstId, '/usuarios', '/busquedas', '/'] as $path) {
        $page = $admin->request($path);
        check($page['status'] === 200, 'Ruta e includes: ' . $path);
        preg_match_all('/(?:href|src)="([^"]+)"/', $page['body'], $matches);
        foreach ($matches[1] as $link) {
            $link = html_entity_decode($link, ENT_QUOTES, 'UTF-8');
            if (str_starts_with($link, '/')) { $link = explode('#', $link)[0]; $prefix = parse_url($base, PHP_URL_PATH) ?: ''; $links[substr($link, strlen($prefix))] = true; }
        }
    }
    foreach (array_keys($links) as $path) { $response = $admin->request($path); check(in_array($response['status'], [200, 302], true), 'Enlace/asset accesible: ' . $path); }
    check($admin->request('/casos/eliminar?id=' . $firstId, [])['status'] === 403 && row_case($firstId) !== [], 'Eliminación sin CSRF rechazada');
    $deleteToken = $admin->token('/casos/eliminar?id=' . $firstId);
    check(row_case($firstId) !== [], 'GET de confirmación no elimina');
    check($admin->request('/casos/eliminar?id=' . $firstId, ['csrf' => $deleteToken])['status'] === 302 && row_case($firstId) === [], 'Administrador elimina mediante POST confirmado');
    check($testers[0]->request('/api/admin/logout')['status'] === 404, 'Logout no usa GET');
    check($testers[0]->request('/api/admin/logout', ['csrf' => $token])['status'] === 200 && $testers[0]->request('/dashboard')['status'] === 302, 'Logout invalida sesión');
} catch (Throwable $error) {
    $failed++; fwrite(STDERR, '[ERROR] ' . $error->getMessage() . "\n");
} finally {
    // Liberar los handles cURL antes de borrar archivos subidos en Windows.
    foreach (array_merge($testers ?? [], [$anonymous ?? null, $admin ?? null, $demoTester ?? null]) as $client) {
        if ($client instanceof Client) { $client->close(); }
    }
    foreach ($createdUsers as $userId) {
        $query = db()->prepare('SELECT id FROM casos_prueba WHERE usuario_id = ?'); $query->execute([$userId]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $caseId) { $createdIds[] = (int) $caseId; }
    }
    foreach (array_unique($createdIds) as $id) {
        $row = row_case($id);
        if (!empty($row['evidencia_archivo'])) { $path = dirname(__DIR__) . '/.runtime/evidence/' . basename($row['evidencia_archivo']); if (is_file($path)) { unlink($path); } }
        db()->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$id]);
    }
    foreach ($createdUsers as $id) { db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]); }
    foreach ($fixtureFiles as $path) { if (is_file($path)) { unlink($path); } }
    // En Windows los archivos borrados pueden seguir abiertos brevemente por cURL.
    if (is_dir($fixtures)) {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (@rmdir($fixtures)) { break; }
            usleep(100000);
        }
        if (is_dir($fixtures)) { $failed++; fwrite(STDERR, "[ERROR] No se pudo limpiar la carpeta temporal.\n"); }
    }
}
echo "\nResultado: $passed correctas, $failed fallidas. Datos temporales eliminados.\n";
exit($failed === 0 ? 0 : 1);
