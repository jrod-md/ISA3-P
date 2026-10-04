<?php
declare(strict_types=1);
namespace Marketplace\Bus\Support;

final class WebPaths
{
    public static function base(): string
    {
        if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') { return ''; }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $suffix = '/apps/bus/public/index.php';
        return str_ends_with($script, $suffix) ? substr($script, 0, -strlen($suffix)) : '';
    }
    public static function url(string $path): string { return self::base() . $path; }
    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::base();
        return $base && ($path === $base || str_starts_with($path, $base . '/')) ? (substr($path, strlen($base)) ?: '/') : $path;
    }
}
