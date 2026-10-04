<?php
declare(strict_types=1);

use Marketplace\Provider\ProductRepository;
use Marketplace\Shared\Domain\SearchCriteria;
use PHPUnit\Framework\TestCase;

final class ProductQueryTest extends TestCase
{
    public function testBindsEveryFilterInsteadOfInterpolatingUserText(): void
    {
        // PDO is a test double: buildQuery is pure and no connection is opened.
        $repository = new ProductRepository($this->createMock(PDO::class));
        $criteria = SearchCriteria::fromQuery(['q' => "' OR 1=1 --", 'category' => 'computers', 'brand' => 'Acme', 'min_price' => '10.5', 'max_price' => '900', 'min_stock' => '2', 'sort' => 'price_desc', 'limit' => '10'])->toArray();
        [$sql, $params] = $repository->buildQuery($criteria);
        self::assertStringNotContainsString("' OR 1=1 --", $sql);
        self::assertStringNotContainsString('Acme', $sql);
        self::assertStringContainsString('ORDER BY price DESC, id ASC LIMIT 10', $sql);
        self::assertSame([':q_name' => "%' OR 1=1 --%", ':q_description' => "%' OR 1=1 --%", ':q_brand' => "%' OR 1=1 --%", ':category' => 'computers', ':brand' => '%Acme%', ':min_price' => '10.5', ':max_price' => '900', ':min_stock' => 2], $params);
    }

    public function testClampsLimitAndFallsBackToSafeOrder(): void
    {
        $repository = new ProductRepository($this->createMock(PDO::class));
        $criteria = SearchCriteria::fromQuery([])->toArray();
        $criteria['sort'] = 'price DESC; DROP TABLE products'; $criteria['limit'] = 1000;
        [$sql, $params] = $repository->buildQuery($criteria);
        self::assertStringEndsWith('ORDER BY id ASC LIMIT 100', $sql);
        self::assertSame([], $params);
        $criteria['limit'] = 0;
        self::assertStringEndsWith('LIMIT 1', $repository->buildQuery($criteria)[0]);
    }
}
