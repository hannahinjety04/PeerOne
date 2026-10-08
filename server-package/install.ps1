#Requires -Version 5.1
$ErrorActionPreference = "Stop"

$SourceDir = $PSScriptRoot
$InstallDir = Join-Path $HOME "CryptoMarketplaceServer"
$SharedPhpDir = Join-Path $env:LOCALAPPDATA "Swopblock\php"
$RuntimePhpDir = Join-Path $InstallDir "runtime\php"

# Official Windows PHP build used when the machine has no usable PHP.
# NTS (Non Thread Safe) is preferred for php -S / CLI.
$PhpZipName = "php-8.5.8-nts-Win32-vs17-x64.zip"
$PhpZipUrl = "https://windows.php.net/downloads/releases/archives/$PhpZipName"
$PhpExpectedSha256 = "63a3f6493f37c9ff3e288ec16621222a6cda5167dd1abffec0019e7f18c8e7e9"

Write-Host ""
Write-Host "Crypto Marketplace Server Installer" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

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
    $candidates = @(
        (Join-Path $SharedPhpDir "php.exe"),
        (Join-Path $RuntimePhpDir "php.exe"),
        (Join-Path $HOME "SwopblockDev\runtime\php\php.exe")
    )
    foreach ($portable in $candidates) {
        if (Test-PhpUsable $portable) { return $portable }
    }

    if (Get-Command php -ErrorAction SilentlyContinue) {
        $fromPath = (Get-Command php).Source
        if (Test-PhpUsable $fromPath) { return $fromPath }
    }

    if ($IsWindows -or $env:OS -eq "Windows_NT") {
        foreach ($path in @("C:\xampp\php\php.exe", "C:\php\php.exe")) {
            if (Test-PhpUsable $path) { return $path }
        }
    }
    return $null
}

function Enable-SqliteInPhpIni {
    param([string]$PhpHome)

    $iniPath = Join-Path $PhpHome "php.ini"
    $devIni = Join-Path $PhpHome "php.ini-development"
    if (-not (Test-Path $iniPath)) {
        if (-not (Test-Path $devIni)) {
            throw "php.ini-development missing in PHP runtime folder."
        }
        Copy-Item $devIni $iniPath -Force
    }

    $content = Get-Content $iniPath -Raw

    $extDir = (Join-Path $PhpHome "ext") -replace '\\', '/'
    if ($content -match '(?m)^[;\s]*extension_dir\s*=') {
        $content = [regex]::Replace(
            $content,
            '(?m)^[;\s]*extension_dir\s*=.*$',
            "extension_dir = `"$extDir`"",
            1
        )
    } else {
        $content = "extension_dir = `"$extDir`"`r`n" + $content
    }

    foreach ($ext in @("pdo_sqlite", "sqlite3", "openssl", "mbstring")) {
        if ($content -match "(?m)^[;\s]*extension\s*=\s*$ext\b") {
            $content = [regex]::Replace(
                $content,
                "(?m)^[;\s]*extension\s*=\s*$ext\b.*$",
                "extension=$ext",
                1
            )
        } elseif ($content -notmatch "(?m)^extension\s*=\s*$ext\b") {
            $content = $content + "`r`nextension=$ext"
        }
    }

    Set-Content -Path $iniPath -Value $content -Encoding ASCII

    $mods = @(& (Join-Path $PhpHome "php.exe") -m 2>$null)
    $hasSqlite = [bool]($mods | Where-Object { $_ -eq 'pdo_sqlite' })
    if (-not $hasSqlite) {
        throw "SQLite extensions failed to load. Check php.ini in $PhpHome"
    }
}

function Install-PortablePhp {
    param([string]$TargetDir)

    Write-Host ""
    Write-Host "PHP 8+ with SQLite was not found on this computer." -ForegroundColor Yellow
    Write-Host "This installer can download the official PHP Windows binary from php.net," -ForegroundColor Yellow
    Write-Host "verify its SHA-256 hash, and install a portable copy for this node only." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  File:   $PhpZipName" -ForegroundColor DarkGray
    Write-Host "  Source: windows.php.net" -ForegroundColor DarkGray
    Write-Host "  SHA256: $PhpExpectedSha256" -ForegroundColor DarkGray
    Write-Host ""

    Write-Host "Press Enter to install PHP, or type N to cancel." -ForegroundColor White
    $answer = Read-Host "Install portable PHP now? [Y/N]"
    # Empty (Enter) or Y = yes — simple for customers double-clicking install.bat
    if ($answer -match '^[Nn]') {
        Write-Host "Install cancelled. PHP is required to run this node." -ForegroundColor Red
        exit 1
    }

    $bundledZip = Join-Path $SourceDir "runtime\$PhpZipName"
    $downloadZip = Join-Path $env:TEMP $PhpZipName
    $zipPath = $null

    if (Test-Path $bundledZip) {
        Write-Host "Using PHP zip bundled with the package..." -ForegroundColor Cyan
        $zipPath = $bundledZip
    } else {
        Write-Host "Downloading official PHP zip..." -ForegroundColor Cyan
        Write-Host "  $PhpZipUrl" -ForegroundColor DarkGray
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -Uri $PhpZipUrl -OutFile $downloadZip -UseBasicParsing
        } catch {
            Write-Host "Download failed: $_" -ForegroundColor Red
            Write-Host "You can place $PhpZipName in server-package\runtime\ and run install again." -ForegroundColor Yellow
            exit 1
        }
        $zipPath = $downloadZip
    }

    Write-Host "Verifying SHA-256 hash..." -ForegroundColor Cyan
    $actual = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $expected = $PhpExpectedSha256.ToLowerInvariant()
    if ($actual -ne $expected) {
        Write-Host "HASH MISMATCH - refusing to install this PHP zip." -ForegroundColor Red
        Write-Host "  Expected: $expected" -ForegroundColor DarkGray
        Write-Host "  Actual:   $actual" -ForegroundColor DarkGray
        if ($zipPath -eq $downloadZip -and (Test-Path $downloadZip)) {
            Remove-Item $downloadZip -Force -ErrorAction SilentlyContinue
        }
        exit 1
    }
    Write-Host "Hash OK - file matches the official PHP build." -ForegroundColor Green

    if (Test-Path $TargetDir) {
        Remove-Item $TargetDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null

    Write-Host "Extracting portable PHP..." -ForegroundColor Cyan
    Expand-Archive -Path $zipPath -DestinationPath $TargetDir -Force

    $phpExe = Join-Path $TargetDir "php.exe"
    if (-not (Test-Path $phpExe)) {
        $nested = Get-ChildItem $TargetDir -Directory | Select-Object -First 1
        if ($nested) {
            Get-ChildItem $nested.FullName | Move-Item -Destination $TargetDir -Force
            Remove-Item $nested.FullName -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    if (-not (Test-Path (Join-Path $TargetDir "php.exe"))) {
        throw "php.exe missing after extract."
    }

    Write-Host "Enabling SQLite extensions in php.ini..." -ForegroundColor Cyan
    Enable-SqliteInPhpIni -PhpHome $TargetDir

    $ver = & (Join-Path $TargetDir "php.exe") -v
    Write-Host "Portable PHP ready:" -ForegroundColor Green
    Write-Host "  $ver" -ForegroundColor DarkGray

    if ($zipPath -eq $downloadZip -and (Test-Path $downloadZip)) {
        Remove-Item $downloadZip -Force -ErrorAction SilentlyContinue
    }

    return (Join-Path $TargetDir "php.exe")
}

# ---------------------------------------------------------------------------
# Resolve PHP runtime
# ---------------------------------------------------------------------------

$php = Find-ExistingPhp
if ($php) {
    Write-Host "Using existing PHP: $php" -ForegroundColor Green
} else {
    if (-not ($IsWindows -or $env:OS -eq "Windows_NT")) {
        Write-Host "PHP 8+ with pdo_sqlite not found." -ForegroundColor Red
        Write-Host "Linux example: sudo apt install php-cli php-sqlite3" -ForegroundColor Yellow
        exit 1
    }
    if (-not (Test-Path $InstallDir)) {
        New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
    }
    Write-Host "Installing shared portable PHP to: $SharedPhpDir" -ForegroundColor Cyan
    $php = Install-PortablePhp -TargetDir $SharedPhpDir
}

Write-Host "Using PHP: $php" -ForegroundColor Green

# ---------------------------------------------------------------------------
# Copy application files
# ---------------------------------------------------------------------------

$serverSource = Join-Path $SourceDir "server"
$publicSource = Join-Path $serverSource "public"

if (-not (Test-Path $publicSource)) {
    Write-Host "ERROR: Missing folder: $publicSource" -ForegroundColor Red
    Write-Host "Your zip must include server/public/ with index.php, api.php, etc." -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path (Join-Path $publicSource "index.php"))) {
    Write-Host "ERROR: Missing file: $publicSource\index.php" -ForegroundColor Red
    exit 1
}

New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
if (Test-Path $InstallDir) {
    Write-Host "Updating install at $InstallDir" -ForegroundColor Yellow
    Get-ChildItem $InstallDir -Force | Where-Object { $_.Name -ne "runtime" } | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Copy-Item -Path (Join-Path $serverSource "*") -Destination $InstallDir -Recurse -Force

# Prefer shared PHP; link install runtime\php so start-server.bat still works
$sharedExe = Join-Path $SharedPhpDir "php.exe"
if (Test-PhpUsable $sharedExe) {
    $php = $sharedExe
}
if (-not (Test-PhpUsable $php)) {
    if ($IsWindows -or $env:OS -eq "Windows_NT") {
        $php = Install-PortablePhp -TargetDir $SharedPhpDir
    } else {
        Write-Host "PHP became unavailable after copy. Aborting." -ForegroundColor Red
        exit 1
    }
}

$runtimeParent = Join-Path $InstallDir "runtime"
if (-not (Test-Path $runtimeParent)) {
    New-Item -ItemType Directory -Path $runtimeParent -Force | Out-Null
}
if ((Test-PhpUsable $sharedExe) -and -not (Test-PhpUsable (Join-Path $RuntimePhpDir "php.exe"))) {
    if (Test-Path $RuntimePhpDir) {
        Remove-Item $RuntimePhpDir -Recurse -Force -ErrorAction SilentlyContinue
    }
    try {
        New-Item -ItemType Junction -Path $RuntimePhpDir -Target $SharedPhpDir -Force | Out-Null
    } catch {
        # start-server.bat also checks PATH / xampp / shared via updates below
    }
}

$configSample = Join-Path $InstallDir "config.sample.php"
$configFile = Join-Path $InstallDir "config.php"
if (-not (Test-Path $configFile)) {
    Copy-Item $configSample $configFile
}

$dataDir = Join-Path $InstallDir "data"
if (-not (Test-Path $dataDir)) {
    New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
}

Write-Host "Running setup.php..." -ForegroundColor Cyan
& $php (Join-Path $InstallDir "setup.php")
if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    Write-Host "setup.php failed." -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------------
# Start scripts - prefer portable runtime PHP
# ---------------------------------------------------------------------------

$startScript = @'
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

$publicDir = Join-Path $PSScriptRoot "public"
if (-not (Test-Path $publicDir)) {
    Write-Host "public folder not found at: $publicDir" -ForegroundColor Red
    Write-Host "Run install.bat first." -ForegroundColor Yellow
    exit 1
}

$php = $null
$portable = Join-Path $PSScriptRoot "runtime\php\php.exe"
if (Test-Path $portable) {
    $php = $portable
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    $php = (Get-Command php).Source
} elseif (Test-Path "C:\xampp\php\php.exe") {
    $php = "C:\xampp\php\php.exe"
}

if (-not $php) {
    Write-Host "PHP not found. Run install.bat again." -ForegroundColor Red
    exit 1
}

Write-Host "Starting Crypto Marketplace Server on http://localhost:8080" -ForegroundColor Cyan
Write-Host "Using: $php" -ForegroundColor DarkGray
Write-Host "Press Ctrl+C to stop." -ForegroundColor DarkGray
& $php -S localhost:8080 -t $publicDir
'@

$startPath = Join-Path $InstallDir "start-server.ps1"
Set-Content -Path $startPath -Value $startScript -Encoding UTF8

$publicDir = Join-Path $InstallDir "public"
if (-not (Test-Path $publicDir)) {
    Write-Host "Install error: public folder was not copied to $InstallDir" -ForegroundColor Red
    exit 1
}

$startBat = @"
@echo off
cd /d "$InstallDir"
if not exist public (
    echo public folder missing. Run install.bat first.
    pause
    exit /b 1
)
if exist runtime\php\php.exe (
    runtime\php\php.exe -S localhost:8080 -t public
    goto :done
)
where php >nul 2>&1 && php -S localhost:8080 -t public && goto :done
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8080 -t public
    goto :done
)
echo PHP not found. Run install.bat again.
pause
exit /b 1
:done
pause
"@
Set-Content -Path (Join-Path $InstallDir "start-server.bat") -Value $startBat -Encoding ASCII

Write-Host ""
Write-Host "Install complete!" -ForegroundColor Green
Write-Host "Server installed to: $InstallDir" -ForegroundColor Green
Write-Host "PHP used: $php" -ForegroundColor Green
Write-Host ""
Write-Host "Next step - start the server:" -ForegroundColor Cyan
Write-Host "  double-click start-server.bat" -ForegroundColor White
Write-Host "  in $InstallDir" -ForegroundColor DarkGray
Write-Host ""
Write-Host "Then open http://localhost:8080 in your browser." -ForegroundColor Cyan
Write-Host ""

$registerScript = Join-Path $SourceDir "register-with-central.ps1"
if (Test-Path $registerScript) {
    $label = $env:COMPUTERNAME + ' Node'
    & $registerScript -InstallLabel $label
}
