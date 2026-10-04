<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Repository\IncidentRepository as Incidents;
use Marketplace\Bus\Support\PrivateEvidence;
use Marketplace\Shared\Support\Database;
$base = rtrim($argv[1] ?? 'http://localhost/ISA3-Proyecto2', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) { fwrite(STDERR, "Solo pruebas locales.\n"); exit(1); }
final class IncidentClient {
    private ?CurlHandle $handle;
    public function __construct(private string $base) { $this->handle = curl_init(); curl_setopt_array($this->handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]); }
    public function request(string $path, ?array $data = null, bool $multipart = false, bool $json = false): array {
        $headers = []; curl_setopt_array($this->handle, [CURLOPT_URL => $this->base . $path, CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [], CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) { if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); } return strlen($line); }]);
        if ($data === null) { curl_setopt($this->handle, CURLOPT_HTTPGET, true); }
        else { curl_setopt($this->handle, CURLOPT_POST, true); curl_setopt($this->handle, CURLOPT_POSTFIELDS, $json ? json_encode($data) : ($multipart ? $data : http_build_query($data))); }
        $body = curl_exec($this->handle); if ($body === false) { throw new RuntimeException(curl_error($this->handle)); }
        return ['status' => curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
    }
    public function token(string $path): string { $page = $this->request($path); if ($page['status'] !== 200 || !preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $match)) { throw new RuntimeException('CSRF no disponible: ' . $path); } return $match[1]; }
    public function login(string $name, string $password): array { return $this->request('/api/admin/login', ['username' => $name, 'password' => $password], false, true); }
    public function close(): void { $this->handle = null; }
}
$pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')); $repository = new Incidents($pdo);
$path = '/formularios/incidentes'; $passed = 0; $failed = 0; $ids = []; $caseIds = []; $userId = null; $fixtureDir = PROJECT_ROOT . '/.runtime/incident-test-' . bin2hex(random_bytes(5));
function check(bool $value, string $label): void { global $passed, $failed; if ($value) { $passed++; echo "PASS {$label}\n"; } else { $failed++; echo "FAIL {$label}\n"; } }
function saved_id(array $response): int { global $ids; if ($response['status'] !== 302 || !preg_match('/ver\?id=(\d+)/', $response['headers']['location'] ?? '', $match)) { throw new RuntimeException('Guardado falló: ' . strip_tags($response['body'])); } $id = (int) $match[1]; $ids[$id] = $id; return $id; }
function payload(): array { return ['titulo' => 'Filtro de precio no aplicado', 'modulo' => 'Bus / búsqueda', 'severidad' => 'Media', 'prioridad' => 'Alta', 'descripcion' => 'Se muestra un precio fuera del rango', 'pasos_reproducir' => "1. Buscar laptop\n2. Fijar precio máximo", 'resultado_esperado' => 'Solo precios válidos', 'resultado_obtenido' => 'Un resultado supera el máximo', 'estado' => 'Abierto', 'asignado_a' => 'Equipo Backend', 'caso_id' => '']; }
function evidence_files(): array { clearstatcache(); $files = []; foreach (glob(PROJECT_ROOT . '/.runtime/evidence/*') ?: [] as $file) { if (is_file($file)) { $files[basename($file)] = hash_file('sha256', $file); } } ksort($files); return $files; }
try {
    $protected = [];
    foreach (['usuarios','casos_prueba','formularios_prueba','equivalencia_filas','limite_filas','decision_reglas','decision_elementos','decision_valores','cobertura_metricas','planes_prueba','plan_cronograma','rubricas','rubrica_criterios','evaluaciones_pares','evaluacion_aspectos','portafolios','portafolio_evidencias','incidentes'] as $table) { $protected[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($table === 'decision_valores' ? 'elemento_id, regla_id' : 'id'))->fetchAll(); }
    $originalFiles = evidence_files(); mkdir($fixtureDir, 0700, true);
    $png = $fixtureDir . '/captura.png'; $jpg = $fixtureDir . '/captura.jpg'; $pdf = $fixtureDir . '/informe.pdf'; $txt = $fixtureDir . '/informe.txt'; $log = $fixtureDir . '/traza.log'; $bad = $fixtureDir . '/archivo.php'; $binary = $fixtureDir . '/binario.txt'; $html = $fixtureDir . '/pagina.txt'; $large = $fixtureDir . '/grande.txt'; $boundary = $fixtureDir . '/limite.txt'; $empty = $fixtureDir . '/vacio.txt';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB0sAAAAASUVORK5CYII='));
    file_put_contents($jpg, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3qiiigD//2Q=='));
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"); file_put_contents($txt, "Informe de búsqueda: resultado inesperado.\n"); file_put_contents($log, "2026-10-04 INFO Prueba\nERROR Respuesta fuera del rango\n"); file_put_contents($bad, '<?php echo "ejecutable";'); file_put_contents($binary, "\x00\x01\x02binario"); file_put_contents($html, '<!doctype html><html><script>alert(1)</script></html>'); file_put_contents($large, str_repeat('A', PrivateEvidence::MAX_BYTES + 1)); file_put_contents($boundary, str_repeat('A', PrivateEvidence::MAX_BYTES)); file_put_contents($empty, '');
    $admin = new IncidentClient($base); $tester = new IncidentClient($base); $other = new IncidentClient($base); $anon = new IncidentClient($base);
    check($admin->login('admin', 'demo-isa3-2026')['status'] === 200, 'Login Admin conservado'); check($tester->login('tester', 'Tester123!')['status'] === 200, 'Login Tester conservado');
    $testerId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'tester'")->fetchColumn(); $adminUserId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'admin'")->fetchColumn();
    $nonce = 'bug-' . bin2hex(random_bytes(5)); $pdo->prepare('INSERT INTO usuarios (username,nombre,correo,password,rol) VALUES (?,?,?,?,?)')->execute([$nonce,'Tester temporal',$nonce . '@isa3.local',password_hash('Temporal123!',PASSWORD_DEFAULT),'tester']); $userId = (int) $pdo->lastInsertId(); $other->login($nonce, 'Temporal123!');
    foreach ([$testerId,$userId,$adminUserId] as $owner) { $pdo->prepare("INSERT INTO casos_prueba (usuario_id,modulo,tecnica,subtecnica,objetivo,precondiciones,datos_entrada,pasos_ejecucion,resultado_esperado,resultado_obtenido,estado,observaciones) VALUES (?,?,'Caja Negra','Partición de equivalencia','Validar','Listo','Precio','Buscar','Respuesta','Defecto','Fallo','Temporal')")->execute([$owner,'Caso temporal incidente ' . $nonce]); $caseIds[] = (int) $pdo->lastInsertId(); }
    [$ownCase,$otherCase,$adminCase] = $caseIds;
    foreach (['','/nuevo','/ver?id=1','/editar?id=1','/eliminar?id=1','/evidencia?id=1'] as $suffix) { check($anon->request($path . $suffix)['status'] === 302, 'Ruta protegida ' . $suffix); }
    check(Incidents::code(1) === 'BUG-001' && Incidents::code(12) === 'BUG-012' && Incidents::code(1000) === 'BUG-1000', 'Código automático mínimo tres dígitos');
    foreach (['/dashboard','/formularios'] as $route) { $body = $tester->request($route)['body']; check(str_contains($body,'10 de 10 disponibles') && !str_contains($body,'>Pendiente<') && !str_contains($body,'Primer avance') && str_contains($body,$path), $route . ' 10/10 sin pendientes'); }
    $editor = $tester->request($path . '/nuevo')['body']; check(!str_contains($editor,'name="codigo"') && !str_contains($editor,'name="id"') && str_contains($editor,'Se genera automáticamente'), 'Código no editable');
    foreach (Incidents::FIELDS as $field => $label) { check(str_contains($editor,'name="' . $field . '"'), 'Campo obligatorio ' . $label); }
    check(str_contains($editor,'Sin caso asociado') && !str_contains($editor,'value="' . $otherCase . '"'), 'Selector opcional limita casos por cuenta');
    check(str_contains($tester->request($path . '/nuevo?caso_id=' . $ownCase)['body'],'value="' . $ownCase . '" selected'), 'Caso preseleccionado desde ficha');
    check($tester->request($path . '/nuevo?caso_id=' . $otherCase)['status'] === 404 && $tester->request($path . '/nuevo?caso_id[]=1')['status'] === 404, 'Preselección ajena/malformada rechazada');
    $data = payload(); $data['csrf'] = $tester->token($path . '/nuevo'); $data['usuario_id'] = $userId; $data['codigo'] = 'BUG-999'; $data['id'] = 999; $data['evidencia_archivo'] = '../../secreto.php'; $data['evidencia_tipo'] = 'text/html';
    $id = saved_id($tester->request($path . '/nuevo',$data)); $record = $repository->find($id,['rol'=>'admin']);
    check((int) $record['usuario_id'] === $testerId && $record['caso_id'] === null && $record['evidencia_archivo'] === null && $record['asignado_a'] === 'Equipo Backend', 'Sin caso/archivo, creador de sesión distinto de responsable y metadatos POST ignorados');
    check(str_contains($tester->request($path . '/ver?id=' . $id)['body'], Incidents::code($id)), 'Código derivado del id real');
    foreach (Incidents::FIELDS as $field => $label) { check($record[$field] === $data[$field], 'Persistencia ' . $field); }
    $linked = $data; $linked['caso_id'] = $ownCase; $linked['titulo'] = 'Incidente relacionado'; $linkedId = saved_id($tester->request($path . '/nuevo',$linked));
    check((int) $repository->find($linkedId,['rol'=>'admin'])['caso_id'] === $ownCase, 'Crear con caso');
    $casePage = $tester->request('/casos/ver?id=' . $ownCase)['body']; check(str_contains($casePage,'Incidentes relacionados') && str_contains($casePage, Incidents::code($linkedId)) && str_contains($casePage, $path . '/nuevo?caso_id=' . $ownCase), 'Ficha del caso enlaza incidentes y nuevo preseleccionado');
    check(str_contains($tester->request($path . '/ver?id=' . $linkedId)['body'], '/casos/ver?id=' . $ownCase), 'Incidente enlaza caso accesible');
    check($tester->request($path . '/ver?id=' . $id)['status'] === 200 && $admin->request($path . '/ver?id=' . $id)['status'] === 200, 'Consulta propia y Admin');
    check(str_contains($tester->request($path)['body'], '/ver?id=' . $id . '"') && !str_contains($other->request($path)['body'], '/ver?id=' . $id . '"'), 'Listado propio, no ajeno');
    foreach (['ver','editar','evidencia'] as $action) { check($other->request($path . '/' . $action . '?id=' . $id)['status'] === 404, 'Ownership ' . $action); }
    check($other->request($path . '/editar?id=' . $id,$data)['status'] === 404, 'Ownership POST');
    check($tester->request($path . '/eliminar?id=' . $id)['status'] === 403 && $tester->request($path . '/eliminar?id=' . $id,$data)['status'] === 403, 'Tester no elimina GET/POST');
    check($admin->request($path . '/eliminar?id=' . $id)['status'] === 200 && $repository->find($id,['rol'=>'admin']) !== null, 'Confirmación GET no elimina');
    foreach (['nuevo','editar?id=' . $id,'eliminar?id=' . $id] as $action) { $client = str_starts_with($action,'eliminar') ? $admin : $tester; $invalid = $data; unset($invalid['csrf']); check($client->request($path . '/' . $action,$invalid)['status'] === 403, 'CSRF ausente ' . $action); $invalid['csrf'] = 'incorrecto'; check($client->request($path . '/' . $action,$invalid)['status'] === 403, 'CSRF inválido ' . $action); }
    foreach (['','/ver?id=' . $id,'/evidencia?id=' . $id] as $suffix) { check($tester->request($path . $suffix,[])['status'] === 405, 'POST no permitido ' . $suffix); }
    $edit = $data; $edit['titulo'] = '<script>alert(1)</script>'; $edit['asignado_a'] = 'Sin asignar'; $edit['estado'] = 'En progreso';
    check(saved_id($tester->request($path . '/editar?id=' . $id,$edit)) === $id, 'Tester edita propio'); $updated = $repository->find($id,['rol'=>'admin']);
    check((int) $updated['usuario_id'] === $testerId && $updated['asignado_a'] === 'Sin asignar' && $updated['estado'] === 'En progreso', 'Edición conserva creador y cambia responsable/estado'); $body = $tester->request($path . '/ver?id=' . $id)['body']; check(str_contains($body,'&lt;script&gt;') && !str_contains($body,'<script>alert(1)</script>'), 'Texto escapado');
    $adminEdit = $edit; $adminEdit['csrf'] = $admin->token($path . '/editar?id=' . $id); $adminEdit['caso_id'] = $adminCase;
    check(saved_id($admin->request($path . '/editar?id=' . $id,$adminEdit)) === $id && (int) $repository->find($id,['rol'=>'admin'])['usuario_id'] === $testerId, 'Admin edita ajeno sin cambiar creador');
    check(!str_contains($tester->request($path . '/ver?id=' . $id)['body'], '/casos/ver?id=' . $adminCase), 'Tester no obtiene enlace a caso ajeno asignado por Admin');
    $edit['caso_id'] = $adminCase; check(saved_id($tester->request($path . '/editar?id=' . $id,$edit)) === $id, 'Tester conserva vínculo previo del Admin sin acceso al caso');
    $edit['caso_id'] = ''; saved_id($tester->request($path . '/editar?id=' . $id,$edit)); check($repository->find($id,['rol'=>'admin'])['caso_id'] === null, 'Edición puede quitar asociación');
    $updated = $repository->find($id,['rol'=>'admin']); $count = (int) $pdo->query('SELECT COUNT(*) FROM incidentes')->fetchColumn(); $invalids = [];
    foreach (Incidents::FIELDS as $field => $label) { foreach (['', ['mal'], str_repeat('á', Incidents::limit($field)+1)] as $index => $value) { $invalid = $data; $invalid[$field] = $value; $invalids[$field . ' inválido ' . $index] = $invalid; } }
    foreach (['severidad'=>'Urgente','prioridad'=>'Crítica','estado'=>'Resuelto'] as $field=>$value) { $invalid=$data; $invalid[$field]=$value; $invalids['opción fuera de catálogo ' . $field]=$invalid; }
    foreach ([999999999,$otherCase,$adminCase,'0','-1','1.5','1e0',4294967296,['mal']] as $index=>$caseValue) { $invalid=$data; $invalid['caso_id']=$caseValue; $invalids['caso inválido ' . $index]=$invalid; }
    foreach ($invalids as $label=>$invalid) { check($tester->request($path . '/nuevo',$invalid)['status']===200 && (int)$pdo->query('SELECT COUNT(*) FROM incidentes')->fetchColumn()===$count,'Crear rechaza ' . $label); check($tester->request($path . '/editar?id=' . $id,$invalid)['status']===200 && $repository->find($id,['rol'=>'admin'])===$updated,'Editar inválido conserva registro: ' . $label); }
    foreach (['severidad'=>Incidents::SEVERITIES,'prioridad'=>Incidents::PRIORITIES,'estado'=>Incidents::STATES] as $field=>$options) { foreach ($options as $option) { $valid=$data; $valid[$field]=$option; $validId=saved_id($tester->request($path . '/nuevo',$valid)); check($repository->find($validId,['rol'=>'admin'])[$field]===$option,'Opción válida ' . $field . ' ' . $option); } }
    $adminData = $data; $adminData['csrf']=$admin->token($path . '/nuevo'); $adminId=saved_id($admin->request($path . '/nuevo',$adminData)); check((int)$repository->find($adminId,['rol'=>'admin'])['usuario_id']===$adminUserId && $tester->request($path . '/ver?id=' . $adminId)['status']===404,'Admin crea y Tester no accede');
    $adminList=$admin->request($path)['body']; check(str_contains($adminList,'/ver?id=' . $adminId . '"') && str_contains($adminList,'/ver?id=' . $linkedId . '"'),'Admin lista todos');
    foreach (['severidad'=>Incidents::SEVERITIES,'prioridad'=>Incidents::PRIORITIES,'estado'=>Incidents::STATES] as $field=>$options) { foreach ($options as $option) { $body=$tester->request($path . '?' . http_build_query([$field=>$option]))['body']; $visible=$repository->listing(['id'=>$testerId,'rol'=>'tester'],[$field=>$option]); check(count($visible)>0 && count(array_filter($visible,fn($row)=>$row[$field]===$option))===count($visible) && str_contains($body,'/ver?id=' . $visible[0]['id'] . '"') && !str_contains($body,'/ver?id=' . $adminId . '"'),'Filtro ' . $field . ' ' . $option); } }
    $filtered=$tester->request($path . '?estado=Abierto&severidad=Media&prioridad=Alta')['body']; check(str_contains($filtered,'/ver?id=' . $linkedId . '"') && !str_contains($filtered,'/ver?id=' . $id . '"'),'Filtros combinados');
    foreach (['estado=Resuelto','severidad=Urgente','prioridad[]=Alta',"estado=%27%20OR%201%3D1"] as $query) { check($tester->request($path . '?' . $query)['status']===400,'Filtro inválido rechazado ' . $query); }
    $uploadData=$linked; $downloadPath=$path . '/evidencia?id=' . $linkedId;
    foreach ([[$png,'image/png','captura.PNG'],[$jpg,'image/jpeg','captura.jpg'],[$jpg,'image/jpeg','captura.jpeg'],[$pdf,'application/pdf','informe.pdf'],[$txt,'text/plain','informe.txt'],[$log,'text/plain','traza.log'],[$boundary,'text/plain','limite.txt'],[$empty,'text/plain','vacio.txt']] as [$file,$mime,$name]) {
        $old=$repository->find($linkedId,['rol'=>'admin'])['evidencia_archivo']; $uploadData['evidencia']=new CURLFile($file,'application/octet-stream',$name);
        check(saved_id($tester->request($path . '/editar?id=' . $linkedId,$uploadData,true))===$linkedId,'Subir evidencia ' . $name . ' con MIME cliente ignorado'); $stored=$repository->find($linkedId,['rol'=>'admin']); $download=$tester->request($downloadPath);
        check($download['status']===200 && $download['body']===file_get_contents($file) && $stored['evidencia_tipo']===$mime && (int)$stored['evidencia_tamano']===filesize($file),'Descarga y metadatos reales ' . $name);
        check(preg_match('/^[a-f0-9]{40}\.(png|jpg|pdf|txt|log)$/D',$stored['evidencia_archivo'])===1 && ($old===null || !is_file(PROJECT_ROOT . '/.runtime/evidence/' . $old)),'Nombre privado aleatorio y reemplazo limpio ' . $name);
        check(str_starts_with($download['headers']['content-disposition']??'','attachment;') && ($download['headers']['x-content-type-options']??'')==='nosniff' && ($download['headers']['cache-control']??'')==='private, no-store','Descarga forzada privada ' . $name);
    }
    $stored=$repository->find($linkedId,['rol'=>'admin']); $filesBefore=evidence_files(); $count=(int)$pdo->query('SELECT COUNT(*) FROM incidentes')->fetchColumn();
    foreach ([[$png,'prohibido.exe'],[$png,'falso.pdf'],[$bad,'falso.png'],[$binary,'binario.txt'],[$html,'pagina.txt'],[$large,'grande.txt']] as [$file,$name]) { $badUpload=$linked; $badUpload['evidencia']=new CURLFile($file,'image/png',$name); check($tester->request($path . '/nuevo',$badUpload,true)['status']===200 && (int)$pdo->query('SELECT COUNT(*) FROM incidentes')->fetchColumn()===$count,'Crear rechaza archivo ' . $name); check($tester->request($path . '/editar?id=' . $linkedId,$badUpload,true)['status']===200 && $repository->find($linkedId,['rol'=>'admin'])===$stored && evidence_files()===$filesBefore,'Archivo inválido conserva registro/evidencia y no deja huérfanos ' . $name); }
    $invalidUpload=$linked; $invalidUpload['titulo']=''; $invalidUpload['evidencia']=new CURLFile($png,'image/png','captura.png'); check($tester->request($path . '/editar?id=' . $linkedId,$invalidUpload,true)['status']===200 && evidence_files()===$filesBefore,'Campos inválidos no guardan archivo');
    unset($invalidUpload['csrf']); $invalidUpload['titulo']='Válido'; check($tester->request($path . '/nuevo',$invalidUpload,true)['status']===403 && evidence_files()===$filesBefore,'CSRF inválido no guarda archivo');
    check($anon->request($downloadPath)['status']===302 && $other->request($downloadPath)['status']===404 && $admin->request($downloadPath)['status']===200,'Descarga solo creador y Admin');
    check(in_array($anon->request('/.runtime/evidence/' . $stored['evidencia_archivo'])['status'],[403,404],true),'Archivo privado inaccesible por ruta directa');
    check($tester->request($path . '/editar?id=' . $linkedId,$linked)['status']===302 && $repository->find($linkedId,['rol'=>'admin'])['evidencia_archivo']===$stored['evidencia_archivo'],'Editar sin archivo conserva evidencia');
    $remove=$linked; $remove['quitar_evidencia']='1'; $removed=$tester->request($path . '/editar?id=' . $linkedId,$remove); clearstatcache();
    check($removed['status']===302 && $repository->find($linkedId,['rol'=>'admin'])['evidencia_archivo']===null && !is_file(PROJECT_ROOT . '/.runtime/evidence/' . $stored['evidencia_archivo']),'Quitar evidencia limpia metadatos y disco'); check($tester->request($downloadPath)['status']===404,'Sin evidencia 404');
    $uploadData=$remove; $uploadData['evidencia']=new CURLFile($log,'text/plain',"../traza-segura.log"); saved_id($tester->request($path . '/editar?id=' . $linkedId,$uploadData,true)); $stored=$repository->find($linkedId,['rol'=>'admin']); check($stored['evidencia_nombre']==='traza-segura.log' && $stored['evidencia_archivo']!==null,'Archivo nuevo prevalece sobre quitar, nombre sin rutas');
    $deleteCase='/casos/eliminar?id=' . $ownCase; check($admin->request($deleteCase,['csrf'=>$admin->token($deleteCase)])['status']===302 && $repository->find($linkedId,['rol'=>'admin'])['caso_id']===null,'Eliminar caso aplica ON DELETE SET NULL al incidente'); check(is_file(PROJECT_ROOT . '/.runtime/evidence/' . $stored['evidencia_archivo']) && $tester->request($downloadPath)['body']===file_get_contents($log),'Eliminar caso conserva evidencia del incidente');
    $headers=$pdo->query('SELECT * FROM incidentes ORDER BY id')->fetchAll(); $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_10_migration.sql')); check($pdo->query('SELECT * FROM incidentes ORDER BY id')->fetchAll()===$headers,'Migración reejecutable conserva incidentes');
    $delete=$path . '/eliminar?id=' . $linkedId; $deleted=$admin->request($delete,['csrf'=>$admin->token($delete)]); clearstatcache();
    check($deleted['status']===302 && $repository->find($linkedId,['rol'=>'admin'])===null && !is_file(PROJECT_ROOT . '/.runtime/evidence/' . $stored['evidencia_archivo']),'Admin elimina incidente y limpia evidencia');
    $stillLinked=$data; $stillLinked['caso_id']=$adminCase; $stillLinked['csrf']=$adminData['csrf']; $stillId=saved_id($admin->request($path . '/nuevo',$stillLinked)); $delete=$path . '/eliminar?id=' . $stillId; $admin->request($delete,['csrf'=>$admin->token($delete)]); check($repository->canAccessCase($adminCase,['rol'=>'admin']),'Eliminar incidente no elimina caso');
    foreach (['ver','editar','eliminar','evidencia'] as $action) { check($admin->request($path . '/' . $action . '?id=999999999')['status']===404,'Incidente inexistente ' . $action); }
} catch (Throwable $error) { $failed++; fwrite(STDERR,'FAIL ' . $error->getMessage() . "\n"); }
finally {
    foreach ($ids as $id) {
        $record=$repository->find($id,['rol'=>'admin']);
        if ($record) {
            $delete=$path . '/eliminar?id=' . $id;
            $response=$admin->request($delete,['csrf'=>$admin->token($delete)]);
            if ($response['status']!==302) { throw new RuntimeException('No se pudo limpiar incidente temporal ' . $id); }
        }
    }
    foreach ($caseIds as $id) { $pdo->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$id]); }
    if ($userId!==null) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$userId]); }
    foreach (['admin','tester','other','anon'] as $clientName) { if (isset($$clientName)) { $$clientName->close(); } }
    foreach (glob($fixtureDir . '/*')?:[] as $file) { unlink($file); } if (is_dir($fixtureDir)) { rmdir($fixtureDir); }
    foreach ($protected??[] as $table=>$before) { check($pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . ($table==='decision_valores'?'elemento_id, regla_id':'id'))->fetchAll()===$before,'Datos originales conservados: ' . $table); }
    if (isset($originalFiles)) { check(evidence_files()===$originalFiles,'Archivos originales conservados; sin archivos temporales huérfanos'); }
}
echo "Summary incidents: {$passed} passed, {$failed} failed\n"; exit($failed?1:0);
