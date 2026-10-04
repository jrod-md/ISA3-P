$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeDir = Join-Path $projectRoot '.runtime'
$RuntimeConfig = @{ DB_HOST='127.0.0.1'; DB_PORT='3306'; DB_USER='root'; DB_PASSWORD=''; BUS_PORT='8000' }
$envPath = Join-Path $projectRoot '.env'
if (!(Test-Path -LiteralPath $envPath)) { Copy-Item -LiteralPath (Join-Path $projectRoot '.env.example') -Destination $envPath }
foreach ($line in Get-Content -LiteralPath $envPath) {
    if ($line.Trim().StartsWith('#') -or !$line.Contains('=')) { continue }
    $pair = $line.Split('=',2)
    $RuntimeConfig[$pair[0].Trim()] = $pair[1].Trim().Trim('"').Trim("'")
}
function Invoke-DatabaseProbe([string] $Query) {
    $savedPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $argsForQuery = @('--protocol=TCP', "--host=$($RuntimeConfig.DB_HOST)", "--port=$($RuntimeConfig.DB_PORT)", "--user=$($RuntimeConfig.DB_USER)", '--connect-timeout=2', '--batch', '--raw', '--skip-column-names')
        if ($RuntimeConfig.DB_PASSWORD) { $argsForQuery += "--password=$($RuntimeConfig.DB_PASSWORD)" }
        $result = & 'C:\xampp\mysql\bin\mysql.exe' @argsForQuery -e $Query 2>$null
        if ($LASTEXITCODE -eq 0) { return ($result -join "`n").Trim() }
        return $null
    } finally { $ErrorActionPreference = $savedPreference }
}

function Get-OwnedProjectProcess([string] $Name) {
    $identityPath = Join-Path $runtimeDir "$Name.process.json"
    if (!(Test-Path -LiteralPath $identityPath)) { return $null }
    try {
        $identity = Get-Content -LiteralPath $identityPath -Raw | ConvertFrom-Json
        if ([int] $identity.id -le 0 -or $identity.executable -ne 'C:\xampp\php\php.exe') { return $null }
        if ($identity.projectRoot -and $identity.projectRoot -ne $projectRoot) { return $null }
        $process = Get-Process -Id $identity.id -ErrorAction SilentlyContinue
        if ($process -and $process.Path -eq $identity.executable -and $process.StartTime.ToUniversalTime().Ticks -eq [long] $identity.started) { return $process }
    } catch { }
    return $null
}
function Get-ListenerProcessIds([int] $Port) {
    foreach ($line in (& netstat.exe -ano -p TCP)) {
        if ($line -match '^\s*TCP\s+(\S+)\s+\S+\s+LISTENING\s+(\d+)\s*$') {
            $localAddress = $Matches[1]
            $listenerId = [int] $Matches[2]
            if ($localAddress -match (':' + $Port + '$')) { $listenerId }
        }
    }
}
