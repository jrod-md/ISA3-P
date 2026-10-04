# PublicaciÃ³n inicial y auditorÃ­a

El historial local de trabajo conserva antiguos ZIPs y capturas. Quitarlos del Ã­ndice actual no los elimina de commits anteriores. Para la primera publicaciÃ³n se prepara una copia sin historial dentro de `.runtime/public/ISA3-P`; la instalaciÃ³n activa y su Git se conservan.

Desde la raÃ­z de la instalaciÃ³n:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\export-public.ps1
cd .runtime\public\ISA3-P
```

La exportaciÃ³n utiliza una lista explÃ­cita de directorios y archivos fuente, respeta `.gitignore` y omite `.git`, runtime, capturas, ZIPs, configuraciÃ³n local y archivos ignorados. Una ejecuciÃ³n posterior archiva la exportaciÃ³n anterior en el runtime; no destruye su Git. Revisa la nueva copia y sustituye `jrod-md` en README por tu cuenta u organizaciÃ³n.

Crea en GitHub un repositorio pÃºblico vacÃ­o llamado **ISA3-P**, sin README, licencia ni gitignore generados por GitHub. Desde la copia exportada:

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

El comando `git ls-files` anterior debe devolver vacÃ­o. Configura tu identidad Git si el commit la solicita; puedes utilizar el correo noreply de GitHub. No publiques el historial del repositorio local antiguo sin sanearlo antes. No se ha creado ni publicado ningÃºn remoto automÃ¡ticamente.

## AuditorÃ­a del contenido pÃºblico

- `.env`, runtime completo, sesiones, evidencia privada, ZIPs, logs y respaldos quedan excluidos.
- Las capturas locales antes versionadas permanecen en disco, fuera del Ã­ndice. Los informes previos se conservaron en un respaldo local excluido y se reemplazaron por documentaciÃ³n portable.
- Los SQL necesarios, `.env.example`, scripts locales, tests, assets y fuentes se conservan.
- Las credenciales Admin/Tester son fixtures demo pÃºblicos creados por seed/migraciÃ³n; los tests tambiÃ©n utilizan cuentas temporales desechables. No se encontraron claves API, tokens, contraseÃ±as privadas o claves SSH incorporados al contenido publicable mediante la inspecciÃ³n y bÃºsquedas realizadas.
- Las rutas genÃ©ricas `C:\xampp` solo documentan o implementan operaciÃ³n local; las rutas personales de usuario y respaldos no se publican.
- No se encontraron dependencias externas vendorizadas, fuentes descargadas ni frameworks en el cÃ³digo. La licencia MIT cubre contribuciones del proyecto; los programas y acciones usados conservan sus propias licencias.

Confirma autorÃ­a y derechos con los integrantes y las reglas acadÃ©micas antes de publicar. Revisa tambiÃ©n la identidad que quedarÃ¡ en el nuevo commit.

## CI y lÃ­mites

Cada push y pull request valida PHP 8.2, MariaDB 10.11, los cuatro esquemas, la migraciÃ³n QA, ocho unitarias, instalaciÃ³n/reimportaciÃ³n y 24 comprobaciones portables del Bus. La base CI es efÃ­mera; root sin contraseÃ±a se limita a ese servicio de pruebas. No se necesitan secretos ni hay CD.

La suite QA completa conserva su ejecuciÃ³n local sobre Apache y comprueba reglas `.htaccess`, login, permisos, CRUD, evidencias y tÃ©cnicas. El test PowerShell original continÃºa local. El test portable del Bus posee y detiene solo sus procesos PHP; si un puerto estÃ¡ ocupado, falla sin matar su ocupante.

Tras el primer push revisa la pestaÃ±a Actions: el workflow Linux no puede considerarse ejecutado solo por verificarlo en Windows. Actualiza `jrod-md` en el badge y en los ejemplos de clone/remoto. El badge mostrarÃ¡ el resultado cuando exista el repositorio y termine una ejecuciÃ³n.

