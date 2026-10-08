@echo off
REM Run one gossip round every 60 seconds
set "PKG=%~dp0.."
cd /d "%PKG%"

set "GOSDIR=%PKG%\client\server"
if not exist "%GOSDIR%\peer-gossip.php" set "GOSDIR=%PKG%\server"
if not exist "%GOSDIR%\peer-gossip.php" (
    echo peer-gossip.php not found.
    pause
    exit /b 1
)

if not "%~1"=="" set "SWOPBLOCK_CONFIG=%~1"

echo Starting peer gossip loop (Ctrl+C to stop)
if defined SWOPBLOCK_CONFIG echo Config: %SWOPBLOCK_CONFIG%
echo.

:loop
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" (
    "%LOCALAPPDATA%\Swopblock\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 "%GOSDIR%\peer-gossip.php"
    goto :wait
)
if exist "%PKG%\runtime\php\php.exe" (
    "%PKG%\runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 "%GOSDIR%\peer-gossip.php"
    goto :wait
)
if exist "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" (
    "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 "%GOSDIR%\peer-gossip.php"
    goto :wait
)
where php >nul 2>&1 && php -d opcache.enable=0 -d opcache.enable_cli=0 "%GOSDIR%\peer-gossip.php" && goto :wait
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 "%GOSDIR%\peer-gossip.php"
    goto :wait
)
echo PHP not found.
pause
exit /b 1

:wait
timeout /t 60 /nobreak >nul
goto :loop
