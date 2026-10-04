# Formularios 2–4: Caja Negra

Desde **Formularios**, abre una técnica, elige **Nuevo registro** y selecciona un caso del Formulario 1. También puedes abrir un caso y usar **Crear** en su documentación asociada. La ficha muestra los documentos disponibles para la cuenta actual; no obliga a completar las tres técnicas.

| Formulario | Ruta | Contenido |
| --- | --- | --- |
| 2 · Equivalencia | `/formularios/equivalencia` | Campo, clase válida, clases inválidas, valores representativos y resultado esperado |
| 3 · Valor límite | `/formularios/limites` | Campo, rango válido, mínimo, máximo, valores límite y resultado esperado |
| 4 · Decisión | `/formularios/decision` | Condiciones, acciones y reglas editables |

Cada ruta ofrece `/nuevo`, `/ver?id=…`, `/editar?id=…` y `/eliminar?id=…`. El catálogo `/formularios` y el dashboard muestran cuatro disponibles y seis pendientes. Las operaciones requieren la sesión existente; guardar y eliminar utilizan POST con CSRF.

Equivalencia y límites permiten agregar o quitar filas, conservando al menos una y hasta 30. Todos sus campos son obligatorios. En límites, si mínimo y máximo son numéricos, el mínimo no puede superar el máximo; también se admiten límites descriptivos o fechas.

Decisión comienza con cuatro reglas y permite de una a veinte reglas, condiciones y acciones por sección. Las condiciones admiten **V**, **F** y **–** (indiferente); las acciones **X** o vacío. Quitar una regla quita su columna completa. Los nombres de reglas, condiciones y acciones son obligatorios.

## Relación y permisos

`formularios_prueba` almacena tipo, caso, creador y fechas. Sus claves foráneas apuntan a `casos_prueba` y `usuarios`. Un caso puede tener varios documentos de cada tipo; el caso y el creador originales se conservan al editar.

`equivalencia_filas` y `limite_filas` almacenan filas ordenadas. `decision_reglas`, `decision_elementos` y `decision_valores` almacenan columnas, condiciones/acciones y sus celdas. Claves foráneas compuestas impiden asociar una celda a una fila o regla de otro documento. Los guardados usan transacciones; las matrices no se almacenan como JSON.

El Tester crea documentos sobre sus propios casos y consulta o edita únicamente los documentos que creó. El Administrador puede crear sobre cualquier caso, consultar y editar todos, y eliminar tras confirmación. Eliminar un documento conserva el caso y su evidencia. Eliminar un caso desde el Formulario 1 elimina en cascada su documentación asociada; esa pantalla lo advierte.

## Migración y comprobación

Con MySQL existente en marcha, ejecuta `php scripts/migrate-proyecto2.php`, o importa `sql/formularios_2_4_migration.sql` dentro de `bus_meta`. Son seis tablas nuevas con `CREATE TABLE IF NOT EXISTS`; no se recrean usuarios, casos ni tablas de búsqueda. `sql/instalacion_completa.sql` incluye el mismo esquema para instalaciones nuevas.

`php tests/smoke-black-box.php http://localhost/ISA3-Proyecto2` comprueba 100 escenarios HTTP y SQL: CRUD, propietarios, roles, CSRF, filas, reglas, validación, relaciones y cascadas. Crea y elimina exclusivamente sus fixtures temporales. CI lo ejecuta dentro de `tests/smoke-bus.php` contra el router PHP, sin Apache ni XAMPP. La regresión completa del Formulario 1 continúa en `tests/smoke-proyecto2.php` sobre Apache.
