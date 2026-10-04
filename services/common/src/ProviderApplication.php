<?php
declare(strict_types=1);

namespace Marketplace\Provider;

use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Domain\SearchCriteria;
use Marketplace\Shared\Support\Database;
use Marketplace\Shared\Support\JsonResponse;
use Marketplace\Shared\Support\ValidationException;

final class ProviderApplication
{
    public static function run(string $provider): never
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            if ($method !== 'GET') {
                JsonResponse::error('METHOD_NOT_ALLOWED', 'Only GET is supported', 405);
            }

            $schemaKey = strtoupper($provider) . '_DB_NAME';
            $schema = Config::string($schemaKey, 'market_' . $provider);

            if ($path === '/health') {
                $pdo = Database::connect($schema);
                $pdo->query('SELECT 1');
                JsonResponse::send(['status' => 'ok', 'service' => "provider-{$provider}", 'database' => $schema]);
            }

            if ($path === '/api/products') {
                $criteria = SearchCriteria::fromQuery($_GET)->toArray();
                $pdo = Database::connect($schema);
                $rows = (new ProductRepository($pdo))->search($criteria);
                JsonResponse::send(ProviderFormatter::format($provider, $rows));
            }

            JsonResponse::error('NOT_FOUND', 'Endpoint not found', 404);
        } catch (ValidationException $exception) {
            JsonResponse::error($exception->errorCode, $exception->getMessage(), $exception->httpStatus, $exception->details);
        } catch (\Throwable $exception) {
            error_log("Provider {$provider} error: " . $exception->getMessage());
            JsonResponse::error('PROVIDER_ERROR', 'Provider service is unavailable', 503);
        }
    }
}

