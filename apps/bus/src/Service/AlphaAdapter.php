<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

use Marketplace\Bus\Domain\NormalizedProduct;

final class AlphaAdapter implements ProviderAdapter
{
    public function providerKey(): string { return 'alpha'; }

    public function normalize(array $payload): array
    {
        if (($payload['provider'] ?? null) !== 'alpha' || !is_array($payload['items'] ?? null)) {
            throw new \UnexpectedValueException('Unexpected Alpha response schema');
        }
        return array_map(static fn (array $item): NormalizedProduct => new NormalizedProduct(
            'alpha', (string) $item['id'], (string) $item['name'], $item['description'] ?? null,
            (string) $item['category'], $item['brand'] ?? null, (float) $item['price'],
            (string) $item['currency'], (int) $item['stock'],
        ), $payload['items']);
    }
}

