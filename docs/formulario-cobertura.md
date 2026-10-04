# Formulario 5: cobertura de Caja Blanca

Abre **Formularios → Cobertura de Caja Blanca → Nuevo registro** y selecciona un caso existente. También puedes usar **Crear** en la documentación asociada de la ficha del caso. La ruta es `/formularios/cobertura`, con `/nuevo`, `/ver?id=…`, `/editar?id=…` y `/eliminar?id=…`.

Siempre se registran cinco métricas: Cobertura de Sentencia, Decisión, Condición, Caminos y Bucles. Para cada una completa Total, Cubiertos y Herramienta Utilizada. Los conteos son enteros entre 0 y 4294967295 y Cubiertos no puede superar Total.

El sistema calcula **Cubiertos / Total × 100**, redondeado a dos decimales. Con Total = 0 solo se admite Cubiertos = 0 y se muestra **0.00 %**. JavaScript actualiza el porcentaje al escribir; el servidor vuelve a validarlo y calcularlo antes de guardar, ignorando cualquier porcentaje enviado por el navegador.

**La cobertura no se obtiene automáticamente desde herramientas externas en esta versión.** Total y Cubiertos son valores registrados por el tester. Herramienta Utilizada documenta la herramienta que empleó: ofrece TestCover, PHPUnit, Manual y Otra como sugerencias, pero admite texto libre de hasta 150 caracteres.

## Persistencia y permisos

La migración aditiva `sql/formulario_5_migration.sql` agrega `cobertura` a `formularios_prueba.tipo` conservando los tres tipos existentes y crea `cobertura_metricas`. Cada fila almacena métrica, conteos, porcentaje, herramienta y orden, vinculados al documento mediante una clave foránea. No se guardan las métricas como JSON.

El encabezado existente conserva las relaciones con `casos_prueba` y `usuarios`. Un documento tiene las cinco métricas; la validación exige el conjunto exacto y la base impide duplicarlas. Los guardados se realizan en una transacción.

El Tester crea documentación sobre sus propios casos y consulta o edita lo que creó. El Administrador crea sobre cualquier caso, consulta y edita todos y elimina con confirmación, POST y CSRF. La edición conserva el caso y el creador originales. Eliminar cobertura elimina sus métricas y conserva el caso y su evidencia. Eliminar un caso elimina en cascada su documentación asociada.

## Instalación y pruebas

En una instalación existente ejecuta `php scripts/migrate-proyecto2.php` con MySQL en marcha, o importa la migración dentro de `bus_meta` después de las migraciones anteriores. El SQL completo incluye el mismo esquema para instalaciones nuevas. La migración es reejecutable sin borrar datos existentes.

`php tests/smoke-coverage.php http://localhost/ISA3-Proyecto2` realiza 87 comprobaciones de creación, consulta, edición, permisos, ownership, CSRF, validación, cálculo, persistencia y relaciones. Crea y retira sus fixtures temporales y compara los registros anteriores.

CI ejecuta esta suite dentro de `tests/smoke-bus.php` con PHP y MariaDB en Linux, sin depender de XAMPP. Continúan las 100 pruebas de Formularios 2–4 y la regresión local del Formulario 1 sobre Apache.
