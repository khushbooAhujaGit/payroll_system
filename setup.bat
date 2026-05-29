@echo off
echo.
echo =============================================
echo   HRMS Payroll Platform - Windows Setup
echo =============================================
echo.

echo [1/5] Installing Composer packages (ignoring ext-gd)...
composer install --ignore-platform-req=ext-gd --ignore-platform-req=ext-redis
if %errorlevel% neq 0 (
    echo ERROR: Composer install failed. Make sure Composer is installed.
    echo Download: https://getcomposer.org/download/
    pause
    exit /b 1
)

echo.
echo [2/5] Setting up .env file...
if not exist ".env" (
    copy ".env.example" ".env"
    echo Created .env from .env.example
) else (
    echo .env already exists, skipping
)

echo.
echo [3/5] Generating application key...
php artisan key:generate

echo.
echo [4/5] Configure your database in .env then press any key to run migrations...
echo.
echo OPEN .env AND SET:
echo   DB_CONNECTION=mysql    (for XAMPP/MySQL)
echo   DB_HOST=127.0.0.1
echo   DB_PORT=3306
echo   DB_DATABASE=hrms_payroll
echo   DB_USERNAME=root
echo   DB_PASSWORD=
echo.
echo ALSO SET (for local dev without Redis):
echo   CACHE_STORE=file
echo   SESSION_DRIVER=file
echo   QUEUE_CONNECTION=sync
echo.
pause

echo [5/5] Running migrations and seeders...
php artisan migrate --force
php artisan db:seed
php artisan storage:link

echo.
echo =============================================
echo   Setup complete!
echo   URL:      http://localhost:8000/api
echo   Email:    admin@hrms.local
echo   Password: Admin@123
echo =============================================
echo.
php artisan serve
pause
