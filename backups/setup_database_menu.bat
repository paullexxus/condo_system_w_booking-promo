@echo off
:: BookIT Database Population Batch Script
:: This script helps populate your BookIT database with test data

title BookIT - Database Population Tool
color 0A
cls

echo ============================================================
echo.
echo          ^<^<^< BOOKIT DATABASE POPULATION TOOL ^>^>^>
echo.
echo ============================================================
echo.
echo This tool will populate your BookIT database with test data:
echo   - 1 Admin user
echo   - 3 Host users (property managers)
echo   - 5 Renter users (customers)
echo   - 3 Branches with locations
echo   - 12 Rental Units
echo   - 6 Amenities
echo   - 5 Sample Reservations
echo.
echo ============================================================
echo.

echo Choose an option:
echo.
echo [1] Start Database Population
echo [2] Verify Data (View Dashboard)
echo [3] View Setup Guide
echo [4] Clear All Data
echo [5] Exit
echo.

set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" (
    cls
    echo Starting database population...
    echo Please wait while test data is being created...
    echo.
    start http://localhost/BookIT/populate_test_data.php
    timeout /t 3
    cls
    echo.
    echo ============================================================
    echo       DATABASE POPULATION STARTED
    echo ============================================================
    echo.
    echo A browser window should have opened with the population script.
    echo Please wait for it to complete (usually takes 5-10 seconds).
    echo.
    echo Once complete, you can:
    echo   1. Verify the data was populated correctly
    echo   2. View the verification dashboard
    echo   3. Start testing with the provided credentials
    echo.
    echo Test Credentials:
    echo   Admin:  admin@bookit.com / Admin@123456
    echo   Host:   maria.garcia@bookit.com / Host@12345
    echo   Renter: michael.johnson@email.com / Renter@123
    echo.
    echo To proceed with verification, press any key...
    pause >nul
    goto menu
)

if "%choice%"=="2" (
    cls
    echo Opening verification dashboard...
    start http://localhost/BookIT/verify_data.php
    echo.
    echo Verification dashboard opened in your browser!
    echo You should see real-time database statistics and booking details.
    echo.
    echo Press any key to return to menu...
    pause >nul
    goto menu
)

if "%choice%"=="3" (
    cls
    echo Opening setup guide...
    start http://localhost/BookIT/DATABASE_SETUP_GUIDE.md
    echo.
    echo Setup guide opened! Review all the important information.
    echo.
    echo Press any key to return to menu...
    pause >nul
    goto menu
)

if "%choice%"=="4" (
    cls
    echo.
    echo WARNING: This will delete ALL data from the database!
    echo.
    set /p confirm="Are you sure? (yes/no): "
    if /i "%confirm%"=="yes" (
        echo Clearing database...
        start http://localhost/BookIT/populate_test_data.php?clear=1
        echo.
        echo Database cleared! Running population script again...
        timeout /t 2
        goto menu
    ) else (
        echo Operation cancelled.
        timeout /t 2
        goto menu
    )
)

if "%choice%"=="5" (
    cls
    echo Thank you for using BookIT!
    echo.
    timeout /t 2
    exit /b
)

echo Invalid choice. Please try again.
timeout /t 2
goto menu

:menu
cls
echo ============================================================
echo          BOOKIT DATABASE POPULATION TOOL - MENU
echo ============================================================
echo.
echo Choose an option:
echo.
echo [1] Start Database Population
echo [2] Verify Data (View Dashboard)
echo [3] View Setup Guide
echo [4] Clear All Data
echo [5] Exit
echo.
set /p choice="Enter your choice (1-5): "

if "%choice%"=="1" goto start
if "%choice%"=="2" goto verify
if "%choice%"=="3" goto guide
if "%choice%"=="4" goto clear
if "%choice%"=="5" goto end

echo Invalid choice. Please try again.
timeout /t 2
goto menu

:start
cls
echo Starting database population...
echo Please wait...
start http://localhost/BookIT/populate_test_data.php
timeout /t 3
goto menu

:verify
cls
echo Opening verification dashboard...
start http://localhost/BookIT/verify_data.php
timeout /t 2
goto menu

:guide
cls
echo Opening setup guide...
start http://localhost/BookIT/DATABASE_SETUP_GUIDE.md
timeout /t 2
goto menu

:clear
cls
echo Clearing database...
start http://localhost/BookIT/populate_test_data.php?clear=1
timeout /t 3
goto menu

:end
cls
echo Thank you for using BookIT!
exit /b
