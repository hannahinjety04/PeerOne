$ErrorActionPreference = "Stop"

$installDir = Join-Path $HOME "CryptoMarketplaceServer"
$startScript = Join-Path $installDir "start-server.ps1"

if (Test-Path $startScript) {
    & pwsh -NoProfile -ExecutionPolicy Bypass -File $startScript
    exit $LASTEXITCODE
}

Write-Host "Node not installed yet." -ForegroundColor Red
Write-Host "Run install.bat first." -ForegroundColor Yellow
exit 1
