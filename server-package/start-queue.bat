@echo off
REM Process the priority message queue in a loop.
setlocal
cd/d "%~dp0"
set "SERVER_DIR=%~dp0server"
if not exist "%SERVER_DIR%\process-queue.php" (
	echo Missing %SERVER_DIR%\process-queue.php 
	exit /b 1
)

set "PHP="
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" set "PHP=%LOCALAPPDATA%\Swopblock\php\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP (
	where php >nul 2>&1 && set "PHP=php"
)
if not defined PHP (
	echo PHP not found. Use Swopblock PHP or add php to PATH.
	exit /b 1
)
echo Priority queue processor - %SERVER_DIR%
echo Press Ctrl+C to stop. 
:loop
"%PHP%" "%SERVER_DIR%\process-queue.php"
timeout /t 3 /nobreak >nul 
goto loop 
