<?php
declare(strict_types=1);

define('PROJECT_ROOT', __DIR__);

error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Marketplace\\Bus\\' => PROJECT_ROOT . '/apps/bus/src/',
        'Marketplace\\Provider\\' => PROJECT_ROOT . '/services/common/src/',
        'Marketplace\\Shared\\' => PROJECT_ROOT . '/shared/src/',
    ];

    foreach ($prefixes as $prefix => $baseDirectory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDirectory . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
});

Marketplace\Bus\Config\Config::load(PROJECT_ROOT . '/.env');
