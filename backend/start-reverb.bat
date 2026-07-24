@echo off
cd /d "c:\laragon\www\DF\DFwed\backend"
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan reverb:start >> reverb.log 2>&1
