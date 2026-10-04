<?php
declare(strict_types=1);

const TECNICAS = [
    'Caja Negra' => [
        'Partición de equivalencia', 'Análisis de valores límite', 'Tabla de decisiones',
        'Pruebas de transición de estados', 'Grafos causa-efecto',
        'Pruebas combinatorias por pares', 'Pruebas basadas en casos de uso',
        'Pruebas basadas en escenarios', 'Pruebas basadas en modelos', 'Pruebas aleatorias',
    ],
    'Caja Blanca' => [
        'Cobertura de sentencias', 'Cobertura de decisiones', 'Cobertura de condiciones',
        'Cobertura decisión/condición', 'Cobertura de condiciones múltiples',
        'Cobertura de caminos', 'Cobertura de caminos básicos', 'Cobertura de bucles',
        'Cobertura de funciones/métodos', 'Cobertura de clases',
    ],
];
const FORMULARIOS = [
    'Registro de Caso de Prueba', 'Matriz de Clases de Equivalencia', 'Análisis de Valor Límite',
    'Tabla de Decisión', 'Cobertura de Caja Blanca', 'Plan de Pruebas del Proyecto',
    'Rúbrica de Evaluación', 'Autoevaluación y Coevaluación', 'Portafolio de Evidencias', 'Registro de Incidentes',
];
const FORMULARIO_RUTAS = ['/casos', '/formularios/equivalencia', '/formularios/limites', '/formularios/decision'];
