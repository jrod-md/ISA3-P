# Continuidad del Proyecto 1 al Proyecto 2

La integración conserva el Marketplace Search Bus, sus tres proveedores, catálogos, adaptadores, filtros, API administrativa, worker y configuración. El código PHP utiliza autoload propio y PDO, sin framework ni sistema adicional de autenticación.

El Proyecto 1 suministrado autenticaba al administrador desde configuración y no incluía una tabla de usuarios. Se amplió el mismo AdminAuth con `usuarios`, contraseñas hash y roles; el login original `/admin` se conserva y `/login` es su alias. El registro crea Testers y ambos roles entran al dashboard.

Se reutilizaron el catálogo de técnicas/subtécnicas, campos, validaciones y operaciones de casos del avance de pruebas, conectándolos al mismo `BUS_DB_NAME`. `sql/proyecto2_migration.sql` agrega `usuarios` y `casos_prueba`; la relación con usuarios y las reglas de acceso evitan una base o un login duplicados.

`scripts/migrate-proyecto2.php` crea únicamente cuentas ausentes y conserva las existentes. El instalador integrado conserva productos y registros; `sql/instalacion_completa.sql` permite instalar la aplicación con datos demo. El Formulario 1 registra pruebas del propio proyecto, incluye evidencias privadas y valida permisos en el servidor. La migración aditiva `sql/formularios_2_4_migration.sql` incorpora seis tablas para equivalencia, valores límite y decisiones. `sql/formulario_5_migration.sql` amplía los tipos de documento y agrega `cobertura_metricas`, conservando los casos y documentos anteriores. Total y Cubiertos se registran manualmente; el sistema calcula porcentajes y el campo Herramienta Utilizada documenta lo empleado por el tester, sin integración automática con herramientas externas. Los Formularios 6–10 permanecen pendientes.

La preparación para GitHub modifica documentación, exclusiones y pruebas de CI; conserva código funcional, SQL, interfaz y operación local. Las capturas, casos y respaldos de instalaciones particulares no forman parte del repositorio público.
