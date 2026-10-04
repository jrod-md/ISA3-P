<?php
declare(strict_types=1);

// Shared implementation for the two optional CLI commands; never loaded by the app.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
require_once PROJECT_ROOT . '/apps/bus/testing/includes/catalogo.php';

use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Domain\SearchCriteria;
use Marketplace\Shared\Support\Database;
use Marketplace\Shared\Support\ValidationException;

final class DemoDataset
{
    public const VERSION = 'ISA3-P-DEMO-v1';
    private const CHILDREN = [
        'formularios_prueba' => ['casos_prueba', 'caso_id'],
        'equivalencia_filas' => ['formularios_prueba', 'formulario_id'],
        'limite_filas' => ['formularios_prueba', 'formulario_id'],
        'decision_reglas' => ['formularios_prueba', 'formulario_id'],
        'decision_elementos' => ['formularios_prueba', 'formulario_id'],
        'decision_valores' => ['formularios_prueba', 'formulario_id'],
        'cobertura_metricas' => ['formularios_prueba', 'formulario_id'],
        'plan_cronograma' => ['planes_prueba', 'plan_id'],
        'rubrica_criterios' => ['rubricas', 'rubrica_id'],
        'evaluacion_aspectos' => ['evaluaciones_pares', 'evaluacion_id'],
        'portafolio_evidencias' => ['portafolios', 'portafolio_id'],
    ];
    private const ROOT_TABLES = ['casos_prueba', 'planes_prueba', 'rubricas', 'evaluaciones_pares', 'portafolios', 'incidentes'];
    public readonly PDO $pdo;
    public readonly array $user;
    public readonly string $manifestPath;
    private array $identity;
    private string $lock;

    public function __construct()
    {
        $this->pdo = Database::connect(Config::string('BUS_DB_NAME', 'bus_meta'));
        $this->identity = ['host' => Config::string('DB_HOST', '127.0.0.1'), 'port' => Config::int('DB_PORT', 3306), 'schema' => $this->pdo->query('SELECT DATABASE()')->fetchColumn()];
        $key = hash('sha256', json_encode($this->identity, JSON_THROW_ON_ERROR));
        $this->manifestPath = PROJECT_ROOT . '/.runtime/demo-data/' . $key . '.json';
        $this->lock = 'isa3-demo-' . substr($key, 0, 40);
        $query = $this->pdo->prepare('SELECT id, username, rol FROM usuarios WHERE username = ?');
        $query->execute(['tester']); $user = $query->fetch();
        if (!$user || $user['rol'] !== 'tester') { throw new RuntimeException('Se requiere la cuenta existente tester con rol Tester. No se crean ni cambian usuarios.'); }
        $this->user = $user;
    }

    public function acquire(): void
    {
        $query = $this->pdo->prepare('SELECT GET_LOCK(?, 10)'); $query->execute([$this->lock]);
        if ((int) $query->fetchColumn() !== 1) { throw new RuntimeException('Otro seed/cleanup está en ejecución.'); }
    }
    public function release(): void { $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$this->lock]); }
    private static function hash(array $row): string { ksort($row); return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
    private static function table(string $table): string
    {
        if (!in_array($table, [...self::ROOT_TABLES, ...array_keys(self::CHILDREN)], true)) { throw new RuntimeException('Tabla fuera del dataset DEMO.'); }
        return '`' . $table . '`';
    }
    public function manifest(): ?array
    {
        if (!is_file($this->manifestPath)) { return null; }
        $data = json_decode((string) file_get_contents($this->manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== self::VERSION || ($data['database'] ?? null) !== $this->identity || ($data['owner'] ?? null) !== (int) $this->user['id'] || empty($data['records'])) {
            throw new RuntimeException('El manifiesto no corresponde a esta base/cuenta/dataset. No se modifica nada.');
        }
        return $data;
    }
    public function verify(array $manifest): void
    {
        $ids = [];
        foreach ($manifest['records'] as $record) {
            $table = self::table($record['table']); $id = (int) $record['id'];
            $composite = $record['table'] === 'decision_valores';
            $expectedFields = $composite ? ['elemento_id', 'regla_id'] : ['id'];
            if (array_keys($record['pk'] ?? []) !== $expectedFields || min(array_map('intval', $record['pk'])) < 1 || (!$composite && $id < 1) || ($record['table'] === 'casos_prueba' && $id === 24)) { throw new RuntimeException('ID protegido o no válido en manifiesto.'); }
            $where = implode(' AND ', array_map(static fn ($field) => '`' . $field . '` = ?', array_keys($record['pk'])));
            $query = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $where . ' FOR UPDATE'); $query->execute(array_values($record['pk'])); $row = $query->fetch();
            if (!$row || self::hash($row) !== $record['hash']) { throw new RuntimeException($record['key'] . ': registro ausente o editado. Se conserva todo; no se sobrescriben ni borran ediciones manuales.'); }
            if (in_array($record['table'], self::ROOT_TABLES, true) && ((int) $row['usuario_id'] !== (int) $this->user['id'] || !str_contains(json_encode($row, JSON_UNESCAPED_UNICODE), self::VERSION))) {
                throw new RuntimeException('Registro sin propietario/marcador DEMO esperado.');
            }
            $ids[$record['table']][] = $record['table'] === 'decision_valores' ? $record['pk']['elemento_id'] . ':' . $record['pk']['regla_id'] : $id;
        }
        // Protect manual children, documents and incident associations before cascades.
        foreach (self::CHILDREN as $child => [$parent, $fk]) {
            $parents = $ids[$parent] ?? []; if (!$parents) { continue; }
            $column = $child === 'decision_valores' ? "CONCAT(elemento_id, ':', regla_id)" : 'id';
            $query = $this->pdo->prepare('SELECT ' . $column . ' FROM ' . self::table($child) . ' WHERE `' . $fk . '` IN (' . implode(',', array_fill(0, count($parents), '?')) . ') FOR UPDATE');
            $query->execute($parents); $actual = $query->fetchAll(PDO::FETCH_COLUMN); $expected = $ids[$child] ?? []; $actual = array_map('strval', $actual); $expected = array_map('strval', $expected); sort($actual); sort($expected);
            if ($actual !== $expected) { throw new RuntimeException('Hay registros ajenos vinculados en ' . $child . '. Cleanup cancelado para conservarlos.'); }
        }
        $cases = $ids['casos_prueba'];
        $query = $this->pdo->prepare('SELECT id FROM incidentes WHERE caso_id IN (' . implode(',', array_fill(0, count($cases), '?')) . ') FOR UPDATE'); $query->execute($cases);
        if ($query->fetch()) { throw new RuntimeException('Hay un incidente asociado manualmente a un caso DEMO. Se conserva la asociación.'); }
    }
    private function collisionCheck(): void
    {
        foreach (['casos_prueba' => 'observaciones', 'planes_prueba' => 'responsable', 'rubricas' => 'observaciones', 'evaluaciones_pares' => 'evaluado', 'portafolios' => 'titulo', 'incidentes' => 'descripcion'] as $table => $field) {
            $query = $this->pdo->prepare('SELECT id FROM ' . self::table($table) . ' WHERE LOCATE(?, `' . $field . '`) > 0'); $query->execute([self::VERSION]);
            if ($query->fetch()) { throw new RuntimeException('Existen marcadores del dataset sin manifiesto en ' . $table . '. No se adoptan ni duplican registros. Recupera el manifiesto original.'); }
        }
    }
    public function seed(): array
    {
        if ($manifest = $this->manifest()) {
            $this->pdo->beginTransaction();
            try { $this->verify($manifest); $this->pdo->commit(); } catch (Throwable $error) { $this->pdo->rollBack(); throw $error; }
            return $manifest + ['operation' => 'Sin cambios: dataset existente verificado, sin duplicados'];
        }
        $this->collisionCheck();
        $observed = self::observe(); // Fail before inserting if the real checks do not pass.
        $nodes = $this->definitions($observed);
        $manifest = ['version' => self::VERSION, 'database' => $this->identity, 'owner' => (int) $this->user['id'], 'prepared_at' => date(DATE_ATOM), 'observed' => $observed, 'roots' => [], 'records' => []];
        $directory = dirname($this->manifestPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('No se puede crear el manifiesto privado.'); }
        $created = false; $this->pdo->beginTransaction();
        try {
            $this->collisionCheck(); $references = [];
            foreach ($nodes as $node) {
                $values = $node['values'];
                foreach ($values as &$value) { if (is_string($value) && str_starts_with($value, '@')) { $value = $references[substr($value, 1)] ?? throw new RuntimeException('Referencia DEMO no resuelta.'); } } unset($value);
                $fields = array_keys($values);
                $query = $this->pdo->prepare('INSERT INTO ' . self::table($node['table']) . ' (`' . implode('`, `', $fields) . '`) VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')'); $query->execute(array_values($values));
                $id = (int) $this->pdo->lastInsertId(); $references[$node['key']] = $id;
                if ($node['table'] === 'casos_prueba' && $id === 24) { throw new RuntimeException('CP-024 está reservado; seed cancelado.'); }
                $pk = $node['table'] === 'decision_valores' ? ['elemento_id' => $values['elemento_id'], 'regla_id' => $values['regla_id']] : ['id' => $id];
                $where = implode(' AND ', array_map(static fn ($field) => '`' . $field . '` = ?', array_keys($pk)));
                $query = $this->pdo->prepare('SELECT * FROM ' . self::table($node['table']) . ' WHERE ' . $where); $query->execute(array_values($pk));
                $manifest['records'][] = ['key' => $node['key'], 'table' => $node['table'], 'id' => $id, 'pk' => $pk, 'hash' => self::hash($query->fetch())];
                if (in_array($node['table'], self::ROOT_TABLES, true) || $node['table'] === 'formularios_prueba') { $manifest['roots'][$node['key']] = $id; }
            }
            // Exclusive file creation before DB commit: write failure rolls back all rows.
            $handle = fopen($this->manifestPath, 'x'); if (!$handle) { throw new RuntimeException('No se puede guardar el manifiesto.'); } $created = true;
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            try { if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) { throw new RuntimeException('Manifiesto incompleto.'); } } finally { fclose($handle); }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($created) { unlink($this->manifestPath); }
            throw $error;
        }
        return $manifest + ['operation' => 'Dataset DEMO creado'];
    }
    public function cleanup(): array
    {
        $manifest = $this->manifest();
        if (!$manifest) { return ['operation' => 'Sin manifiesto: no se elimina ningún registro', 'deleted_rows' => 0]; }
        $this->pdo->beginTransaction();
        try {
            $this->verify($manifest);
            foreach (array_reverse($manifest['records']) as $record) {
                $where = implode(' AND ', array_map(static fn ($field) => '`' . $field . '` = ?', array_keys($record['pk'])));
                $this->pdo->prepare('DELETE FROM ' . self::table($record['table']) . ' WHERE ' . $where)->execute(array_values($record['pk']));
            }
            $this->pdo->commit();
        } catch (Throwable $error) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $error; }
        if (!unlink($this->manifestPath)) { throw new RuntimeException('Dataset eliminado, pero no se pudo retirar el manifiesto privado.'); }
        return ['operation' => 'Solo dataset DEMO eliminado; usuarios, CP-024, búsquedas, catálogos y archivos no se tocan', 'deleted_rows' => count($manifest['records']), 'roots' => $manifest['roots']];
    }
    private static function request(string $url): array
    {
        $handle = curl_init($url); curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
        if ($body === false) { throw new RuntimeException('Bus no disponible: ' . $error . '. Inicia scripts/start-dev.ps1.'); }
        return [$status, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    }
    public static function observe(): array
    {
        $base = 'http://' . Config::string('BUS_HOST', '127.0.0.1') . ':' . Config::int('BUS_PORT', 8000);
        foreach ([$base, Config::string('PROVIDER_ALPHA_URL', 'http://127.0.0.1:8101'), Config::string('PROVIDER_BETA_URL', 'http://127.0.0.1:8102'), Config::string('PROVIDER_GAMMA_URL', 'http://127.0.0.1:8103')] as $service) {
            [$status, $health] = self::request(rtrim($service, '/') . '/health');
            if ($status !== 200 || ($health['status'] ?? null) !== 'ok') { throw new RuntimeException('Servicio no saludable: ' . $service); }
        }
        [$status, $all] = self::request($base . '/api/search?q=laptop');
        $providers = array_values(array_unique(array_column($all['results'] ?? [], 'provider'))); sort($providers);
        if ($status !== 200 || $providers !== ['alpha', 'beta', 'gamma'] || !empty($all['warnings'])) { throw new RuntimeException('La búsqueda real no reúne resultados válidos de los tres proveedores.'); }
        foreach ($providers as $provider) { if (($all['providers'][$provider]['status'] ?? null) !== 'ok') { throw new RuntimeException('Proveedor no saludable durante la búsqueda: ' . $provider); } }
        foreach ($all['results'] as $item) { foreach (['provider', 'external_id', 'title', 'price', 'currency', 'stock'] as $field) { if (!isset($item[$field])) { throw new RuntimeException('Producto sin normalizar: ' . $field); } } }
        [$invalidStatus, $invalid] = self::request($base . '/api/search?min_price=900&max_price=100');
        if ($invalidStatus !== 422 || ($invalid['error']['code'] ?? null) !== 'VALIDATION_ERROR') { throw new RuntimeException('El rango invertido no produjo el error de validación esperado.'); }
        [$status, $alpha] = self::request($base . '/api/search?q=laptop&provider=alpha&sort=price_asc');
        $prices = array_column($alpha['results'] ?? [], 'price'); $sorted = $prices; sort($sorted, SORT_NUMERIC);
        if ($status !== 200 || !$prices || array_keys($alpha['providers'] ?? []) !== ['alpha'] || array_unique(array_column($alpha['results'], 'provider')) !== ['alpha'] || $prices !== $sorted) { throw new RuntimeException('Selección Alpha/orden ascendente no aprobados.'); }
        $accepted = [[], ['q' => str_repeat('á', 119)], ['q' => str_repeat('á', 120)]];
        foreach (['min_price', 'max_price'] as $field) { foreach (['0', '1', '99999', '100000'] as $value) { $accepted[] = [$field => $value]; } }
        foreach (['0', '1', '99999', '100000'] as $value) { $accepted[] = ['min_stock' => $value]; }
        $limit = Config::int('MAX_RESULT_LIMIT', 100);
        if ($limit !== 100) { throw new RuntimeException('Este dataset documenta MAX_RESULT_LIMIT=100; la configuración actual difiere.'); }
        foreach (['1', '2', '99', '100'] as $value) { $accepted[] = ['limit' => $value]; }
        foreach (['all', 'alpha', 'beta', 'gamma'] as $value) { $accepted[] = ['provider' => $value]; }
        foreach ($accepted as $query) {
            $criteria = SearchCriteria::fromQuery($query); $provider = $query['provider'] ?? 'all';
            if ($criteria->providerKeys() !== ($provider === 'all' ? ['alpha', 'beta', 'gamma'] : [$provider])) { throw new RuntimeException('Selección interna de proveedor incorrecta.'); }
        }
        $rejected = [['q' => str_repeat('á', 121)], ['provider' => 'delta'], ['min_price' => '900', 'max_price' => '100']];
        foreach (['min_price', 'max_price'] as $field) { foreach (['-0.01', '100000.01', 'gratis'] as $value) { $rejected[] = [$field => $value]; } }
        foreach (['-1', '100001', '1.5'] as $value) { $rejected[] = ['min_stock' => $value]; }
        foreach (['0', '101', '1.5'] as $value) { $rejected[] = ['limit' => $value]; }
        foreach ($rejected as $query) { try { SearchCriteria::fromQuery($query); } catch (ValidationException) { continue; } throw new RuntimeException('SearchCriteria aceptó una entrada demo inválida.'); }
        return ['verified_at' => date(DATE_ATOM), 'laptops' => count($all['results']), 'providers' => $providers, 'inverted_range_http' => $invalidStatus, 'inverted_range_message' => $invalid['error']['message'], 'alpha_laptops' => count($prices), 'alpha_prices' => $prices, 'criteria_accepted' => count($accepted), 'criteria_rejected' => count($rejected)];
    }

    private function definitions(array $observed): array
    {
        return demo_definitions($this->pdo, (int) $this->user['id'], $observed);
    }
}

require_once __DIR__ . '/demo-data-definitions.php';
