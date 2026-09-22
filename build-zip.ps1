<#
.SYNOPSIS
    Genera el .zip instalable del plugin Navidad TVS para subir a WordPress.

.DESCRIPTION
    Copia solo los archivos de produccion a una carpeta temporal llamada
    "navidad-tvs" y la comprime en dist\navidad-tvs-<version>.zip.
    La version se lee del header "Version:" de navidad-tvs.php.

    Todo lo de desarrollo (docker, dev/, docs/, game/, ayudas/, CSV,
    este script) queda fuera del paquete. Ojo con game/: es el fuente
    TypeScript; lo que viaja al zip es su build ya compilado en
    assets/game/.

.PARAMETER OutDir
    Carpeta destino del zip. Default: .\dist

.PARAMETER Version
    Fuerza un numero de version en el nombre del archivo en vez de leerlo
    del header del plugin.

.PARAMETER Suffix
    Texto extra en el nombre, ej: -Suffix "rc1" => navidad-tvs-1.0.0-rc1.zip

.EXAMPLE
    .\build-zip.ps1

.EXAMPLE
    .\build-zip.ps1 -Suffix rc1 -OutDir C:\entregas
#>

[CmdletBinding()]
param(
    [string]$OutDir,
    [string]$Version,
    [string]$Suffix
)

$ErrorActionPreference = 'Stop'

$SlugPlugin = 'navidad-tvs'
$Root       = $PSScriptRoot
if (-not $OutDir) { $OutDir = Join-Path $Root 'dist' }

# --- Whitelist: solo esto entra al zip ------------------------------------
$IncludeFiles = @(
    'navidad-tvs.php',
    'README.md'
)
$IncludeDirs = @(
    'includes',
    'assets',
    'templates',
    'languages'
)

# Patrones que nunca entran, aunque esten dentro de una carpeta incluida
$ExcludePatterns = @(
    '.gitkeep',
    '.DS_Store',
    'Thumbs.db',
    '*.log',
    '*.map',
    '*.psd',
    '*.zip',
    '*.csv'
)

function Test-Excluded {
    param([string]$Name)
    foreach ($p in $ExcludePatterns) {
        if ($Name -like $p) { return $true }
    }
    return $false
}

# --- Version ---------------------------------------------------------------
$MainFile = Join-Path $Root 'navidad-tvs.php'
if (-not (Test-Path $MainFile)) {
    throw "No se encontro navidad-tvs.php en $Root. Corre el script desde la raiz del plugin."
}

if (-not $Version) {
    $header = Get-Content $MainFile -TotalCount 30 -Encoding UTF8
    $match  = $header | Select-String -Pattern '^\s*\*?\s*Version:\s*(.+?)\s*$' | Select-Object -First 1
    if (-not $match) { throw "No se pudo leer 'Version:' del header de navidad-tvs.php." }
    $Version = $match.Matches[0].Groups[1].Value.Trim()
}

$Name = "$SlugPlugin-$Version"
if ($Suffix) { $Name = "$Name-$Suffix" }
$ZipPath = Join-Path $OutDir "$Name.zip"

# --- Staging ---------------------------------------------------------------
$Stage   = Join-Path ([System.IO.Path]::GetTempPath()) ("navidad-tvs-build-" + [guid]::NewGuid().ToString('N').Substring(0,8))
$StageWp = Join-Path $Stage $SlugPlugin
New-Item -ItemType Directory -Path $StageWp -Force | Out-Null

try {
    $copied = 0

    foreach ($f in $IncludeFiles) {
        $src = Join-Path $Root $f
        if (-not (Test-Path $src)) {
            Write-Warning "Falta $f (se omite)."
            continue
        }
        if (Test-Excluded (Split-Path $src -Leaf)) { continue }
        Copy-Item $src (Join-Path $StageWp $f) -Force
        $copied++
    }

    foreach ($d in $IncludeDirs) {
        $srcDir = Join-Path $Root $d
        if (-not (Test-Path $srcDir)) { continue }

        Get-ChildItem $srcDir -Recurse -File | ForEach-Object {
            if (Test-Excluded $_.Name) { return }

            $rel     = $_.FullName.Substring($Root.Length).TrimStart('\', '/')
            $dest    = Join-Path $StageWp $rel
            $destDir = Split-Path $dest -Parent
            if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
            Copy-Item $_.FullName $dest -Force
            $copied++
        }
    }

    if ($copied -eq 0) { throw "No se copio ningun archivo. Revisa la whitelist." }

    # --- Chequeos de seguridad antes de empaquetar -------------------------
    if (-not (Test-Path (Join-Path $StageWp 'navidad-tvs.php'))) {
        throw "El staging quedo sin navidad-tvs.php."
    }

    $leaks = Get-ChildItem $StageWp -Recurse -File |
        Where-Object {
            $_.Name -match '^(docker-compose\.ya?ml|DOCKER\.md|CLAUDE\.md|.*\.sql|.*\.csv|build-zip\.(ps1|sh))$' -or
            $_.FullName -match '\\(dev|docker|dist|docs|ayudas|imagenes_apoyo|game|\.git|node_modules)\\'
        }
    if ($leaks) {
        throw ("Archivos de desarrollo en el paquete: " + ($leaks.Name -join ', '))
    }

    # --- Zip ----------------------------------------------------------------
    if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir -Force | Out-Null }
    if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    # Se arman las entradas a mano para forzar separador "/" dentro del zip.
    # ZipFile::CreateFromDirectory en Windows PowerShell 5.1 escribe "\", y
    # algunos descompresores (y hosts con PclZip) no lo interpretan como
    # carpeta: el plugin queda como archivos sueltos con backslash en el nombre.
    $zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem $Stage -Recurse -File | Sort-Object FullName | ForEach-Object {
            $bs = [string][char]92
            $entryName = $_.FullName.Substring($Stage.Length).TrimStart($bs, '/').Replace($bs, '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $_.FullName, $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
    finally {
        $zip.Dispose()
    }

    $sizeKb = [math]::Round((Get-Item $ZipPath).Length / 1KB, 1)

    Write-Host ""
    Write-Host "OK  $ZipPath" -ForegroundColor Green
    Write-Host "    version: $Version | archivos: $copied | tamano: $sizeKb KB"
    Write-Host "    raiz del zip: $SlugPlugin/"
    Write-Host ""
    Write-Host "Subir en WordPress: Plugins > Anadir nuevo > Subir plugin > Instalar ahora."
}
finally {
    if (Test-Path $Stage) { Remove-Item $Stage -Recurse -Force -ErrorAction SilentlyContinue }
}
