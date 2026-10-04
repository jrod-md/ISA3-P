# Publicación y auditoría

El repositorio público es [jrod-md/ISA3-P](https://github.com/jrod-md/ISA3-P). La fuente canónica es la instalación activa; el Git público existente se mantiene en `.runtime/public/ISA3-P`.

Después de probar los cambios, desde la raíz de la instalación:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\export-public.ps1
cd .runtime\public\ISA3-P
git status --short
git diff
git ls-files .env .runtime '*.zip' 'docs/evidencias/*'
git add .
git diff --cached --check
git commit -m "Describe the tested change"
git push origin main
```

El exportador conserva el `.git`, los commits y el remoto existentes. Antes de sincronizar exige que la copia pública esté limpia, para evitar sobrescribir cambios pendientes. Si el destino aún no tiene Git, archiva una exportación anterior antes de crear la nueva. Utiliza una lista explícita de fuentes, respeta `.gitignore` y verifica cada copia por SHA256. No exporta el historial local antiguo.

El comando `git ls-files` anterior debe devolver vacío. `.env`, `.runtime`, sesiones, evidencias privadas, capturas, logs, ZIPs y respaldos quedan excluidos. Los SQL, `.env.example`, scripts, tests y assets sí forman parte del producto. Las cuentas Admin/Tester y las cuentas temporales de las pruebas son fixtures públicos; antes de publicar revisa el diff para detectar credenciales privadas o archivos inesperados.

## CI

Cada push y pull request valida sintaxis PHP 8.2, MariaDB 10.11, los cuatro esquemas, las migraciones aditivas, ocho unitarias, instalación/reimportación, 24 comprobaciones del Bus, 100 de Caja Negra, 87 de cobertura, 112 del Plan de Pruebas y 365 de evaluación/evidencias. La base CI es efímera; no se necesitan secretos ni hay despliegue. El test portable controla exclusivamente sus procesos PHP y falla si un puerto está ocupado.

La regresión completa del Formulario 1 continúa localmente sobre Apache: login, roles, CRUD, evidencias y técnicas. El test PowerShell original también continúa local.

Después del push comprueba el workflow del commit publicado en Actions. El badge apunta a `jrod-md/ISA3-P/actions/workflows/ci.yml` y muestra su estado real.

## Encoding

Los archivos de texto se guardan en UTF-8. En Windows PowerShell 5.1 usa `Get-Content -Encoding UTF8` y escritura UTF-8 explícita. No vuelvas a convertir texto que ya está correcto.
