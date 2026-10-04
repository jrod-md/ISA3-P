# ISA3-P

[![CI](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml/badge.svg)](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml)

Integrated Software Engineering III project combining a distributed Marketplace Search Bus with a software testing management module.

Proyecto académico de Ingeniería de Software Aplicada III. Integra el **Marketplace Search Bus** del Proyecto 1 con el módulo de gestión de pruebas del Proyecto 2, dentro de la misma aplicación PHP y base de datos.

## Funcionalidades

- Búsqueda distribuida en Alpha, Beta y Gamma, con filtros, normalización de resultados, caché temporal y worker de caducidad.
- Autenticación existente ampliada con roles Administrador y Tester, dashboard y Formularios 1–10.
- Creación, consulta y edición de casos; eliminación reservada al Administrador. El Tester accede a sus propios casos.
- Registro de técnica y subtécnica de Caja Negra/Caja Blanca, resultados Éxito/Fallo y evidencias privadas PNG, JPG o PDF de hasta 2 MB.

Los Formularios 2–5 documentan actividades **asociadas a casos de prueba**: equivalencia, valores límite, decisiones y cobertura de Caja Blanca. En cobertura, el tester ingresa Total y Cubiertos y el sistema calcula el porcentaje; Herramienta Utilizada es texto libre y no existe importación automática desde herramientas externas.

El **Formulario 6 es el Plan de Pruebas a nivel proyecto**, con versiones y cronograma de actividades; no depende de un caso individual. Su Responsable es independiente de la cuenta creadora. El Tester consulta y edita sus registros; el Administrador gestiona todos. Los **Formularios 1–10 están completos**.

Los **Formularios 7–9** son documentos de proyecto: rúbrica sumativa, autoevaluación/coevaluación formativa y portafolio de evidencias. Las puntuaciones las registra el usuario; el servidor calcula total y promedios. El portafolio organiza nombres/descripciones sin adjuntos. [Detalles y permisos](docs/evaluacion-evidencias.md).

El **Formulario 10 registra incidentes del proyecto** con código BUG automático, caso opcional, filtros y captura/log privado de hasta 2 MB. Eliminar un caso conserva el incidente. [Gestión de defectos](docs/incidentes.md).

## Requisitos y tecnologías

PHP 8.2 con `pdo_mysql`, `curl`, `fileinfo` y `mbstring`; MariaDB/MySQL; HTML, CSS y JavaScript. La aplicación no requiere Composer, npm ni framework para funcionar; PHPUnit usa Composer como dependencia de desarrollo. Para el entorno local: XAMPP en `C:\xampp`, Apache con `mod_rewrite` y Windows PowerShell 5.1.

| Servicio | Puerto |
| --- | --- |
| Apache de XAMPP | 80 |
| MySQL/MariaDB | 3306 |
| Bus | 8000 |
| Alpha / Beta / Gamma | 8101 / 8102 / 8103 |

Consulta [la arquitectura](docs/architecture.md) para la estructura y las relaciones entre servicios y bases.

## Instalación y configuración

```powershell
cd C:\xampp\htdocs
git clone https://github.com/jrod-md/ISA3-P.git ISA3-Proyecto2
cd ISA3-Proyecto2
Copy-Item -LiteralPath .env.example -Destination .env
```

La **Configuración** predeterminada usa `127.0.0.1:3306`, usuario `root` y contraseña vacía. Las bases son `bus_meta`, `market_alpha`, `market_beta` y `market_gamma`; los valores se configuran en `.env`. Conserva tu `.env` si ya tienes una instalación.

Inicia **Apache y MySQL desde XAMPP** y ejecuta:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
```

El script prepara las bases y tablas de forma aditiva e inicia Bus, proveedores y worker PHP. Conserva los datos existentes. Abre [el login](http://localhost/ISA3-Proyecto2/login) o [el buscador](http://127.0.0.1:8000/).

Para detener únicamente los procesos propios:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-dev.ps1
```

La instalación utiliza los SQL originales y las migraciones aditivas de los Formularios 1–10. En una instalación existente también puedes ejecutar `php scripts/migrate-proyecto2.php`. No ejecutes `database/reset.sql` ni el seed original sobre datos que quieras conservar. Más detalles en [operación local](docs/entorno-xampp.md), [Caja Negra](docs/formularios-caja-negra.md), [cobertura](docs/formulario-cobertura.md) y [plan del proyecto](docs/plan-pruebas.md).

## Cuentas demo

| Rol | Usuario | Contraseña pública de demostración |
| --- | --- | --- |
| Administrador | `admin` | `demo-isa3-2026` |
| Tester | `tester` | `Tester123!` |

Las cuentas se generan por seed/migración con contraseñas hash. `.env`, sesiones, evidencias locales, respaldos y ZIPs están excluidos de Git.

## Pruebas e Integración continua

```powershell
& C:\xampp\php\php.exe .\tests\run.php
& C:\xampp\php\php.exe .\tests\verify-installation.php
& C:\xampp\php\php.exe .\tests\smoke-proyecto2.php http://localhost/ISA3-Proyecto2
& C:\xampp\php\php.exe .\tests\smoke-black-box.php http://localhost/ISA3-Proyecto2
& C:\xampp\php\php.exe .\tests\smoke-coverage.php http://localhost/ISA3-Proyecto2
& C:\xampp\php\php.exe .\tests\smoke-test-plan.php http://localhost/ISA3-Proyecto2
& C:\xampp\php\php.exe .\tests\smoke-project-evaluations.php http://localhost/ISA3-Proyecto2
& C:\xampp\php\php.exe .\tests\smoke-incidents.php http://localhost/ISA3-Proyecto2
```

GitHub Actions ejecuta sintaxis PHP, instalación/reimportación, ocho unitarias previas, PHPUnit con Xdebug, 24 comprobaciones portables del Bus, 100 de Caja Negra, 87 de cobertura, 112 del Plan de Pruebas, 365 de evaluación/evidencias y 255 de incidentes en Ubuntu con PHP 8.2 y MariaDB 10.11. Se activa en `push` y `pull_request`; no despliega.

La suite QA completa se ejecuta localmente sobre Apache. El Bus portable se prueba con `php tests/smoke-bus.php`, con MySQL iniciado y los puertos del runtime libres. La prueba distribuida original `scripts/smoke-test.ps1` requiere Windows PowerShell 5.1; consulta [las instrucciones locales](docs/entorno-xampp.md).

## Herramientas de pruebas

PHPUnit prueba unidades reales del proyecto; Xdebug genera cobertura de los archivos seleccionados, con informes Clover/HTML y artefactos en CI. El proyecto Selenium IDE incluye un recorrido Tester ejecutado con Side Runner. JMeter tiene un plan pequeño validado, pendiente de ejecución con Java/JMeter local. No se utiliza TestCover. [Instalación, comandos, alcance y evidencias](docs/testing-tools.md).

```powershell
composer install
php vendor/bin/phpunit
```

## Roadmap

Formularios 1–10 completos. Integraciones con herramientas externas y automatización avanzada quedan como trabajo futuro.

Licencia: [MIT](LICENSE).
