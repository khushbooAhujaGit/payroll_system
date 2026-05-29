FINAL PATCH - Instructions
==========================
1. Copy all files into hrms-payroll folder (overwrite)
2. In PowerShell (inside hrms-payroll):
   
   composer update --ignore-platform-req=ext-gd
   php artisan config:clear
   php artisan cache:clear
   php artisan serve

3. In another PowerShell window:
   $body = '{"email":"admin@hrms.local","password":"Admin@123"}'
   $r = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/login" -Method POST -Body $body -ContentType "application/json"
   $r.token
