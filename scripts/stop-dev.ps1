[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'runtime-config.ps1')
foreach ($name in @('bus','provider-alpha','provider-beta','provider-gamma','cleanup-worker')) {
    $identityPath = Join-Path $runtimeDir "$name.process.json"
    if (!(Test-Path -LiteralPath $identityPath)) { continue }
    $process = Get-OwnedProjectProcess $name
    if ($process) {
        Stop-Process -Id $process.Id -Force -ErrorAction Stop
        $process.WaitForExit(5000) | Out-Null
        Write-Host "Detenido $name (PID $($process.Id))."
    } else {
        Write-Host "Registro inactivo de $name; no se detuvo ningun proceso ajeno."
    }
    Remove-Item -LiteralPath $identityPath
    $pidPath = Join-Path $runtimeDir "$name.pid"
    if (Test-Path -LiteralPath $pidPath) { Remove-Item -LiteralPath $pidPath }
}
