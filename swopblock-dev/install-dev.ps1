#Requires -Version 5.1
$ErrorActionPreference = 'Stop'

# Sets up THIS unzipped folder in place.
# After install, use start-central.bat / start-client.bat / register-client.bat here.

$SourceDir = $PSScriptRoot
$InstallRoot = $SourceDir
# Per-package runtime (optional). Shared machine-wide PHP used across all copies:
$SharedPhpDir = Join-Path $env:LOCALAPPDATA 'Swopblock\php'
$RuntimePhpDir = Join-Path $InstallRoot 'runtime\php'
$CentralDir = Join-Path $InstallRoot 'central'
$ClientDir = Join-Path $InstallRoot 'client\server'

$PhpZipName = 'php-8.5.8-nts-Win32-vs17-x64.zip'
$PhpZipUrl = "https://windows.php.net/downloads/releases/archives/$PhpZipName"
$PhpExpectedSha256 = '63a3f6493f37c9ff3e288ec16621222a6cda5167dd1abffec0019e7f18c8e7e9'

Write-Host ''
Write-Host 'Swopblock Development Tools Installer' -ForegroundColor Cyan
Write-Host '======================================' -ForegroundColor Cyan
Write-Host 'Sets up this folder (no separate SwopblockDev copy).' -ForegroundColor DarkGray
Write-Host 'SQLite only - no XAMPP / MySQL needed.' -ForegroundColor DarkGray
Write-Host ''

function Test-PhpUsable {
    param([string]$PhpExe)
    if (-not $PhpExe -or -not (Test-Path $PhpExe)) { return $false }
    try {
        $verText = & $PhpExe -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>$null
        if (-not $verText) { return $false }
        $parts = $verText.Trim().Split('.')
        $major = [int]$parts[0]
        if ($major -lt 8) { return $false }
        $mods = @(& $PhpExe -m 2>$null)
        return [bool]($mods | Where-Object { $_ -eq 'pdo_sqlite' })
    } catch {
        return $false
    }
}

function Find-ExistingPhp {
    $candidates = [System.Collections.Generic.List[string]]::new()
    $candidates.Add((Join-Path $SharedPhpDir 'php.exe'))
    $candidates.Add((Join-Path $RuntimePhpDir 'php.exe'))
    $candidates.Add((Join-Path $HOME 'CryptoMarketplaceServer\runtime\php\php.exe'))
    $candidates.Add((Join-Path $HOME 'SwopblockDev\runtime\php\php.exe'))

    # Reuse PHP from earlier unzipped swopblock-dev downloads
    $downloads = Join-Path $HOME 'Downloads'
    if (Test-Path $downloads) {
        Get-ChildItem $downloads -Directory -ErrorAction SilentlyContinue |
            Where-Object { $_.Name -like 'swopblock-development-tools*' } |
            ForEach-Object {
                $candidates.Add((Join-Path $_.FullName 'swopblock-dev\runtime\php\php.exe'))
            }
    }

    foreach ($portable in $candidates) {
        if (Test-PhpUsable $portable) {
            # If we found PHP outside the shared folder, link/copy into shared for next installs
            $foundDir = Split-Path $portable -Parent
            if ($foundDir -ne $SharedPhpDir -and -not (Test-PhpUsable (Join-Path $SharedPhpDir 'php.exe'))) {
                try {
                    $parent = Split-Path $SharedPhpDir -Parent
                    if (-not (Test-Path $parent)) {
                        New-Item -ItemType Directory -Path $parent -Force | Out-Null
                    }
                    if (Test-Path $SharedPhpDir) {
                        Remove-Item $SharedPhpDir -Recurse -Force -ErrorAction SilentlyContinue
                    }
                    New-Item -ItemType Junction -Path $SharedPhpDir -Target $foundDir -Force | Out-Null
                    Write-Host "Reusing PHP from earlier install via shared link." -ForegroundColor DarkGray
                } catch {
                    # still return the found php.exe
                }
            }
            return $portable
        }
    }

    if (Get-Command php -ErrorAction SilentlyContinue) {
        $fromPath = (Get-Command php).Source
        if (Test-PhpUsable $fromPath) { return $fromPath }
    }

    if ($IsWindows -or $env:OS -eq 'Windows_NT') {
        foreach ($path in @('C:\xampp\php\php.exe', 'C:\php\php.exe')) {
            if (Test-PhpUsable $path) { return $path }
        }
    }
    return $null
}

function Enable-SqliteInPhpIni {
    param([string]$PhpHome)

    $iniPath = Join-Path $PhpHome 'php.ini'
    $devIni = Join-Path $PhpHome 'php.ini-development'
    if (-not (Test-Path $iniPath)) {
        if (-not (Test-Path $devIni)) {
            throw 'php.ini-development missing in PHP runtime folder.'
        }
        Copy-Item $devIni $iniPath -Force
    }

    $content = Get-Content $iniPath -Raw
    $extDir = (Join-Path $PhpHome 'ext') -replace '\\', '/'
    if ($content -match '(?m)^[;\s]*extension_dir\s*=') {
        $content = [regex]::Replace(
            $content,
            '(?m)^[;\s]*extension_dir\s*=.*$',
            ('extension_dir = "' + $extDir + '"'),
            1
        )
    } else {
        $content = ('extension_dir = "' + $extDir + '"' + "`r`n") + $content
    }

    foreach ($ext in @('pdo_sqlite', 'sqlite3', 'openssl', 'mbstring')) {
        if ($content -match ("(?m)^[;\s]*extension\s*=\s*$ext\b")) {
            $content = [regex]::Replace(
                $content,
                ("(?m)^[;\s]*extension\s*=\s*$ext\b.*$"),
                ("extension=$ext"),
                1
            )
        } elseif ($content -notmatch ("(?m)^extension\s*=\s*$ext\b")) {
            $content = $content + "`r`nextension=$ext"
        }
    }

    Set-Content -Path $iniPath -Value $content -Encoding ASCII

    $mods = @(& (Join-Path $PhpHome 'php.exe') -m 2>$null)
    $hasSqlite = [bool]($mods | Where-Object { $_ -eq 'pdo_sqlite' })
    if (-not $hasSqlite) {
        throw "SQLite extensions failed to load. Check php.ini in $PhpHome"
    }
}

function Install-PortablePhp {
    param([string]$TargetDir)

    Write-Host ''
    Write-Host 'PHP 8+ with SQLite was not found on this computer.' -ForegroundColor Yellow
    Write-Host 'This installer can download the official PHP Windows binary from php.net,' -ForegroundColor Yellow
    Write-Host 'verify its SHA-256 hash, and install a portable copy in this folder.' -ForegroundColor Yellow
    Write-Host ''
    Write-Host "  File:   $PhpZipName" -ForegroundColor DarkGray
    Write-Host '  Source: windows.php.net' -ForegroundColor DarkGray
    Write-Host "  SHA256: $PhpExpectedSha256" -ForegroundColor DarkGray
    Write-Host ''

    Write-Host 'Press Enter to install PHP, or type N to cancel.' -ForegroundColor White
    $answer = Read-Host 'Install portable PHP now? [Y/N]'
    if ($answer -match '^[Nn]') {
        Write-Host 'Install cancelled. PHP is required for local development.' -ForegroundColor Red
        exit 1
    }

    $bundledZip = Join-Path $SourceDir "runtime\$PhpZipName"
    $downloadZip = Join-Path $env:TEMP $PhpZipName
    $zipPath = $null

    if (Test-Path $bundledZip) {
        Write-Host 'Using PHP zip bundled with the package...' -ForegroundColor Cyan
        $zipPath = $bundledZip
    } else {
        Write-Host 'Downloading official PHP zip...' -ForegroundColor Cyan
        Write-Host "  $PhpZipUrl" -ForegroundColor DarkGray
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $PhpZipUrl -OutFile $downloadZip -UseBasicParsing
        } catch {
            Write-Host "Download failed: $_" -ForegroundColor Red
            exit 1
        }
        $zipPath = $downloadZip
    }

    Write-Host 'Verifying SHA-256 hash...' -ForegroundColor Cyan
    $actual = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $expected = $PhpExpectedSha256.ToLowerInvariant()
    if ($actual -ne $expected) {
        Write-Host 'HASH MISMATCH - refusing to install this PHP zip.' -ForegroundColor Red
        Write-Host "  Expected: $expected" -ForegroundColor DarkGray
        Write-Host "  Actual:   $actual" -ForegroundColor DarkGray
        if ($zipPath -eq $downloadZip -and (Test-Path $downloadZip)) {
            Remove-Item $downloadZip -Force -ErrorAction SilentlyContinue
        }
        exit 1
    }
    Write-Host 'Hash OK - file matches the official PHP build.' -ForegroundColor Green

    if (Test-Path $TargetDir) {
        Remove-Item $TargetDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null

    Write-Host 'Extracting portable PHP...' -ForegroundColor Cyan
    Expand-Archive -Path $zipPath -DestinationPath $TargetDir -Force

    $phpExe = Join-Path $TargetDir 'php.exe'
    if (-not (Test-Path $phpExe)) {
        $nested = Get-ChildItem $TargetDir -Directory | Select-Object -First 1
        if ($nested) {
            Get-ChildItem $nested.FullName | Move-Item -Destination $TargetDir -Force
            Remove-Item $nested.FullName -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    if (-not (Test-Path (Join-Path $TargetDir 'php.exe'))) {
        throw 'php.exe missing after extract.'
    }

    Write-Host 'Enabling SQLite extensions in php.ini...' -ForegroundColor Cyan
    Enable-SqliteInPhpIni -PhpHome $TargetDir

    if ($zipPath -eq $downloadZip -and (Test-Path $downloadZip)) {
        Remove-Item $downloadZip -Force -ErrorAction SilentlyContinue
    }

    return (Join-Path $TargetDir 'php.exe')
}

# ---------------------------------------------------------------------------
# Resolve PHP
# ---------------------------------------------------------------------------

$php = Find-ExistingPhp
if ($php) {
    Write-Host "Using existing PHP: $php" -ForegroundColor Green
} else {
    if (-not ($IsWindows -or $env:OS -eq 'Windows_NT')) {
        Write-Host 'PHP 8+ with pdo_sqlite not found. Install php-cli + php-sqlite3.' -ForegroundColor Red
        exit 1
    }
    # Install once for this Windows user; all package copies reuse it
    Write-Host "Installing shared portable PHP to: $SharedPhpDir" -ForegroundColor Cyan
    $php = Install-PortablePhp -TargetDir $SharedPhpDir
}

# Point this package's runtime\php at the shared install (so start-*.bat keep working)
$sharedExe = Join-Path $SharedPhpDir 'php.exe'
if (Test-PhpUsable $sharedExe) {
    $php = $sharedExe
    $localRuntimeParent = Join-Path $InstallRoot 'runtime'
    if (-not (Test-Path $localRuntimeParent)) {
        New-Item -ItemType Directory -Path $localRuntimeParent -Force | Out-Null
    }
    if (-not (Test-PhpUsable (Join-Path $RuntimePhpDir 'php.exe'))) {
        if (Test-Path $RuntimePhpDir) {
            Remove-Item $RuntimePhpDir -Recurse -Force -ErrorAction SilentlyContinue
        }
        try {
            New-Item -ItemType Junction -Path $RuntimePhpDir -Target $SharedPhpDir -Force | Out-Null
            Write-Host "Linked this package runtime\\php -> shared PHP" -ForegroundColor DarkGray
        } catch {
            Write-Host "Note: could not link local runtime; start scripts will use shared PHP path." -ForegroundColor DarkGray
        }
    }
}

Write-Host "Using PHP: $php" -ForegroundColor Green

# ---------------------------------------------------------------------------
# Validate package layout
# ---------------------------------------------------------------------------

if (-not (Test-Path (Join-Path $CentralDir 'index.php'))) {
    Write-Host 'ERROR: Missing central\index.php in this package.' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path (Join-Path $ClientDir 'public\index.php'))) {
    Write-Host 'ERROR: Missing client\server\public\index.php in this package.' -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------------
# Central (SQLite) - configure in place
# ---------------------------------------------------------------------------

Write-Host 'Configuring local central (SQLite)...' -ForegroundColor Cyan

$includesDir = Join-Path $CentralDir 'includes'
if (-not (Test-Path $includesDir)) {
    New-Item -ItemType Directory -Path $includesDir -Force | Out-Null
}

$configLines = @(
    '<?php'
    'return ['
    "    'db_driver' => 'sqlite',"
    "    'db_path' => dirname(__DIR__) . '/data/central.db',"
    "    'package_version' => '1.0.0-dev',"
    "    'admin_email' => 'owner@central.local',"
    '];'
)
Set-Content -Path (Join-Path $includesDir 'config.local.php') -Value $configLines -Encoding ASCII

Write-Host 'Creating SQLite database and owner account...' -ForegroundColor Cyan
& $php (Join-Path $CentralDir 'database\setup_central_sqlite.php')
if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    Write-Host 'Central SQLite setup failed.' -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------------
# Client node - configure in place
# ---------------------------------------------------------------------------

Write-Host 'Configuring local client node...' -ForegroundColor Cyan

$endpoint = 'http://127.0.0.1:8081/api/register-node.php'
Set-Content -Path (Join-Path $InstallRoot 'central-endpoint.txt') -Value $endpoint -Encoding ASCII
Set-Content -Path (Join-Path $ClientDir 'central-endpoint.txt') -Value $endpoint -Encoding ASCII

$configSample = Join-Path $ClientDir 'config.sample.php'
$configFile = Join-Path $ClientDir 'config.php'
if ((Test-Path $configSample) -and -not (Test-Path $configFile)) {
    Copy-Item $configSample $configFile
}

$dataDir = Join-Path $ClientDir 'data'
if (-not (Test-Path $dataDir)) {
    New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
}

Write-Host 'Running client setup.php...' -ForegroundColor Cyan
# Do not let a previously opened node-2/gossip shell redirect normal setup.
Remove-Item Env:SWOPBLOCK_CONFIG -ErrorAction SilentlyContinue
& $php (Join-Path $ClientDir 'setup.php')
if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    Write-Host 'Client setup.php failed.' -ForegroundColor Red
    exit 1
}

# Prepare register script paths (relative template already in package)
$regTemplate = Join-Path $SourceDir 'register-dev.ps1'
if (Test-Path $regTemplate) {
    $regText = Get-Content $regTemplate -Raw
    $regText = $regText.Replace('__INSTALL_ROOT__', $InstallRoot)
    $regText = $regText.Replace('__CLIENT_DIR__', $ClientDir)
    Set-Content -Path (Join-Path $InstallRoot 'register-with-central.ps1') -Value $regText -Encoding ASCII
}

$readmeLines = @(
    'Swopblock local development'
    '==========================='
    ''
    'Everything stays in THIS folder.'
    ''
    '1. Double-click install-dev.bat   (once)'
    '2. Double-click start-central.bat'
    '   -> http://127.0.0.1:8081'
    '3. Double-click start-client.bat   (second window)'
    '   -> http://127.0.0.1:8080'
    '4. Double-click register-client.bat'
    '   (central must already be running)'
    '5. Refresh admin dashboard:'
    '   http://127.0.0.1:8081/admin/login.php'
    '   Email: owner@central.local'
    '   Password: changeme123'
)
Set-Content -Path (Join-Path $InstallRoot 'README.txt') -Value $readmeLines -Encoding ASCII

Write-Host ''
Write-Host 'Setup complete in this folder:' -ForegroundColor Green
Write-Host "  $InstallRoot" -ForegroundColor Green
Write-Host ''
Write-Host 'Next (same folder):' -ForegroundColor Cyan
Write-Host '  1. start-central.bat     -> http://127.0.0.1:8081' -ForegroundColor White
Write-Host '  2. start-client.bat      -> http://127.0.0.1:8080' -ForegroundColor White
Write-Host '  3. register-client.bat   -> shows up on admin dashboard' -ForegroundColor White
Write-Host '  Admin: owner@central.local / changeme123' -ForegroundColor DarkGray
Write-Host '  Use 127.0.0.1 in the browser (not localhost).' -ForegroundColor DarkGray
Write-Host ''
