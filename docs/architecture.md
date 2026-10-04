# Arquitectura de ISA3-P

```mermaid
flowchart TD
    Cliente[Cliente / buscador web] --> Bus[Marketplace Search Bus :8000]
    Bus --> Alpha[Alpha :8101]
    Bus --> Beta[Beta :8102]
    Bus --> Gamma[Gamma :8103]
    Alpha --> MA[(market_alpha.products)]
    Beta --> MB[(market_beta.products)]
    Gamma --> MG[(market_gamma.products)]
    Bus --> Meta[(bus_meta en MySQL :3306)]
    QA[Aplicación PHP / módulo QA] --> Meta
    Meta --> U[usuarios]
    Meta --> C[casos_prueba]
    C --> F[formularios_prueba: caso y autor]
    F --> E[equivalencia_filas / limite_filas]
    F --> D[decision_reglas / decision_elementos / decision_valores]
    Meta --> S[search_sessions]
    Meta --> K[search_cache]
    Worker[Worker PHP de caducidad] --> Meta
```

El Bus consulta simultáneamente tres proveedores HTTP mediante cURL, normaliza sus contratos con adaptadores y aplica ordenamiento global. Persiste una SearchSession y su caché en `bus_meta`; el worker elimina físicamente las búsquedas caducadas y la clave foránea elimina su caché. Los catálogos de los proveedores viven en bases independientes.

El Proyecto 2 extiende el router, la autenticación y la navegación del Proyecto 1. La misma aplicación sirve `/login`, `/admin`, `/dashboard`, `/casos`, `/formularios`, `/usuarios`, `/busquedas` y el buscador. Apache puede servirla desde una subcarpeta; el servidor PHP del Bus utiliza el mismo router en 8000. No existe un segundo login ni una base QA independiente.

`usuarios` incorpora Administrador y Tester con contraseñas hash. `casos_prueba` referencia al autor; los permisos se comprueban en el servidor. El Administrador puede consultar y eliminar todos los casos; el Tester consulta y edita los propios. Las evidencias están en `.runtime/evidence`, fuera de Git y del acceso estático, y sus metadatos en MySQL. Las sesiones PHP se guardan en `.runtime/sessions`.

Las bases se extienden mediante SQL aditivo. Los datos de casos no expiran con las búsquedas. Los Formularios 2–4 guardan matrices relacionales vinculadas al Formulario 1 y a su creador, con permisos comprobados por documento. Los Formularios 5–10 continúan pendientes. Consulta [las relaciones de Caja Negra](formularios-caja-negra.md); la documentación UML complementaria se encuentra en `docs/uml/`.

Localmente XAMPP controla Apache y MySQL. Los scripts `start-dev.ps1` y `stop-dev.ps1` controlan exclusivamente PHP propio. CI usa MariaDB efímera y procesos PHP controlados por `tests/smoke-bus.php`, sin despliegue ni dependencia del entorno local.
