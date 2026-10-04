# Estructura de la aplicación integrada

La aplicación final ocupa directamente la raíz de la carpeta local, con `apps`, `database`, `docs`, `scripts`, `services`, `shared`, `sql` y `tests`. El prototipo independiente anterior se archivó fuera de la aplicación activa; no se fusionaron configuraciones o sistemas de login de ambos proyectos.

Las rutas PHP calculan su raíz a partir de `__DIR__`, `PROJECT_ROOT` y WebPaths. Los scripts locales la calculan desde `PSScriptRoot`. Apache permite una subcarpeta bajo `htdocs`, y el servidor PHP del Bus sirve el mismo proyecto desde su raíz.

El nombre público del repositorio es `ISA3-P`; la carpeta local existente `ISA3-Proyecto2` se mantiene. No se cambian puertos, bases, cuentas ni evidencias por el cambio de nombre público. Los datos vivos y respaldos permanecen privados.

El historial de trabajo local contiene antiguos artefactos de entrega. Para una primera publicación limpia se exporta solo el contenido público actual, sin reutilizar ese historial. Consulta `docs/publicacion.md`.
