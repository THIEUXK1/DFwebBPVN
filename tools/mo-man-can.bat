@echo off
REM ============================================================================
REM  Mo man CAN o che do TRAM (kiosk) — IN THANG RA MAY IN, KHONG hien hop thoai in.
REM
REM  VI SAO CAN FILE NAY:
REM  Chrome/Edge THOAT che do toan man hinh (F11) moi khi hien hop thoai in — day la
REM  hanh vi cua trinh duyet, khong co CSS/JS nao trong trang chan duoc. Co bo cua so
REM  in di roi (in bang iframe an) van con hop thoai, va con hop thoai la con mat F11.
REM
REM  Co --kiosk-printing thi window.print() in THANG ra may in mac dinh, khong hien hop
REM  thoai nao ca -> khong mat F11, va in nhanh nhu ban Excel VBA (Sheet.PrintOut cung
REM  in thang, khong hoi gi).
REM
REM  BAT BUOC: may in TEM phai duoc dat lam MAY IN MAC DINH cua Windows tren may tram
REM  (Settings > Bluetooth & devices > Printers > bo chon "Let Windows manage my
REM  default printer", roi chon may in tem > Set as default). --kiosk-printing luon in
REM  ra may in mac dinh va dung cai dat mac dinh cua driver — no khong hoi gi ai.
REM
REM  DUNG PROFILE RIENG (--user-data-dir) khong phai de cho gon: neu Chrome dang mo san
REM  bang profile thuong thi lenh nay chi mo them mot TAB trong tien trinh cu va MOI CO
REM  --kiosk-printing BI BO QUA. Profile rieng ep Chrome chay tien trinh moi nen co
REM  luon an. Doi lai: lan dau phai dang nhap lai mot lan (sau do nho).
REM
REM  Cach dung:
REM     mo-man-can.bat                            -> man can nho, server mac dinh
REM     mo-man-can.bat /weighing-station-large     -> man can to
REM     mo-man-can.bat http://may-khac:3001/...    -> dia chi khac han
REM ============================================================================
setlocal

set "MAY_CHU=http://10.0.60.209:3001"
set "DUONG_DAN=%~1"
if "%DUONG_DAN%"=="" set "DUONG_DAN=/weighing-station-v2"

echo %DUONG_DAN% | findstr /I /B "http" >nul
if errorlevel 1 (set "URL=%MAY_CHU%%DUONG_DAN%") else (set "URL=%DUONG_DAN%")

set "HO_SO=%LOCALAPPDATA%\DFStation\browser-profile"

set "TRINH_DUYET="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "TRINH_DUYET=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined TRINH_DUYET if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "TRINH_DUYET=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined TRINH_DUYET if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set "TRINH_DUYET=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not defined TRINH_DUYET if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set "TRINH_DUYET=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if not defined TRINH_DUYET (
  echo [LOI] Khong tim thay Chrome hay Edge tren may nay.
  pause
  exit /b 1
)

echo Mo: %URL%
echo Trinh duyet: %TRINH_DUYET%
start "" "%TRINH_DUYET%" --kiosk-printing --start-fullscreen --user-data-dir="%HO_SO%" --no-first-run --no-default-browser-check "%URL%"

endlocal
