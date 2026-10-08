@echo off
REM Registers this local client with local central (8081)
cd /d "%~dp0"
echo.
echo Registering local client with http://127.0.0.1:8081 ...
echo Make sure start-central.bat is already running.
echo.

if exist "register-with-central.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-with-central.ps1"
) else if exist "register-dev.ps1" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-dev.ps1"
) else (
    echo ERROR: register script missing. Run install-dev.bat first.
    pause
    exit /b 1
)

if errorlevel 1 (
    echo.
    echo Registration failed. Is start-central.bat running?
    echo Use start-central.bat from this same folder (binds to 127.0.0.1).
    pause
    exit /b 1
)

echo.
echo Done. Open http://127.0.0.1:8081/admin/login.php and refresh.
echo Email: owner@central.local
echo Password: changeme123
echo.
pause
