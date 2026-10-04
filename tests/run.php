<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Marketplace\Bus\Domain\SearchSession;
use Marketplace\Bus\Service\AlphaAdapter;
use Marketplace\Bus\Service\BetaAdapter;
use Marketplace\Bus\Service\GammaAdapter;
use Marketplace\Provider\ProductRepository;
use Marketplace\Shared\Domain\SearchCriteria;
use Marketplace\Shared\Support\Database;
use Marketplace\Shared\Support\ValidationException;

$passed = 0;
$failed = 0;

function test(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "FAIL {$name}: {$exception->getMessage()}\n";
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

test('UT-001 criteria normalization', function (): void {
    $criteria = SearchCriteria::fromQuery(['q' => '  laptop ', 'category' => '', 'min_price' => '300.50', 'min_stock' => '2'])->toArray();
    assertSameValue('laptop', $criteria['q']);
    assertSameValue(null, $criteria['category']);
    assertSameValue(300.5, $criteria['min_price']);
    assertSameValue(2, $criteria['min_stock']);
});

test('UT-002 price range validation', function (): void {
    try {
        SearchCriteria::fromQuery(['min_price' => '900', 'max_price' => '100']);
        throw new RuntimeException('Invalid range was accepted');
    } catch (ValidationException $exception) {
        assertSameValue(422, $exception->httpStatus);
    }
});

test('UT-003 sort allowlist', function (): void {
    try {
        SearchCriteria::fromQuery(['sort' => 'price DESC; DROP TABLE products']);
        throw new RuntimeException('Unsafe sort was accepted');
    } catch (ValidationException) {
        assertTrue(true, 'Rejected');
    }
});

test('UT-004 alpha adapter', function (): void {
    $product = (new AlphaAdapter())->normalize(['provider' => 'alpha', 'items' => [[
        'id' => 4, 'name' => 'Alpha Test', 'description' => 'd', 'category' => 'computers',
        'brand' => 'A', 'price' => 10.5, 'currency' => 'USD', 'stock' => 2,
    ]]])[0]->toArray();
    assertSameValue('4', $product['external_id']);
    assertSameValue('Alpha Test', $product['title']);
});

test('UT-005 beta adapter', function (): void {
    $product = (new BetaAdapter())->normalize(['source' => 'beta', 'products' => [[
        'product_id' => 'B-1', 'title' => 'Beta Test', 'details' => 'd', 'group' => 'audio',
        'maker' => 'B', 'unit_price' => 20, 'currency_code' => 'USD', 'available_units' => 3,
    ]]])[0]->toArray();
    assertSameValue('B-1', $product['external_id']);
    assertSameValue('audio', $product['category']);
});

test('UT-006 gamma adapter', function (): void {
    $product = (new GammaAdapter())->normalize(['market' => 'gamma', 'results' => [[
        'code' => 'G-1', 'product_name' => 'Gamma Test', 'summary' => 'd', 'classification' => 'phones',
        'manufacturer' => 'G', 'amount' => 30, 'money' => 'USD', 'inventory' => 4,
    ]]])[0]->toArray();
    assertSameValue('Gamma Test', $product['title']);
    assertSameValue(4, $product['stock']);
});

test('UT-007 dynamic SQL binds malicious text', function (): void {
    $repository = new ProductRepository(Database::connect('market_alpha'));
    $criteria = SearchCriteria::fromQuery(['q' => "' OR 1=1 --", 'category' => 'computers', 'sort' => 'price_desc', 'limit' => '10'])->toArray();
    [$sql, $params] = $repository->buildQuery($criteria);
    assertTrue(!str_contains($sql, "' OR 1=1 --"), 'User value leaked into SQL text');
    assertTrue(str_contains($sql, 'category = :category'), 'Dynamic category predicate missing');
    assertTrue(str_contains($sql, 'ORDER BY price DESC, id ASC LIMIT 10'), 'Allowlisted order/limit missing');
    assertSameValue("%' OR 1=1 --%", $params[':q_name']);
});

test('UT-008 TTL computation', function (): void {
    $session = SearchSession::create(60);
    assertSameValue(60, $session->expiresAt->getTimestamp() - $session->createdAt->getTimestamp());
    assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $session->id), 'UUID shape invalid');
});

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

