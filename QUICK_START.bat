@echo off
cls
color 0A
echo.
echo ========================================================
echo           SCHOOL MANAGEMENT SYSTEM
echo                 QUICK START
echo ========================================================
echo.
echo Starting services...
echo.

REM Start Apache
echo [1/3] Starting Apache...
net start Apache2.4 >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo       [OK] Apache started
) else (
    echo       [INFO] Apache may already be running or needs XAMPP Control
)

REM Start MySQL
echo [2/3] Starting MySQL...
net start MySQL >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo       [OK] MySQL started
) else (
    echo       [INFO] MySQL may already be running or needs XAMPP Control
)

REM Wait a moment
timeout /t 2 /nobreak >nul

echo [3/3] Opening application...
echo.

REM Open XAMPP Control Panel
start "" "C:\xampp\xampp-control.exe"

REM Wait 2 seconds
timeout /t 2 /nobreak >nul

REM Open application in browser
start "" "http://localhost/School-Management-PhpVersion/frontend/index.html"

echo.
echo ========================================================
echo                    READY!
echo ========================================================
echo.
echo [*] XAMPP Control Panel opened
echo [*] Application opening in browser...
echo.
echo IMPORTANT: In XAMPP Control Panel:
echo   1. Click START for Apache (if not green)
echo   2. Click START for MySQL (if not green)
echo.
echo If you see errors, close this and use XAMPP Control Panel
echo to start services manually.
echo.
echo ========================================================
echo.
echo Access Points:
echo   Application: http://localhost/School-Management-PhpVersion/frontend/index.html
echo   phpMyAdmin:  http://localhost/phpmyadmin
echo   Login:       admin / admin123
echo.
echo ========================================================
echo.
echo Press any key to exit...
pause >nul
