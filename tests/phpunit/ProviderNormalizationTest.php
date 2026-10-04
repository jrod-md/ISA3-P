<?php
declare(strict_types=1);

use Marketplace\Bus\Service\AlphaAdapter;
use Marketplace\Bus\Service\BetaAdapter;
use Marketplace\Bus\Service\GammaAdapter;
use Marketplace\Provider\ProviderFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderNormalizationTest extends TestCase
{
    public static function providers(): array
    {
        return [['alpha', AlphaAdapter::class, '4'], ['beta', BetaAdapter::class, 'SKU-4'], ['gamma', GammaAdapter::class, 'SKU-4']];
    }

    #[DataProvider('providers')]
    public function testConvertsRealProviderSchemaToTheCommonProduct(string $provider, string $class, string $externalId): void
    {
        $row = ['id' => '4', 'sku' => 'SKU-4', 'name' => 'Portátil de prueba', 'description' => null, 'category' => 'computers', 'brand' => null, 'price' => '10.50', 'currency' => 'USD', 'stock' => '2'];
        $adapter = new $class();
        $products = $adapter->normalize(ProviderFormatter::format($provider, [$row]));
        self::assertSame($provider, $adapter->providerKey());
        self::assertCount(1, $products);
        self::assertSame(['provider' => $provider, 'external_id' => $externalId, 'title' => 'Portátil de prueba', 'description' => null, 'category' => 'computers', 'brand' => null, 'price' => 10.5, 'currency' => 'USD', 'stock' => 2], $products[0]->toArray());
    }

    #[DataProvider('providers')]
    public function testAcceptsEmptyCatalog(string $provider, string $class): void
    {
        self::assertSame([], (new $class())->normalize(ProviderFormatter::format($provider, [])));
    }

    #[DataProvider('providers')]
    public function testRejectsUnexpectedSchema(string $provider, string $class): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new $class())->normalize(['provider' => 'ajeno', 'items' => []]);
    }

    public function testFormatterRejectsUnknownProvider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProviderFormatter::format('ajeno', []);
    }
}
