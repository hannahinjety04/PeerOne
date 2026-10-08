@echo off
REM Run one gossip round every 60 seconds for this node
cd /d "%~dp0"

set "ROOT=%~dp0"
if exist "%ROOT%server\peer-gossip.php" (
    set "GOSDIR=%ROOT%server"
) else if exist "%ROOT%peer-gossip.php" (
    set "GOSDIR=%ROOT%"
) else (
    echo peer-gossip.php not found. Run from server-package or server folder.
    pause
    exit /b 1
)

if not "%~1"=="" set "SWOPBLOCK_CONFIG=%~1"

echo Starting peer gossip loop (Ctrl+C to stop)
echo Config: %SWOPBLOCK_CONFIG%
echo.

:loop
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" (
    "%LOCALAPPDATA%\Swopblock\php\php.exe" "%GOSDIR%\peer-gossip.php"
    goto :wait
)
if exist "%ROOT%runtime\php\php.exe" (
    "%ROOT%runtime\php\php.exe" "%GOSDIR%\peer-gossip.php"
    goto :wait
)
if exist "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" (
    "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" "%GOSDIR%\peer-gossip.php"
    goto :wait
)
where php >nul 2>&1 && php "%GOSDIR%\peer-gossip.php" && goto :wait
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" "%GOSDIR%\peer-gossip.php"
    goto :wait
)
echo PHP not found.
pause
exit /b 1

:wait
timeout /t 60 /nobreak >nul
goto :loop
