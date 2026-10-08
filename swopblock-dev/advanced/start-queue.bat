@echo off
REM Process queue for the main client node (8080).
REM advanced\ is one level deeper, so server is ..\client\server
setlocal
cd /d "%~dp0"
set "SERVER_DIR=%~dp0..\client\server"
if not exist "%SERVER_DIR%\process-queue.php" (
  echo Missing "%SERVER_DIR%\process-queue.php"
  pause
  exit /b 1
)
call "%~dp0..\client\start-queue.bat"
