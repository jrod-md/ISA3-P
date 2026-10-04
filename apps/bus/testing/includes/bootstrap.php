<?php
declare(strict_types=1);
require_once dirname(__DIR__, 4) . '/bootstrap.php';

use Marketplace\Bus\Support\AdminAuth;

define('ROOT_PATH', PROJECT_ROOT);
define('TESTING_PATH', dirname(__DIR__));
AdminAuth::start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/funciones.php';
set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    http_response_code(503);
    $title = 'Solicitud no completada';
    require TESTING_PATH . '/includes/auth_header.php';
    echo '<h2>No se pudo completar la solicitud</h2><p>Revisa la conexión del Proyecto 1 y la migración del Proyecto 2. Consulta el README de la copia integrada.</p>';
    require TESTING_PATH . '/includes/auth_footer.php';
});
