<?php
declare(strict_types=1);

namespace Marketplace\Bus\Service;

use Marketplace\Bus\Domain\NormalizedProduct;

final class BetaAdapter implements ProviderAdapter
{
    public function providerKey(): string { return 'beta'; }

    public function normalize(array $payload): array
    {
        if (($payload['source'] ?? null) !== 'beta' || !is_array($payload['products'] ?? null)) {
            throw new \UnexpectedValueException('Unexpected Beta response schema');
        }
        return array_map(static fn (array $item): NormalizedProduct => new NormalizedProduct(
            'beta', (string) $item['product_id'], (string) $item['title'], $item['details'] ?? null,
            (string) $item['group'], $item['maker'] ?? null, (float) $item['unit_price'],
            (string) $item['currency_code'], (int) $item['available_units'],
        ), $payload['products']);
    }
}

