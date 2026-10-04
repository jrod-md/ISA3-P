[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'runtime-config.ps1')
$php = 'C:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $php)) { throw 'No se encontro PHP de XAMPP.' }
& $php -v
$modules = & $php -m
foreach ($extension in @('pdo_mysql','curl','fileinfo')) {
    if ($modules -notcontains $extension) { throw "Falta la extension $extension." }
    Write-Host "$extension OK"
}
$database = Invoke-DatabaseProbe 'SELECT VERSION(), @@port, @@datadir'
if (!$database) { throw 'No se pudo conectar a MySQL. Inicia MySQL desde el panel XAMPP y revisa .env.' }
Write-Host "MySQL $($RuntimeConfig.DB_HOST):$($RuntimeConfig.DB_PORT): $database"
foreach ($port in @([int] $RuntimeConfig.BUS_PORT,8101,8102,8103)) {
    try { $health = Invoke-RestMethod "http://127.0.0.1:$port/health" -TimeoutSec 2 -UseBasicParsing; Write-Host "HTTP $port $($health.status)" }
    catch { Write-Warning "HTTP $port sin respuesta. Ejecuta scripts/start-dev.ps1." }
}
