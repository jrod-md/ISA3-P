<?php
declare(strict_types=1);

use Marketplace\Bus\Repository\CoverageMetrics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoverageMetricsTest extends TestCase
{
    #[DataProvider('percentages')]
    public function testCalculatesActualPercentage(int $total, int $covered, float $expected): void
    {
        self::assertSame($expected, CoverageMetrics::percentage($total, $covered));
    }

    public static function percentages(): array
    {
        return [[0, 0, 0.0], [10, 0, 0.0], [10, 10, 100.0], [3, 1, 33.33], [6, 5, 83.33], [4294967295, 4294967295, 100.0]];
    }

    private static function valid(): array
    {
        $data = CoverageMetrics::blank();
        foreach ($data['metricas'] as &$row) { $row = ['total' => '3', 'cubiertos' => '1', 'herramienta' => ' PHPUnit ', 'porcentaje' => 100]; }
        return $data;
    }

    public function testRecalculatesForgedPercentageAndNormalizesTool(): void
    {
        $clean = CoverageMetrics::validate(self::valid());
        self::assertCount(5, $clean['metricas']);
        foreach ($clean['metricas'] as $row) { self::assertSame(['total' => 3, 'cubiertos' => 1, 'porcentaje' => 33.33, 'herramienta' => 'PHPUnit'], $row); }
    }

    #[DataProvider('invalidMetrics')]
    public function testRejectsInvalidMetricDocuments(array $data): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoverageMetrics::validate($data);
    }

    public static function invalidMetrics(): array
    {
        $base = self::valid(); $cases = [['metricas' => []], ['metricas' => 'texto']];
        foreach (['total' => ['-1', '1.5', '1e2', '4294967296', []], 'cubiertos' => ['4'], 'herramienta' => ['', str_repeat('x', 151), []]] as $field => $values) {
            foreach ($values as $value) { $copy = $base; $copy['metricas']['sentencia'][$field] = $value; $cases[] = $copy; }
        }
        $missing = $base; unset($missing['metricas']['decision']); $cases[] = $missing;
        $extra = $base; $extra['metricas']['ajena'] = $extra['metricas']['sentencia']; $cases[] = $extra;
        $notRow = $base; $notRow['metricas']['bucles'] = null; $cases[] = $notRow;
        return array_map(static fn ($data) => [$data], $cases);
    }
}
