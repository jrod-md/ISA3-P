# Dataset de demostración académica

Dataset opcional y pequeño para recorrer los Formularios 1–10 de ISA3-P con datos coherentes. Todos sus documentos son visibles como **[DEMO]** y pertenecen a la cuenta existente **tester**. Administrador puede consultarlos con los permisos actuales. No modifica la aplicación, el SQL estructural, las cuentas ni la configuración de XAMPP; la instalación normal no ejecuta el seed.

## Preparar y limpiar

Con Apache y MySQL de XAMPP iniciados, y el Bus/proveedores saludables mediante el arranque habitual:

```powershell
cd C:\xampp\htdocs\ISA3-Proyecto2
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-dev.ps1
C:\xampp\php\php.exe .\scripts\seed-demo.php
```

Abre `http://localhost/ISA3-Proyecto2/login`:

| Cuenta | Usuario | Contraseña |
| --- | --- | --- |
| Tester, propietario del dataset | `tester` | `Tester123!` |
| Administrador | `admin` | `demo-isa3-2026` |

Repetir `seed-demo.php` conserva los IDs y el contenido: no duplica ni sobrescribe registros. El comando imprime los IDs locales; no son IDs fijos que deban coincidir en otra instalación.

Para retirar exclusivamente este dataset:

```powershell
C:\xampp\php\php.exe .\scripts\cleanup-demo.php
```

Después puedes volver a ejecutar el seed. Los registros nuevos tendrán otros IDs, porque se respeta el autoincremento existente.

## Qué crea

| Formulario | Demostración |
| --- | --- |
| 1 — Casos | Cuatro casos: búsqueda unificada de laptops, rango de precios invertido, selección de Alpha/orden ascendente y validaciones de SearchCriteria. |
| 2 — Equivalencia | Una matriz de cuatro filas para `q`, `provider`, precios y `limit`, vinculada al caso de búsqueda. |
| 3 — Límites | Un análisis de cuatro filas para precios, `min_stock`, `limit` y longitud de `q`, vinculado al caso del rango. |
| 4 — Decisiones | Una tabla de cuatro reglas, tres condiciones y tres acciones, vinculada al caso de proveedor/orden. |
| 5 — Cobertura | Un documento con las cinco métricas existentes, vinculado al caso de caja blanca. |
| 6 — Plan | Un plan ISA3-P versión 1.0 y cinco actividades de preparación. |
| 7 — Rúbrica | Una auto-rúbrica preliminar con seis criterios, rotulada como no oficial. |
| 8 — Evaluación | Una autoevaluación/coevaluación de personas ficticias, con seis aspectos y comentarios de ejemplo. |
| 9 — Portafolio | Un portafolio con seis referencias documentales existentes o creadas por el propio dataset. |
| 10 — Incidente | Un incidente cerrado que documenta el problema histórico del puerto de phpMyAdmin, sin caso asociado ni archivo inventado. |

Son **cuatro casos y nueve documentos**, con 83 filas en total contando reglas, celdas y demás detalles. No crea usuarios, productos ni archivos de evidencia.

## Evidencia real y ejemplos

Antes de insertar, el seed comprueba la salud del Bus y los tres proveedores. Ejecuta las búsquedas reales y exige que pasen:

- `q=laptop`: HTTP 200 y productos normalizados de Alpha, Beta y Gamma. Guarda el número de resultados observado, no uno supuesto.
- `min_price=900&max_price=100`: HTTP 422 y `VALIDATION_ERROR`. Guarda el mensaje recibido. El caso es exitoso porque se rechaza la entrada inválida como se esperaba.
- `q=laptop&provider=alpha&sort=price_asc`: solo Alpha en los proveedores consultados y productos ordenados por precio. Guarda los precios observados.
- Validaciones reales de `SearchCriteria::fromQuery`: 23 entradas aceptadas y 15 rechazadas, con fronteras inclusivas y valores fuera de rango. Requiere `MAX_RESULT_LIMIT=100`, tal como documenta este dataset. La consulta vacía se normaliza a `null`; los límites de texto se cuentan con `mb_strlen` después de `trim`.

Las búsquedas de preparación generan únicamente las sesiones/caché temporales normales del Bus, sujetas a su TTL. `cleanup-demo.php` no elimina búsquedas ni cambia el worker.

El Formulario 5 documenta el reporte real de **PHPUnit 11.5.56: 99 tests, 172 assertions**, y **Xdebug 3.5.3: 219/295 líneas, 74.24 %** del alcance seleccionado. No son 99 pruebas exclusivas de SearchCriteria ni cobertura de toda la aplicación. La fila académica «Sentencia» utiliza cobertura de líneas como **aproximación documental**, y su herramienta advierte que líneas y sentencias no son métricas idénticas. Decisión, Condición, Caminos y Bucles tienen total/cubiertos 0 y «No medido en este reporte»; ese 0 % no demuestra ausencia de cobertura.

El plan y el portafolio referencian el [run real de JMeter 37242589899](https://github.com/jrod-md/ISA3-P/actions/runs/37242589899): JMeter 5.6.3, Java Temurin 17.0.20.1+1, 5 usuarios, ramp-up 5 s, 3 iteraciones, 45 muestras, 45 exitosas, 0 fallidas, 0 % de error, promedio 12.3111 ms y throughput 10.6534 solicitudes/s. **Prueba académica ligera, no benchmark empresarial.** Es un reporte histórico comprobado; el seed no vuelve a ejecutar JMeter ni inventa mediciones nuevas. El recorrido Selenium fue ejecutado previamente con Side Runner 4.0.13; las referencias están en [testing-tools.md](testing-tools.md).

La rúbrica es una valoración preliminar DEMO: cobertura y herramientas reciben **Bueno (4)**; TestCover no fue utilizado y no se adjudica Excelente por dominar las cuatro herramientas. Presentación usa **3 como ejemplo prudente**, sin afirmar que la exposición ya fue evaluada. La coevaluación contiene puntuaciones ilustrativas de personas ficticias. **Ninguna representa una calificación oficial ni una evaluación de compañeros reales.**

La fecha de los documentos y las cinco actividades es el día real de preparación. Las semanas del portafolio son una agrupación académica de referencias, no una cronología histórica inventada. El Formulario 9 no usa uploads: sus filas referencian README, arquitectura, matriz, cobertura, plan, `.side` y resultados de CI; no afirma que haya adjuntos físicos. El incidente de phpMyAdmin procede del problema relatado y resuelto por el usuario; el seed no recrea la avería ni toca configuraciones globales.

## Protección e idempotencia

Los scripts comparten una implementación CLI y los datos estáticos de `scripts/demo-data-definitions.php`. Reutilizan los validadores y catálogos existentes. Las inserciones se realizan en una transacción; si una observación, validación o escritura del manifiesto falla, no se deja un dataset parcialmente creado.

El inventario privado está en `.runtime/demo-data/<identificador-de-base>.json`: registra base/puerto/propietario, IDs, claves compuestas y huellas SHA-256 del contenido creado. No añade tablas de tracking y queda fuera de Git. Un bloqueo de MariaDB serializa seed/cleanup del mismo dataset.

El cleanup verifica todas las huellas y relaciones antes de borrar únicamente las filas inventariadas, en una transacción. **CP-024 está protegido explícitamente** y nunca forma parte del seed. No elimina usuarios, catálogos, búsquedas, archivos de evidencia ni otros registros, aunque lleven “[DEMO]”. Si has editado un registro demo, añadido una evidencia o vinculado un documento/incidente manual, el cleanup se detiene y conserva todo; no existe una opción de borrado forzado.

Conserva el manifiesto mientras conserves sus datos. Sin manifiesto, cleanup no elimina nada; seed rechaza los marcadores reservados ya existentes para evitar duplicados y no adopta registros por su título. Una interrupción que deje un manifiesto incompleto o sin filas confirmadas también causa un rechazo seguro: conserva los datos y revisa el manifiesto antes de reintentar. No reconstruyas el inventario solo buscando “[DEMO]”.

GitHub contiene únicamente los scripts y esta documentación. La base viva, el manifiesto, sesiones, reportes locales y evidencias privadas nunca se exportan al repositorio.

## Recorrido de exposición

1. Inicia sesión como Tester: el dashboard muestra CP-024 y los cuatro casos DEMO.
2. Abre el caso de búsqueda y su matriz de equivalencia; luego muestra límites y decisiones desde sus respectivos casos.
3. Abre el caso de caja blanca y el Formulario 5: explica el 74.24 % y las cuatro métricas no medidas.
4. Desde Formularios, abre el plan, la rúbrica preliminar, la coevaluación de ejemplo y el portafolio de referencias.
5. Muestra el incidente cerrado y aclara que es un registro histórico sin archivo adjunto.
6. Cierra sesión y entra como Administrador: puede consultar los mismos documentos del Tester. Para conservar la preparación, evita editar o eliminar sus registros durante este recorrido.
