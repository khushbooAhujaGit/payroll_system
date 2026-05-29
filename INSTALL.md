# Windows Installation Guide

## Prerequisites

### 1. PHP 8.1+ (XAMPP recommended)
Download: https://www.apachefriends.org/download.html
- Install XAMPP with PHP 8.1 or 8.2
- Enable these extensions in `C:\xampp\php\php.ini`:
  ```
  extension=pdo_pgsql     (if using PostgreSQL)
  extension=pdo_mysql     (if using XAMPP MySQL) ← already enabled
  extension=pdo_sqlite    (if using SQLite)       ← already enabled
  extension=gd            ← remove the ; at the start
  extension=fileinfo      ← remove the ; at the start
  extension=mbstring      ← remove the ; at the start
  extension=openssl       ← remove the ; at the start
  extension=zip           ← remove the ; at the start
  ```

### 2. Composer
Download: https://getcomposer.org/download/
Run the Windows installer.

---

## Quickest Setup (SQLite - No database install needed)

```powershell
# In PowerShell (NOT ISE - use regular PowerShell or Windows Terminal):
cd hrms-payroll

# Install packages
composer install --ignore-platform-req=ext-gd --ignore-platform-req=ext-redis

# Create .env
Copy-Item .env.example .env

# Set SQLite in .env - open with Notepad:
notepad .env
```

**In .env, change these lines:**
```
DB_CONNECTION=sqlite
DB_DATABASE=database/hrms_payroll.sqlite
# Comment out or delete: DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

```powershell
# Create the SQLite file
New-Item -ItemType File -Path "database\hrms_payroll.sqlite" -Force

# Generate key, migrate, seed
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan storage:link
php artisan serve
```

---

## Setup with XAMPP MySQL

1. Start XAMPP → Start **Apache** and **MySQL**
2. Open **phpMyAdmin** → http://localhost/phpmyadmin
3. Create a new database named `hrms_payroll`

```powershell
cd hrms-payroll
composer install --ignore-platform-req=ext-gd --ignore-platform-req=ext-redis
Copy-Item .env.example .env
notepad .env
```

**Set in .env:**
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_payroll
DB_USERNAME=root
DB_PASSWORD=          # leave blank if no password
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

```powershell
php artisan key:generate
php artisan migrate --force
php artisan db:seed
php artisan storage:link
php artisan serve
```

---

## Automated Setup (run the script)

```powershell
# Allow script execution (run once as Administrator):
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# Then run setup:
cd hrms-payroll
.\setup.ps1
```

Or use the batch file:
```cmd
cd hrms-payroll
setup.bat
```

---

## After Setup

**API is live at:** http://localhost:8000/api

### Test with curl (PowerShell):
```powershell
# Login
$body = '{"email":"admin@hrms.local","password":"Admin@123"}'
$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/login" -Method POST -Body $body -ContentType "application/json"
$token = $response.token
Write-Host "Token: $token"

# Get employees
Invoke-RestMethod -Uri "http://localhost:8000/api/employees?entity_id=1" -Headers @{Authorization="Bearer $token"} | ConvertTo-Json
```

### Test with Postman:
1. Import: `POST http://localhost:8000/api/auth/login`
   Body JSON: `{"email":"admin@hrms.local","password":"Admin@123"}`
2. Copy the `token` from response
3. Add header: `Authorization: Bearer <your-token>`
4. Hit any endpoint: `GET http://localhost:8000/api/employees?entity_id=1`

---

## Troubleshooting

| Error | Fix |
|---|---|
| `ext-gd missing` | Run with `--ignore-platform-req=ext-gd` OR enable `extension=gd` in php.ini |
| `artisan not found` | Make sure you're inside the `hrms-payroll` folder |
| `&&` not valid | Use separate commands or `;` in PowerShell |
| `SQLSTATE connection refused` | Check DB credentials in .env and ensure MySQL/PG is running |
| `No application encryption key` | Run `php artisan key:generate` |
| `Class not found` | Run `composer dump-autoload` |
| `Target class not found` | Run `php artisan config:clear` then `php artisan cache:clear` |

---

## Default Credentials

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@hrms.local | Admin@123 |

---

## Key API Endpoints

```
POST  /api/auth/login
GET   /api/auth/me
GET   /api/employees?entity_id=1
POST  /api/employees
GET   /api/attendance/today?entity_id=1
POST  /api/attendance/mark
GET   /api/leave/balances/me
POST  /api/leave/apply
GET   /api/payroll/periods?entity_id=1
POST  /api/payroll/runs/{id}/calculate
GET   /api/dashboards/manager
GET   /api/dashboards/hr-analytics
GET   /api/statutory/compliance-dashboard?entity_id=1
```
