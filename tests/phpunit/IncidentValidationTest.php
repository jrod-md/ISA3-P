<?php
declare(strict_types=1);

use Marketplace\Bus\Repository\IncidentRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IncidentValidationTest extends TestCase
{
    private static function valid(): array
    {
        return ['titulo' => ' Error en búsqueda ', 'modulo' => ' Bus ', 'severidad' => 'Media', 'prioridad' => 'Alta', 'descripcion' => ' Descripción ', 'pasos_reproducir' => ' Buscar laptop ', 'resultado_esperado' => ' Resultados ', 'resultado_obtenido' => ' Error ', 'estado' => 'Abierto', 'asignado_a' => ' Equipo QA ', 'caso_id' => '24'];
    }

    public function testValidatesWithoutDbAndIgnoresForgedOwnerAndEvidence(): void
    {
        $clean = IncidentRepository::validate(self::valid() + ['usuario_id' => 999, 'codigo' => 'BUG-999', 'evidencia_archivo' => 'ajena.log']);
        self::assertSame('Error en búsqueda', $clean['titulo']);
        self::assertSame('Equipo QA', $clean['asignado_a']);
        self::assertSame(24, $clean['caso_id']);
        self::assertCount(11, $clean);
        self::assertArrayNotHasKey('usuario_id', $clean);
        self::assertArrayNotHasKey('evidencia_archivo', $clean);
        $data = self::valid(); $data['caso_id'] = '';
        self::assertNull(IncidentRepository::validate($data)['caso_id']);
    }

    #[DataProvider('requiredFields')]
    public function testRejectsMissingRequiredField(string $field): void
    {
        $data = self::valid(); unset($data[$field]);
        $this->expectException(InvalidArgumentException::class);
        IncidentRepository::validate($data);
    }

    public static function requiredFields(): array { return array_map(static fn ($field) => [$field], array_keys(IncidentRepository::FIELDS)); }

    #[DataProvider('invalidCases')]
    public function testRejectsMalformedOptionalCase(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        IncidentRepository::caseId($value);
    }

    public static function invalidCases(): array { return [[0], [-1], ['0'], ['1.5'], ['1e2'], ['4294967296'], [[]], [true]]; }

    public function testOptionalCaseAndMaximumId(): void
    {
        self::assertNull(IncidentRepository::caseId(null));
        self::assertNull(IncidentRepository::caseId(''));
        self::assertSame(4294967295, IncidentRepository::caseId('4294967295'));
    }

    #[DataProvider('catalogValues')]
    public function testAcceptsEveryAcademicCatalogValue(string $field, string $value): void
    {
        $data = self::valid(); $data[$field] = $value;
        self::assertSame($value, IncidentRepository::validate($data)[$field]);
    }

    public static function catalogValues(): array
    {
        $cases = [];
        foreach (['severidad' => ['Crítica', 'Alta', 'Media', 'Baja'], 'prioridad' => ['Alta', 'Media', 'Baja'], 'estado' => ['Abierto', 'En progreso', 'Cerrado']] as $field => $values) { foreach ($values as $value) { $cases[] = [$field, $value]; } }
        return $cases;
    }

    #[DataProvider('invalidFields')]
    public function testRejectsInvalidCatalogsAndOversizedText(string $field, mixed $value): void
    {
        $data = self::valid(); $data[$field] = $value;
        $this->expectException(InvalidArgumentException::class);
        IncidentRepository::validate($data);
    }

    public static function invalidFields(): array
    {
        return [['severidad', 'Urgente'], ['prioridad', 'Crítica'], ['estado', 'Resuelto'], ['modulo', str_repeat('á', 151)], ['titulo', str_repeat('á', 251)], ['descripcion', str_repeat('á', 5001)], ['asignado_a', []]];
    }
}
