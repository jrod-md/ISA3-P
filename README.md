# ISA3-P

[![CI](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml/badge.svg)](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml)

> **ConfiguraciÃ³n pendiente del badge:** sustituir `jrod-md` por el usuario u organizaciÃ³n de GitHub antes de publicar.

Integrated Software Engineering III project combining a distributed Marketplace Search Bus with a software testing management module.

Proyecto acadÃ©mico de IngenierÃ­a de Software Aplicada III que integra un Bus de bÃºsqueda distribuida de productos y un mÃ³dulo de gestiÃ³n de pruebas de software. El Proyecto 2 continÃºa el Proyecto 1 dentro de la misma aplicaciÃ³n PHP y de la misma base del Bus.

## Alcance y estado

El **Proyecto 1** aporta el buscador, tres proveedores HTTP con contratos distintos, adaptadores de normalizaciÃ³n, filtros, ordenamiento global, sesiones de bÃºsqueda, cachÃ© temporal y un worker de caducidad. El **Proyecto 2** amplÃ­a la autenticaciÃ³n existente con usuarios persistentes y roles, dashboard y Formulario 1: Registro de Caso de Prueba.

El Formulario 1 permite crear, consultar y editar casos, documentar Caja Negra o Caja Blanca con diez subtÃ©cnicas cada una, registrar resultados Ã‰xito/Fallo y adjuntar evidencia PNG, JPG o PDF de hasta 2 MB. El servidor valida permisos, CSRF y compatibilidad entre tÃ©cnica y subtÃ©cnica. Las evidencias se descargan mediante una ruta autenticada.

| Rol | Alcance |
| --- | --- |
| Administrador | Todos los casos, creaciÃ³n, consulta, ediciÃ³n y eliminaciÃ³n; consulta de usuarios y administraciÃ³n original de bÃºsquedas temporales. |
| Tester | CreaciÃ³n, consulta y ediciÃ³n de sus propios casos y acceso a sus evidencias; sin eliminaciÃ³n ni administraciÃ³n de usuarios. |

El registro pÃºblico crea Ãºnicamente Testers. `/login` y `/admin` usan la misma autenticaciÃ³n y sesiÃ³n; ambos roles llegan al dashboard. Seleccionar Caja Blanca documenta pruebas realizadas: el formulario no calcula cobertura automÃ¡ticamente.

Los **Formularios 2â€“10 estÃ¡n en desarrollo y pendientes de implementaciÃ³n**. El dashboard los identifica como Pendiente. El roadmap es incorporar esos formularios en siguientes avances acadÃ©micos; no estÃ¡n incluidos en este avance. Este repositorio incorpora integraciÃ³n continua; el despliegue automatizado/CD queda fuera del alcance actual.

## Arquitectura y tecnologÃ­as

PHP 8.2, PDO MySQL, cURL, sesiones PHP, MariaDB/MySQL, HTML, CSS y JavaScript sin framework. El autoload es propio; no se requieren Composer ni npm para ejecutar la aplicaciÃ³n. Windows utiliza XAMPP y PowerShell para la operaciÃ³n local; CI utiliza Linux y PHP.

| Servicio | Puerto | Responsabilidad |
| --- | --- | --- |
| Apache de XAMPP | 80, configuraciÃ³n local existente | Interfaz PHP mediante subcarpeta |
| MariaDB/MySQL de XAMPP | 3306 | Cuatro bases del proyecto |
| Marketplace Search Bus | 8000 | Buscador y API unificada |
| Alpha | 8101 | CatÃ¡logo `market_alpha` |
| Beta | 8102 | CatÃ¡logo `market_beta` |
| Gamma | 8103 | CatÃ¡logo `market_gamma` |
| Worker PHP | Sin puerto HTTP | EliminaciÃ³n fÃ­sica de bÃºsquedas caducadas |

`bus_meta` contiene `usuarios`, `casos_prueba`, `search_sessions` y `search_cache`. Cada proveedor conserva su tabla `products` en una base independiente. Consulta [la arquitectura](docs/architecture.md) y los diagramas en `docs/uml/`.

```text
.github/workflows/ci.yml    IntegraciÃ³n continua
apps/bus/                  Router, buscador, login y mÃ³dulo QA
database/                  SQL original de esquemas, tablas y catÃ¡logos
docs/                      Arquitectura e instrucciones
scripts/                   PreparaciÃ³n, migraciÃ³n, worker y operaciÃ³n local
services/                  Alpha, Beta, Gamma y cÃ³digo comÃºn
shared/                    Dominio, conexiÃ³n PDO y utilidades
sql/                       MigraciÃ³n QA e instalaciÃ³n completa
tests/                     Unitarias, instalaciÃ³n y comprobaciones HTTP
bootstrap.php              Autoload y carga de configuraciÃ³n
.env.example               Plantilla pÃºblica
.runtime/                  Generado localmente; excluido de Git
```

## Requisitos e instalaciÃ³n local

- Windows, XAMPP con PHP 8.2 y MariaDB/MySQL, y Windows PowerShell 5.1 (`powershell.exe`) para los comandos locales y la comprobaciÃ³n distribuida original.
- Extensiones PHP `pdo_mysql`, `curl`, `fileinfo` y `mbstring`.
- Apache con `mod_rewrite` y soporte para `.htaccess` cuando se usa la URL de XAMPP.
- Puerto 3306 disponible para el MySQL de XAMPP; puertos 8000 y 8101â€“8103 disponibles para los procesos del proyecto.

El nombre pÃºblico es **ISA3-P**; la carpeta local puede seguir llamÃ¡ndose **ISA3-Proyecto2**. Para una instalaciÃ³n nueva:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/jrod-md/ISA3-P.git ISA3-Proyecto2
cd ISA3-Proyecto2
Copy-Item -LiteralPath .env.example -Destination .env
notepad .env
```

No sobrescribas `.env` si ya tienes una instalaciÃ³n configurada. Sus valores predeterminados son:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
BUS_DB_NAME=bus_meta
ALPHA_DB_NAME=market_alpha
BETA_DB_NAME=market_beta
GAMMA_DB_NAME=market_gamma
```

La plantilla tambiÃ©n configura las URLs de los proveedores, TTL de bÃºsquedas de 60 segundos, intervalos del worker y la cuenta Administrador de demostraciÃ³n. Los valores se pueden cambiar mediante `.env`; las variables de entorno del proceso tienen prioridad. `.env` y `.runtime/` nunca se publican.

Para preparar las bases con XAMPP MySQL ya iniciado:

```powershell
& C:\xampp\php\php.exe .\scripts\prepare-database.php
```

El instalador crea `bus_meta`, `market_alpha`, `market_beta` y `market_gamma` si faltan. Ejecuta `database/001_create_databases.sql`, `database/002_create_tables.sql`, los INSERT de `database/003_seed_products.sql` solamente para catÃ¡logos nuevos, y `sql/proyecto2_migration.sql`. Crea las cuentas demo ausentes con contraseÃ±as hash mediante `scripts/migrate-proyecto2.php`. Conserva catÃ¡logos, usuarios y casos existentes.

Alternativamente, para instalar los cuatro nombres predeterminados desde phpMyAdmin importa `sql/instalacion_completa.sql`: incluye tablas, 37 productos de demostraciÃ³n y dos cuentas demo, sin borrar registros existentes. Para extender Ãºnicamente una base del Proyecto 1, selecciona la base del Bus e importa `sql/proyecto2_migration.sql`; luego ejecuta `php scripts/migrate-proyecto2.php` con tu configuraciÃ³n.

`database/reset.sql` y el seed original `database/003_seed_products.sql` contienen borrados explÃ­citos para reinicializar datos. No los ejecutes sobre informaciÃ³n que quieras conservar. Los scripts de instalaciÃ³n integrada y arranque no ejecutan esos borrados.

## Arranque definitivo con XAMPP

A. Abrir XAMPP Control Panel.
B. Pulsar **Start** en Apache.
C. Pulsar **Start** en MySQL.
D. Abrir PowerShell.
E. Ir a la carpeta del proyecto.
F. Ejecutar `start-dev.ps1`.
G. Abrir la URL de login.

```powershell
cd C:\xampp\htdocs\ISA3-Proyecto2
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
```

Abrir [http://localhost/ISA3-Proyecto2/login](http://localhost/ISA3-Proyecto2/login). El buscador tambiÃ©n estÃ¡ en [http://127.0.0.1:8000/](http://127.0.0.1:8000/).

El script prepara las tablas de forma aditiva e inicia Ãºnicamente Bus, proveedores y worker PHP. Comprueba la identidad de procesos registrados antes de reiniciarlos y rechaza puertos ocupados por procesos ajenos. Apache y MySQL se controlan desde XAMPP. Para detener los procesos propios:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-dev.ps1
```

No se borran datos ni se detienen Apache o MySQL. Los scripts locales esperan XAMPP en `C:\xampp`; no forman parte de la lÃ³gica de CI.

## Cuentas de demostraciÃ³n

Estas credenciales son **pÃºblicas, exclusivamente de demostraciÃ³n** y se generan por la migraciÃ³n/seed. No representan secretos ni cuentas privadas.

| Rol | Usuario | ContraseÃ±a demo |
| --- | --- | --- |
| Administrador | `admin` | `demo-isa3-2026` |
| Tester | `tester` | `Tester123!` |

TambiÃ©n se admiten `admin@isa3.local` y `tester@isa3.local`. DespuÃ©s de crear las cuentas, el login verifica el hash almacenado en `usuarios`. Cambiar `ADMIN_PASSWORD` en `.env` no sobrescribe la contraseÃ±a de una cuenta existente. Las sesiones y evidencias de una instalaciÃ³n local no forman parte del repositorio; los casos existentes tampoco se transportan con el cÃ³digo.

## Pruebas

Con MySQL de XAMPP iniciado:

```powershell
& C:\xampp\php\php.exe .\tests\run.php
& C:\xampp\php\php.exe .\tests\verify-installation.php
```

La primera ejecuta las ocho unitarias originales; una utiliza PDO para verificar construcciÃ³n segura de consultas. La segunda importa y reimporta el SQL completo en cuatro esquemas temporales con nombres aleatorios, comprueba los 37 productos, hashes demo y claves forÃ¡neas, y retira Ãºnicamente sus propios esquemas.

La prueba portable del Bus inicia y detiene sus propios procesos PHP en 8000 y 8101â€“8103. Rechaza puertos ocupados y no administra MySQL. DetÃ©n primero el runtime de desarrollo:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-dev.ps1
& C:\xampp\php\php.exe .\tests\smoke-bus.php
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
```

Verifica salud, interfaz/login, filtros, consultas literales, agregaciÃ³n, normalizaciÃ³n, orden, cachÃ©, administraciÃ³n, fallo parcial/total y TTL fÃ­sico con el worker. Usa TTL de 3 segundos solo en sus procesos. En CI comprueba borrado global sobre la base efÃ­mera; localmente borra Ãºnicamente sus propias bÃºsquedas.

Con Apache y el runtime local iniciado, la prueba existente del mÃ³dulo QA es:

```powershell
& C:\xampp\php\php.exe .\tests\smoke-proyecto2.php http://localhost/ISA3-Proyecto2
```

Comprueba login, roles, propiedad, CRUD, las veinte subtÃ©cnicas, CSRF, validaciones, evidencias, migraciÃ³n y acceso directo bloqueado por Apache. Crea y retira Ãºnicamente sus usuarios, casos y archivos temporales. `scripts/smoke-test.ps1` conserva las 24 comprobaciones distribuidas originales especÃ­ficas de Windows PowerShell 5.1, incluidas la interrupciÃ³n de proveedores y eliminaciÃ³n de todas las bÃºsquedas temporales; consulta [el entorno local](docs/entorno-xampp.md) antes de ejecutarlas. Su manejo de errores HTTP utiliza una API de Windows PowerShell 5.1; ejecuta ese archivo con `powershell.exe`, no directamente dentro de PowerShell 7 (`pwsh`).

## IntegraciÃ³n continua

En cada `push` y `pull_request`, `.github/workflows/ci.yml` ejecuta Ubuntu, PHP 8.2 con las extensiones requeridas y un servicio MariaDB 10.11 en 3306. Prepara `.env.example`, instala las cuatro bases y la migraciÃ³n, valida sintaxis PHP y ejecuta unitarias, instalaciÃ³n/reimportaciÃ³n y las 24 comprobaciones portables del Bus.

No usa rutas Windows, XAMPP ni PowerShell; no requiere secretos del repositorio y no despliega. Las comprobaciones de Apache y el mÃ³dulo QA permanecen como prueba local completa. Consulta [publicaciÃ³n y auditorÃ­a](docs/publicacion.md) para el inventario, historial y pasos previos al primer push.

## English overview

ISA3-P is an academic Software Engineering III project. Project 1 provides a distributed Marketplace Search Bus with three HTTP providers, normalized results, temporary search sessions, cache and an expiration worker. Project 2 extends the same PHP application and authentication with Administrator/Tester roles and test-case management backed by `bus_meta`.

Form 1 supports CRUD with role restrictions, black-box/white-box techniques, twenty subtechniques and private evidence. Forms 2â€“10 are under development and are not implemented yet. Local execution uses XAMPP and PowerShell; GitHub Actions uses Linux, PHP 8.2 and MariaDB. CI validates syntax, database installation, existing unit tests and distributed Bus behavior. Automated deployment is outside the current scope.

## Licencia

CÃ³digo del proyecto bajo [MIT](LICENSE). No se encontraron librerÃ­as, fuentes o frameworks externos incorporados al Ã¡rbol de cÃ³digo. PHP, MariaDB, XAMPP y las acciones de CI se distribuyen por sus respectivos autores bajo sus propias licencias. Antes de publicar, los integrantes deben confirmar que tienen derecho a licenciar sus contribuciones acadÃ©micas.

