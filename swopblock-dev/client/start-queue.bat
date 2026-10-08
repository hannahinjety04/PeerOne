@echo off
REM Process the client node priority queue in a loop (node on 8080 = config.php).
setlocal
cd /d "%~dp0"

REM This bat lives in client\ so server code is client\server\
set "SERVER_DIR=%~dp0server"
if not exist "%SERVER_DIR%\process-queue.php" (
  echo Missing "%SERVER_DIR%\process-queue.php"
  echo Copy process-queue.php into client\server first, or use the updated package.
  pause
  exit /b 1
)

set "PHP="
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" set "PHP=%LOCALAPPDATA%\Swopblock\php\php.exe"
if not defined PHP if exist "%~dp0..\runtime\php\php.exe" set "PHP=%~dp0..\runtime\php\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP (
  where php >nul 2>&1 && set "PHP=php"
)
if not defined PHP (
  echo PHP not found. Run install-dev.bat first.
  pause
  exit /b 1
)

REM Always use THIS package's config/db (same one start-client.bat uses)
set "SWOPBLOCK_CONFIG=%SERVER_DIR%\config.php"

echo Priority queue processor
echo   PHP:    %PHP%
echo   Server: %SERVER_DIR%
echo   Config: %SWOPBLOCK_CONFIG%
echo Press Ctrl+C to stop.
echo.
:loop
"%PHP%" -d opcache.enable=0 -d opcache.enable_cli=0 "%SERVER_DIR%\process-queue.php"
timeout /t 3 /nobreak >nul
goto loop
