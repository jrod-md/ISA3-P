# Evaluación y evidencias del proyecto · Formularios 7–9

Accede desde **Formularios**. Son documentos a nivel proyecto, sin `caso_id`: no dependen de un caso ni se eliminan al borrar uno. El Formulario 10 permanece pendiente. Se conserva el único enlace Formularios en la navegación.

| Formulario | Ruta | Propósito |
| --- | --- | --- |
| 7 · Rúbrica de Evaluación | `/formularios/rubrica` | Evaluación sumativa del desempeño |
| 8 · Autoevaluación y Coevaluación | `/formularios/evaluacion` | Evaluación formativa, reflexión y valoración entre pares |
| 9 · Portafolio de Evidencias | `/formularios/portafolio` | Organización de evidencias de aprendizaje |

Cada ruta tiene listado, nuevo, consulta, edición y confirmación de eliminación. Tester crea, consulta y edita sus propios documentos; no elimina. Administrador crea, consulta y edita todos, y elimina mediante confirmación, POST y CSRF. Todas las escrituras requieren CSRF. El creador se obtiene de la sesión y permanece inmutable; Persona evaluada y Co-evaluador son texto independiente de la cuenta.

La rúbrica guarda exactamente seis criterios: Diseño de casos, Aplicación de técnicas, Cobertura, Uso de herramientas, Documentación y Presentación. Quien registra selecciona una puntuación entera de 1 a 5 por criterio; la observación es opcional. La referencia de Unidad III permanece visible: Excelente = 5, Bueno = 4, Regular = 3, Deficiente = 1–2. El servidor suma las seis puntuaciones, con máximo 30. La aplicación no determina las puntuaciones según métricas de cobertura ni herramientas; tampoco afirma que alguien domina herramientas automáticamente.

La autoevaluación/coevaluación conserva exactamente seis aspectos: Comprensión de conceptos, Aplicación de técnicas, Trabajo en equipo, Uso de herramientas, Calidad de documentación y Cumplimiento de plazos. Cada uno lleva Autoevaluación y Coevaluación enteras de 1 a 5, y Comentarios opcionales. El servidor calcula ambos promedios sobre seis aspectos y los guarda con dos decimales. JavaScript muestra feedback inmediato; los totales/promedios enviados por el cliente se ignoran.

El portafolio tiene Título y entre 1 y 30 filas, cada una con Semana (entero de 1 a 52), Evidencia (nombre/descripción), Tipo libre, Fecha válida y Observaciones opcionales. Las sugerencias Documento, Taller, Laboratorio, Proyecto y Presentación no son una lista cerrada. Los ejemplos académicos no se precargan ni son obligatorios. Agregar, quitar y editar filas conserva su orden; para modificar dinámicamente el número de filas se requiere JavaScript.

**Decisión de archivos:** esta versión organiza nombres/descripciones de evidencias sin adjuntos. El formato académico no exige archivos por fila. El mecanismo privado del Formulario 1 está vinculado a casos individuales; ampliarlo a filas de portafolio requeriría nuevos controles de almacenamiento y descarga. Se mantiene el mecanismo del Formulario 1 sin duplicarlo ni modificarlo.

La migración aditiva `sql/formularios_7_9_migration.sql` crea `rubricas` / `rubrica_criterios`, `evaluaciones_pares` / `evaluacion_aspectos` y `portafolios` / `portafolio_evidencias`. Las cabeceras referencian `usuarios` con RESTRICT; las filas referencian su cabecera con CASCADE y tienen orden único por documento. Las escalas y semanas tienen CHECK y los criterios/aspectos tienen claves únicas. Las filas se guardan en tablas relacionales, no JSON. Crear/editar cabecera y filas es una transacción.

En una instalación existente ejecuta `php scripts/migrate-proyecto2.php`; el arranque también aplica las migraciones. `sql/instalacion_completa.sql` incluye las tablas para instalaciones nuevas y admite reimportación sin pérdida de datos.

`php tests/smoke-project-evaluations.php http://localhost/ISA3-Proyecto2` verifica CRUD, propiedad, roles, CSRF, referencias académicas, límites, fechas, cálculos, adulteración, edición, orden, cascadas, reimportación y conservación de datos. `tests/smoke-bus.php` incluye esta suite en CI portable con PHP/MariaDB, sin XAMPP. `tests/verify-installation.php` verifica instalación limpia y reimportación de las seis tablas con registros existentes.
