# ISA3-P

[![CI](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml/badge.svg)](https://github.com/jrod-md/ISA3-P/actions/workflows/ci.yml)

Integrated Software Engineering III project combining a distributed Marketplace Search Bus with a software testing management module.

Proyecto académico de Ingeniería de Software Aplicada III. Integra el **Marketplace Search Bus** del Proyecto 1 con el módulo de gestión de pruebas del Proyecto 2, dentro de la misma aplicación PHP y base de datos.

## Funcionalidades

- Búsqueda distribuida en Alpha, Beta y Gamma, con filtros, normalización de resultados, caché temporal y worker de caducidad.
- Autenticación existente ampliada con roles Administrador y Tester, dashboard y Formularios 1–4.
- Creación, consulta y edición de casos; eliminación reservada al Administrador. El Tester accede a sus propios casos.
- Registro de técnica y subtécnica de Caja Negra/Caja Blanca, resultados Éxito/Fallo y evidencias privadas PNG, JPG o PDF de hasta 2 MB.

Los Formularios 2–4 documentan **clases de equivalencia, valores límite y tablas de decisión**, con filas y reglas dinámicas vinculadas a un caso existente. El Tester consulta y edita su documentación; el Administrador gestiona todos los registros. Los **Formularios 5–10 continúan pendientes**. Los formularios documentan pruebas del propio proyecto; no calculan cobertura automáticamente.

## Requisitos y tecnologías

PHP 8.2 con `pdo_mysql`, `curl`, `fileinfo` y `mbstring`; MariaDB/MySQL; HTML, CSS y JavaScript. No requiere Composer, npm ni framework. Para el entorno local: XAMPP en `C:\xampp`, Apache con `mod_rewrite` y Windows PowerShell 5.1.

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

La instalación utiliza los SQL originales y las migraciones aditivas QA y `sql/formularios_2_4_migration.sql`. En una instalación existente también puedes ejecutar `php scripts/migrate-proyecto2.php`. No ejecutes `database/reset.sql` ni el seed original sobre datos que quieras conservar. Más detalles en [operación local](docs/entorno-xampp.md) y [Formularios 2–4](docs/formularios-caja-negra.md).

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
```

GitHub Actions ejecuta sintaxis PHP, instalación/reimportación, ocho unitarias, 24 comprobaciones portables del Bus y 100 de los Formularios 2–4 en Ubuntu con PHP 8.2 y MariaDB 10.11. Se activa en `push` y `pull_request`; no despliega.

La suite QA completa se ejecuta localmente sobre Apache. El Bus portable se prueba con `php tests/smoke-bus.php`, con MySQL iniciado y los puertos del runtime libres. La prueba distribuida original `scripts/smoke-test.ps1` requiere Windows PowerShell 5.1; consulta [las instrucciones locales](docs/entorno-xampp.md).

Licencia: [MIT](LICENSE).
