@echo off
setlocal

set "INSTALLDIR=%USERPROFILE%\CryptoMarketplaceServer"

if exist "%INSTALLDIR%\start-server.bat" (
    call "%INSTALLDIR%\start-server.bat"
    exit /b %errorlevel%
)

if exist "%INSTALLDIR%\start-server.ps1" (
    cd /d "%INSTALLDIR%"
    where pwsh >nul 2>&1 && pwsh -NoProfile -ExecutionPolicy Bypass -File "%INSTALLDIR%\start-server.ps1" && goto :done
    powershell -NoProfile -ExecutionPolicy Bypass -File "%INSTALLDIR%\start-server.ps1"
    goto :done
)

echo.
echo Node not installed yet.
echo 1. Run install.bat first
echo 2. Then run start-server.bat again
echo.
pause
exit /b 1

:done
pause
