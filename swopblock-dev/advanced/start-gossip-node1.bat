@echo off
set "PKG=%~dp0.."
cd /d "%PKG%"
set "SERVER=%PKG%\client\server"
if not exist "%SERVER%\peer-gossip.php" (
    echo peer-gossip.php not found under client\server
    pause
    exit /b 1
)
echo Gossip for NODE 1 — keep start-client.bat running on 8080
echo.
call "%~dp0start-gossip.bat" "%SERVER%\config.php"
