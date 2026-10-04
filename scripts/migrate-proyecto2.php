<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;

try {
    $pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/proyecto2_migration.sql'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formularios_2_4_migration.sql'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_5_migration.sql'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_6_migration.sql'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formularios_7_9_migration.sql'));
    $pdo->exec(file_get_contents(PROJECT_ROOT . '/sql/formulario_10_migration.sql'));
    $accounts = [
        [Config::string('ADMIN_USERNAME', 'admin'), 'Administrador', 'admin@isa3.local', Config::string('ADMIN_PASSWORD', 'demo-isa3-2026'), 'admin'],
        ['tester', 'Tester Demo', 'tester@isa3.local', 'Tester123!', 'tester'],
    ];
    foreach ($accounts as [$username, $name, $email, $password, $role]) {
        $find = $pdo->prepare('SELECT id FROM usuarios WHERE username = ? OR correo = ?');
        $find->execute([$username, $email]);
        if ($find->fetch()) { continue; }
        $pdo->prepare('INSERT INTO usuarios (username, nombre, correo, password, rol) VALUES (?, ?, ?, ?, ?)')
            ->execute([$username, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    echo "Proyecto 2 integrado en la base del Bus. Datos existentes conservados.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migración no completada: ' . $error->getMessage() . "\n"); exit(1);
}
