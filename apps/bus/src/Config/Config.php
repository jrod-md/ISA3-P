<?php
declare(strict_types=1);

namespace Marketplace\Bus\Config;

final class Config
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    $value = substr($value, 1, -1);
                }
                if (getenv($key) === false) {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function string(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false) {
            if ($default === null) {
                throw new \RuntimeException("Missing configuration: {$key}");
            }
            return $default;
        }
        return (string) $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = getenv($key);
        return $value === false || $value === '' ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

