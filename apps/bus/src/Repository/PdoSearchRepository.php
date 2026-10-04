<?php
declare(strict_types=1);

namespace Marketplace\Bus\Repository;

use Marketplace\Bus\Domain\SearchSession;
use PDO;

final class PdoSearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(SearchSession $session, array $criteria, array $providers): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO search_sessions '
            . '(id, criteria_json, created_at, expires_at, ttl_seconds, requested_providers, result_count) '
            . 'VALUES (:id, :criteria, :created_at, :expires_at, :ttl, :providers, 0)'
        );
        $statement->execute([
            ':id' => $session->id,
            ':criteria' => json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':created_at' => $session->createdAt->format('Y-m-d H:i:s.u'),
            ':expires_at' => $session->expiresAt->format('Y-m-d H:i:s.u'),
            ':ttl' => $session->ttlSeconds,
            ':providers' => implode(',', $providers),
        ]);
    }

    public function saveResults(string $searchId, array $results, array $providers, array $warnings): void
    {
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE search_sessions SET result_count = :count WHERE id = :id');
            $update->execute([':count' => count($results), ':id' => $searchId]);
            $cache = $this->pdo->prepare(
                'INSERT INTO search_cache (search_id, results_json, providers_json, warnings_json, created_at) '
                . 'VALUES (:id, :results, :providers, :warnings, UTC_TIMESTAMP(6))'
            );
            $cache->execute([
                ':id' => $searchId,
                ':results' => json_encode($results, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':providers' => json_encode($providers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':warnings' => json_encode($warnings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.*, c.results_json, c.providers_json, c.warnings_json '
            . 'FROM search_sessions s LEFT JOIN search_cache c ON c.search_id = s.id WHERE s.id = :id'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function list(): array
    {
        $rows = $this->pdo->query(
            'SELECT s.*, CASE WHEN c.search_id IS NULL THEN 0 ELSE 1 END AS has_cache, '
            . 'GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(6), s.expires_at)) AS seconds_remaining '
            . 'FROM search_sessions s LEFT JOIN search_cache c ON c.search_id = s.id '
            . 'ORDER BY s.created_at DESC'
        )->fetchAll();
        return array_map(function (array $row): array {
            $row['criteria'] = json_decode($row['criteria_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($row['criteria_json']);
            $row['ttl_seconds'] = (int) $row['ttl_seconds'];
            $row['result_count'] = (int) $row['result_count'];
            $row['seconds_remaining'] = (int) $row['seconds_remaining'];
            $row['has_cache'] = (bool) $row['has_cache'];
            $row['created_at'] = self::mysqlToIso($row['created_at']);
            $row['expires_at'] = self::mysqlToIso($row['expires_at']);
            return $row;
        }, $rows);
    }

    public function delete(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM search_sessions WHERE id = :id');
        $statement->execute([':id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function deleteExpired(): int
    {
        return $this->pdo->exec('DELETE FROM search_sessions WHERE expires_at <= UTC_TIMESTAMP(6)');
    }

    public function deleteAll(): int
    {
        return $this->pdo->exec('DELETE FROM search_sessions');
    }

    public function counts(): array
    {
        return [
            'sessions' => (int) $this->pdo->query('SELECT COUNT(*) FROM search_sessions')->fetchColumn(),
            'cache_rows' => (int) $this->pdo->query('SELECT COUNT(*) FROM search_cache')->fetchColumn(),
        ];
    }

    private function hydrate(array $row): array
    {
        return [
            'search_id' => $row['id'],
            'criteria' => json_decode($row['criteria_json'], true, 512, JSON_THROW_ON_ERROR),
            'created_at' => self::mysqlToIso($row['created_at']),
            'expires_at' => self::mysqlToIso($row['expires_at']),
            'ttl_seconds' => (int) $row['ttl_seconds'],
            'total' => (int) $row['result_count'],
            'providers' => $row['providers_json'] === null ? [] : json_decode($row['providers_json'], true, 512, JSON_THROW_ON_ERROR),
            'warnings' => $row['warnings_json'] === null ? [] : json_decode($row['warnings_json'], true, 512, JSON_THROW_ON_ERROR),
            'results' => $row['results_json'] === null ? [] : json_decode($row['results_json'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private static function mysqlToIso(string $value): string
    {
        return str_replace(' ', 'T', $value) . 'Z';
    }
}

