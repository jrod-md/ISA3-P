[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'runtime-config.ps1')
New-Item -ItemType Directory -Force -Path $runtimeDir | Out-Null
if (!(Invoke-DatabaseProbe 'SELECT 1')) {
    throw "No se pudo conectar a $($RuntimeConfig.DB_HOST):$($RuntimeConfig.DB_PORT). Inicia MySQL desde XAMPP y revisa .env. Este script no controla el servidor MySQL."
}
& 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'prepare-database.php')
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la base de datos.' }
Write-Host "Base de datos preparada en $($RuntimeConfig.DB_HOST):$($RuntimeConfig.DB_PORT)." -ForegroundColor Green
