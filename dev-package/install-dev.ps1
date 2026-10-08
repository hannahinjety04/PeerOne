#Requires -Version 5.1
$ErrorActionPreference = "Stop"

# Local development installer (Option B): portable PHP + SQLite central + client node.
# No XAMPP / MySQL required.

$SourceDir = $PSScriptRoot
$InstallRoot = Join-Path $HOME "SwopblockDev"
$RuntimePhpDir = Join-Path $InstallRoot "runtime\php"
$CentralDir = Join-Path $InstallRoot "central"
$ClientDir = Join-Path $InstallRoot "client"

$PhpZipName = "php-8.5.8-nts-Win32-vs17-x64.zip"
$PhpZipUrl = "https://windows.php.net/downloads/releases/$PhpZipName"
$PhpExpectedSha256 = "63a3f6493f37c9ff3e288ec16621222a6cda5167dd1abffec0019e7f18c8e7e9"

Write-Host ""
Write-Host "Swopblock Development Tools Installer" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "SQLite only — no XAMPP / MySQL needed." -ForegroundColor DarkGray
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
    $portable = Join-Path $RuntimePhpDir "php.exe"
    if (Test-PhpUsable $portable) { return $portable }

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
    Write-Host "verify its SHA-256 hash, and install a portable copy for development only." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  File:   $PhpZipName" -ForegroundColor DarkGray
    Write-Host "  Source: windows.php.net" -ForegroundColor DarkGray
    Write-Host "  SHA256: $PhpExpectedSha256" -ForegroundColor DarkGray
    Write-Host ""

    Write-Host "Press Enter to install PHP, or type N to cancel." -ForegroundColor White
    $answer = Read-Host "Install portable PHP now? [Y/N]"
    if ($answer -match '^[Nn]') {
        Write-Host "Install cancelled. PHP is required for local development." -ForegroundColor Red
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

    if ($zipPath -eq $downloadZip -and (Test-Path $downloadZip)) {
        Remove-Item $downloadZip -Force -ErrorAction SilentlyContinue
    }

    return (Join-Path $TargetDir "php.exe")
}

# ---------------------------------------------------------------------------
# Resolve PHP
# ---------------------------------------------------------------------------

$php = Find-ExistingPhp
if ($php) {
    Write-Host "Using existing PHP: $php" -ForegroundColor Green
} else {
    if (-not ($IsWindows -or $env:OS -eq "Windows_NT")) {
        Write-Host "PHP 8+ with pdo_sqlite not found. Install php-cli + php-sqlite3." -ForegroundColor Red
        exit 1
    }
    New-Item -ItemType Directory -Path $InstallRoot -Force | Out-Null
    $php = Install-PortablePhp -TargetDir $RuntimePhpDir
}

Write-Host "Using PHP: $php" -ForegroundColor Green

# ---------------------------------------------------------------------------
# Validate package layout
# ---------------------------------------------------------------------------

$centralSource = Join-Path $SourceDir "central"
$clientSource = Join-Path $SourceDir "client"

if (-not (Test-Path (Join-Path $centralSource "index.php"))) {
    Write-Host "ERROR: Missing central/index.php in the downloaded package." -ForegroundColor Red
    Write-Host "Expected: $centralSource" -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path (Join-Path $clientSource "server\public\index.php"))) {
    Write-Host "ERROR: Missing client/server/public/index.php in the package." -ForegroundColor Red
    Write-Host "Expected: $clientSource\server\public" -ForegroundColor Yellow
    exit 1
}

# ---------------------------------------------------------------------------
# Install root (keep runtime)
# ---------------------------------------------------------------------------

New-Item -ItemType Directory -Path $InstallRoot -Force | Out-Null
if (Test-Path $InstallRoot) {
    Write-Host "Updating install at $InstallRoot" -ForegroundColor Yellow
    Get-ChildItem $InstallRoot -Force | Where-Object { $_.Name -ne "runtime" } | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
}

# Prefer portable PHP if present
if (Test-PhpUsable (Join-Path $RuntimePhpDir "php.exe")) {
    $php = Join-Path $RuntimePhpDir "php.exe"
}

# ---------------------------------------------------------------------------
# Central (SQLite)
# ---------------------------------------------------------------------------

Write-Host "Installing local central node..." -ForegroundColor Cyan
New-Item -ItemType Directory -Path $CentralDir -Force | Out-Null
Copy-Item -Path (Join-Path $centralSource "*") -Destination $CentralDir -Recurse -Force

$configLocal = @"
<?php
return [
    'db_driver' => 'sqlite',
    'db_path' => dirname(__DIR__) . '/data/central.db',
    'package_version' => '1.0.0-dev',
    'admin_email' => 'owner@central.local',
];
"@
Set-Content -Path (Join-Path $CentralDir "includes\config.local.php") -Value $configLocal -Encoding UTF8

Write-Host "Creating SQLite database and owner account..." -ForegroundColor Cyan
& $php (Join-Path $CentralDir "database\setup_central_sqlite.php")
if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    Write-Host "Central SQLite setup failed." -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------------
# Client node
# ---------------------------------------------------------------------------

Write-Host "Installing local client node..." -ForegroundColor Cyan
New-Item -ItemType Directory -Path $ClientDir -Force | Out-Null
Copy-Item -Path (Join-Path $clientSource "server\*") -Destination $ClientDir -Recurse -Force

# Point client registration at local central
$endpoint = "http://127.0.0.1:8081/api/register-node.php"
Set-Content -Path (Join-Path $InstallRoot "central-endpoint.txt") -Value $endpoint -Encoding ASCII
Set-Content -Path (Join-Path $ClientDir "central-endpoint.txt") -Value $endpoint -Encoding ASCII
# Also keep a copy next to client package scripts for register-with-central.ps1 patterns
Copy-Item (Join-Path $InstallRoot "central-endpoint.txt") (Join-Path $clientSource "central-endpoint.txt") -Force -ErrorAction SilentlyContinue

$configSample = Join-Path $ClientDir "config.sample.php"
$configFile = Join-Path $ClientDir "config.php"
if ((Test-Path $configSample) -and -not (Test-Path $configFile)) {
    Copy-Item $configSample $configFile
}

$dataDir = Join-Path $ClientDir "data"
if (-not (Test-Path $dataDir)) {
    New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
}

Write-Host "Running client setup.php..." -ForegroundColor Cyan
& $php (Join-Path $ClientDir "setup.php")
if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    Write-Host "Client setup.php failed." -ForegroundColor Red
    exit 1
}

# Register helper pointed at local central + this client install
$registerDev = @"
#Requires -Version 5.1
`$ErrorActionPreference = "Stop"
`$InstallRoot = "$InstallRoot"
`$ClientDir = "$ClientDir"
`$endpointFile = Join-Path `$InstallRoot "central-endpoint.txt"
`$url = (Get-Content `$endpointFile -Raw).Trim()
`$label = `$env:COMPUTERNAME + " Dev Node"
`$nodeJson = Join-Path `$ClientDir "node.json"

# Fingerprint from client install
`$files = @("schema.sql", "setup.php", "public\index.php", "public\api.php")
`$sha = [System.Security.Cryptography.SHA256]::Create()
`$ms = New-Object System.IO.MemoryStream
foreach (`$file in `$files) {
    `$path = Join-Path `$ClientDir `$file
    if (Test-Path `$path) {
        `$bytes = [System.IO.File]::ReadAllBytes(`$path)
        `$ms.Write(`$bytes, 0, `$bytes.Length)
    }
}
`$fp = ([BitConverter]::ToString(`$sha.ComputeHash(`$ms.ToArray()))).Replace("-", "").ToLower()

`$body = @{
    installLabel = `$label
    packageVersion = "1.0.0-dev"
    packageFingerprint = `$fp
} | ConvertTo-Json

Write-Host "Registering with `$url ..." -ForegroundColor Cyan
Write-Host "Start central first (start-central.bat) if this fails." -ForegroundColor DarkGray
`$response = Invoke-RestMethod -Uri `$url -Method Post -Body `$body -ContentType "application/json"
if (-not `$response.apiKey) { throw "No apiKey returned" }

`$nodeConfig = @{
    installID = `$response.installID
    apiKey = `$response.apiKey
    installStatus = `$response.installStatus
    centralRegister = `$url
    centralCheckin = `$url.Replace("register-node.php", "checkin.php")
} | ConvertTo-Json
Set-Content -Path `$nodeJson -Value `$nodeConfig -Encoding UTF8
Write-Host "Registered. Saved `$nodeJson" -ForegroundColor Green
"@
Set-Content -Path (Join-Path $InstallRoot "register-with-central.ps1") -Value $registerDev -Encoding UTF8

# ---------------------------------------------------------------------------
# Start scripts
# ---------------------------------------------------------------------------

$startCentralBat = @"
@echo off
cd /d "$CentralDir"
echo Starting local CENTRAL on http://localhost:8081
echo Admin: http://localhost:8081/admin/login.php
echo   owner@central.local / changeme123
echo.
if exist "$RuntimePhpDir\php.exe" (
    "$RuntimePhpDir\php.exe" -S localhost:8081 -t "$CentralDir"
    goto :done
)
where php >nul 2>&1 && php -S localhost:8081 -t "$CentralDir" && goto :done
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8081 -t "$CentralDir"
    goto :done
)
echo PHP not found. Run install-dev.bat again.
pause
exit /b 1
:done
pause
"@
Set-Content -Path (Join-Path $InstallRoot "start-central.bat") -Value $startCentralBat -Encoding ASCII

$startClientBat = @"
@echo off
cd /d "$ClientDir"
echo Starting local CLIENT node on http://localhost:8080
echo.
if exist "$RuntimePhpDir\php.exe" (
    "$RuntimePhpDir\php.exe" -S localhost:8080 -t "$ClientDir\public"
    goto :done
)
where php >nul 2>&1 && php -S localhost:8080 -t "$ClientDir\public" && goto :done
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8080 -t "$ClientDir\public"
    goto :done
)
echo PHP not found. Run install-dev.bat again.
pause
exit /b 1
:done
pause
"@
Set-Content -Path (Join-Path $InstallRoot "start-client.bat") -Value $startClientBat -Encoding ASCII

$readmeOut = @"
Swopblock local development
===========================

Installed to: $InstallRoot

1. Double-click start-central.bat
   -> http://localhost:8081
   -> Admin: http://localhost:8081/admin/login.php
      Email: owner@central.local
      Password: changeme123

2. Double-click start-client.bat (second window)
   -> http://localhost:8080

3. Optional: with central running, register the client:
   pwsh .\register-with-central.ps1

Database: SQLite file at central\data\central.db
No XAMPP or MySQL required for this development package.
"@
Set-Content -Path (Join-Path $InstallRoot "README.txt") -Value $readmeOut -Encoding ASCII

Write-Host ""
Write-Host "Development install complete!" -ForegroundColor Green
Write-Host "Folder: $InstallRoot" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Double-click start-central.bat  -> http://localhost:8081" -ForegroundColor White
Write-Host "  2. Double-click start-client.bat   -> http://localhost:8080" -ForegroundColor White
Write-Host "  Admin: owner@central.local / changeme123" -ForegroundColor DarkGray
Write-Host ""
