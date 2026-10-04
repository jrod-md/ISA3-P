<?php
declare(strict_types=1);

namespace Marketplace\Bus\Support;

use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\Database;
use PDO;

final class AdminAuth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionPath = PROJECT_ROOT . '/.runtime/sessions';
            if (!is_dir($sessionPath) && !mkdir($sessionPath, 0770, true) && !is_dir($sessionPath)) {
                throw new \RuntimeException('Could not create the session directory');
            }
            session_save_path($sessionPath);
            session_name('isa3_admin');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'path' => WebPaths::base() . '/',
            ]);
            session_start();
        }
    }

    public static function login(string $username, string $password): bool
    {
        self::start();
        $query = self::database()->prepare('SELECT id, password, rol FROM usuarios WHERE username = ? OR correo = ? LIMIT 1');
        $query->execute([$username, $username]);
        $account = $query->fetch();
        if (!$account || !password_verify($password, $account['password'])) { return false; }
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $account['id'];
        $_SESSION['admin_authenticated'] = $account['rol'] === 'admin';
        unset($_SESSION['csrf']);
        return true;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'httponly' => true, 'secure' => $params['secure'], 'samesite' => 'Strict']);
        session_destroy();
    }

    public static function authenticated(): bool
    {
        return (self::current()['rol'] ?? null) === 'admin';
    }

    private static function database(): PDO { return Database::connect(Config::string('BUS_DB_NAME', 'bus_meta')); }
    public static function current(): ?array
    {
        self::start();
        if (empty($_SESSION['usuario_id'])) { return null; }
        $query = self::database()->prepare('SELECT id, username, nombre, correo, rol FROM usuarios WHERE id = ?');
        $query->execute([$_SESSION['usuario_id']]);
        return $query->fetch() ?: null;
    }
    public static function loggedIn(): bool { return self::current() !== null; }
    public static function csrf(): string
    {
        self::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
}
