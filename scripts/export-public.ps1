[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$sourceRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$publicRoot = Join-Path $sourceRoot '.runtime\public'
$destination = [IO.Path]::GetFullPath((Join-Path $publicRoot 'ISA3-P'))
if (!$destination.StartsWith($sourceRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'La exportacion debe permanecer dentro del runtime de este proyecto.'
}
New-Item -ItemType Directory -Force -Path $publicRoot | Out-Null
$preserveGit = Test-Path -LiteralPath (Join-Path $destination '.git')
if ($preserveGit) {
    $pendingChanges = & git -C $destination status --porcelain
    if ($LASTEXITCODE -ne 0 -or $pendingChanges) { throw 'La copia publica tiene cambios pendientes. Revisalos antes de sincronizar.' }
} elseif (Test-Path -LiteralPath $destination) {
    $archive = [IO.Path]::GetFullPath((Join-Path $publicRoot ('ISA3-P-anterior-' + (Get-Date -Format 'yyyyMMdd-HHmmss-ffff'))))
    if (!$archive.StartsWith($sourceRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'El archivo de una exportacion previa debe permanecer dentro del proyecto.'
    }
    Move-Item -LiteralPath $destination -Destination $archive
}
New-Item -ItemType Directory -Force -Path $destination | Out-Null

$directories = @('.github', 'apps', 'database', 'docs', 'scripts', 'services', 'shared', 'sql', 'tests')
$rootFiles = @('.env.example', '.gitignore', '.htaccess', 'bootstrap.php', 'README.md', 'LICENSE', 'PRODUCT.md', 'DESIGN.md')
$candidates = @($rootFiles | ForEach-Object { Get-Item -LiteralPath (Join-Path $sourceRoot $_) })
foreach ($directory in $directories) {
    $candidates += Get-ChildItem -LiteralPath (Join-Path $sourceRoot $directory) -File -Recurse -Force
}
$count = 0
foreach ($file in $candidates) {
    if ($file.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'No se exportan enlaces o reparse points.' }
    $relative = $file.FullName.Substring($sourceRoot.Length + 1).Replace('\', '/')
    & git -C $sourceRoot check-ignore --no-index --quiet -- $relative
    if ($LASTEXITCODE -eq 0) { continue }
    if ($LASTEXITCODE -ne 1) { throw "No se pudo comprobar gitignore para $relative" }
    $target = Join-Path $destination $relative
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $target) | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $target
    if ((Get-FileHash -LiteralPath $file.FullName).Hash -ne (Get-FileHash -LiteralPath $target).Hash) {
        throw "La copia no coincide: $relative"
    }
    $count++
}
Write-Host "Exportacion publica verificada: $count archivos en $destination"
Write-Host "Git publico existente conservado: $preserveGit. Repositorio: jrod-md/ISA3-P."
