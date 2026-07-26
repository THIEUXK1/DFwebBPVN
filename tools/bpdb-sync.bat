@echo off
set PATH=C:\web\tools\php;%PATH%
cd /d C:\DFwebBPVN\backend
php artisan colorservice:bpdb-sync >> C:\DFwebBPVN\tools\logs\bpdb-sync.log 2>&1
