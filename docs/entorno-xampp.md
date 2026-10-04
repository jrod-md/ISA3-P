# Operación y pruebas locales con XAMPP

La aplicación utiliza MySQL/MariaDB de XAMPP en `127.0.0.1:3306`, con valores configurables en `.env`. Las bases son `bus_meta`, `market_alpha`, `market_beta` y `market_gamma`. No se necesita una segunda instancia de base de datos.

Inicia Apache y MySQL desde el panel XAMPP. Desde la raíz local ejecuta:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
```

Abre `http://localhost/ISA3-Proyecto2/login` si esa es tu carpeta en `htdocs`, o `http://127.0.0.1:8000/admin`. Para detener únicamente PHP propio usa `stop-dev.ps1`. Los scripts conservan sus comprobaciones de identidad; no detienen un proceso por el mero hecho de ocupar un puerto.

Las pruebas QA necesitan Apache porque también verifican las restricciones de `.htaccess`:

```powershell
& C:\xampp\php\php.exe .\tests\smoke-proyecto2.php http://localhost/ISA3-Proyecto2
```

Para repetir la prueba distribuida original con TTL breve usa Windows PowerShell 5.1 (`powershell.exe`). Su manejo HTTP depende de esa versión; el equivalente PHP portable no depende de PowerShell.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-dev.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1 -SearchTtlSeconds 3
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\smoke-test.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\stop-dev.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
```

Esta prueba original elimina búsquedas temporales globalmente y detiene/reinicia proveedores propios. Ejecútala durante una sesión dedicada de pruebas, sin búsquedas que necesites conservar. No elimina casos QA ni evidencias. El equivalente portable `tests/smoke-bus.php` evita el borrado global al usar la base local.

En phpMyAdmin, selecciona `bus_meta` para comprobar persistencia:

```sql
SELECT @@port AS puerto, DATABASE() AS base_actual;
SELECT c.id, CONCAT('CP-', LPAD(c.id, 3, '0')) AS codigo,
       u.username AS autor, c.modulo, c.tecnica, c.subtecnica, c.estado,
       c.evidencia_nombre, c.creado_en, c.actualizado_en
FROM casos_prueba c JOIN usuarios u ON u.id = c.usuario_id
ORDER BY c.id DESC;
```

La evidencia binaria vive en el disco local y no viaja con un clone. Las credenciales demo y los datos semilla se documentan en README; los casos de una instalación existente se conservan en su MySQL. No se publican nombres de bases ajenas, PIDs, informes de sesiones privadas ni rutas de respaldos personales.
