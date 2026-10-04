<?php
declare(strict_types=1);
namespace Marketplace\Bus\Repository;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Marketplace\Bus\Support\PrivateEvidence;

final class IncidentRepository
{
    public const SEVERITIES = ['Crítica', 'Alta', 'Media', 'Baja'];
    public const PRIORITIES = ['Alta', 'Media', 'Baja'];
    public const STATES = ['Abierto', 'En progreso', 'Cerrado'];
    public const FIELDS = ['titulo' => 'Título', 'modulo' => 'Módulo', 'severidad' => 'Severidad', 'prioridad' => 'Prioridad', 'descripcion' => 'Descripción', 'pasos_reproducir' => 'Pasos para Reproducir', 'resultado_esperado' => 'Resultado Esperado', 'resultado_obtenido' => 'Resultado Obtenido', 'estado' => 'Estado', 'asignado_a' => 'Asignado a'];
    public function __construct(private PDO $pdo) {}
    public static function code(int $id): string { return 'BUG-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT); }
    public static function limit(string $field): int { return in_array($field, ['descripcion', 'pasos_reproducir', 'resultado_esperado', 'resultado_obtenido'], true) ? 5000 : ($field === 'modulo' ? 150 : 250); }
    public static function blank(): array { return array_fill_keys(array_keys(self::FIELDS), '') + ['caso_id' => null]; }
    public static function choices(string $field): array { return match ($field) { 'severidad' => self::SEVERITIES, 'prioridad' => self::PRIORITIES, 'estado' => self::STATES, default => [] }; }
    public static function caseId(mixed $value): ?int {
        if ($value === null || $value === '') { return null; }
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value) || (float) $value > 4294967295) { throw new InvalidArgumentException('Selecciona un caso válido o deja el incidente sin caso asociado.'); }
        return (int) $value;
    }
    public static function validate(array $data): array {
        $clean = [];
        foreach (self::FIELDS as $field => $label) {
            $value = $data[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > self::limit($field)) { throw new InvalidArgumentException($label . ': completa el campo (máximo ' . self::limit($field) . ' caracteres).'); }
            $clean[$field] = trim($value);
            if (self::choices($field) && !in_array($clean[$field], self::choices($field), true)) { throw new InvalidArgumentException($label . ': selecciona un valor válido.'); }
        }
        $clean['caso_id'] = self::caseId($data['caso_id'] ?? null); return $clean;
    }
    public function accessibleCases(array $user): array {
        $query = $this->pdo->prepare('SELECT id, modulo FROM casos_prueba' . ($user['rol'] === 'admin' ? '' : ' WHERE usuario_id = ?') . ' ORDER BY id DESC'); $query->execute($user['rol'] === 'admin' ? [] : [$user['id']]); return $query->fetchAll();
    }
    public function canAccessCase(int $id, array $user): bool {
        $query = $this->pdo->prepare('SELECT id FROM casos_prueba WHERE id = ?' . ($user['rol'] === 'admin' ? '' : ' AND usuario_id = ?')); $query->execute($user['rol'] === 'admin' ? [$id] : [$id, $user['id']]); return (bool) $query->fetchColumn();
    }
    public function listing(array $user, array $filters = [], ?int $caseId = null): array {
        $where = []; $params = [];
        if ($user['rol'] !== 'admin') { $where[] = 'i.usuario_id = ?'; $params[] = $user['id']; }
        if ($caseId !== null) { $where[] = 'i.caso_id = ?'; $params[] = $caseId; }
        foreach (['estado', 'severidad', 'prioridad'] as $field) {
            $value = $filters[$field] ?? ''; if ($value === '') { continue; }
            if (!is_string($value) || !in_array($value, self::choices($field), true)) { throw new InvalidArgumentException('Filtro ' . $field . ' no válido.'); }
            $where[] = 'i.' . $field . ' = ?'; $params[] = $value;
        }
        $query = $this->pdo->prepare('SELECT i.*, u.nombre AS creador FROM incidentes i JOIN usuarios u ON u.id = i.usuario_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.id DESC'); $query->execute($params); return $query->fetchAll();
    }
    public function find(int $id, array $user, bool $lock = false): ?array {
        $query = $this->pdo->prepare('SELECT i.*, u.nombre AS creador FROM incidentes i JOIN usuarios u ON u.id = i.usuario_id WHERE i.id = ?' . ($user['rol'] === 'admin' ? '' : ' AND i.usuario_id = ?') . ($lock ? ' FOR UPDATE' : ''));
        $query->execute($user['rol'] === 'admin' ? [$id] : [$id, $user['id']]); return $query->fetch() ?: null;
    }
    public function save(array $user, array $data, ?int $id = null, ?array $upload = null, bool $remove = false): int {
        $clean = self::validate($data); $newFile = null; $previous = null;
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) { $previous = $this->find($id, $user, true); if (!$previous) { throw new RuntimeException('Incidente no disponible.'); } }
            if ($clean['caso_id'] !== null) {
                // Lock the case until commit. A Tester may retain an existing association
                // made by Admin, but cannot newly attach a case belonging to another user.
                $query = $this->pdo->prepare('SELECT usuario_id FROM casos_prueba WHERE id = ? FOR UPDATE'); $query->execute([$clean['caso_id']]); $owner = $query->fetchColumn();
                if ($owner === false || ($user['rol'] !== 'admin' && (int) $owner !== (int) $user['id'] && $clean['caso_id'] !== ($previous['caso_id'] ?? null))) { throw new InvalidArgumentException('El caso seleccionado no está disponible para tu cuenta.'); }
            }
            $evidence = PrivateEvidence::store($upload, true); $newFile = $evidence['evidencia_archivo'] ?? null;
            foreach (['evidencia_archivo', 'evidencia_nombre', 'evidencia_tipo', 'evidencia_tamano'] as $field) { $clean[$field] = $evidence[$field] ?? ($remove ? null : ($previous[$field] ?? null)); }
            $fields = array_keys($clean);
            if ($id !== null) {
                $query = $this->pdo->prepare('UPDATE incidentes SET ' . implode(', ', array_map(fn ($field) => $field . ' = ?', $fields)) . ', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?'); $query->execute([...array_values($clean), $id]);
            } else {
                $query = $this->pdo->prepare('INSERT INTO incidentes (usuario_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')'); $query->execute([$user['id'], ...array_values($clean)]); $id = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->commit();
        } catch (\Throwable $error) { $this->pdo->rollBack(); PrivateEvidence::delete($newFile); throw $error; }
        if ($previous && ($newFile !== null || $remove)) { PrivateEvidence::delete($previous['evidencia_archivo']); }
        return $id;
    }
    public function delete(int $id, array $user): bool {
        if ($user['rol'] !== 'admin') { throw new RuntimeException('La eliminación requiere Administrador.'); }
        $this->pdo->beginTransaction();
        try {
            $record = $this->find($id, $user, true);
            if (!$record) { $this->pdo->commit(); return false; }
            $this->pdo->prepare('DELETE FROM incidentes WHERE id = ?')->execute([$id]); $this->pdo->commit();
        } catch (\Throwable $error) { $this->pdo->rollBack(); throw $error; }
        PrivateEvidence::delete($record['evidencia_archivo']); return true;
    }
}
