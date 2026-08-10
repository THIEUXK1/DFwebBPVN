@echo off
REM ===========================================================================
REM MAU credential cho dong bo MES batch-completions.
REM
REM CACH DUNG (chi lam 1 lan, ngay tren CS-SERVER):
REM   1. Copy file nay thanh:  C:\web\tools\mes-batch-creds.bat
REM   2. Dien gia tri that vao ben duoi.
REM   3. KHONG commit file that len git (no nam ngoai repo, canh .pgpass).
REM
REM Ly do de o C:\web\tools\ thay vi .env: mat khau MES khong duoc luu trong
REM .env cua app (yeu cau cua chu du an). File nay chi Scheduled Task doc.
REM
REM LUU Y config cache: neu server co chay `php artisan config:cache` thi env
REM dat o day se KHONG toi duoc config('mes.batch.*'). Server hien KHONG cache
REM config (quy trinh deploy khong co buoc do) nen dung binh thuong. Neu sau nay
REM bat config:cache, phai dua cac bien nay vao .env roi cache lai.
REM ===========================================================================

REM MES noi bo (192.168.250.147) dung sys/login; MES production
REM (f.mes.bestpacific.vn/mes) dung sys/ssologin. Chon URL ma CS-SERVER TOI DUOC.
set MES_BATCH_BASE_URL=http://192.168.250.147:38085/nhmes
set MES_BATCH_LOGIN_PATH=sys/login

set MES_BATCH_USERNAME=DIEN_TAI_KHOAN
set MES_BATCH_PASSWORD=DIEN_MAT_KHAU

REM Bat buoc cho eBatchLine/batchView (thieu -> MES tra "chua tim thay nha may").
REM Tai khoan V190986 = BPVN. Lay tu Network tab trang 车间生产 neu dung tai khoan khac.
set MES_BATCH_FACTORY_CODE=BPVN
set MES_BATCH_ORG_FILTER=
