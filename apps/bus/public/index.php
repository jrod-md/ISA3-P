<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && str_starts_with($path, '/assets/') && !str_contains($path, '..') && is_file(__DIR__ . $path)) {
    return false;
}

require dirname(__DIR__, 3) . '/bootstrap.php';

use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Controller\AdminController;
use Marketplace\Bus\Controller\SearchController;
use Marketplace\Bus\Support\AppFactory;
use Marketplace\Bus\Support\WebPaths;
use Marketplace\Shared\Support\JsonResponse;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = WebPaths::path();

if ($method === 'GET' && $path === '/health') {
    try {
        $counts = AppFactory::repository()->counts();
        JsonResponse::send(['status' => 'ok', 'service' => 'marketplace-search-bus', 'database' => 'ok', 'temporary_data' => $counts]);
    } catch (Throwable $exception) {
        error_log('Bus health error: ' . $exception->getMessage());
        JsonResponse::error('SERVICE_UNAVAILABLE', 'Bus database is unavailable', 503);
    }
}

if ($method === 'GET' && $path === '/api/search') {
    (new SearchController())->search();
}
if ($method === 'GET' && preg_match('#^/api/search/([0-9a-f-]{36})$#i', $path, $matches)) {
    (new SearchController())->cached($matches[1]);
}

$admin = new AdminController();
if ($method === 'POST' && $path === '/api/admin/login') { $admin->login(); }
if ($method === 'POST' && $path === '/api/admin/logout') { $admin->logout(); }
if ($method === 'GET' && $path === '/api/admin/status') { $admin->status(); }
if ($method === 'GET' && $path === '/api/admin/searches') { $admin->list(); }
if ($method === 'DELETE' && $path === '/api/admin/searches/expired') { $admin->deleteExpired(); }
if ($method === 'DELETE' && $path === '/api/admin/searches') { $admin->deleteAll(); }
if ($method === 'DELETE' && preg_match('#^/api/admin/searches/([0-9a-f-]{36})$#i', $path, $matches)) { $admin->delete($matches[1]); }

$testingRoutes = [
    '/formularios' => 'formularios/index.php',
    '/dashboard' => 'dashboard.php', '/usuarios' => 'usuarios.php', '/registro' => 'register.php', '/busquedas' => 'busquedas.php',
    '/casos' => 'casos/index.php', '/casos/nuevo' => 'casos/crear.php',
    '/casos/ver' => 'casos/ver.php', '/casos/editar' => 'casos/editar.php',
    '/casos/eliminar' => 'casos/eliminar.php', '/casos/evidencia' => 'casos/evidencia.php',
];
if (preg_match('#^/formularios/plan(?:/(nuevo|ver|editar|eliminar))?$#', $path, $matches)) {
    if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit('Método no permitido'); }
    $planAction = $matches[1] ?? 'listado';
    if ($method === 'POST' && in_array($planAction, ['listado', 'ver'], true)) { http_response_code(405); header('Allow: GET'); exit('Método no permitido'); }
    require dirname(__DIR__) . '/testing/formularios/plan.php'; exit;
}
if (preg_match('#^/formularios/(equivalencia|limites|decision|cobertura)(?:/(nuevo|ver|editar|eliminar))?$#', $path, $matches)) {
    if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit('Método no permitido'); }
    $matrixType = $matches[1]; $matrixAction = $matches[2] ?? 'listado';
    if ($method === 'POST' && in_array($matrixAction, ['listado', 'ver'], true)) { http_response_code(405); header('Allow: GET'); exit('Método no permitido'); }
    require dirname(__DIR__) . '/testing/formularios/documento.php'; exit;
}
if (isset($testingRoutes[$path])) {
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405); header('Allow: GET, POST'); exit('Método no permitido');
    }
    require dirname(__DIR__) . '/testing/' . $testingRoutes[$path];
    exit;
}

if ($method === 'GET' && ($path === '/' || $path === '/index.php')) {
    require __DIR__ . '/search-page.php';
    exit;
}
if ($method === 'GET' && in_array($path, ['/admin', '/admin.php', '/login'], true)) {
    require __DIR__ . '/admin.php';
    exit;
}

if (str_starts_with($path, '/api/')) {
    JsonResponse::error('NOT_FOUND', 'Endpoint not found', 404);
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<h1>404</h1><p>Ruta no encontrada.</p>';
