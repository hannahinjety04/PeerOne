# Registers this client node with the central server after local install.
param(
    [string]$CentralRegisterUrl = "",
    [string]$InstallLabel = "",
    [string]$PackageVersion = "1.0.0",
    [string]$ConfigPath = ""
)

$ErrorActionPreference = "Stop"

if (-not $ConfigPath) {
    $ConfigPath = Join-Path $HOME "CryptoMarketplaceServer\node.json"
}

if (-not $CentralRegisterUrl) {
    $endpointFile = Join-Path $PSScriptRoot "central-endpoint.txt"
    if (Test-Path $endpointFile) {
        $CentralRegisterUrl = (Get-Content $endpointFile -Raw).Trim()
    }
}

if (-not $CentralRegisterUrl) {
    Write-Host "No central URL configured. Skipping registration." -ForegroundColor Yellow
    Write-Host "Create central-endpoint.txt next to install.ps1 with one line:" -ForegroundColor Yellow
    Write-Host "  http://localhost/swopblock-marketplace/api/register-node.php" -ForegroundColor DarkGray
    return
}

if (-not $InstallLabel) {
    $InstallLabel = "$env:COMPUTERNAME Node"
}

function Get-PackageFingerprint {
    param([string]$Root)
    $files = @("schema.sql", "setup.php", "public\index.php", "public\api.php")
    $sha = [System.Security.Cryptography.SHA256]::Create()
    $ms = New-Object System.IO.MemoryStream
    foreach ($file in $files) {
        $path = Join-Path $Root $file
        if (Test-Path $path) {
            $bytes = [System.IO.File]::ReadAllBytes($path)
            $ms.Write($bytes, 0, $bytes.Length)
        }
    }
    $hash = $sha.ComputeHash($ms.ToArray())
    return ([BitConverter]::ToString($hash)).Replace("-", "").ToLower()
}

$installDir = Join-Path $HOME "CryptoMarketplaceServer"
$packageFingerprint = ""
if (Test-Path $installDir) {
    $packageFingerprint = Get-PackageFingerprint -Root $installDir
}

$body = @{
    installLabel       = $InstallLabel
    packageVersion     = $PackageVersion
    packageFingerprint = $packageFingerprint
} | ConvertTo-Json

Write-Host "Registering node with central server..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri $CentralRegisterUrl -Method Post -Body $body -ContentType "application/json"
} catch {
    Write-Host "Registration failed: $_" -ForegroundColor Red
    exit 1
}

if (-not $response.apiKey) {
    Write-Host "Registration failed: no apiKey returned." -ForegroundColor Red
    exit 1
}

$nodeConfig = @{
    installID       = $response.installID
    apiKey          = $response.apiKey
    installStatus   = $response.installStatus
    centralRegister = $CentralRegisterUrl
    centralCheckin  = $CentralRegisterUrl.Replace("register-node.php", "checkin.php")
    installLabel    = $InstallLabel
    registeredAt    = (Get-Date).ToString("o")
}

$configDir = Split-Path $ConfigPath -Parent
if (-not (Test-Path $configDir)) {
    New-Item -ItemType Directory -Path $configDir -Force | Out-Null
}

$nodeConfig | ConvertTo-Json | Set-Content -Path $ConfigPath -Encoding UTF8

Write-Host "Node registered. installID: $($response.installID)" -ForegroundColor Green
Write-Host "API key saved to: $ConfigPath" -ForegroundColor Green

# Optional check-in to mark node active
if ($nodeConfig.centralCheckin) {
    try {
        $checkinBody = @{
            apiKey             = $response.apiKey
            packageFingerprint = $packageFingerprint
        } | ConvertTo-Json
        Invoke-RestMethod -Uri $nodeConfig.centralCheckin -Method Post -Body $checkinBody -ContentType "application/json" | Out-Null
        Write-Host "Check-in sent. Node marked active on central server." -ForegroundColor Green
    } catch {
        Write-Host "Check-in failed (node is still registered): $_" -ForegroundColor Yellow
    }
}
