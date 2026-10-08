$ErrorActionPreference = "Stop"

$installed = Join-Path (Join-Path $HOME "CryptoMarketplaceServer") "start-server.ps1"
if (Test-Path $installed) {
    & pwsh -NoProfile -ExecutionPolicy Bypass -File $installed
    exit $LASTEXITCODE
}

$local = Join-Path $PSScriptRoot "start-server.ps1"
if (Test-Path $local) {
    & pwsh -NoProfile -ExecutionPolicy Bypass -File $local
    exit $LASTEXITCODE
}

Write-Host "Run install.ps1 first." -ForegroundColor Red
exit 1
