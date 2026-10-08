#Requires -Version 5.1
$ErrorActionPreference = 'Stop'

$InstallRoot = $PSScriptRoot
$ClientDir = Join-Path $InstallRoot 'client\server'
$endpointFile = Join-Path $InstallRoot 'central-endpoint.txt'

# Must match start-central.bat bind address (127.0.0.1, not localhost/::1)
$defaultUrl = 'http://127.0.0.1:8081/api/register-node.php'
if (-not (Test-Path $endpointFile)) {
    Set-Content -Path $endpointFile -Value $defaultUrl -Encoding ASCII
}

$url = (Get-Content $endpointFile -Raw).Trim()
# Old zips may say localhost; force IPv4 so it matches php -S 127.0.0.1:8081
if ($url -match '://localhost([:/]|$)') {
    $url = $url.Replace('://localhost', '://127.0.0.1')
}
$label = $env:COMPUTERNAME + ' Dev Node'
$nodeJson = Join-Path $ClientDir 'node.json'

$files = @('schema.sql', 'setup.php', 'public\index.php', 'public\api.php')
$sha = [System.Security.Cryptography.SHA256]::Create()
$ms = New-Object System.IO.MemoryStream
foreach ($file in $files) {
    $path = Join-Path $ClientDir $file
    if (Test-Path $path) {
        $bytes = [System.IO.File]::ReadAllBytes($path)
        $ms.Write($bytes, 0, $bytes.Length)
    }
}
$fp = ([BitConverter]::ToString($sha.ComputeHash($ms.ToArray()))).Replace('-', '').ToLower()

$body = @{
    installLabel = $label
    packageVersion = '1.0.0-dev'
    packageFingerprint = $fp
} | ConvertTo-Json

Write-Host "Registering with $url ..." -ForegroundColor Cyan
Write-Host 'Start central first (start-central.bat) if this fails.' -ForegroundColor DarkGray
$response = Invoke-RestMethod -Uri $url -Method Post -Body $body -ContentType 'application/json'
if (-not $response.apiKey) { throw 'No apiKey returned' }

$nodeConfig = @{
    installID = $response.installID
    apiKey = $response.apiKey
    installStatus = $response.installStatus
    centralRegister = $url
    centralCheckin = $url.Replace('register-node.php', 'checkin.php')
} | ConvertTo-Json
Set-Content -Path $nodeJson -Value $nodeConfig -Encoding ASCII
Write-Host "Registered installID=$($response.installID). Saved $nodeJson" -ForegroundColor Green
