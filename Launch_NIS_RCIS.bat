@echo off
title NIS Residence Card Issuance System (NIS-RCIS)
color 0A

echo ======================================================================
echo        NIGERIA IMMIGRATION SERVICE - RESIDENCE CARD PORTAL
echo           NIS Residence Card Issuance System (NIS-RCIS)
echo ======================================================================
echo.

:: Check if port 80 (Apache) is active
netstat -ano | findstr ":80 " >nul
if %errorlevel% equ 0 (
    echo [OK] Apache Web Server detected on Port 80.
    echo Launching: http://localhost/nis-rcis
    start http://localhost/nis-rcis
    goto END
)

:: Otherwise launch using PHP built-in server on port 8088
echo [INFO] Starting NIS-RCIS via Standalone PHP Server on Port 8088...
start "" "C:\xampp\php\php.exe" -S 127.0.0.1:8088 -t "C:\xampp\htdocs\nis-rcis"
timeout /t 2 >nul
echo Launching: http://127.0.0.1:8088
start http://127.0.0.1:8088

:END
echo.
echo ======================================================================
echo  SYSTEM TEST CREDENTIALS:
echo   - SuperAdmin:       admin     / Admin@2026!
echo   - Issuing Officer:  officer   / Officer@2026!
echo   - Approving Officer: approver / Approver@2026!
echo   - Border Inspector: inspector / Inspector@2026!
echo ======================================================================
echo.
echo NIS-RCIS is now running. Keep this window open or minimize it.
echo.
