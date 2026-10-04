<?php
declare(strict_types=1);

namespace Marketplace\Shared\Domain;

use Marketplace\Bus\Config\Config;
use Marketplace\Shared\Support\ValidationException;

final class SearchCriteria
{
    private const PROVIDERS = ['all', 'alpha', 'beta', 'gamma'];
    private const SORTS = ['default', 'price_asc', 'price_desc', 'name_asc'];

    private function __construct(private readonly array $values)
    {
    }

    public static function fromQuery(array $query): self
    {
        foreach (['q', 'category', 'brand', 'min_price', 'max_price', 'min_stock', 'provider', 'sort', 'limit'] as $key) {
            if (isset($query[$key]) && !is_scalar($query[$key])) {
                throw new ValidationException("{$key} must be a scalar value", 400, 'MALFORMED_QUERY');
            }
        }

        $q = self::nullableText($query['q'] ?? null, 'q', 120);
        $category = self::nullableText($query['category'] ?? null, 'category', 80);
        $brand = self::nullableText($query['brand'] ?? null, 'brand', 80);
        $minPrice = self::nullableDecimal($query['min_price'] ?? null, 'min_price');
        $maxPrice = self::nullableDecimal($query['max_price'] ?? null, 'max_price');
        $minStock = self::nullableInteger($query['min_stock'] ?? null, 'min_stock', 0, 100000);

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            throw new ValidationException('max_price must be greater than or equal to min_price');
        }

        $provider = strtolower(trim((string) ($query['provider'] ?? 'all')));
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new ValidationException('provider must be all, alpha, beta, or gamma');
        }

        $sort = strtolower(trim((string) ($query['sort'] ?? 'default')));
        if (!in_array($sort, self::SORTS, true)) {
            throw new ValidationException('sort is not allowed');
        }

        $defaultLimit = Config::int('DEFAULT_RESULT_LIMIT', 50);
        $maxLimit = Config::int('MAX_RESULT_LIMIT', 100);
        $limit = self::nullableInteger($query['limit'] ?? $defaultLimit, 'limit', 1, $maxLimit) ?? $defaultLimit;

        return new self([
            'q' => $q,
            'category' => $category,
            'brand' => $brand,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'min_stock' => $minStock,
            'provider' => $provider,
            'sort' => $sort,
            'limit' => $limit,
        ]);
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function providerKeys(): array
    {
        return $this->values['provider'] === 'all' ? ['alpha', 'beta', 'gamma'] : [$this->values['provider']];
    }

    public function providerQuery(): array
    {
        $query = $this->values;
        unset($query['provider']);
        return array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function nullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if (mb_strlen($text) > $maxLength) {
            throw new ValidationException("{$field} must not exceed {$maxLength} characters");
        }
        return $text;
    }

    private static function nullableDecimal(mixed $value, string $field): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ValidationException("{$field} must be numeric", 400, 'MALFORMED_QUERY');
        }
        $number = (float) $value;
        if ($number < 0 || $number > 100000) {
            throw new ValidationException("{$field} must be between 0 and 100000");
        }
        return $number;
    }

    private static function nullableInteger(mixed $value, string $field, int $min, int $max): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < $min || $validated > $max) {
            throw new ValidationException("{$field} must be an integer between {$min} and {$max}", 400, 'MALFORMED_QUERY');
        }
        return (int) $validated;
    }
}

