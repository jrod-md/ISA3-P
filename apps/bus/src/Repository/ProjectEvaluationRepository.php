<?php
declare(strict_types=1);
namespace Marketplace\Bus\Repository;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Small shared persistence for the three project-level evaluation documents. */
final class ProjectEvaluationRepository
{
    public const RUBRIC = [
        'diseno' => ['Diseño de casos', 'Todos los casos bien documentados y justificados', 'La mayoría bien documentados', 'Algunos casos documentados', 'Casos incompletos o ausentes'],
        'tecnicas' => ['Aplicación de técnicas', 'Aplica correctamente caja negra y blanca', 'Aplica la mayoría correctamente', 'Aplica parcialmente', 'No aplica correctamente'],
        'cobertura' => ['Cobertura', '>90%', '70–90%', '50–70%', '<50%'],
        'herramientas' => ['Uso de herramientas', 'Domina Selenium, PHPUnit, JMeter, TestCover', 'Usa la mayoría', 'Usa algunas', 'No usa herramientas'],
        'documentacion' => ['Documentación', 'Completa, clara y organizada', 'Completa pero poco clara', 'Incompleta', 'Ausente'],
        'presentacion' => ['Presentación', 'Excelente comunicación', 'Buena comunicación', 'Comunicación regular', 'Deficiente'],
    ];
    public const ASPECTS = [
        'conceptos' => 'Comprensión de conceptos', 'tecnicas' => 'Aplicación de técnicas',
        'equipo' => 'Trabajo en equipo', 'herramientas' => 'Uso de herramientas',
        'documentacion' => 'Calidad de documentación', 'plazos' => 'Cumplimiento de plazos',
    ];
    // Identifiers come exclusively from this internal catalog, never from request data.
    public const TYPES = [
        'rubrica' => ['number' => 7, 'title' => 'Rúbrica de Evaluación', 'purpose' => 'Evaluación sumativa del desempeño en el proyecto. Selecciona cada puntuación según tu valoración.', 'table' => 'rubricas', 'child' => 'rubrica_criterios', 'fk' => 'rubrica_id', 'fields' => ['titulo' => 'Título de la evaluación', 'evaluado' => 'Persona evaluada', 'fecha' => 'Fecha', 'observaciones' => 'Observaciones generales'], 'row_fields' => ['criterio', 'puntuacion', 'observacion']],
        'evaluacion' => ['number' => 8, 'title' => 'Autoevaluación y Coevaluación', 'purpose' => 'Evaluación formativa: reflexiona sobre el aprendizaje y registra la valoración entre pares.', 'table' => 'evaluaciones_pares', 'child' => 'evaluacion_aspectos', 'fk' => 'evaluacion_id', 'fields' => ['evaluado' => 'Persona evaluada', 'evaluador' => 'Co-evaluador', 'fecha' => 'Fecha'], 'row_fields' => ['aspecto', 'autoevaluacion', 'coevaluacion', 'comentarios']],
        'portafolio' => ['number' => 9, 'title' => 'Portafolio de Evidencias', 'purpose' => 'Organiza las evidencias de aprendizaje por semana. Evidencia es su nombre o descripción; no requiere un archivo.', 'table' => 'portafolios', 'child' => 'portafolio_evidencias', 'fk' => 'portafolio_id', 'fields' => ['titulo' => 'Título del portafolio'], 'row_fields' => ['semana', 'evidencia', 'tipo', 'fecha', 'observaciones']],
    ];
    private array $config;
    public function __construct(private PDO $pdo, private string $type) {
        $this->config = self::TYPES[$type] ?? throw new InvalidArgumentException('Documento no válido.');
    }
    public static function blank(string $type): array {
        $config = self::TYPES[$type]; $data = array_fill_keys(array_keys($config['fields']), ''); $data['filas'] = [];
        if ($type === 'portafolio') { $data['filas'][] = array_fill_keys($config['row_fields'], ''); }
        else {
            foreach ($type === 'rubrica' ? self::RUBRIC : self::ASPECTS as $key => $label) {
                $data['filas'][$key] = array_fill_keys(array_slice($config['row_fields'], 1), '');
            }
        }
        return $data;
    }
    public static function limit(string $field): int { return in_array($field, ['observacion', 'observaciones', 'comentarios'], true) ? 5000 : 250; }
    private static function text(mixed $value, string $label, int $limit, bool $optional = false): string {
        if (!is_string($value) || (!$optional && trim($value) === '') || mb_strlen(trim($value)) > $limit) { throw new InvalidArgumentException($label . ': ingresa texto válido (máximo ' . $limit . ' caracteres).'); }
        return trim($value);
    }
    private static function integer(mixed $value, string $label, int $max): int {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value) || (int) $value > $max) { throw new InvalidArgumentException($label . ': ingresa un entero entre 1 y ' . $max . '.'); }
        return (int) $value;
    }
    private static function date(mixed $value): string {
        if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || (int) substr($value, 0, 4) < 1000) { throw new InvalidArgumentException('Fecha: ingresa una fecha válida.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new InvalidArgumentException('Fecha: ingresa una fecha válida.'); }
        return $value;
    }
    public function validate(array $data): array {
        $clean = [];
        foreach ($this->config['fields'] as $field => $label) {
            $clean[$field] = $field === 'fecha' ? self::date($data[$field] ?? null) : self::text($data[$field] ?? '', $label, self::limit($field), in_array($field, ['evaluador', 'observaciones'], true));
        }
        $rows = $data['filas'] ?? null;
        if (!is_array($rows)) { throw new InvalidArgumentException('Completa las filas del documento.'); }
        $clean['filas'] = [];
        if ($this->type === 'portafolio') {
            if (count($rows) < 1 || count($rows) > 30) { throw new InvalidArgumentException('Agrega entre 1 y 30 evidencias.'); }
            foreach (array_values($rows) as $row) {
                if (!is_array($row)) { throw new InvalidArgumentException('Evidencia no válida.'); }
                $item = ['semana' => self::integer($row['semana'] ?? null, 'Semana', 52)];
                foreach (['evidencia', 'tipo', 'observaciones'] as $field) { $item[$field] = self::text($row[$field] ?? '', ucfirst($field), self::limit($field), $field === 'observaciones'); }
                $item['fecha'] = self::date($row['fecha'] ?? null); $clean['filas'][] = $item;
            }
        } else {
            $definitions = $this->type === 'rubrica' ? self::RUBRIC : self::ASPECTS;
            if (count($rows) !== 6 || array_diff(array_keys($rows), array_keys($definitions)) || array_diff(array_keys($definitions), array_keys($rows))) { throw new InvalidArgumentException('Registra exactamente los seis criterios/aspectos indicados.'); }
            foreach ($definitions as $key => $label) {
                $row = $rows[$key]; if (!is_array($row)) { throw new InvalidArgumentException('Criterio/aspecto no válido.'); }
                if ($this->type === 'rubrica') {
                    $clean['filas'][] = ['criterio' => $key, 'puntuacion' => self::integer($row['puntuacion'] ?? null, $label[0], 5), 'observacion' => self::text($row['observacion'] ?? '', 'Observación', 5000, true)];
                } else {
                    $clean['filas'][] = ['aspecto' => $key, 'autoevaluacion' => self::integer($row['autoevaluacion'] ?? null, $label . ' · Autoevaluación', 5), 'coevaluacion' => self::integer($row['coevaluacion'] ?? null, $label . ' · Coevaluación', 5), 'comentarios' => self::text($row['comentarios'] ?? '', 'Comentarios', 5000, true)];
                }
            }
            if ($this->type === 'rubrica') { $clean['total'] = array_sum(array_column($clean['filas'], 'puntuacion')); }
            else {
                $clean['promedio_auto'] = round(array_sum(array_column($clean['filas'], 'autoevaluacion')) / 6, 2);
                $clean['promedio_co'] = round(array_sum(array_column($clean['filas'], 'coevaluacion')) / 6, 2);
            }
        }
        return $clean;
    }
    public function listing(array $user): array {
        $query = $this->pdo->prepare('SELECT p.*, u.nombre AS creador FROM ' . $this->config['table'] . ' p JOIN usuarios u ON u.id = p.usuario_id' . ($user['rol'] === 'admin' ? '' : ' WHERE p.usuario_id = ?') . ' ORDER BY p.id DESC');
        $query->execute($user['rol'] === 'admin' ? [] : [$user['id']]); return $query->fetchAll();
    }
    public function find(int $id, array $user, bool $lock = false): ?array {
        $query = $this->pdo->prepare('SELECT p.*, u.nombre AS creador FROM ' . $this->config['table'] . ' p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?' . ($user['rol'] === 'admin' ? '' : ' AND p.usuario_id = ?') . ($lock ? ' FOR UPDATE' : ''));
        $query->execute($user['rol'] === 'admin' ? [$id] : [$id, $user['id']]); return $query->fetch() ?: null;
    }
    public function rows(int $id): array {
        $query = $this->pdo->prepare('SELECT * FROM ' . $this->config['child'] . ' WHERE ' . $this->config['fk'] . ' = ? ORDER BY orden'); $query->execute([$id]); return $query->fetchAll();
    }
    public function save(array $user, array $data, ?int $id = null): int {
        $clean = $this->validate($data); $rows = $clean['filas']; unset($clean['filas']); $fields = array_keys($clean);
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) {
                if (!$this->find($id, $user, true)) { throw new RuntimeException('Documento no disponible.'); }
                $query = $this->pdo->prepare('UPDATE ' . $this->config['table'] . ' SET ' . implode(', ', array_map(fn ($field) => $field . ' = ?', $fields)) . ', actualizado_en = CURRENT_TIMESTAMP WHERE id = ?'); $query->execute([...array_values($clean), $id]);
            } else {
                $query = $this->pdo->prepare('INSERT INTO ' . $this->config['table'] . ' (usuario_id, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 1, '?')) . ')'); $query->execute([$user['id'], ...array_values($clean)]); $id = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('DELETE FROM ' . $this->config['child'] . ' WHERE ' . $this->config['fk'] . ' = ?')->execute([$id]);
            $rowFields = $this->config['row_fields'];
            $insert = $this->pdo->prepare('INSERT INTO ' . $this->config['child'] . ' (' . $this->config['fk'] . ', ' . implode(', ', $rowFields) . ', orden) VALUES (' . implode(', ', array_fill(0, count($rowFields) + 2, '?')) . ')');
            foreach ($rows as $order => $row) { $insert->execute([$id, ...array_map(fn ($field) => $row[$field], $rowFields), $order]); }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }
    public function delete(int $id, array $user): bool {
        if ($user['rol'] !== 'admin') { throw new RuntimeException('La eliminación requiere Administrador.'); }
        $query = $this->pdo->prepare('DELETE FROM ' . $this->config['table'] . ' WHERE id = ?'); $query->execute([$id]); return $query->rowCount() === 1;
    }
}
