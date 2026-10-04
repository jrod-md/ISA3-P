<?php
declare(strict_types=1);
namespace Marketplace\Bus\Repository;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class BlackBoxRepository
{
    public const TYPES = ['equivalencia' => 2, 'limites' => 3, 'decision' => 4];
    public const FIELDS = [
        'equivalencia' => ['campo' => 'Campo', 'clase_valida' => 'Clase Válida', 'clases_invalidas' => 'Clases Inválidas', 'valores_representativos' => 'Valores Representativos', 'resultado_esperado' => 'Resultado Esperado'],
        'limites' => ['campo' => 'Campo', 'rango_valido' => 'Rango Válido', 'valor_minimo' => 'Valor Mínimo', 'valor_maximo' => 'Valor Máximo', 'valores_limite' => 'Valores Límite a Probar', 'resultado_esperado' => 'Resultado Esperado'],
    ];
    private const TABLES = ['equivalencia' => 'equivalencia_filas', 'limites' => 'limite_filas'];

    public function __construct(private PDO $pdo) {}

    public function cases(array $user): array
    {
        $sql = 'SELECT id, modulo FROM casos_prueba';
        $query = $this->pdo->prepare($sql . ($user['rol'] === 'admin' ? '' : ' WHERE usuario_id = ?') . ' ORDER BY id DESC');
        $query->execute($user['rol'] === 'admin' ? [] : [$user['id']]);
        return $query->fetchAll();
    }

    public function listing(string $type, array $user, ?int $caseId = null): array
    {
        $sql = 'SELECT f.*, c.modulo, u.nombre AS autor FROM formularios_prueba f JOIN casos_prueba c ON c.id = f.caso_id JOIN usuarios u ON u.id = f.usuario_id WHERE f.tipo = ?';
        $params = [$type];
        if ($user['rol'] !== 'admin') { $sql .= ' AND f.usuario_id = ?'; $params[] = $user['id']; }
        if ($caseId !== null) { $sql .= ' AND f.caso_id = ?'; $params[] = $caseId; }
        $query = $this->pdo->prepare($sql . ' ORDER BY f.id DESC'); $query->execute($params);
        return $query->fetchAll();
    }

    public function find(int $id, string $type, array $user, bool $lock = false): ?array
    {
        $sql = 'SELECT f.*, c.modulo, u.nombre AS autor FROM formularios_prueba f JOIN casos_prueba c ON c.id = f.caso_id JOIN usuarios u ON u.id = f.usuario_id WHERE f.id = ? AND f.tipo = ?';
        $params = [$id, $type];
        if ($user['rol'] !== 'admin') { $sql .= ' AND f.usuario_id = ?'; $params[] = $user['id']; }
        $query = $this->pdo->prepare($sql . ($lock ? ' FOR UPDATE' : '')); $query->execute($params);
        return $query->fetch() ?: null;
    }

    public function contents(array $document): array
    {
        $id = (int) $document['id']; $type = $document['tipo'];
        if ($type !== 'decision') {
            $query = $this->pdo->prepare('SELECT * FROM ' . self::TABLES[$type] . ' WHERE formulario_id = ? ORDER BY orden');
            $query->execute([$id]); return ['filas' => $query->fetchAll()];
        }
        $query = $this->pdo->prepare('SELECT * FROM decision_reglas WHERE formulario_id = ? ORDER BY orden'); $query->execute([$id]); $rules = $query->fetchAll();
        $query = $this->pdo->prepare('SELECT * FROM decision_elementos WHERE formulario_id = ? ORDER BY orden'); $query->execute([$id]); $elements = $query->fetchAll();
        $query = $this->pdo->prepare('SELECT * FROM decision_valores WHERE formulario_id = ?'); $query->execute([$id]); $cells = [];
        foreach ($query->fetchAll() as $cell) { $cells[$cell['elemento_id']][$cell['regla_id']] = $cell['valor']; }
        $data = ['reglas' => array_column($rules, 'nombre'), 'condiciones' => [], 'acciones' => []];
        foreach ($elements as $element) {
            $data[$element['tipo'] === 'condicion' ? 'condiciones' : 'acciones'][] = [
                'texto' => $element['descripcion'], 'valores' => array_map(fn ($rule) => $cells[$element['id']][$rule['id']], $rules),
            ];
        }
        return $data;
    }

    private static function text(mixed $value, string $label, int $limit = 2000): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $limit) {
            throw new InvalidArgumentException($label . ': completa el campo (máximo ' . $limit . ' caracteres).');
        }
        return trim($value);
    }

    public static function validate(string $type, array $data): array
    {
        if (!isset(self::TYPES[$type])) { throw new InvalidArgumentException('Formulario no válido.'); }
        if ($type !== 'decision') {
            $rows = $data['filas'] ?? null;
            if (!is_array($rows) || count($rows) < 1 || count($rows) > 30) { throw new InvalidArgumentException('Agrega entre 1 y 30 filas.'); }
            $clean = [];
            foreach (array_values($rows) as $index => $row) {
                if (!is_array($row)) { throw new InvalidArgumentException('Fila no válida.'); }
                $item = [];
                foreach (self::FIELDS[$type] as $field => $label) {
                    $item[$field] = self::text($row[$field] ?? null, 'Fila ' . ($index + 1) . ' · ' . $label, in_array($field, ['campo', 'valor_minimo', 'valor_maximo'], true) ? 150 : 2000);
                }
                if ($type === 'limites' && is_numeric($item['valor_minimo']) && is_numeric($item['valor_maximo']) && (float) $item['valor_minimo'] > (float) $item['valor_maximo']) {
                    throw new InvalidArgumentException('El valor mínimo no puede superar el máximo.');
                }
                $clean[] = $item;
            }
            return ['filas' => $clean];
        }
        $rules = $data['reglas'] ?? null;
        if (!is_array($rules) || count($rules) < 1 || count($rules) > 20) { throw new InvalidArgumentException('Agrega entre 1 y 20 reglas.'); }
        $clean = ['reglas' => array_map(fn ($name) => self::text($name, 'Nombre de regla', 100), array_values($rules))];
        foreach (['condiciones', 'acciones'] as $kind) {
            $elements = $data[$kind] ?? null;
            if (!is_array($elements) || count($elements) < 1 || count($elements) > 20) { throw new InvalidArgumentException('Agrega entre 1 y 20 ' . $kind . '.'); }
            $clean[$kind] = [];
            foreach (array_values($elements) as $element) {
                if (!is_array($element)) { throw new InvalidArgumentException('Elemento no válido.'); }
                $text = self::text($element['texto'] ?? null, 'Condición / Acción', 250);
                $values = $element['valores'] ?? null;
                if (!is_array($values) || count($values) !== count($rules)) { throw new InvalidArgumentException('Cada condición y acción debe tener un valor para cada regla.'); }
                $values = array_values($values);
                foreach ($values as $value) {
                    if (!in_array($value, $kind === 'condiciones' ? ['V', 'F', '-'] : ['X', ''], true)) { throw new InvalidArgumentException('Valor de decisión no válido. Usa V/F/– o X/vacío.'); }
                }
                $clean[$kind][] = ['texto' => $text, 'valores' => $values];
            }
        }
        return $clean;
    }

    public function save(string $type, array $user, int $caseId, array $data, ?int $id = null): int
    {
        $data = self::validate($type, $data);
        $this->pdo->beginTransaction();
        try {
            if ($id !== null) {
                $document = $this->find($id, $type, $user, true);
                if (!$document) { throw new RuntimeException('Registro no disponible.'); }
                // The original case and creator are immutable when editing.
                $caseId = (int) $document['caso_id'];
            } else {
                $query = $this->pdo->prepare('SELECT id FROM casos_prueba WHERE id = ?' . ($user['rol'] === 'admin' ? '' : ' AND usuario_id = ?') . ' FOR UPDATE');
                $query->execute($user['rol'] === 'admin' ? [$caseId] : [$caseId, $user['id']]);
                if (!$query->fetch()) { throw new InvalidArgumentException('Selecciona un caso existente disponible para tu cuenta.'); }
                $this->pdo->prepare('INSERT INTO formularios_prueba (caso_id, usuario_id, tipo) VALUES (?, ?, ?)')->execute([$caseId, $user['id'], $type]);
                $id = (int) $this->pdo->lastInsertId();
            }
            if ($type !== 'decision') {
                $table = self::TABLES[$type]; $fields = array_keys(self::FIELDS[$type]);
                $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE formulario_id = ?')->execute([$id]);
                $insert = $this->pdo->prepare('INSERT INTO ' . $table . ' (formulario_id, orden, ' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields) + 2, '?')) . ')');
                foreach ($data['filas'] as $order => $row) { $insert->execute([$id, $order, ...array_values($row)]); }
            } else {
                foreach (['decision_elementos', 'decision_reglas'] as $table) { $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE formulario_id = ?')->execute([$id]); }
                $ruleIds = [];
                $insert = $this->pdo->prepare('INSERT INTO decision_reglas (formulario_id, orden, nombre) VALUES (?, ?, ?)');
                foreach ($data['reglas'] as $order => $name) { $insert->execute([$id, $order, $name]); $ruleIds[] = (int) $this->pdo->lastInsertId(); }
                $insert = $this->pdo->prepare('INSERT INTO decision_elementos (formulario_id, tipo, orden, descripcion) VALUES (?, ?, ?, ?)');
                $cell = $this->pdo->prepare('INSERT INTO decision_valores (formulario_id, elemento_id, regla_id, valor) VALUES (?, ?, ?, ?)');
                foreach (['condiciones' => 'condicion', 'acciones' => 'accion'] as $kind => $elementType) {
                    foreach ($data[$kind] as $order => $element) {
                        $insert->execute([$id, $elementType, $order, $element['texto']]); $elementId = (int) $this->pdo->lastInsertId();
                        foreach ($element['valores'] as $rule => $value) { $cell->execute([$id, $elementId, $ruleIds[$rule], $value]); }
                    }
                }
            }
            $this->pdo->prepare('UPDATE formularios_prueba SET actualizado_en = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            $this->pdo->commit(); return $id;
        } catch (\Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }

    public function delete(int $id, string $type, array $user): bool
    {
        if ($user['rol'] !== 'admin') { throw new RuntimeException('La eliminación requiere Administrador.'); }
        $query = $this->pdo->prepare('DELETE FROM formularios_prueba WHERE id = ? AND tipo = ?');
        $query->execute([$id, $type]); return $query->rowCount() === 1;
    }
}
