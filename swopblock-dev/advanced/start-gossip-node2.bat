@echo off
set "PKG=%~dp0.."
cd /d "%PKG%"
set "SERVER=%PKG%\client\server"
if not exist "%SERVER%\peer-gossip.php" (
    echo peer-gossip.php not found under client\server
    pause
    exit /b 1
)
echo Gossip for NODE 2 — keep node 2 running on 8082
echo.
call "%~dp0start-gossip.bat" "%SERVER%\config-node2.php"
