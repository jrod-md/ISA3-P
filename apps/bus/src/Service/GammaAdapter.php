<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

use Marketplace\Bus\Domain\NormalizedProduct;

final class GammaAdapter implements ProviderAdapter
{
    public function providerKey(): string { return 'gamma'; }

    public function normalize(array $payload): array
    {
        if (($payload['market'] ?? null) !== 'gamma' || !is_array($payload['results'] ?? null)) {
            throw new \UnexpectedValueException('Unexpected Gamma response schema');
        }
        return array_map(static fn (array $item): NormalizedProduct => new NormalizedProduct(
            'gamma', (string) $item['code'], (string) $item['product_name'], $item['summary'] ?? null,
            (string) $item['classification'], $item['manufacturer'] ?? null, (float) $item['amount'],
            (string) $item['money'], (int) $item['inventory'],
        ), $payload['results']);
    }
}

