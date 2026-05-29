# =============================================================
#  HRMS + Payroll Platform — Windows PowerShell Setup Script
#  Run from inside the hrms-payroll folder:
#    cd hrms-payroll
#    .\setup.ps1
# =============================================================

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  HRMS Payroll Platform — Setup Script   " -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# ── Step 1: Install Composer dependencies ────────────────────
Write-Host "[1/6] Installing Composer dependencies..." -ForegroundColor Yellow
composer install --ignore-platform-req=ext-gd --ignore-platform-req=ext-redis
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: composer install failed." -ForegroundColor Red
    Write-Host "Make sure Composer is installed: https://getcomposer.org/download/" -ForegroundColor Red
    exit 1
}
Write-Host "OK - Dependencies installed" -ForegroundColor Green

# ── Step 2: Create .env ──────────────────────────────────────
Write-Host ""
Write-Host "[2/6] Creating .env file..." -ForegroundColor Yellow
if (-Not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "OK - .env created from .env.example" -ForegroundColor Green
} else {
    Write-Host "SKIP - .env already exists" -ForegroundColor DarkGray
}

# ── Step 3: Generate app key ─────────────────────────────────
Write-Host ""
Write-Host "[3/6] Generating application key..." -ForegroundColor Yellow
php artisan key:generate
Write-Host "OK - App key generated" -ForegroundColor Green

# ── Step 4: Configure database ───────────────────────────────
Write-Host ""
Write-Host "[4/6] Database configuration..." -ForegroundColor Yellow
Write-Host ""
Write-Host "  Choose your database:" -ForegroundColor Cyan
Write-Host "  [1] PostgreSQL (default, recommended)"
Write-Host "  [2] MySQL (XAMPP)"
Write-Host "  [3] SQLite (quick test, no install needed)"
Write-Host ""
$dbChoice = Read-Host "  Enter choice (1/2/3)"

switch ($dbChoice) {
    "3" {
        Write-Host "  Configuring SQLite..." -ForegroundColor Yellow
        # Update .env for SQLite
        (Get-Content ".env") -replace "DB_CONNECTION=pgsql", "DB_CONNECTION=sqlite" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_HOST=.*", "" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PORT=.*", "" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_DATABASE=.*", "DB_DATABASE=database/hrms_payroll.sqlite" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_USERNAME=.*", "" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PASSWORD=.*", "" | Set-Content ".env"

        # Create SQLite file
        New-Item -ItemType File -Path "database/hrms_payroll.sqlite" -Force | Out-Null
        Write-Host "  OK - SQLite configured" -ForegroundColor Green
    }
    "2" {
        Write-Host "  Configuring MySQL (XAMPP)..." -ForegroundColor Yellow
        $dbName = Read-Host "  Database name [hrms_payroll]"
        if ([string]::IsNullOrEmpty($dbName)) { $dbName = "hrms_payroll" }
        $dbUser = Read-Host "  MySQL username [root]"
        if ([string]::IsNullOrEmpty($dbUser)) { $dbUser = "root" }
        $dbPass = Read-Host "  MySQL password (leave blank for none)"
        $dbPort = Read-Host "  MySQL port [3306]"
        if ([string]::IsNullOrEmpty($dbPort)) { $dbPort = "3306" }

        (Get-Content ".env") -replace "DB_CONNECTION=pgsql", "DB_CONNECTION=mysql" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PORT=5432", "DB_PORT=$dbPort" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_DATABASE=hrms_payroll", "DB_DATABASE=$dbName" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_USERNAME=postgres", "DB_USERNAME=$dbUser" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PASSWORD=secret", "DB_PASSWORD=$dbPass" | Set-Content ".env"

        Write-Host "  OK - MySQL configured" -ForegroundColor Green
        Write-Host "  NOTE: Create the '$dbName' database in phpMyAdmin first!" -ForegroundColor Yellow
    }
    default {
        Write-Host "  Configuring PostgreSQL..." -ForegroundColor Yellow
        $dbHost = Read-Host "  PG Host [127.0.0.1]"
        if ([string]::IsNullOrEmpty($dbHost)) { $dbHost = "127.0.0.1" }
        $dbPort = Read-Host "  PG Port [5432]"
        if ([string]::IsNullOrEmpty($dbPort)) { $dbPort = "5432" }
        $dbName = Read-Host "  Database name [hrms_payroll]"
        if ([string]::IsNullOrEmpty($dbName)) { $dbName = "hrms_payroll" }
        $dbUser = Read-Host "  Username [postgres]"
        if ([string]::IsNullOrEmpty($dbUser)) { $dbUser = "postgres" }
        $dbPass = Read-Host "  Password"

        (Get-Content ".env") -replace "DB_HOST=127.0.0.1", "DB_HOST=$dbHost" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PORT=5432", "DB_PORT=$dbPort" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_DATABASE=hrms_payroll", "DB_DATABASE=$dbName" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_USERNAME=postgres", "DB_USERNAME=$dbUser" | Set-Content ".env"
        (Get-Content ".env") -replace "DB_PASSWORD=secret", "DB_PASSWORD=$dbPass" | Set-Content ".env"

        Write-Host "  OK - PostgreSQL configured" -ForegroundColor Green
        Write-Host "  NOTE: Create the '$dbName' database first!" -ForegroundColor Yellow
    }
}

# Disable Redis (use file cache instead) for local dev
(Get-Content ".env") -replace "CACHE_STORE=redis", "CACHE_STORE=file" | Set-Content ".env"
(Get-Content ".env") -replace "SESSION_DRIVER=redis", "SESSION_DRIVER=file" | Set-Content ".env"
(Get-Content ".env") -replace "QUEUE_CONNECTION=redis", "QUEUE_CONNECTION=sync" | Set-Content ".env"
Write-Host "  NOTE: Cache/Session/Queue set to file/sync (no Redis needed for local dev)" -ForegroundColor DarkGray

# ── Step 5: Run migrations ───────────────────────────────────
Write-Host ""
Write-Host "[5/6] Running database migrations..." -ForegroundColor Yellow
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Migrations failed. Check your database connection in .env" -ForegroundColor Red
    exit 1
}
Write-Host "OK - All 8 migrations completed (60+ tables created)" -ForegroundColor Green

# ── Step 6: Seed data ────────────────────────────────────────
Write-Host ""
Write-Host "[6/6] Seeding demo data..." -ForegroundColor Yellow
php artisan db:seed
if ($LASTEXITCODE -ne 0) {
    Write-Host "WARNING: Seeding failed. You can retry: php artisan db:seed" -ForegroundColor Yellow
} else {
    Write-Host "OK - Demo data seeded" -ForegroundColor Green
}

# ── Storage link ─────────────────────────────────────────────
Write-Host ""
Write-Host "Creating storage link..." -ForegroundColor Yellow
php artisan storage:link
Write-Host "OK" -ForegroundColor Green

# ── Done! ────────────────────────────────────────────────────
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "   Setup Complete! Starting server...     " -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  API Base URL : http://localhost:8000/api" -ForegroundColor Cyan
Write-Host "  Login Email  : admin@hrms.local" -ForegroundColor Cyan
Write-Host "  Password     : Admin@123" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Press Ctrl+C to stop the server" -ForegroundColor DarkGray
Write-Host ""

php artisan serve
