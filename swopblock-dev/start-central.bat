@echo off
REM Starts local CENTRAL from this same package folder
cd /d "%~dp0"
echo.
echo Starting local CENTRAL on http://127.0.0.1:8081
echo Admin: http://127.0.0.1:8081/admin/login.php
echo   owner@central.local / changeme123
echo.
echo Keep this window open. Then run start-client.bat and register-client.bat.
echo.

if not exist "central\index.php" (
    echo ERROR: central folder missing. Unzip the full package.
    pause
    exit /b 1
)

REM -d opcache.enable=0 avoids Windows ASLR fatal with portable PHP Opcache
if exist "runtime\php\php.exe" (
    "runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8081 -t "%~dp0central"
    goto :done
)
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" (
    "%LOCALAPPDATA%\Swopblock\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8081 -t "%~dp0central"
    goto :done
)
if exist "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" (
    "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8081 -t "%~dp0central"
    goto :done
)
where php >nul 2>&1 && php -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8081 -t "%~dp0central" && goto :done
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8081 -t "%~dp0central"
    goto :done
)

echo PHP not found. Run install-dev.bat first.
pause
exit /b 1

:done
pause
