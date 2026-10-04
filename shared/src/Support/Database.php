<?php
declare(strict_types=1);

namespace Marketplace\Shared\Support;

use Marketplace\Bus\Config\Config;
use PDO;

final class Database
{
    public static function connect(string $schema): PDO
    {
        $host = Config::string('DB_HOST', '127.0.0.1');
        $port = Config::int('DB_PORT', 3306);
        $dsn = "mysql:host={$host};port={$port};dbname={$schema};charset=utf8mb4";
        $pdo = new PDO($dsn, Config::string('DB_USER', 'root'), Config::string('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}

