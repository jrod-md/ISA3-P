# Herramientas de pruebas de ISA3-P

La aplicación y sus Formularios 1–10 conservan su comportamiento. Esta capa añade pruebas de código, un recorrido funcional y un plan de carga pequeño. Las suites smoke existentes siguen verificando HTTP, permisos, persistencia, evidencias y la integración con MySQL.

## Estado comprobado

| Herramienta | Integración | Ejecución comprobada | CI |
| --- | --- | --- | --- |
| PHPUnit 11.5.56 | Composer, lockfile y `phpunit.xml.dist` | 99 tests, 172 assertions, sin fallos | Sí |
| Xdebug 3.5.3 + PHPUnit | Clover, HTML y consola | 219/295 líneas, 74,24 % del alcance seleccionado | Sí; Xdebug mediante setup-php |
| Selenium IDE | Proyecto `.side` importable y configuración CLI para Edge | Un flujo aprobado con Selenium Side Runner 4.0.13 y Edge; no se ejecutó mediante la extensión IDE | No; ejecución local opcional |
| Apache JMeter 5.6.3 | Plan `.jmx`, tres requests y assertions | Implementado y ejecutado en GitHub Actions: 45 muestras, 45 exitosas, 0 fallidas | Sí; Java Temurin 17 |
| PCOV / TestCover | No integrados | No utilizados | No |

Los recuentos de PHPUnit y la cobertura corresponden a la ejecución local de esta entrega; JMeter corresponde al run de CI enlazado más abajo. Para una revisión posterior, consulta los resultados de su propia ejecución; no son un umbral obligatorio ni una medición general de rendimiento de la aplicación.

## PHPUnit: unidades reales

Los seis archivos `tests/phpunit/*Test.php` prueban directamente:

- `SearchCriteria`: normalización, filtros vacíos, ceros, límites, Unicode, rangos y valores rechazados.
- `CoverageMetrics`: porcentaje, redondeo, total cero, cinco métricas obligatorias y recálculo de porcentajes enviados por el cliente.
- `ProviderFormatter`, adaptadores Alpha/Beta/Gamma y `NormalizedProduct`: conversión de esquemas, tipos, catálogos vacíos y respuestas inválidas.
- Métodos puros de `IncidentRepository`: campos requeridos, catálogos, caso opcional, límites e ignorar propietario/metadatos falsificados.
- Helpers existentes: códigos CP/BUG, escape HTML, extracción de texto, zona horaria y catálogo de técnicas/formularios; `SearchSession` y UUID v4.
- `ProductRepository::buildQuery`: parámetros enlazados, límites y orden permitido. Usa un doble de PDO; no abre una conexión ni ejecuta SQL.

No llaman a smoke tests mediante `exec`. El bootstrap de PHPUnit reutiliza el autoloader y los helpers existentes, sin iniciar una sesión ni conectarse a MySQL. No se extrajo ni cambió lógica de negocio.

Desde la raíz del proyecto, con Composer y PHP en PATH:

```powershell
composer install --no-interaction --prefer-dist
php vendor/bin/phpunit
# Equivalentes: vendor/bin/phpunit o composer test
```

En XAMPP se puede usar Composer local, sin instalación global. Descarga y verifica su instalador según [las instrucciones oficiales](https://getcomposer.org/download/):

```powershell
cd C:\xampp\htdocs\ISA3-Proyecto2
New-Item -ItemType Directory -Force .runtime/tools/composer | Out-Null
Invoke-WebRequest https://getcomposer.org/installer -OutFile .runtime/tools/composer/installer.php
$expected = (Invoke-RestMethod https://composer.github.io/installer.sig).Trim()
$actual = (Get-FileHash .runtime/tools/composer/installer.php -Algorithm SHA384).Hash.ToLowerInvariant()
if ($actual -ne $expected) { throw 'Firma de Composer incorrecta' }
& C:\xampp\php\php.exe .runtime/tools/composer/installer.php --install-dir=.runtime/tools/composer --filename=composer.phar
& C:\xampp\php\php.exe -d extension=zip .runtime/tools/composer/composer.phar install --no-interaction --prefer-dist
& C:\xampp\php\php.exe vendor/bin/phpunit
```

`-d extension=zip` carga la extensión solo para ese comando cuando ZIP está deshabilitado en XAMPP. Si tu PHP ya la tiene activa, omite ese argumento. `vendor/` se instala desde `composer.lock` y queda fuera de Git; la aplicación sigue usando su autoloader original y no requiere Composer para funcionar.

## Cobertura real con Xdebug

PHPUnit mide ejecución de código con Xdebug. `phpunit.xml.dist` delimita los archivos analizados: dominio y criterios del Bus, adaptadores, UUID, validaciones/helpers seleccionados y dos repositorios. Se incluyen también los métodos no ejecutados de esos archivos; por ejemplo, el CRUD SQL de incidentes queda sin cubrir por estas unitarias, aunque lo prueben las suites HTTP.

La medición local fue **219 líneas ejecutadas de 295 medibles: 74,24 %**. No representa toda la aplicación, ni cobertura de ramas/caminos, ni la cantidad de pruebas smoke. No se suman recuentos smoke al numerador de cobertura. El reporte incluye archivos sin ejecución dentro del alcance configurado. Los porcentajes manuales del Formulario 5 siguen siendo datos académicos independientes: no se agregó importación automática.

En Linux/macOS, con Xdebug instalado:

```bash
XDEBUG_MODE=coverage composer test:coverage
```

En PowerShell, con Xdebug ya instalado en el PHP de pruebas:

```powershell
$env:XDEBUG_MODE = 'coverage'
composer test:coverage
Remove-Item Env:XDEBUG_MODE
```

Para el XAMPP comprobado de esta entrega —PHP 8.2 TS, VS16, x64— se utilizó una DLL oficial únicamente en el proceso CLI, sin editar `php.ini` ni configurar Apache:

```powershell
New-Item -ItemType Directory -Force .runtime/tools/xdebug | Out-Null
Invoke-WebRequest https://xdebug.org/files/php_xdebug-3.5.3-8.2-ts-vs16-x86_64.dll -OutFile .runtime/tools/xdebug/php_xdebug.dll
$dll = (Resolve-Path .runtime/tools/xdebug/php_xdebug.dll).Path
& C:\xampp\php\php.exe -d "zend_extension=$dll" -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text --coverage-clover coverage/clover.xml --coverage-html coverage/html --log-junit test-results/phpunit.xml
```

Usa una DLL que corresponda a la versión, arquitectura y Thread Safety de tu PHP; consulta [Xdebug](https://xdebug.org/download) y [la cobertura de PHPUnit](https://docs.phpunit.de/en/11.5/code-coverage.html). Abre `coverage/html/index.html` para inspeccionar cada archivo/línea. Clover está en `coverage/clover.xml`; JUnit en `test-results/phpunit.xml`. Esas carpetas no se publican en Git.

## Selenium IDE: recorrido funcional

Proyecto: `tests/selenium/ISA3-P.side`. Contiene una suite, un test y 39 comandos con esperas por elementos, IDs existentes, dos atributos `data-testid` y enlaces por su ruta. No usa posiciones `nth-child` ni pausas fijas. El menú móvil se abre solo cuando está visible.

Precondiciones: Apache y MySQL iniciados en XAMPP, `scripts/start-dev.ps1` ejecutado, las cuentas demo y los catálogos originales disponibles. CP-024 debe pertenecer a Tester, como en esta instalación. Comienza sin sesión activa; no cambies permisos para adaptar el test. En una instalación sin CP-024, sustituye esa comprobación por un caso accesible existente o un estado vacío válido.

El flujo abre el login en `http://localhost/ISA3-Proyecto2/login`, ingresa como `tester / Tester123!`, verifica Dashboard, busca `laptop`, comprueba las seis laptops de los catálogos demo, verifica los diez formularios disponibles, consulta CP-024 y cierra sesión. No crea/edita/elimina casos, documentos ni incidentes. La búsqueda crea la sesión/caché temporal normal del Bus, que caduca con el worker; no deja registros académicos persistentes.

Para ejecutarlo en la extensión:

1. Instala Selenium IDE para Chrome o Firefox desde los enlaces del [sitio oficial](https://www.selenium.dev/selenium-ide/docs/en/introduction/getting-started).
2. Abre Selenium IDE y selecciona **Open an existing project**.
3. Importa `tests/selenium/ISA3-P.side` y confirma base URL `http://localhost`.
4. Ejecuta la suite **Demostración académica**. Comprueba que todas las assertions terminan en verde; el cierre de sesión permite repetirla.

La comprobación de esta entrega se realizó con el runner real, no con la extensión. Instalación opcional local:

```powershell
npm.cmd install --prefix .runtime/tools/selenium --no-audit --no-fund --ignore-scripts selenium-side-runner@4.0.13
```

Descarga [EdgeDriver oficial](https://developer.microsoft.com/en-us/microsoft-edge/tools/webdriver/) compatible con Edge y descomprímelo en `.runtime/tools/selenium/driver`. Los tres primeros componentes de versión deben coincidir; no reutilices un driver de otra versión. Luego:

```powershell
$env:PATH = (Resolve-Path .runtime/tools/selenium/driver).Path + ';' + $env:PATH
& .runtime/tools/selenium/node_modules/.bin/selenium-side-runner.cmd -n tests/selenium/edge.side.yml -o .runtime/selenium/results -z .runtime/selenium/failures tests/selenium/ISA3-P.side
```

`edge.side.yml` usa un solo worker y Edge headless con perfil temporal; no usa el perfil personal del navegador. Node y Edge deben estar disponibles. Resultados JSON y capturas de fallos quedan en `.runtime/selenium/`. [Referencia del runner](https://www.selenium.dev/selenium-ide/docs/en/introduction/command-line-runner).

## Apache JMeter: carga pequeña del Bus

Plan: `tests/jmeter/isa3-bus.jmx`, formato de Apache JMeter 5.6.3. Tiene **5 usuarios, ramp-up de 5 segundos y 3 iteraciones**, con tres requests GET por iteración: **45 muestras previstas**.

- `/health`: HTTP 200, JSON `status=ok` y `database=ok`.
- `/api/search?q=laptop`: HTTP 200, JSON con ID de búsqueda, resultados no vacíos, producto normalizado y los tres proveedores saludables.
- `/api/search?q=laptop&category=computers&min_price=600&max_price=900&sort=price_asc`: las mismas assertions sobre una búsqueda con filtros.

Hay timeouts de conexión/respuesta y assertions de respuesta y JSONPath. No hay login, solicitudes de escritura académica ni listeners pesados. No es un benchmark representativo de producción; el servidor PHP local y esta carga pequeña limitan las conclusiones. Las búsquedas generan datos temporales que limpia el worker.

**Implementado y ejecutado en GitHub Actions.** El [run 37242427326](https://github.com/jrod-md/ISA3-P/actions/runs/37242427326) terminó exitosamente: **45 muestras, 45 exitosas, 0 fallidas, 0 % de error, promedio 23,2222 ms y throughput 10,7117 solicitudes/s**, calculados a partir del JTL. Hubo 15 muestras por endpoint. Estos valores pertenecen a esa ejecución; los siguientes runs pueden tener tiempos distintos.

El runner Linux obtiene Java mediante `actions/setup-java@v6`, con distribución **Temurin** y versión fijada **17.0.20+101**; `java -version` confirmó **OpenJDK 17.0.20.1+1**. Descarga **Apache JMeter 5.6.3**, verificando su SHA-512 antes de descomprimirlo en el directorio temporal del runner. No requiere instalar Java/JMeter en Windows.

Después de la instalación de las bases y la suite portátil, `tests/jmeter/run_ci.py` comprueba que los puertos están libres, inicia sus propios procesos del Bus y los tres proveedores, verifica `/health` y ejecuta el plan en modo headless. Detiene únicamente sus procesos al terminar. La suite portátil ya detiene los suyos, por lo que no se duplican servicios.

La pipeline exige código de salida 0, exactamente 45 muestras (15 por endpoint), HTTP 200 y ausencia de fallos en `success`/`failureMessage`, incluidas las assertions JSON. Imprime total, éxitos, fallos, error, promedio y throughput calculados del JTL. El artefacto **jmeter-results**, con retención de 14 días, conserva `results.jtl`, `jmeter.log`, `report/`, `summary.json`, `versions.txt` y logs de los procesos. El throughput es muestras por segundo entre el inicio de la primera muestra y el final de la última. Es una prueba académica ligera del Bus, no una prueba de carga empresarial.

Para ejecutar, prepara un JDK compatible —por ejemplo Java 17— y descarga la distribución binaria desde [Apache JMeter](https://jmeter.apache.org/download_jmeter.cgi). Puedes descomprimir ambas herramientas dentro de `.runtime/tools`, sin instalación global. Establece `JAVA_HOME` solo en esa ventana de PowerShell y apunta al directorio real de tu JDK. Con JMeter en PATH:

```powershell
New-Item -ItemType Directory -Force .runtime/jmeter | Out-Null
jmeter -n -t tests/jmeter/isa3-bus.jmx -l .runtime/jmeter/results.jtl -j .runtime/jmeter/jmeter.log
```

Con una distribución local 5.6.3:

```powershell
& .runtime/tools/apache-jmeter-5.6.3/bin/jmeter.bat -n -t tests/jmeter/isa3-bus.jmx -l .runtime/jmeter/results.jtl -j .runtime/jmeter/jmeter.log
```

Usa un archivo de resultados nuevo en cada ejecución. El destino por defecto es `127.0.0.1:8000`; no hace falta cambiar puertos. También puedes abrir el `.jmx` en la GUI para inspeccionarlo, pero ejecuta la carga en modo headless. Revisa `success` y `failureMessage` en el JTL y las assertions; no consideres un simple fin de proceso como prueba de que todas las muestras pasaron. [Referencia de componentes y assertions](https://jmeter.apache.org/usermanual/component_reference.html).

## CI y regresión

GitHub Actions conserva sintaxis PHP, instalación/migraciones, ocho unitarias previas, instalación limpia/reimportación y todas las suites HTTP/Bus existentes. Instala desde `composer.lock` y ejecuta PHPUnit con Xdebug. Publica `coverage/` y JUnit como artefacto **phpunit-coverage**, con retención de 14 días. Añade la ejecución de JMeter y valida sus muestras; los informes generados quedan fuera de Git. Selenium mantiene su ejecución local y no se vuelve a ejecutar en CI.

La regresión académica completa sigue siendo la indicada en README. Para el Bus portátil, primero detén exclusivamente los procesos propios con `scripts/stop-dev.ps1`, ejecuta `php tests/smoke-bus.php` con MySQL activo y luego `scripts/start-dev.ps1`. No detengas Apache ni MySQL. Nunca ejecutes suites de datos en paralelo contra la misma base.
