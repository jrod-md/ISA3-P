<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::string('DB_HOST', '127.0.0.1'), Config::int('DB_PORT', 3306)), Config::string('DB_USER', 'root'), Config::string('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schemas = ['bus_meta' => Config::string('BUS_DB_NAME', 'bus_meta'), 'market_alpha' => Config::string('ALPHA_DB_NAME', 'market_alpha'), 'market_beta' => Config::string('BETA_DB_NAME', 'market_beta'), 'market_gamma' => Config::string('GAMMA_DB_NAME', 'market_gamma')];
    foreach ($schemas as $schema) { if (!preg_match('/^[a-zA-Z0-9_]+$/D', $schema)) { throw new RuntimeException('Nombre de base de datos no válido'); } }
    $newCatalogs = [];
    foreach (['market_alpha', 'market_beta', 'market_gamma'] as $original) {
        $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = 'products'");
        $query->execute([$schemas[$original]]); if (!(int) $query->fetchColumn()) { $newCatalogs[] = $original; }
    }
    // Conserva los esquemas configurados y las tablas originales.
    foreach (['001_create_databases.sql', '002_create_tables.sql'] as $file) {
        $sql = strtr(file_get_contents(PROJECT_ROOT . '/database/' . $file), $schemas);
        $pdo->exec($sql);
    }
    // Solo inicializa catálogos cuya tabla no existía antes; no reseedea datos.
    $seed = file_get_contents(PROJECT_ROOT . '/database/003_seed_products.sql');
    foreach ($newCatalogs as $original) {
        preg_match('/USE ' . preg_quote($original, '/') . ';(.*?)(?=USE market_|\z)/s', $seed, $match);
        $safe = str_replace('DELETE FROM products;', '', $match[1]);
        $pdo->exec('USE `' . $schemas[$original] . '`;' . str_replace('INSERT INTO products', 'INSERT IGNORE INTO products', $safe));
    }
    echo "Bases del Proyecto 1 preparadas. Catálogos existentes conservados.\n";
    require __DIR__ . '/migrate-proyecto2.php';
} catch (Throwable $error) { fwrite(STDERR, 'Preparación no completada: ' . $error->getMessage() . "\n"); exit(1); }
