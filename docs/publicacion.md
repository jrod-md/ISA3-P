# Publicación inicial y auditoría

El repositorio público es [jrod-md/ISA3-P](https://github.com/jrod-md/ISA3-P). Para su mantenimiento utiliza el Git existente en `.runtime/public/ISA3-P`. No ejecutes el exportador sobre esa copia: archivaría su directorio y su `.git`. Las instrucciones de exportación y primer commit siguientes describen la preparación inicial.

El historial local de trabajo conserva antiguos ZIPs y capturas. Quitarlos del índice actual no los elimina de commits anteriores. Para la primera publicación se prepara una copia sin historial dentro de `.runtime/public/ISA3-P`; la instalación activa y su Git se conservan.

Desde la raíz de la instalación:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\export-public.ps1
cd .runtime\public\ISA3-P
```

La exportación utiliza una lista explícita de directorios y archivos fuente, respeta `.gitignore` y omite `.git`, runtime, capturas, ZIPs, configuración local y archivos ignorados. Una ejecución posterior archiva la exportación anterior en el runtime; no destruye su Git. El owner del repositorio y del badge es `jrod-md`.

Crea en GitHub un repositorio público vacío llamado **ISA3-P**, sin README, licencia ni gitignore generados por GitHub. Desde la copia exportada:

```powershell
git init -b main
git add .
git status --short
git diff --cached --stat
git ls-files .env .runtime '*.zip' 'docs/evidencias/*'
git commit -m "Prepare ISA3-P with reproducible setup and CI"
git remote add origin https://github.com/jrod-md/ISA3-P.git
git push -u origin main
```

El comando `git ls-files` anterior debe devolver vacío. Configura tu identidad Git si el commit la solicita; puedes utilizar el correo noreply de GitHub. No publiques el historial del repositorio local antiguo sin sanearlo antes. La preparación inicial no creó ningún remoto automáticamente.

## Auditoría del contenido público

- `.env`, runtime completo, sesiones, evidencia privada, ZIPs, logs y respaldos quedan excluidos.
- Las capturas locales antes versionadas permanecen en disco, fuera del índice. Los informes previos se conservaron en un respaldo local excluido y se reemplazaron por documentación portable.
- Los SQL necesarios, `.env.example`, scripts locales, tests, assets y fuentes se conservan.
- Las credenciales Admin/Tester son fixtures demo públicos creados por seed/migración; los tests también utilizan cuentas temporales desechables. No se encontraron claves API, tokens, contraseñas privadas o claves SSH incorporados al contenido publicable mediante la inspección y búsquedas realizadas.
- Las rutas genéricas `C:\xampp` solo documentan o implementan operación local; las rutas personales de usuario y respaldos no se publican.
- No se encontraron dependencias externas vendorizadas, fuentes descargadas ni frameworks en el código. La licencia MIT cubre contribuciones del proyecto; los programas y acciones usados conservan sus propias licencias.

Confirma autoría y derechos con los integrantes y las reglas académicas antes de publicar. Revisa también la identidad que quedará en el nuevo commit.

## CI y límites

Cada push y pull request valida PHP 8.2, MariaDB 10.11, los cuatro esquemas, la migración QA, ocho unitarias, instalación/reimportación y 24 comprobaciones portables del Bus. La base CI es efímera; root sin contraseña se limita a ese servicio de pruebas. No se necesitan secretos ni hay CD.

La suite QA completa conserva su ejecución local sobre Apache y comprueba reglas `.htaccess`, login, permisos, CRUD, evidencias y técnicas. El test PowerShell original continúa local. El test portable del Bus posee y detiene solo sus procesos PHP; si un puerto está ocupado, falla sin matar su ocupante.

Tras el primer push revisa la pestaña Actions: el workflow Linux no puede considerarse ejecutado solo por verificarlo en Windows. El badge y los ejemplos de clone/remoto apuntan a `jrod-md/ISA3-P`. El badge muestra el resultado real del workflow.

## Encoding de la documentación

Los archivos de texto se guardan en UTF-8. En Windows PowerShell 5.1, `Get-Content` necesita `-Encoding UTF8` para leer archivos UTF-8 sin BOM; al escribir, indica también el encoding. No vuelvas a convertir texto que ya está correcto.
