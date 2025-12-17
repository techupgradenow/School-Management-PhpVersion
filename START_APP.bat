@echo off
echo ================================================
echo  School Management System - Startup Script
echo ================================================
echo.

REM Check if XAMPP is installed
if not exist "C:\xampp\xampp-control.exe" (
    echo [ERROR] XAMPP not found at C:\xampp
    echo Please install XAMPP from: https://www.apachefriends.org/
    pause
    exit /b 1
)

echo [INFO] XAMPP found!
echo.

REM Start XAMPP Control Panel
echo [INFO] Starting XAMPP Control Panel...
start "" "C:\xampp\xampp-control.exe"

echo.
echo ================================================
echo  NEXT STEPS:
echo ================================================
echo.
echo 1. In XAMPP Control Panel:
echo    - Click START for Apache
echo    - Click START for MySQL
echo.
echo 2. Create Database:
echo    - Open: http://localhost/phpmyadmin
echo    - Create database: edumanage_pro
echo    - Import: database/schema.sql
echo.
echo 3. Open Application:
echo    - URL: http://localhost/School-Management-PhpVersion/frontend/index.html
echo    - Login: admin / admin123
echo.
echo ================================================
echo.

REM Wait 5 seconds
timeout /t 5

REM Open browser after delay
echo [INFO] Opening application in browser...
start "" "http://localhost/School-Management-PhpVersion/frontend/index.html"

echo.
echo [SUCCESS] Application started!
echo.
echo If login page appears, you're ready to go!
echo If not, please follow the steps above.
echo.
pause
