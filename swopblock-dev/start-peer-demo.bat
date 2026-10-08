@echo off
REM One-click peer gossip demo (keeps the original 4-file boss flow unchanged)
REM Requires: start-client.bat already running on 8080, with an order created.
cd /d "%~dp0"

echo.
echo Peer demo helper
echo ================
echo 1. Make sure start-client.bat is already running (http://127.0.0.1:8080)
echo 2. Create at least one order on node 1
echo 3. This will open node 2 + gossip automatically
echo.
pause

start "Swopblock Client Node 2" cmd /c "%~dp0advanced\start-client-2.bat"
timeout /t 3 /nobreak >nul
start "Swopblock Gossip Node 2" cmd /c "%~dp0advanced\start-gossip-node2.bat"

echo.
echo Opened:
echo   - Node 2 server  -> http://127.0.0.1:8082
echo   - Gossip for node 2 (pulls missing info from node 1)
echo.
echo Wait up to ~1 minute, then refresh http://127.0.0.1:8082
echo.
pause
