<?php
declare(strict_types=1);

namespace Marketplace\Provider;

final class ProviderFormatter
{
    public static function format(string $provider, array $rows): array
    {
        return match ($provider) {
            'alpha' => [
                'provider' => 'alpha',
                'count' => count($rows),
                'items' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'category' => $row['category'],
                    'brand' => $row['brand'],
                    'price' => (float) $row['price'],
                    'currency' => $row['currency'],
                    'stock' => (int) $row['stock'],
                ], $rows),
            ],
            'beta' => [
                'source' => 'beta',
                'total_items' => count($rows),
                'products' => array_map(static fn (array $row): array => [
                    'product_id' => $row['sku'],
                    'title' => $row['name'],
                    'details' => $row['description'],
                    'group' => $row['category'],
                    'maker' => $row['brand'],
                    'unit_price' => (float) $row['price'],
                    'currency_code' => $row['currency'],
                    'available_units' => (int) $row['stock'],
                ], $rows),
            ],
            'gamma' => [
                'market' => 'gamma',
                'matches' => count($rows),
                'results' => array_map(static fn (array $row): array => [
                    'code' => $row['sku'],
                    'product_name' => $row['name'],
                    'summary' => $row['description'],
                    'classification' => $row['category'],
                    'manufacturer' => $row['brand'],
                    'amount' => (float) $row['price'],
                    'money' => $row['currency'],
                    'inventory' => (int) $row['stock'],
                ], $rows),
            ],
            default => throw new \InvalidArgumentException('Unknown provider'),
        };
    }
}

