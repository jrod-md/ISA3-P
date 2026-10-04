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
    if ((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() !== 2 || (int) $pdo->query('SELECT COUNT(*) FROM casos_prueba')->fetchColumn() !== 0) { throw new RuntimeException('Instalación inicial incorrecta'); }
    foreach (['admin' => 'demo-isa3-2026', 'tester' => 'Tester123!'] as $username => $password) {
        $query = $pdo->prepare('SELECT password FROM usuarios WHERE username = ?'); $query->execute([$username]);
        if (!password_verify($password, (string) $query->fetchColumn())) { throw new RuntimeException('Credencial incorrecta: ' . $username); }
    }
    $pdo->exec("INSERT INTO search_sessions (id, criteria_json, created_at, expires_at, ttl_seconds, requested_providers, result_count) VALUES ('sql-installation-sentinel', '{}', UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR), 3600, 'alpha', 0)");
    $pdo->exec("UPDATE usuarios SET nombre = 'Nombre conservado' WHERE username = 'tester'");
    $pdo->exec($sql);
    $pdo->exec('USE `' . $schemas['bus_meta'] . '`');
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
