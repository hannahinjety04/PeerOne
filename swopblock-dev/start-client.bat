@echo off
REM Starts local CLIENT node from this same package folder
cd /d "%~dp0"
echo.
echo Starting local CLIENT on http://127.0.0.1:8080
echo.
echo Keep this window open. Central should already be running on 8081.
echo Then run register-client.bat so it appears on the admin dashboard.
echo.
echo Starting background peer-gossip for this node...
if exist "%~dp0advanced\start-gossip-node1.bat" (
    start "Swopblock Gossip Node 1" cmd /c "%~dp0advanced\start-gossip-node1.bat"
)
echo.

if not exist "client\server\public\index.php" (
    echo ERROR: client\server\public missing. Unzip the full package.
    pause
    exit /b 1
)

REM -d opcache.enable=0 avoids Windows ASLR fatal with portable PHP Opcache
if exist "runtime\php\php.exe" (
    "runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8080 -t "%~dp0client\server\public"
    goto :done
)
if exist "%LOCALAPPDATA%\Swopblock\php\php.exe" (
    "%LOCALAPPDATA%\Swopblock\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8080 -t "%~dp0client\server\public"
    goto :done
)
if exist "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" (
    "%USERPROFILE%\CryptoMarketplaceServer\runtime\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8080 -t "%~dp0client\server\public"
    goto :done
)
where php >nul 2>&1 && php -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8080 -t "%~dp0client\server\public" && goto :done
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:8080 -t "%~dp0client\server\public"
    goto :done
)

echo PHP not found. Run install-dev.bat first.
pause
exit /b 1

:done
pause
