@echo off
REM Swopblock development tools installer (SQLite + portable PHP)
cd /d "%~dp0"
echo.
echo Starting Swopblock Development Tools installer...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-dev.ps1"
if errorlevel 1 (
    echo.
    echo Install failed.
    pause
    exit /b 1
)
echo.
pause
