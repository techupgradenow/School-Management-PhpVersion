@echo off
echo ================================================
echo  School Management System - Status Check
echo ================================================
echo.

echo Checking services...
echo.

REM Check if Apache is running
netstat -ano | findstr ":80" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] Apache is running on port 80
) else (
    echo [WARN] Apache is NOT running
    echo        Please start Apache in XAMPP Control Panel
)

echo.

REM Check if MySQL is running
netstat -ano | findstr ":3306" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] MySQL is running on port 3306
) else (
    echo [WARN] MySQL is NOT running
    echo        Please start MySQL in XAMPP Control Panel
)

echo.

REM Check if project folder exists in htdocs
if exist "C:\xampp\htdocs\School-Management-PhpVersion" (
    echo [OK] Project is linked to XAMPP htdocs
) else (
    echo [WARN] Project link not found in htdocs
)

echo.
echo ================================================
echo  Quick Access Links:
echo ================================================
echo.
echo Application:  http://localhost/School-Management-PhpVersion/frontend/index.html
echo phpMyAdmin:   http://localhost/phpmyadmin
echo API Test:     http://localhost/School-Management-PhpVersion/backend/api/students.php?action=list
echo.
echo ================================================
echo.

pause
