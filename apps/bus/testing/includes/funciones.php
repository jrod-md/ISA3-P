<?php
declare(strict_types=1);
use Marketplace\Bus\Support\AdminAuth;
use Marketplace\Bus\Support\WebPaths;
use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;

function db(): PDO { static $pdo = null; return $pdo ??= Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')); }
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path): string { return WebPaths::url($path); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function post_text(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : ''; }
function user(): ?array { return AdminAuth::current(); }
function csrf_token(): string { return AdminAuth::csrf(); }
function csrf_field(): void { echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals(csrf_token(), $_POST['csrf'])) {
        abort_page(403, 'Solicitud no válida', 'Vuelve a abrir el formulario e inténtalo de nuevo.');
    }
}
function require_login(): array {
    $current = user();
    if (!$current) { redirect('/admin'); }
    return $current;
}
function require_admin(): array {
    $current = require_login();
    if ($current['rol'] !== 'admin') { abort_page(403, 'Acceso restringido', 'Esta sección requiere el rol Administrador.'); }
    return $current;
}
function abort_page(int $status, string $heading, string $message): never {
    http_response_code($status); $title = $heading;
    require TESTING_PATH . '/includes/auth_header.php';
    echo '<h2>' . e($heading) . '</h2><p>' . e($message) . '</p><a class="button secondary" href="' . e(url('/dashboard')) . '">Volver al dashboard</a>';
    require TESTING_PATH . '/includes/auth_footer.php'; exit;
}
function flash(string $message): void { $_SESSION['flash'] = $message; }
function code_case(int $id): string { return 'CP-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT); }
function date_display(string $date): string {
    return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m/Y · H:i');
}
function case_by_id(): array {
    $current = require_login();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) { abort_page(404, 'Caso no encontrado', 'El caso solicitado no está disponible.'); }
    $sql = 'SELECT c.*, u.nombre AS tester FROM casos_prueba c JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = ?';
    $params = [$id];
    if ($current['rol'] !== 'admin') { $sql .= ' AND c.usuario_id = ?'; $params[] = $current['id']; }
    $query = db()->prepare($sql); $query->execute($params); $case = $query->fetch();
    if (!$case) { abort_page(404, 'Caso no encontrado', 'El caso no está disponible para tu cuenta.'); }
    return $case;
}
function errors_block(array $errors): void {
    if (!$errors) { return; }
    echo '<div class="message error" role="alert"><strong>Revisa los siguientes datos:</strong><ul>';
    foreach ($errors as $error) { echo '<li>' . e($error) . '</li>'; }
    echo '</ul></div>';
}
function delete_evidence(?string $file): void {
    Marketplace\Bus\Support\PrivateEvidence::delete($file);
}
