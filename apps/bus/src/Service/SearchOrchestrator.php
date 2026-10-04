<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

use Marketplace\Bus\Config\Config;
use Marketplace\Bus\Domain\NormalizedProduct;
use Marketplace\Bus\Domain\SearchSession;
use Marketplace\Bus\Http\ProviderClient;
use Marketplace\Bus\Repository\PdoSearchRepository;
use Marketplace\Shared\Domain\SearchCriteria;

final class SearchOrchestrator
{
    /** @var array<string,ProviderAdapter> */
    private array $adapters;

    public function __construct(
        private readonly PdoSearchRepository $repository,
        private readonly ProviderClient $client,
        ProviderAdapter ...$adapters,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->providerKey()] = $adapter;
        }
    }

    public function search(SearchCriteria $criteria): array
    {
        $ttl = max(1, Config::int('SEARCH_TTL_SECONDS', 60));
        $session = SearchSession::create($ttl);
        $providerKeys = $criteria->providerKeys();
        $criteriaArray = $criteria->toArray();
        $this->repository->create($session, $criteriaArray, $providerKeys);

        $rawResponses = $this->client->search($providerKeys, $criteria->providerQuery());
        $products = [];
        $statuses = [];
        $warnings = [];
        $successes = 0;

        foreach ($providerKeys as $key) {
            $response = $rawResponses[$key];
            if (!$response['ok']) {
                $statuses[$key] = [
                    'status' => 'error', 'count' => 0,
                    'duration_ms' => $response['duration_ms'], 'message' => $response['message'],
                ];
                $warnings[] = "El proveedor {$key} no estuvo disponible: {$response['message']}";
                continue;
            }

            try {
                $normalized = $this->adapters[$key]->normalize($response['payload']);
                array_push($products, ...$normalized);
                $successes++;
                $statuses[$key] = ['status' => 'ok', 'count' => count($normalized), 'duration_ms' => $response['duration_ms']];
            } catch (\Throwable $exception) {
                $statuses[$key] = ['status' => 'error', 'count' => 0, 'duration_ms' => $response['duration_ms'], 'message' => 'unexpected schema'];
                $warnings[] = "El proveedor {$key} devolvió un formato inesperado";
            }
        }

        $this->sort($products, $criteriaArray['sort']);
        $products = array_slice($products, 0, $criteriaArray['limit']);
        $resultArrays = array_map(static fn (NormalizedProduct $product): array => $product->toArray(), $products);
        $this->repository->saveResults($session->id, $resultArrays, $statuses, $warnings);

        if ($successes === 0) {
            throw new AllProvidersFailed([
                'search_id' => $session->id,
                'providers' => $statuses,
                'warnings' => $warnings,
            ]);
        }

        return [
            'search_id' => $session->id,
            'criteria' => $criteriaArray,
            'created_at' => $session->createdIso(),
            'expires_at' => $session->expiresIso(),
            'ttl_seconds' => $session->ttlSeconds,
            'total' => count($resultArrays),
            'providers' => $statuses,
            'warnings' => $warnings,
            'results' => $resultArrays,
        ];
    }

    /** @param array<NormalizedProduct> $products */
    private function sort(array &$products, string $sort): void
    {
        usort($products, static function (NormalizedProduct $left, NormalizedProduct $right) use ($sort): int {
            return match ($sort) {
                'price_asc' => [$left->price, $left->title] <=> [$right->price, $right->title],
                'price_desc' => $right->price <=> $left->price ?: strcasecmp($left->title, $right->title),
                'name_asc' => strcasecmp($left->title, $right->title),
                default => [$left->provider, $left->externalId] <=> [$right->provider, $right->externalId],
            };
        });
    }
}
