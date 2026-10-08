# Verifies this node against another installation on the central server.
param(
    [Parameter(Mandatory = $true)]
    [int]$PeerInstallID,
    [string]$ConfigPath = ""
)

$ErrorActionPreference = "Stop"

if (-not $ConfigPath) {
    $ConfigPath = Join-Path $HOME "CryptoMarketplaceServer\node.json"
}

if (-not (Test-Path $ConfigPath)) {
    Write-Host "node.json not found. Run install.bat and register first." -ForegroundColor Red
    exit 1
}

$node = Get-Content $ConfigPath -Raw | ConvertFrom-Json
$installDir = Join-Path $HOME "CryptoMarketplaceServer"

function Get-PackageFingerprint {
    param([string]$Root)
    $files = @(
        "schema.sql",
        "setup.php",
        "public\index.php",
        "public\api.php"
    )
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

$verifyUrl = $node.centralRegister.Replace("register-node.php", "verify-installation.php")
$body = @{
    apiKey             = $node.apiKey
    peerInstallID      = $PeerInstallID
    packageVersion     = "1.0.0"
    packageFingerprint = (Get-PackageFingerprint -Root $installDir)
} | ConvertTo-Json

Write-Host "Verifying installation $($node.installID) against peer $PeerInstallID..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri $verifyUrl -Method Post -Body $body -ContentType "application/json"
} catch {
    if ($_.ErrorDetails.Message) {
        Write-Host $_.ErrorDetails.Message -ForegroundColor Red
    } else {
        Write-Host "Verification request failed: $_" -ForegroundColor Red
    }
    exit 1
}

if ($response.verified) {
    Write-Host "VERIFIED: installations match." -ForegroundColor Green
    Write-Host "Version: $($response.left.packageVersion)" -ForegroundColor Green
} else {
    Write-Host "NOT VERIFIED: installations do not match." -ForegroundColor Red
    Write-Host $response.message -ForegroundColor Yellow
    exit 1
}
