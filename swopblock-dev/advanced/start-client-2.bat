@echo off
REM Second local client node on 127.0.0.1:8082 (genesis = node on 8080)
set "PKG=%~dp0.."
cd /d "%PKG%"

set "SERVER=%PKG%\client\server"
if not exist "%SERVER%\public\index.php" (
    echo ERROR: client\server\public not found. Run install-dev.bat first.
    pause
    exit /b 1
)

set "SWOPBLOCK_CONFIG=%SERVER%\config-node2.php"
if not exist "%SWOPBLOCK_CONFIG%" (
    echo Missing config-node2.php
    pause
    exit /b 1
)

set "PHP="
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" set "PHP=%LOCALAPPDATA%\Swopblock\php\php.exe"
if not defined PHP if exist "%PKG%\runtime\php\php.exe" set "PHP=%PKG%\runtime\php\php.exe"
if not defined PHP if exist "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" set "PHP=%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe"
if not defined PHP (
    where php >nul 2>&1 && for /f "delims=" %%i in ('where php') do set "PHP=%%i" & goto :havephp
)
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
:havephp
if not defined PHP (
    echo PHP not found. Run install-dev.bat first.
    pause
    exit /b 1
)

echo Preparing node-2 database...
"%PHP%" "%SERVER%\setup.php"
if errorlevel 1 (
    echo setup failed for node-2
    pause
    exit /b 1
)

echo.
echo Starting CLIENT NODE 2 on http://127.0.0.1:8082
echo Genesis peer: http://127.0.0.1:8080/api.php
echo.
"%PHP%" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8082 -t "%SERVER%\public"
pause
