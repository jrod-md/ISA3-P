# Formulario 10 · Registro de Incidentes

ISA3-P incluye un registro académico de defectos encontrados durante las pruebas. Los **Formularios 1–10 están completos**. El acceso principal continúa siendo **Formularios**; no se agrega otra sección de navegación.

Desde `/formularios/incidentes` puedes listar, filtrar por Estado/Severidad/Prioridad y registrar un incidente. Las operaciones están en `/nuevo`, `/ver?id=…`, `/editar?id=…`, `/eliminar?id=…` y `/evidencia?id=…`. El código se deriva del ID de MySQL: `BUG-001`, `BUG-012`, `BUG-1000`; no es editable. La numeración puede tener huecos tras eliminaciones y pruebas.

Los campos requeridos son Título, Módulo, Severidad, Prioridad, Descripción, Pasos para Reproducir, Resultado Esperado, Resultado Obtenido, Estado y Asignado a. El código se genera automáticamente; Caso relacionado y archivo de Evidencia son opcionales. Asignado a es texto libre y no cambia el propietario del incidente.

| Campo | Valores exactos |
| --- | --- |
| Severidad | Crítica, Alta, Media, Baja |
| Prioridad | Alta, Media, Baja |
| Estado | Abierto, En progreso, Cerrado |

Tester crea, consulta y edita sus incidentes; no elimina. Administrador crea, consulta y edita todos, y elimina con confirmación, POST y CSRF. El creador se toma de sesión y permanece inmutable al editar. Todas las escrituras requieren CSRF y el servidor valida campos, escalas, propiedad, relación con casos y archivos.

`caso_id` es opcional. El selector muestra casos accesibles para la cuenta. Desde la ficha de un caso, **Registrar incidente desde este caso** lo preselecciona; los incidentes disponibles aparecen en **Incidentes relacionados**, separados de la documentación de Formularios 2–5. La ficha del incidente permite volver al caso si la cuenta puede consultarlo. Si Admin vincula un incidente de Tester a un caso ajeno, Tester puede conservar o quitar esa asociación al editar, pero no obtiene acceso al caso ni puede crear asociaciones nuevas a casos ajenos.

La migración aditiva `sql/formulario_10_migration.sql` crea únicamente `incidentes` en la base del Bus. `usuario_id` referencia `usuarios` con RESTRICT; `caso_id` referencia `casos_prueba` con **ON DELETE SET NULL**. Eliminar un caso conserva sus incidentes y sus evidencias. Eliminar un incidente conserva el caso. `sql/instalacion_completa.sql` incluye la tabla para instalaciones nuevas; `php scripts/migrate-proyecto2.php` incorpora la migración en instalaciones existentes sin borrar datos.

Se permite un archivo opcional de hasta **2 MB**, PNG, JPG/JPEG, PDF, TXT o LOG. El servidor comprueba extensión, contenido/MIME real y tamaño. TXT/LOG deben ser texto UTF-8 sin caracteres binarios; se descargan como `text/plain`, incluso cuando libmagic clasifica líneas largas como octet-stream. Los nombres físicos son aleatorios y se guardan en `.runtime/evidence`; MySQL guarda metadatos, no binarios. No hay acceso estático directo. La descarga requiere sesión y permisos del incidente, fuerza attachment y utiliza nosniff y no-store.

`PrivateEvidence` comparte validación, almacenamiento, eliminación y descarga con el Formulario 1; este conserva sus formatos PNG/JPG/PDF. Editar sin archivo conserva la evidencia; subir otro la reemplaza y limpia el archivo anterior después de guardar. Quitar evidencia limpia metadatos y archivo. Si llega un archivo nuevo junto a Quitar, prevalece el nuevo. Una validación fallida no guarda archivos ni modifica el registro; un fallo SQL revierte y retira el archivo nuevo.

`php tests/smoke-incidents.php http://localhost/ISA3-Proyecto2` ejecuta CRUD, roles, propiedad, CSRF, códigos, filtros, catálogos exactos, asociación opcional, SET NULL, formatos y límites de archivo, sustitución, limpieza, descarga privada y reimportación. Se integra en `tests/smoke-bus.php` para CI en Ubuntu/PHP/MariaDB, sin XAMPP. `tests/verify-installation.php` verifica la instalación completa, el primer código BUG-001 y la reimportación con incidentes existentes.

Trabajo futuro: integración con herramientas externas y automatización avanzada. El alcance actual es documentar y gestionar las pruebas e incidentes del propio proyecto.
