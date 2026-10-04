[CmdletBinding()]
param(
    [ValidateRange(0, 86400)] [int] $SearchTtlSeconds = 0
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeDir = Join-Path $projectRoot '.runtime'
$php = 'C:\xampp\php\php.exe'
. (Join-Path $PSScriptRoot 'runtime-config.ps1')
if (-not (Test-Path -LiteralPath $php -PathType Leaf)) { throw "PHP not found at $php" }

$services = @(
    @{ Name = 'provider-alpha'; Port = 8101; Public = 'services\provider-alpha\public'; Router = 'services\provider-alpha\public\index.php' },
    @{ Name = 'provider-beta'; Port = 8102; Public = 'services\provider-beta\public'; Router = 'services\provider-beta\public\index.php' },
    @{ Name = 'provider-gamma'; Port = 8103; Public = 'services\provider-gamma\public'; Router = 'services\provider-gamma\public\index.php' },
    @{ Name = 'bus'; Port = [int] $RuntimeConfig.BUS_PORT; Public = 'apps\bus\public'; Router = 'apps\bus\public\index.php' }
)

# Comprueba todos los puertos antes de iniciar servicios o preparar la BD.
# Nunca detiene una instancia ajena para liberar un puerto.
foreach ($service in $services) {
    $listeners = @(Get-ListenerProcessIds $service.Port | Select-Object -Unique)
    $owned = Get-OwnedProjectProcess $service.Name
    foreach ($listener in $listeners) {
        if (!$owned -or $listener -ne $owned.Id) { throw "El puerto $($service.Port) esta ocupado por un proceso que no pertenece a esta copia (PID $listener). No se inicio ni se detuvo ningun servicio." }
    }
}
& (Join-Path $PSScriptRoot 'init-db.ps1')
# Reinicia solo procesos identificados de esta copia para aplicar tambien .env al worker.
& (Join-Path $PSScriptRoot 'stop-dev.ps1')

if ($SearchTtlSeconds -gt 0) {
    $env:SEARCH_TTL_SECONDS = [string] $SearchTtlSeconds
    Write-Host "Runtime TTL override: $SearchTtlSeconds seconds" -ForegroundColor Yellow
}

foreach ($service in $services) {
    $pidFile = Join-Path $runtimeDir "$($service.Name).pid"
    $stdout = Join-Path $runtimeDir "$($service.Name).out.log"
    $stderr = Join-Path $runtimeDir "$($service.Name).err.log"
    $public = Join-Path $projectRoot $service.Public
    $router = Join-Path $projectRoot $service.Router
    $process = Start-Process -WindowStyle Hidden -FilePath $php -WorkingDirectory $projectRoot -ArgumentList @('-S', "127.0.0.1:$($service.Port)", '-t', "`"$public`"", "`"$router`"") -RedirectStandardOutput $stdout -RedirectStandardError $stderr -PassThru
    Set-Content -LiteralPath $pidFile -Value $process.Id
    @{ id=$process.Id; started=$process.StartTime.ToUniversalTime().Ticks; executable=$php; projectRoot=$projectRoot } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimeDir ($service.Name + '.process.json'))
    Write-Host "$($service.Name) -> http://127.0.0.1:$($service.Port) (PID $($process.Id))" -ForegroundColor Green
}

$workerPidFile = Join-Path $runtimeDir 'cleanup-worker.pid'
$workerPath = Join-Path $projectRoot 'scripts\cleanup-worker.php'
$worker = Start-Process -WindowStyle Hidden -FilePath $php -WorkingDirectory $projectRoot -ArgumentList "`"$workerPath`"" -RedirectStandardOutput (Join-Path $runtimeDir 'cleanup-worker.out.log') -RedirectStandardError (Join-Path $runtimeDir 'cleanup-worker.err.log') -PassThru
Set-Content -LiteralPath $workerPidFile -Value $worker.Id
@{ id=$worker.Id; started=$worker.StartTime.ToUniversalTime().Ticks; executable=$php; projectRoot=$projectRoot } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimeDir 'cleanup-worker.process.json')
Write-Host "cleanup-worker (PID $($worker.Id))" -ForegroundColor Green

foreach ($service in $services) {
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $health = Invoke-RestMethod -Uri "http://127.0.0.1:$($service.Port)/health" -TimeoutSec 2 -UseBasicParsing
            if ($health.status -eq 'ok') { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 250
    }
    if (!$ready) { throw "$($service.Name) no responde. Revisa .runtime/$($service.Name).err.log." }
}
if (!(Get-OwnedProjectProcess 'cleanup-worker')) { throw 'El worker no inicio. Revisa .runtime/cleanup-worker.err.log.' }

Write-Host ''
Write-Host 'Demo ready:' -ForegroundColor Cyan
Write-Host "  Search: http://127.0.0.1:$($RuntimeConfig.BUS_PORT)/"
Write-Host "  Admin:  http://127.0.0.1:$($RuntimeConfig.BUS_PORT)/admin"
Write-Host '  Login:  admin / demo-isa3-2026 (credenciales originales)'
Write-Host '  Tester: tester / Tester123!'
Write-Host "  Pruebas: http://127.0.0.1:$($RuntimeConfig.BUS_PORT)/dashboard"
