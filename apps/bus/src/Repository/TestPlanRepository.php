<?php
declare(strict_types=1);
namespace Marketplace\Bus\Repository;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class TestPlanRepository
{
    public const FIELDS = [
        'nombre_proyecto' => 'Nombre del Proyecto', 'version' => 'Versión',
        'responsable' => 'Responsable', 'fecha' => 'Fecha',
        'alcance' => 'Alcance', 'objetivos' => 'Objetivos',
        'estrategia' => 'Estrategia de Pruebas', 'recursos' => 'Recursos',
        'criterios_aceptacion' => 'Criterios de Aceptación', 'riesgos' => 'Riesgos',
    ];
    public const SECTIONS = [
        'Información general' => ['nombre_proyecto', 'version', 'responsable', 'fecha'],
        'Definición' => ['alcance', 'objetivos'],
        'Estrategia' => ['estrategia', 'recursos'],
        'Cierre' => ['criterios_aceptacion', 'riesgos'],
    ];
    public function __construct(private PDO $pdo) {}
    public static function limit(string $field): int { return match ($field) { 'nombre_proyecto', 'responsable' => 150, 'version' => 50, default => 5000 }; }
    public static function blank(): array { return array_fill_keys(array_keys(self::FIELDS), '') + ['cronograma' => [['actividad' => '', 'fecha_inicio' => '', 'fecha_fin' => '']]]; }
    private static function text(mixed $value, string $label, int $limit): string {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $limit) { throw new InvalidArgumentException($label . ': completa el campo (máximo ' . $limit . ' caracteres).'); }
        return trim($value);
    }
    private static function date(mixed $value, string $label): string {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || (int) substr($value, 0, 4) < 1000) { throw new InvalidArgumentException($label . ': ingresa una fecha válida.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new InvalidArgumentException($label . ': ingresa una fecha válida.'); }
        return $value;
    }
    public static function validate(array $data): array {
        $clean = [];
        foreach (self::FIELDS as $field => $label) { $clean[$field] = $field === 'fecha' ? self::date($data[$field] ?? null, $label) : self::text($data[$field] ?? null, $label, self::limit($field)); }
        $rows = $data['cronograma'] ?? null;
        if (!is_array($rows) || count($rows) < 1 || count($rows) > 30) { throw new InvalidArgumentException('Cronograma: agrega entre 1 y 30 actividades.'); }
        $clean['cronograma'] = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) { throw new InvalidArgumentException('Cronograma: fila no válida.'); }
            $label = 'Actividad ' . ($index + 1);
            $item = ['actividad' => self::text($row['actividad'] ?? null, $label, 250), 'fecha_inicio' => self::date($row['fecha_inicio'] ?? null, $label . ' · Fecha de Inicio'), 'fecha_fin' => self::date($row['fecha_fin'] ?? null, $label . ' · Fecha de Fin')];
            if ($item['fecha_fin'] < $item['fecha_inicio']) { throw new InvalidArgumentException($label . ': Fecha de Fin no puede ser anterior a Fecha de Inicio.'); }
            $clean['cronograma'][] = $item;
        }
        return $clean;
    }
    public function listing(array $user): array {
        $sql = 'SELECT p.*, u.nombre AS creador FROM planes_prueba p JOIN usuarios u ON u.id = p.usuario_id';
        $query = $this->pdo->prepare($sql . ($user['rol'] === 'admin' ? '' : ' WHERE p.usuario_id = ?') . ' ORDER BY p.id DESC');
        $query->execute($user['rol'] === 'admin' ? [] : [$user['id']]); return $query->fetchAll();
    }
    public function find(int $id, array $user, bool $lock = false): ?array {
        $sql = 'SELECT p.*, u.nombre AS creador FROM planes_prueba p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?'; $params = [$id];
        if ($user['rol'] !== 'admin') { $sql .= ' AND p.usuario_id = ?'; $params[] = $user['id']; }
        $query = $this->pdo->prepare($sql . ($lock ? ' FOR UPDATE' : '')); $query->execute($params); return $query->fetch() ?: null;
    }
    public function schedule(int $id): array {
        $query = $this->pdo->prepare('SELECT * FROM plan_cronograma WHERE plan_id = ? ORDER BY orden'); $query->execute([$id]); return $query->fetchAll();
    }
    public function save(array $user, array $data, ?int $id = null): int {
        $data = self::validate($data); $fields = array_keys(self::FIELDS);
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) {
                if (!$this->find($id, $user, true)) { throw new RuntimeException('Plan no disponible.'); }
                // Only document fields are updated; the original creator is immutable.
                $query = $this->pdo->prepare('UPDATE planes_prueba SET ' . implode(', ', array_map(fn ($field) => $field . ' = ?', $fields)) . ', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?');
                $query->execute([...array_map(fn ($field) => $data[$field], $fields), $id]);
            } else {
                $query = $this->pdo->prepare('INSERT INTO planes_prueba (usuario_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')');
                $query->execute([$user['id'], ...array_map(fn ($field) => $data[$field], $fields)]); $id = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('DELETE FROM plan_cronograma WHERE plan_id = ?')->execute([$id]);
            $insert = $this->pdo->prepare('INSERT INTO plan_cronograma (plan_id, actividad, fecha_inicio, fecha_fin, orden) VALUES (?, ?, ?, ?, ?)');
            foreach ($data['cronograma'] as $order => $row) { $insert->execute([$id, $row['actividad'], $row['fecha_inicio'], $row['fecha_fin'], $order]); }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }
    public function delete(int $id, array $user): bool {
        if ($user['rol'] !== 'admin') { throw new RuntimeException('La eliminación requiere Administrador.'); }
        $query = $this->pdo->prepare('DELETE FROM planes_prueba WHERE id = ?'); $query->execute([$id]); return $query->rowCount() === 1;
    }
}
