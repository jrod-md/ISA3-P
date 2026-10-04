<?php
declare(strict_types=1);

use Marketplace\Shared\Domain\SearchCriteria;
use Marketplace\Shared\Support\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchCriteriaTest extends TestCase
{
    private array $environment;

    protected function setUp(): void
    {
        $this->environment = ['DEFAULT_RESULT_LIMIT' => getenv('DEFAULT_RESULT_LIMIT'), 'MAX_RESULT_LIMIT' => getenv('MAX_RESULT_LIMIT')];
        putenv('DEFAULT_RESULT_LIMIT=50');
        putenv('MAX_RESULT_LIMIT=100');
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) { putenv($value === false ? $name : $name . '=' . $value); }
    }

    public function testNormalizesAllFiltersAndKeepsZeroInProviderQuery(): void
    {
        $criteria = SearchCriteria::fromQuery(['q' => '  laptop  ', 'category' => ' computers ', 'brand' => ' Acme ', 'min_price' => '0', 'max_price' => '899.95', 'min_stock' => '0', 'provider' => ' ALPHA ', 'sort' => ' PRICE_ASC ', 'limit' => '10']);
        $expected = ['q' => 'laptop', 'category' => 'computers', 'brand' => 'Acme', 'min_price' => 0.0, 'max_price' => 899.95, 'min_stock' => 0, 'provider' => 'alpha', 'sort' => 'price_asc', 'limit' => 10];
        self::assertSame($expected, $criteria->toArray());
        unset($expected['provider']);
        self::assertSame($expected, $criteria->providerQuery());
        self::assertSame(['alpha'], $criteria->providerKeys());
    }

    public function testEmptyFiltersUseDefaultsWithoutSendingNullsToProviders(): void
    {
        $criteria = SearchCriteria::fromQuery(['q' => ' ', 'category' => '', 'min_price' => '', 'limit' => '']);
        self::assertSame(['q' => null, 'category' => null, 'brand' => null, 'min_price' => null, 'max_price' => null, 'min_stock' => null, 'provider' => 'all', 'sort' => 'default', 'limit' => 50], $criteria->toArray());
        self::assertSame(['sort' => 'default', 'limit' => 50], $criteria->providerQuery());
        self::assertSame(['alpha', 'beta', 'gamma'], $criteria->providerKeys());
    }

    public function testAcceptsInclusiveNumericAndUnicodeBoundaries(): void
    {
        $criteria = SearchCriteria::fromQuery(['q' => str_repeat('á', 120), 'category' => str_repeat('ñ', 80), 'brand' => str_repeat('é', 80), 'min_price' => '100000', 'max_price' => '100000', 'min_stock' => '100000', 'limit' => '100']);
        self::assertSame(120, mb_strlen($criteria->toArray()['q']));
        self::assertSame(100000.0, $criteria->toArray()['max_price']);
        self::assertSame(100, $criteria->toArray()['limit']);
    }

    #[DataProvider('invalidQueries')]
    public function testRejectsInvalidFiltersWithTheirActualHttpStatus(array $query, int $status): void
    {
        try { SearchCriteria::fromQuery($query); self::fail('Se aceptó un filtro inválido'); }
        catch (ValidationException $error) { self::assertSame($status, $error->httpStatus); self::assertNotSame('', $error->getMessage()); }
    }

    public static function invalidQueries(): array
    {
        return [
            'array' => [['q' => ['laptop']], 400],
            'query largo' => [['q' => str_repeat('á', 121)], 422],
            'categoría larga' => [['category' => str_repeat('x', 81)], 422],
            'marca larga' => [['brand' => str_repeat('x', 81)], 422],
            'decimal no numérico' => [['min_price' => 'gratis'], 400],
            'precio negativo' => [['min_price' => '-0.01'], 422],
            'precio fuera de límite' => [['max_price' => '100000.01'], 422],
            'rango invertido' => [['min_price' => '900', 'max_price' => '100'], 422],
            'stock fraccionario' => [['min_stock' => '1.5'], 400],
            'stock negativo' => [['min_stock' => '-1'], 400],
            'stock fuera de límite' => [['min_stock' => '100001'], 400],
            'límite cero' => [['limit' => '0'], 400],
            'límite excesivo' => [['limit' => '101'], 400],
            'proveedor ajeno' => [['provider' => 'otro'], 422],
            'orden SQL' => [['sort' => 'price DESC; DROP TABLE products'], 422],
        ];
    }
}
