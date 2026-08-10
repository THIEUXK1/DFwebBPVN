@echo off
REM ---------------------------------------------------------------------------
REM Dong bo gio ket thuc nhuom THAT cua me (may VD) tu VN-MES ve
REM app.mes_batch_completions. Chay dinh ky qua Scheduled Task DFWeb-MesSync
REM (dang ky bang tools\register-mes-sync-task.ps1).
REM
REM Credential MES KHONG nam trong repo va KHONG nam trong .env cua app.
REM Chung duoc nap tu file rieng ngoai repo (dat canh .pgpass):
REM     C:\web\tools\mes-batch-creds.bat
REM Xem mau tai tools\mes-batch-creds.example.bat. Neu file do khong ton tai,
REM lenh se chay voi cau hinh mac dinh trong config\mes.php (co the thieu quyen).
REM ---------------------------------------------------------------------------
set PATH=C:\web\tools\php;%PATH%
if exist C:\web\tools\mes-batch-creds.bat call C:\web\tools\mes-batch-creds.bat
cd /d C:\DFwebBPVN\backend
php artisan mes:sync-batch-completions --days=3 >> C:\DFwebBPVN\tools\logs\mes-sync-batch.log 2>&1
