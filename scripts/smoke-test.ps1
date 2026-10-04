[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeDir = Join-Path $projectRoot '.runtime'
$php = 'C:\xampp\php\php.exe'
. (Join-Path $PSScriptRoot 'runtime-config.ps1')
$script:Passed = 0
$script:Failed = 0

function Assert-Check {
    param([string] $Name, [bool] $Condition, [string] $Detail = '')
    if ($Condition) {
        $script:Passed++
        Write-Host "PASS $Name $Detail" -ForegroundColor Green
    } else {
        $script:Failed++
        Write-Host "FAIL $Name $Detail" -ForegroundColor Red
    }
}

function Invoke-WebRequestWithStatus {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)] [string] $Uri,
        [int] $TimeoutSec = 0
    )

    $requestParameters = @{
        Uri = $Uri
        UseBasicParsing = $true
        ErrorAction = 'Stop'
    }
    if ($TimeoutSec -gt 0) {
        $requestParameters.TimeoutSec = $TimeoutSec
    }

    try {
        $response = Invoke-WebRequest @requestParameters
        return [PSCustomObject] @{
            StatusCode = [int] $response.StatusCode
            Content = [string] $response.Content
        }
    } catch {
        $webResponse = $_.Exception.Response
        if ($null -eq $webResponse) {
            throw
        }

        $content = ''
        $responseStream = $null
        $reader = $null
        try {
            $responseStream = $webResponse.GetResponseStream()
            if ($null -ne $responseStream) {
                $reader = New-Object System.IO.StreamReader($responseStream)
                $content = $reader.ReadToEnd()
            }
        } finally {
            if ($null -ne $reader) { $reader.Dispose() }
            if ($null -ne $responseStream) { $responseStream.Dispose() }
        }

        if ([string]::IsNullOrWhiteSpace($content) -and $null -ne $_.ErrorDetails) {
            $content = [string] $_.ErrorDetails.Message
        }

        return [PSCustomObject] @{
            StatusCode = [int] $webResponse.StatusCode
            Content = $content
        }
    }
}

function Wait-Provider {
    param([string] $Name, [int] $Port)
    for ($i = 0; $i -lt 25; $i++) {
        try {
            $health = Invoke-RestMethod -Uri "http://127.0.0.1:$Port/health" -TimeoutSec 1 -UseBasicParsing
            if ($health.status -eq 'ok') { return $true }
        } catch { }
        Start-Sleep -Milliseconds 200
    }
    return $false
}

function Stop-Provider {
    param([string] $Name)
    $process = Get-OwnedProjectProcess $Name
    if (!$process) { throw "No se pudo comprobar la identidad del proveedor $Name." }
    Stop-Process -Id $process.Id -Force
    $process.WaitForExit(5000) | Out-Null
    Start-Sleep -Milliseconds 500
}

function Start-Provider {
    param([string] $Name, [int] $Port)
    if (@(Get-ListenerProcessIds $Port).Count -gt 0) { throw "El puerto $Port esta ocupado; no se reinicio $Name." }
    $public = Join-Path $projectRoot "services\$Name\public"
    $router = Join-Path $public 'index.php'
    $process = Start-Process -WindowStyle Hidden -FilePath $php -WorkingDirectory $projectRoot -ArgumentList @('-S', "127.0.0.1:$Port", '-t', $public, $router) -RedirectStandardOutput (Join-Path $runtimeDir "$Name.out.log") -RedirectStandardError (Join-Path $runtimeDir "$Name.err.log") -PassThru
    Set-Content -LiteralPath (Join-Path $runtimeDir "$Name.pid") -Value $process.Id
    @{ id=$process.Id; started=$process.StartTime.ToUniversalTime().Ticks; executable=$php; projectRoot=$projectRoot } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimeDir "$Name.process.json")
    if (-not (Wait-Provider -Name $Name -Port $Port)) { throw "$Name failed to restart" }
}

function Ensure-Providers {
    foreach ($provider in @(@('provider-alpha',8101),@('provider-beta',8102),@('provider-gamma',8103))) {
        if (-not (Wait-Provider -Name $provider[0] -Port $provider[1])) {
            Start-Provider -Name $provider[0] -Port $provider[1]
        }
    }
}

Write-Host 'Marketplace Search Bus distributed smoke test' -ForegroundColor Cyan
Ensure-Providers

foreach ($service in @(@('alpha',8101),@('beta',8102),@('gamma',8103))) {
    $health = Invoke-RestMethod -Uri "http://127.0.0.1:$($service[1])/health" -TimeoutSec 3 -UseBasicParsing
    Assert-Check "AC-002 $($service[0]) health" ($health.status -eq 'ok')
}
$busHealth = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/health' -TimeoutSec 3 -UseBasicParsing
Assert-Check 'AC-001 Bus health' ($busHealth.status -eq 'ok')

$searchPage = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/' -TimeoutSec 3 -UseBasicParsing
$adminPage = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/admin' -TimeoutSec 3 -UseBasicParsing
# Windows PowerShell 5.1 reads UTF-8 scripts without a BOM through the active
# ANSI code page. Regex Unicode escapes keep these exact Spanish assertions
# encoding-independent while still verifying the rendered headings.
Assert-Check 'UI search page' ($searchPage.Content -match 'B\u00fasqueda unificada')
Assert-Check 'UI admin login page' ($adminPage.Content -match 'id="login-form"')

foreach ($port in 8101,8102,8103) {
    $direct = Invoke-RestMethod -Uri "http://127.0.0.1:$port/api/products?q=laptop&category=computers&max_price=900" -TimeoutSec 3 -UseBasicParsing
    $serialized = $direct | ConvertTo-Json -Depth 6 -Compress
    Assert-Check "IT-P-005 combined filters on $port" ($serialized -match 'laptop')
    $attack = [uri]::EscapeDataString("' OR 1=1 --")
    $injection = Invoke-RestMethod -Uri "http://127.0.0.1:$port/api/products?q=$attack" -TimeoutSec 3 -UseBasicParsing
    $count = if ($port -eq 8101) { $injection.count } elseif ($port -eq 8102) { $injection.total_items } else { $injection.matches }
    Assert-Check "IT-P-006 injection literal on $port" ($count -eq 0)
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$login = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/login' -Method Post -WebSession $session -ContentType 'application/json' -Body '{"username":"admin","password":"demo-isa3-2026"}' -UseBasicParsing
Assert-Check 'Admin authentication' ($login.authenticated -eq $true)
Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/searches' -Method Delete -WebSession $session -UseBasicParsing | Out-Null

$search = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/search?q=laptop&category=computers&max_price=900&provider=all&sort=price_asc' -TimeoutSec 5 -UseBasicParsing
Assert-Check 'IT-B-001 all providers aggregated' (($search.providers.alpha.status -eq 'ok') -and ($search.providers.beta.status -eq 'ok') -and ($search.providers.gamma.status -eq 'ok'))
Assert-Check 'IT-B-002 normalized results' (($search.results | Where-Object { $_.PSObject.Properties.Name -contains 'external_id' -and $_.PSObject.Properties.Name -contains 'provider' }).Count -eq $search.total)
Assert-Check 'Global price sort' (($search.results[0].price -le $search.results[-1].price))
$cached = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/search/$($search.search_id)" -TimeoutSec 3 -UseBasicParsing
Assert-Check 'IT-B-005 cache tied to session' ($cached.search_id -eq $search.search_id)
$listed = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/searches' -WebSession $session -UseBasicParsing
Assert-Check 'IT-B-004 admin lists SearchSession' ($listed.searches.id -contains $search.search_id)

$delete = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/admin/searches/$($search.search_id)" -Method Delete -WebSession $session -UseBasicParsing
Assert-Check 'IT-E-002 single delete' ($delete.deleted -eq 1)
$afterDelete = Invoke-WebRequestWithStatus -Uri "http://127.0.0.1:8000/api/search/$($search.search_id)"
Assert-Check 'FK cache deleted with session' ($afterDelete.StatusCode -eq 404)

try {
    Stop-Provider -Name 'provider-gamma'
    $partial = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/search?q=laptop&provider=all' -TimeoutSec 5 -UseBasicParsing
    Assert-Check 'IT-B-006 partial provider failure' (($partial.providers.gamma.status -eq 'error') -and ($partial.total -gt 0) -and ($partial.warnings.Count -gt 0))
} finally {
    Start-Provider -Name 'provider-gamma' -Port 8103
}

try {
    Stop-Provider -Name 'provider-alpha'
    Stop-Provider -Name 'provider-beta'
    Stop-Provider -Name 'provider-gamma'
    $allFailed = Invoke-WebRequestWithStatus -Uri 'http://127.0.0.1:8000/api/search?q=laptop&provider=all' -TimeoutSec 5
    $allFailedBody = $allFailed.Content | ConvertFrom-Json
    Assert-Check 'IT-B-007 total provider failure' (($allFailed.StatusCode -eq 502) -and ($allFailedBody.error.code -eq 'ALL_PROVIDERS_FAILED'))
} finally {
    Ensure-Providers
}

Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/searches' -Method Delete -WebSession $session -UseBasicParsing | Out-Null
$expiring = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/search?q=mouse&provider=alpha' -TimeoutSec 5 -UseBasicParsing
$waitSeconds = [int] $expiring.ttl_seconds + 7
Write-Host "Waiting $waitSeconds seconds for worker-driven TTL cleanup..." -ForegroundColor Yellow
Start-Sleep -Seconds $waitSeconds
$expired = Invoke-WebRequestWithStatus -Uri "http://127.0.0.1:8000/api/search/$($expiring.search_id)"
$counts = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/health' -UseBasicParsing
Assert-Check 'IT-E-001 automatic physical cleanup' (($expired.StatusCode -eq 404) -and ($counts.temporary_data.sessions -eq 0) -and ($counts.temporary_data.cache_rows -eq 0))

$one = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/search?q=monitor&provider=alpha' -UseBasicParsing
$two = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/search?q=phone&provider=beta' -UseBasicParsing
$deleteAll = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/searches' -Method Delete -WebSession $session -UseBasicParsing
$final = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/admin/searches' -WebSession $session -UseBasicParsing
Assert-Check 'IT-E-003 delete all' (($deleteAll.deleted -ge 2) -and ($final.count -eq 0))

Write-Host "`nSummary: $($script:Passed) passed, $($script:Failed) failed"
exit $(if ($script:Failed -eq 0) { 0 } else { 1 })
