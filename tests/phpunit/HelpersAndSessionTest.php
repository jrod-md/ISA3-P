<?php
declare(strict_types=1);

use Marketplace\Bus\Domain\SearchSession;
use Marketplace\Bus\Repository\IncidentRepository;
use Marketplace\Shared\Support\Uuid;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelpersAndSessionTest extends TestCase
{
    #[DataProvider('codes')]
    public function testExistingCaseAndIncidentCodes(int $id, string $digits): void
    {
        self::assertSame('CP-' . $digits, code_case($id));
        self::assertSame('BUG-' . $digits, IncidentRepository::code($id));
    }

    public static function codes(): array { return [[1, '001'], [24, '024'], [1000, '1000']]; }

    public function testHtmlEscapingAndInvalidUtf8Substitution(): void
    {
        self::assertSame('&lt;script&gt;&quot;&#039;&amp;&lt;/script&gt;', e('<script>"\'&</script>'));
        self::assertSame('José', e('José'));
        self::assertSame("\u{FFFD}", e("\xFF"));
    }

    public function testPostTextRejectsArraysAndRestoresRequestState(): void
    {
        $original = $_POST;
        try {
            $_POST = ['campo' => '  técnica  ', 'array' => ['x'], 'entero' => 4];
            self::assertSame('técnica', post_text('campo'));
            self::assertSame('', post_text('array'));
            self::assertSame('', post_text('entero'));
            self::assertSame('', post_text('ausente'));
        } finally { $_POST = $original; }
    }

    public function testDateUsesExistingBogotaTimezone(): void
    {
        self::assertSame('03/10/2026 · 19:30', date_display('2026-10-04 00:30:00'));
    }

    public function testAcademicCatalogContainsBothTechniquesAndTenAvailableRoutes(): void
    {
        self::assertSame(['Caja Negra', 'Caja Blanca'], array_keys(TECNICAS));
        self::assertCount(10, TECNICAS['Caja Negra']);
        self::assertCount(10, TECNICAS['Caja Blanca']);
        self::assertContains('Partición de equivalencia', TECNICAS['Caja Negra']);
        self::assertContains('Cobertura de caminos básicos', TECNICAS['Caja Blanca']);
        self::assertCount(10, FORMULARIOS);
        self::assertCount(10, array_unique(FORMULARIO_RUTAS));
        self::assertSame('/formularios/incidentes', FORMULARIO_RUTAS[9]);
    }

    #[DataProvider('ttls')]
    public function testSearchSessionTtlUuidAndUtcDates(int $ttl): void
    {
        $session = SearchSession::create($ttl);
        self::assertSame($ttl, $session->ttlSeconds);
        self::assertSame($ttl, $session->expiresAt->getTimestamp() - $session->createdAt->getTimestamp());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $session->id);
        self::assertSame($session->createdAt->format('Y-m-d\TH:i:s.u\Z'), $session->createdIso());
        self::assertSame($session->expiresAt->format('Y-m-d\TH:i:s.u\Z'), $session->expiresIso());
    }

    public static function ttls(): array { return [[0], [3], [60]]; }

    public function testUuidGeneratorProducesDistinctVersionFourIds(): void
    {
        $first = Uuid::v4(); $second = Uuid::v4();
        self::assertNotSame($first, $second);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $second);
    }
}
