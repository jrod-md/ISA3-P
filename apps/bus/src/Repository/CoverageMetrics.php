<?php
declare(strict_types=1);
namespace Marketplace\Bus\Repository;

use InvalidArgumentException;

final class CoverageMetrics
{
    public const METRICS = [
        'sentencia' => 'Cobertura de Sentencia',
        'decision' => 'Cobertura de Decisión',
        'condicion' => 'Cobertura de Condición',
        'caminos' => 'Cobertura de Caminos',
        'bucles' => 'Cobertura de Bucles',
    ];
    public const MAX_COUNT = 4294967295;

    public static function percentage(int $total, int $covered): float
    {
        return $total === 0 ? 0.0 : round($covered / $total * 100, 2);
    }

    public static function blank(): array
    {
        return ['metricas' => array_fill_keys(array_keys(self::METRICS), ['total' => '', 'cubiertos' => '', 'herramienta' => '', 'porcentaje' => 0])];
    }

    public static function validate(array $data): array
    {
        $rows = $data['metricas'] ?? null;
        if (!is_array($rows) || count($rows) !== count(self::METRICS) || array_diff(array_keys($rows), array_keys(self::METRICS))) {
            throw new InvalidArgumentException('Completa exactamente las cinco métricas de cobertura.');
        }
        $clean = [];
        foreach (self::METRICS as $metric => $label) {
            $row = $rows[$metric] ?? null;
            if (!is_array($row)) { throw new InvalidArgumentException($label . ': métrica no válida.'); }
            $counts = [];
            foreach (['total' => 'Total', 'cubiertos' => 'Cubiertos'] as $field => $name) {
                $raw = $row[$field] ?? null;
                if (!is_string($raw) && !is_int($raw)) { throw new InvalidArgumentException($label . ' · ' . $name . ': ingresa un entero no negativo.'); }
                $raw = (string) $raw;
                if (!preg_match('/^[0-9]{1,10}$/D', $raw) || (float) $raw > self::MAX_COUNT) {
                    throw new InvalidArgumentException($label . ' · ' . $name . ': ingresa un entero entre 0 y ' . self::MAX_COUNT . '.');
                }
                $counts[$field] = (int) $raw;
            }
            if ($counts['cubiertos'] > $counts['total']) { throw new InvalidArgumentException($label . ': Cubiertos no puede superar Total.'); }
            $tool = $row['herramienta'] ?? null;
            if (!is_string($tool) || trim($tool) === '' || mb_strlen(trim($tool)) > 150) {
                throw new InvalidArgumentException($label . ': completa Herramienta Utilizada (máximo 150 caracteres).');
            }
            $clean[$metric] = $counts + ['porcentaje' => self::percentage($counts['total'], $counts['cubiertos']), 'herramienta' => trim($tool)];
        }
        return ['metricas' => $clean];
    }
}
