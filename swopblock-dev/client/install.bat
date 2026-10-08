@echo off
setlocal
cd /d "%~dp0"
title Swopblock Node Installer

echo.
echo ========================================
echo    Swopblock Node Installer
echo ========================================
echo.
echo Double-click is enough - no Powershell typing needed.
echo If PHP is missing, this will download the official PHP
echo binary, verify its hash, and install it for this node.
echo.
where pwsh >nul 2>&1
if %errorlevel%==0 (
	pwsh -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
	if %errorlevel%==0 goto :success
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
if %errorlevel%==0 goto :success


echo.
echo ========================================
echo    INSTALL FAILED
echo ========================================
echo.
echo Common fixes:
echo 	1. Stay connected to the Internet (needed to download PHP)
echo 	2. When asked about PHP, press Enter or type Y
echo	3. Unzip the FULL package so install.ps1 and server\ exist
echo    4. Right-click install.bat - Run as administrator if blocked
echo.
pause 
exit /b 1 


:success
echo.
echo ========================================
echo   NEXT STEP
echo ========================================
echo.
echo 1. Open: C:\Users\%USERNAME\swopblock-marketplace
echo 2. Double-click: start-server.bat
echo 3. Browser: http://localhost:8080
echo.
pause
exit /b 0