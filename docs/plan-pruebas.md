# Formulario 6: Plan de Pruebas del Proyecto

Desde **Formularios → Plan de Pruebas del Proyecto → Nuevo plan**, registra el proyecto y su versión. El listado está en `/formularios/plan`; las operaciones utilizan `/nuevo`, `/ver?id=…`, `/editar?id=…` y `/eliminar?id=…`.

**Es un documento a nivel proyecto, independiente de los casos individuales.** No requiere `caso_id`, no se almacena en `formularios_prueba` y no aparece en la documentación asociada de CP-024 ni de otros casos. Los Formularios 2–5 conservan su vinculación con casos.

Todos los campos requeridos por la Unidad III se conservan, organizados así:

| Sección | Campos |
| --- | --- |
| Información general | Nombre del Proyecto, Versión, Responsable y Fecha |
| Definición | Alcance y Objetivos |
| Estrategia | Estrategia de Pruebas y Recursos |
| Cronograma | Actividad, Fecha de Inicio y Fecha de Fin por fila |
| Cierre | Criterios de Aceptación y Riesgos |

Puedes crear varios planes del mismo proyecto para distintas versiones. No se impone un único plan global. `usuario_id` identifica la cuenta que creó el documento; Responsable es texto editable que indica la persona responsable del plan, y puede ser distinta del creador.

El cronograma admite entre 1 y 30 actividades. Agrega o quita filas con sus botones; se conserva al menos una. Todas las fechas deben existir en el calendario y Fecha de Fin debe ser igual o posterior a Fecha de Inicio. Cliente y servidor validan la relación; el servidor valida además el conjunto completo antes de guardar. Recursos y Riesgos son campos de texto del plan.

## Persistencia y permisos

La migración aditiva `sql/formulario_6_migration.sql` crea `planes_prueba`, con clave foránea al usuario creador, y `plan_cronograma`, con clave foránea al plan y orden por actividad. El cronograma se guarda en filas relacionales, no como JSON. Los guardados son transaccionales y editar sustituye únicamente las actividades de ese plan.

El Tester crea, consulta y edita sus propios planes; no elimina. El Administrador crea, consulta y edita todos y elimina mediante confirmación, POST y CSRF. Editar conserva el creador original, aunque cambie el Responsable. Eliminar un plan elimina su cronograma en cascada y conserva los otros planes. Eliminar casos de prueba no afecta a los planes.

## Instalación y pruebas

Con MySQL en marcha, ejecuta `php scripts/migrate-proyecto2.php` en una instalación existente, o importa `sql/formulario_6_migration.sql` dentro de la base del Bus. La migración es reejecutable sin borrar datos y el SQL completo incluye estas tablas para instalaciones nuevas.

`php tests/smoke-test-plan.php http://localhost/ISA3-Proyecto2` realiza 112 comprobaciones HTTP y SQL: CRUD, roles, ownership, CSRF, campos obligatorios, versiones, Responsable independiente, cronograma múltiple, fechas, edición, cascada, independencia de casos y reimportación. Crea y retira sus fixtures temporales y compara los datos anteriores. CI ejecuta la misma suite en `tests/smoke-bus.php`, con PHP y MariaDB en Linux, sin dependencia de XAMPP.
