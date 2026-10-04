<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use Marketplace\Bus\Repository\BlackBoxRepository;
use Marketplace\Bus\Repository\CoverageMetrics;
use Marketplace\Bus\Repository\TestPlanRepository;
use Marketplace\Bus\Repository\ProjectEvaluationRepository;
use Marketplace\Bus\Repository\IncidentRepository;

/** Static academic examples plus actual observations; no live database export. */
function demo_definitions(PDO $pdo, int $owner, array $observed): array
{
    $nodes = [];
    $add = static function (string $key, string $table, array $values) use (&$nodes): void { $nodes[] = compact('key', 'table', 'values'); };
    $today = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    $tag = DemoDataset::VERSION;
    $cases = [
        'case-a' => [
            'modulo' => '[DEMO] Búsqueda unificada de laptops', 'tecnica' => 'Caja Negra', 'subtecnica' => 'Partición de equivalencia',
            'objetivo' => 'Verificar que una búsqueda válida se distribuya entre Alpha, Beta y Gamma y normalice los resultados.',
            'datos_entrada' => 'q=laptop', 'pasos_ejecucion' => '1. Verificar /health del Bus y proveedores.\n2. GET /api/search?q=laptop.\n3. Revisar los proveedores y los campos normalizados de cada producto.',
            'resultado_esperado' => 'HTTP 200 y resultados normalizados provenientes de Alpha, Beta y Gamma.',
            'resultado_obtenido' => 'HTTP 200; ' . $observed['laptops'] . ' resultados. Proveedores observados: ' . implode(', ', $observed['providers']) . '. Todos incluyen provider, external_id, title, price, currency y stock.',
        ],
        'case-b' => [
            'modulo' => '[DEMO] Validación del rango de precios', 'tecnica' => 'Caja Negra', 'subtecnica' => 'Análisis de valores límite',
            'objetivo' => 'Verificar que min_price y max_price admitan 0–100000 y que un rango invertido sea rechazado.',
            'datos_entrada' => 'min_price=900&max_price=100', 'pasos_ejecucion' => '1. GET /api/search?min_price=900&max_price=100.\n2. Revisar HTTP y el error JSON.\n3. Consultar las fronteras documentadas en el Formulario 3.',
            'resultado_esperado' => 'HTTP 422, código VALIDATION_ERROR; max_price debe ser mayor o igual a min_price.',
            'resultado_obtenido' => 'HTTP ' . $observed['inverted_range_http'] . ', VALIDATION_ERROR: ' . $observed['inverted_range_message'],
        ],
        'case-c' => [
            'modulo' => '[DEMO] Selección de proveedor y orden', 'tecnica' => 'Caja Negra', 'subtecnica' => 'Tabla de decisiones',
            'objetivo' => 'Verificar que provider=alpha consulte únicamente Alpha y que price_asc ordene los resultados por precio ascendente.',
            'datos_entrada' => 'q=laptop&provider=alpha&sort=price_asc', 'pasos_ejecucion' => '1. Ejecutar GET /api/search con la entrada indicada.\n2. Comprobar providers=alpha y provider=alpha en los productos.\n3. Comparar precios consecutivos.',
            'resultado_esperado' => 'HTTP 200; únicamente Alpha consultado y precios no decrecientes.',
            'resultado_obtenido' => 'HTTP 200; ' . $observed['alpha_laptops'] . ' laptops, únicamente Alpha. Precios observados en orden ascendente: ' . implode(', ', $observed['alpha_prices']) . ' USD.',
        ],
        'case-d' => [
            'modulo' => '[DEMO] Validaciones de SearchCriteria', 'tecnica' => 'Caja Blanca', 'subtecnica' => 'Cobertura de decisiones',
            'objetivo' => 'Documentar decisiones de validación y selección interna de SearchCriteria ejercitadas por PHPUnit y verificadas durante la preparación. Relacionar el reporte de líneas con el Formulario 5 sin afirmar cobertura total.',
            'datos_entrada' => 'q vacío/119/120/121 caracteres; provider all/alpha/beta/gamma/delta; precios 0–100000 y fuera de rango; stock 0–100000; limit 1–100 e inválidos.',
            'pasos_ejecucion' => '1. Consultar tests/phpunit/SearchCriteriaTest.php.\n2. Ejecutar validaciones reales de SearchCriteria::fromQuery durante seed.\n3. Revisar el reporte de cobertura de líneas del Formulario 5.',
            'resultado_esperado' => 'Aceptar fronteras inclusivas y rechazar entradas inválidas con ValidationException; providerKeys selecciona proveedores válidos.',
            'resultado_obtenido' => 'Preparación: ' . $observed['criteria_accepted'] . ' entradas válidas aceptadas y ' . $observed['criteria_rejected'] . ' inválidas rechazadas. La suite completa previamente ejecutada tuvo 99 tests y 172 assertions; no son un recuento exclusivo de SearchCriteria. No se midió cobertura de decisiones en ese reporte.',
        ],
    ];
    foreach ($cases as $key => $data) {
        $data += ['precondiciones' => 'Bus en 8000, Alpha/Beta/Gamma en 8101/8102/8103 saludables; catálogos existentes y MySQL de XAMPP en 3306. MAX_RESULT_LIMIT=100.', 'estado' => 'Éxito', 'observaciones' => '[DEMO] Verificado en ' . $observed['verified_at'] . '. Identificador reservado: ' . $tag . '/' . $key . '. No incluye un archivo adjunto; el resultado observado está transcrito aquí.'];
        if (!in_array($data['subtecnica'], TECNICAS[$data['tecnica']], true)) { throw new RuntimeException('Subtécnica ajena al catálogo.'); }
        foreach ($data as $field => &$value) { $value = str_replace('\\n', "\n", $value); if (mb_strlen($value) > ($field === 'modulo' ? 150 : 5000)) { throw new RuntimeException('Campo del caso DEMO demasiado largo.'); } } unset($value);
        $add($key, 'casos_prueba', ['usuario_id' => $owner] + $data);
    }
    $equivalence = BlackBoxRepository::validate('equivalencia', ['filas' => [
        ['campo' => '[DEMO] q', 'clase_valida' => 'Texto vacío o hasta 120 caracteres después de trim.', 'clases_invalidas' => 'Más de 120 caracteres; parámetro no escalar.', 'valores_representativos' => 'Vacío; laptop; texto de 120 y 121 caracteres.', 'resultado_esperado' => 'Vacío se normaliza a null; laptop/120 aceptados; 121 rechazado HTTP 422.'],
        ['campo' => '[DEMO] provider', 'clase_valida' => 'all, alpha, beta, gamma (trim y minúsculas).', 'clases_invalidas' => 'delta o cualquier valor fuera del catálogo.', 'valores_representativos' => 'all; alpha; beta; gamma; delta.', 'resultado_esperado' => 'all selecciona tres proveedores; los demás uno; delta rechazado HTTP 422.'],
        ['campo' => '[DEMO] min_price / max_price', 'clase_valida' => 'Valores numéricos entre 0 y 100000 inclusive; min_price <= max_price si ambos están presentes.', 'clases_invalidas' => 'Negativo; >100000; no numérico; rango invertido.', 'valores_representativos' => '0; 749.99; 100000; -0.01; 100000.01; gratis; min=900/max=100.', 'resultado_esperado' => 'Valores válidos aceptados. Fuera de rango/inversión: 422. No numérico: 400.'],
        ['campo' => '[DEMO] limit', 'clase_valida' => 'Entero entre 1 y MAX_RESULT_LIMIT=100; vacío utiliza el predeterminado.', 'clases_invalidas' => '0; 101; valor no entero.', 'valores_representativos' => '1; 50; 100; 0; 101; 1.5.', 'resultado_esperado' => '1–100 aceptados; 0/101/1.5 rechazados HTTP 400.'],
    ]]);
    $add('form-2', 'formularios_prueba', ['caso_id' => '@case-a', 'usuario_id' => $owner, 'tipo' => 'equivalencia']);
    foreach ($equivalence['filas'] as $order => $row) { $add('equivalence-' . $order, 'equivalencia_filas', ['formulario_id' => '@form-2', 'orden' => $order] + $row); }
    $limits = BlackBoxRepository::validate('limites', ['filas' => [
        ['campo' => '[DEMO] min_price / max_price', 'rango_valido' => '0–100000 inclusive; mínimo <= máximo.', 'valor_minimo' => '0', 'valor_maximo' => '100000', 'valores_limite' => '-0.01; 0; 1; 99999; 100000; 100000.01.', 'resultado_esperado' => 'Fronteras/internos aceptados; -0.01 y 100000.01 rechazados HTTP 422.'],
        ['campo' => '[DEMO] min_stock', 'rango_valido' => 'Entero entre 0 y 100000.', 'valor_minimo' => '0', 'valor_maximo' => '100000', 'valores_limite' => '-1; 0; 1; 99999; 100000; 100001.', 'resultado_esperado' => '0/1/99999/100000 aceptados; -1/100001 rechazados HTTP 400.'],
        ['campo' => '[DEMO] limit', 'rango_valido' => 'Entero entre 1 y 100 (configuración actual).', 'valor_minimo' => '1', 'valor_maximo' => '100', 'valores_limite' => '0; 1; 2; 99; 100; 101.', 'resultado_esperado' => '1/2/99/100 aceptados; 0/101 rechazados HTTP 400.'],
        ['campo' => '[DEMO] longitud de q', 'rango_valido' => 'Vacío o texto de hasta 120 caracteres después de trim; mb_strlen cuenta caracteres.', 'valor_minimo' => '0', 'valor_maximo' => '120', 'valores_limite' => '119; 120; 121 caracteres.', 'resultado_esperado' => '119/120 aceptados; 121 rechazado HTTP 422. No se mide longitud en bytes.'],
    ]]);
    $add('form-3', 'formularios_prueba', ['caso_id' => '@case-b', 'usuario_id' => $owner, 'tipo' => 'limites']);
    foreach ($limits['filas'] as $order => $row) { $add('limit-' . $order, 'limite_filas', ['formulario_id' => '@form-3', 'orden' => $order] + $row); }
    $decision = BlackBoxRepository::validate('decision', ['reglas' => ['R1 [DEMO]', 'R2 [DEMO]', 'R3 [DEMO]', 'R4 [DEMO]'], 'condiciones' => [
        ['texto' => '[DEMO] C1: proveedor válido', 'valores' => ['V', 'V', 'F', '-']],
        ['texto' => '[DEMO] C2: rango de precios válido', 'valores' => ['V', 'V', '-', 'F']],
        ['texto' => '[DEMO] C3: provider = all', 'valores' => ['V', 'F', '-', '-']],
    ], 'acciones' => [
        ['texto' => '[DEMO] Consultar Alpha, Beta y Gamma', 'valores' => ['X', '', '', '']],
        ['texto' => '[DEMO] Consultar el proveedor seleccionado', 'valores' => ['', 'X', '', '']],
        ['texto' => '[DEMO] Rechazar solicitud por validación', 'valores' => ['', '', 'X', 'X']],
    ]]);
    $add('form-4', 'formularios_prueba', ['caso_id' => '@case-c', 'usuario_id' => $owner, 'tipo' => 'decision']);
    foreach ($decision['reglas'] as $order => $name) { $add('rule-' . $order, 'decision_reglas', ['formulario_id' => '@form-4', 'orden' => $order, 'nombre' => $name]); }
    foreach (['condiciones' => 'condicion', 'acciones' => 'accion'] as $kind => $type) {
        foreach ($decision[$kind] as $order => $row) {
            $key = $kind . '-' . $order;
            $add($key, 'decision_elementos', ['formulario_id' => '@form-4', 'tipo' => $type, 'orden' => $order, 'descripcion' => $row['texto']]);
            foreach ($row['valores'] as $rule => $value) { $add($key . '-rule-' . $rule, 'decision_valores', ['formulario_id' => '@form-4', 'elemento_id' => '@' . $key, 'regla_id' => '@rule-' . $rule, 'valor' => $value]); }
        }
    }
    $coverage = CoverageMetrics::blank();
    foreach (CoverageMetrics::METRICS as $metric => $label) { $coverage['metricas'][$metric] = ['total' => 0, 'cubiertos' => 0, 'herramienta' => '[DEMO] No medido en este reporte. 0 % no demuestra ausencia de cobertura.']; }
    $coverage['metricas']['sentencia'] = ['total' => 295, 'cubiertos' => 219, 'herramienta' => '[DEMO] PHPUnit 11.5.56 + Xdebug 3.5.3. Cobertura de líneas como aproximación documental de sentencia; no son métricas idénticas.'];
    $coverage = CoverageMetrics::validate($coverage);
    $add('form-5', 'formularios_prueba', ['caso_id' => '@case-d', 'usuario_id' => $owner, 'tipo' => 'cobertura']);
    foreach (array_keys(CoverageMetrics::METRICS) as $order => $metric) { $add('coverage-' . $metric, 'cobertura_metricas', ['formulario_id' => '@form-5', 'metrica' => $metric, 'orden' => $order] + $coverage['metricas'][$metric]); }

    $jmeter = 'Run real https://github.com/jrod-md/ISA3-P/actions/runs/37242589899: JMeter 5.6.3 / Java Temurin 17.0.20.1+1; 5 usuarios, ramp-up 5 s, 3 iteraciones, 45 muestras, 45 exitosas, 0 fallidas, 0 % error, promedio 12.3111 ms, throughput 10.6534 solicitudes/s. Prueba académica ligera, no benchmark empresarial.';
    $plan = TestPlanRepository::validate([
        'nombre_proyecto' => '[DEMO] ISA3-P / Marketplace Search Bus', 'version' => '1.0', 'responsable' => 'Equipo ISA3-P [DEMO] / ' . $tag, 'fecha' => $today,
        'alcance' => '[DEMO] Bus distribuido, Alpha/Beta/Gamma, autenticación, roles, Formularios 1–10, persistencia y validaciones. Dataset de exposición; conserva CP-024. Sin ampliar funcionalidad.',
        'objetivos' => '[DEMO] Verificar funcionalidad, integridad de datos, permisos y comportamiento del Bus mediante evidencia reproducible.',
        'estrategia' => '[DEMO] PHPUnit (caja blanca), suites HTTP/integración y caja negra, Selenium Side Runner, JMeter y GitHub Actions. Reporte comprobado: PHPUnit 99 tests / 172 assertions; Xdebug 219/295 líneas (74.24 %) del alcance seleccionado, no toda la aplicación. ' . $jmeter,
        'recursos' => '[DEMO] PHP 8.2; MariaDB/MySQL; XAMPP; PHPUnit 11.5.56; Xdebug 3.5.3; Selenium Side Runner 4.0.13; Apache JMeter 5.6.3; Java Temurin 17.0.20.1+1; GitHub Actions. TestCover no utilizado.',
        'criterios_aceptacion' => '[DEMO] CI verde; pruebas críticas aprobadas; Formularios 1–10 operativos; roles respetados; Bus y proveedores saludables. Evaluar según las suites y artefactos, no según puntuaciones demo.',
        'riesgos' => '[DEMO] Conflicto de puertos; dependencia de XAMPP; indisponibilidad de un proveedor; configuración incorrecta de base de datos. Cronograma de preparación: todas las fechas son del día real de seed, no una historia de desarrollo inventada.',
        'cronograma' => array_map(static fn ($activity) => ['actividad' => '[DEMO] ' . $activity, 'fecha_inicio' => $today, 'fecha_fin' => $today], ['Comprobar ambiente y servicios', 'Validar casos y preparar matrices', 'Revisar reportes de PHPUnit, Xdebug, Selenium y JMeter', 'Verificar idempotencia y limpieza del dataset', 'Recorrer Formularios 1–10 como Tester y Administrador']),
    ]);
    $schedule = $plan['cronograma']; unset($plan['cronograma']); $add('form-6', 'planes_prueba', ['usuario_id' => $owner] + $plan);
    foreach ($schedule as $order => $row) { $add('schedule-' . $order, 'plan_cronograma', ['plan_id' => '@form-6', 'orden' => $order] + $row); }
    $rubric = ['titulo' => '[DEMO] Auto-rúbrica preliminar, no calificación oficial', 'evaluado' => 'Equipo ISA3-P [DEMO]', 'fecha' => $today, 'observaciones' => 'Registro de demostración. No representa la calificación oficial del docente. ' . $tag . '. Valoración preliminar prudente; la exposición todavía no se ha evaluado.', 'filas' => [
        'diseno' => ['puntuacion' => 4, 'observacion' => '[DEMO] Cuatro casos documentados con entradas verificadas; auto-valoración preliminar.'],
        'tecnicas' => ['puntuacion' => 4, 'observacion' => '[DEMO] Caja negra con tres técnicas y caja blanca sobre SearchCriteria; no todas las subtécnicas ejecutadas.'],
        'cobertura' => ['puntuacion' => 4, 'observacion' => '[DEMO] 219/295 líneas = 74.24 % del alcance seleccionado; intervalo 70–90: Bueno (4). No es cobertura total de la aplicación ni de decisiones.'],
        'herramientas' => ['puntuacion' => 4, 'observacion' => '[DEMO] PHPUnit, Selenium Side Runner y JMeter ejecutados. TestCover no utilizado; no corresponde Excelente (5).'],
        'documentacion' => ['puntuacion' => 4, 'observacion' => '[DEMO] README, arquitectura y reportes existentes; valoración preliminar que puede revisar el docente.'],
        'presentacion' => ['puntuacion' => 3, 'observacion' => '[DEMO] Valor de ejemplo prudente para mostrar el formulario. Exposición pendiente; no acredita una presentación realizada.'],
    ]];
    $rubric = (new ProjectEvaluationRepository($pdo, 'rubrica'))->validate($rubric); $rows = $rubric['filas']; unset($rubric['filas']); $add('form-7', 'rubricas', ['usuario_id' => $owner] + $rubric);
    foreach ($rows as $order => $row) { $add('rubric-' . $order, 'rubrica_criterios', ['rubrica_id' => '@form-7', 'orden' => $order] + $row); }
    $evaluation = ['evaluado' => 'Tester Demo [DEMO] / ' . $tag, 'evaluador' => 'Administrador Demo [DEMO]', 'fecha' => $today, 'filas' => []];
    foreach (ProjectEvaluationRepository::ASPECTS as $key => $label) { $evaluation['filas'][$key] = ['autoevaluacion' => 4, 'coevaluacion' => $key === 'equipo' ? 3 : 4, 'comentarios' => '[DEMO] Datos de demostración para mostrar el funcionamiento del formulario; no representan una evaluación académica oficial. Puntuaciones ilustrativas, no observaciones de compañeros reales.']; }
    $evaluation = (new ProjectEvaluationRepository($pdo, 'evaluacion'))->validate($evaluation); $rows = $evaluation['filas']; unset($evaluation['filas']); $add('form-8', 'evaluaciones_pares', ['usuario_id' => $owner] + $evaluation);
    foreach ($rows as $order => $row) { $add('evaluation-' . $order, 'evaluacion_aspectos', ['evaluacion_id' => '@form-8', 'orden' => $order] + $row); }
    foreach (['README.md', 'docs/architecture.md', 'tests/phpunit/SearchCriteriaTest.php', 'docs/testing-tools.md', 'tests/selenium/ISA3-P.side', 'tests/jmeter/isa3-bus.jmx'] as $file) { if (!is_file(PROJECT_ROOT . '/' . $file)) { throw new RuntimeException('Falta evidencia documental real: ' . $file); } }
    $portfolio = ['titulo' => '[DEMO] Portafolio ISA3-P / ' . $tag, 'filas' => []];
    foreach ([
        [1, 'README y arquitectura del proyecto', 'Documento', 'README.md; docs/architecture.md'],
        [2, 'Matriz de clases de equivalencia', 'Taller', 'Formulario 2 de este dataset, asociado al caso A; docs/formularios-caja-negra.md'],
        [3, 'Reporte PHPUnit + Xdebug', 'Laboratorio', 'docs/testing-tools.md; coverage/clover.xml y coverage/html/index.html locales cuando se generan; artefacto phpunit-coverage en el run de CI enlazado. 99 tests/172 assertions; 219/295 líneas (74.24 %).'],
        [4, 'Plan de Pruebas ISA3-P', 'Proyecto', 'Formulario 6 de este dataset; docs/plan-pruebas.md'],
        [4, 'Flujo Selenium ISA3-P.side', 'Automatización', 'tests/selenium/ISA3-P.side; docs/testing-tools.md. Flujo ejecutado previamente con Selenium Side Runner 4.0.13, no en CI.'],
        [4, 'Resultados JMeter en GitHub Actions', 'Rendimiento', $jmeter],
    ] as [$week, $name, $type, $reference]) { $portfolio['filas'][] = ['semana' => $week, 'evidencia' => '[DEMO] ' . $name, 'tipo' => $type, 'fecha' => $today, 'observaciones' => '[DEMO] Referencia, sin adjunto físico. Semanas como agrupación académica; fecha real de preparación: ' . $today . '. ' . $reference]; }
    $portfolio = (new ProjectEvaluationRepository($pdo, 'portafolio'))->validate($portfolio); $rows = $portfolio['filas']; unset($portfolio['filas']); $add('form-9', 'portafolios', ['usuario_id' => $owner] + $portfolio);
    foreach ($rows as $order => $row) { $add('portfolio-' . $order, 'portafolio_evidencias', ['portafolio_id' => '@form-9', 'orden' => $order] + $row); }
    $incident = IncidentRepository::validate([
        'titulo' => '[DEMO] phpMyAdmin apuntaba a un puerto de base de datos incorrecto', 'modulo' => '[DEMO] Entorno local / configuración', 'severidad' => 'Media', 'prioridad' => 'Alta',
        'descripcion' => '[DEMO] Incidente histórico real relatado durante la preparación: phpMyAdmin estaba configurado para conectarse a un puerto distinto al MariaDB de XAMPP. El usuario estabilizó el entorno manualmente. Este seed no reproduce la avería ni cambia XAMPP. ' . $tag,
        'pasos_reproducir' => "1. Iniciar Apache y MySQL en XAMPP.\n2. Abrir /phpmyadmin.\n3. Intentar conexión.\n[DEMO] Secuencia histórica; actualmente el entorno funciona.",
        'resultado_esperado' => 'phpMyAdmin conecta con MariaDB de XAMPP en 3306.', 'resultado_obtenido' => 'Histórico: conexión rechazada porque phpMyAdmin apuntaba a otro puerto. Resuelto manualmente por el usuario; entorno actual confirmado saludable en 3306. Sin archivo de evidencia.',
        'estado' => 'Cerrado', 'asignado_a' => 'Equipo ISA3-P [DEMO]', 'caso_id' => null,
    ]);
    $add('form-10', 'incidentes', ['usuario_id' => $owner] + $incident);
    return $nodes;
}
