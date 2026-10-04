<?php
declare(strict_types=1);
namespace Marketplace\Bus\Support;

use InvalidArgumentException;
use RuntimeException;

/** Shared private storage; callers must authenticate and authorize the document. */
final class PrivateEvidence
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private static function path(?string $file): ?string {
        if (!$file || !preg_match('/^[a-f0-9]{40}\.(?:png|jpg|pdf|txt|log)$/D', $file)) { return null; }
        return PROJECT_ROOT . '/.runtime/evidence/' . $file;
    }
    public static function store(?array $upload, bool $allowText = false): ?array {
        if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) { return null; }
        if (in_array($upload['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) { throw new InvalidArgumentException('La evidencia debe pesar como máximo 2 MB.'); }
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name']) || !is_string($upload['name'] ?? null)) {
            throw new InvalidArgumentException('No se pudo subir la evidencia. Elige un archivo válido de hasta 2 MB.');
        }
        $size = filesize($upload['tmp_name']);
        if ($size === false || $size > self::MAX_BYTES) { throw new InvalidArgumentException('La evidencia debe pesar como máximo 2 MB.'); }
        $name = basename(str_replace('\\', '/', $upload['name']));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'pdf' => 'application/pdf'];
        if ($allowText) { $types += ['txt' => 'text/plain', 'log' => 'text/plain']; }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $plainText = $allowText && in_array($extension, ['txt', 'log'], true);
        // libmagic can classify long plain-text lines as octet-stream. The content
        // check below still requires UTF-8 text without binary control characters.
        $valid = isset($types[$extension]) && ($mime === $types[$extension] || ($plainText && ($mime === 'application/octet-stream' || ($size === 0 && in_array($mime, ['application/x-empty', 'inode/x-empty'], true)))));
        if ($valid && in_array($extension, ['png', 'jpg', 'jpeg'], true)) { $valid = @getimagesize($upload['tmp_name']) !== false; }
        if ($valid && in_array($extension, ['txt', 'log'], true)) {
            $text = file_get_contents($upload['tmp_name']);
            $valid = $text !== false && mb_check_encoding($text, 'UTF-8') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text);
        }
        if (!$valid) { throw new InvalidArgumentException($allowText ? 'La evidencia debe ser PNG, JPG/JPEG, PDF, TXT o LOG, con extensión y contenido válidos; TXT/LOG requieren texto UTF-8.' : 'La evidencia debe ser una imagen PNG, JPG o un documento PDF, con extensión y contenido válidos.'); }
        $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '', 0, 255);
        if ($name === '') { $name = 'evidencia.' . $extension; }
        $directory = PROJECT_ROOT . '/.runtime/evidence';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('No se pudo crear la carpeta privada de evidencias.'); }
        $file = bin2hex(random_bytes(20)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $file)) { throw new RuntimeException('No se pudo guardar la evidencia. Revisa los permisos de .runtime.'); }
        return ['evidencia_archivo' => $file, 'evidencia_nombre' => $name, 'evidencia_tipo' => $types[$extension], 'evidencia_tamano' => $size];
    }
    public static function delete(?string $file): void {
        $path = self::path($file); if ($path !== null && is_file($path)) { unlink($path); }
    }
    public static function download(array $record, string $code): bool {
        $path = self::path($record['evidencia_archivo'] ?? null);
        if ($path === null || !is_file($path)) { return false; }
        header('Content-Type: ' . $record['evidencia_tipo']);
        header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
        header("Content-Disposition: attachment; filename=\"evidencia-" . $code . '.' . pathinfo($path, PATHINFO_EXTENSION) . "\"; filename*=UTF-8''" . rawurlencode($record['evidencia_nombre']));
        header('Content-Length: ' . filesize($path)); readfile($path); return true;
    }
}
