<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sinh file .cmd tai chay tu bat UAC (Start-Process -Verb RunAs) de tai va cai DFAgent
// MSI voi quyen admin — .msi khong tu hien hop thoai UAC khi nguoi dung khong phai admin
// double-click truc tiep (khac voi .exe), chi bao loi "khong du quyen" (MSI 1925) va
// dung, thay vi hien hop thoai xin mat khau admin nhu ban Inno Setup .exe cu. Sinh dong
// (khong phai file tinh) de tu lay dung host dang truy cap (localhost luc dev, IP LAN
// luc that) — giong cach frontend tinh agentInstallerOptions trong AppLayout.vue.
// Tu 2026-08-03 co lai DUNG 2 bo cai, nhung lan nay tach theo LOAI CAN chu khong phai vai
// tro thiet bi: can nho (duoi 6kg, /weighing-station-v2) va can to (/weighing-station-large).
// Hai bo hoan toan doc lap - khac UpgradeCode, khac ten service, khac thu muc cai - nen cai
// ca hai len cung mot may van chay song song duoc (xem agent/installer/DFAgentSetup.wxs).
//
// Giai doan 2026-07-31 -> 2026-08-02 chi co 1 bo (DFAgentSetup-Scale.msi); truoc nua co 3 vai
// tro (print-station / weighing-printer / weighing-scale) vi may in di qua Agent — nay ca
// Print Station lan Weighing Station deu in bang hop thoai in cua trinh duyet.
//
// Tham so {kind} khong bat buoc: URL cu /downloads/agent-launcher (khong tham so) van chay,
// tra ve bo can nho — giu nguyen cho trinh duyet da bookmark hoac frontend chua deploy kip.
Route::get('/downloads/agent-launcher/{kind?}', function (Request $request, string $kind = 'small') {
    // 'large-inout' (2026-08-05): bo cai THU BA — chi lam viec IN/OUT (SEND OVER 6) cua tram
    // can to, cai CHONG LEN bo 'large' tren cung mot may. Phai tach vi no khong cai service ma
    // chay trong phien dang nhap cua tho: mo phong chuot tu Windows Service (session 0) khong
    // cham duoc desktop nguoi dung. Xem ghi chu RunMode dau agent/installer/DFAgentSetup.wxs.
    $boCai = [
        'small' => ['nhan' => 'can-nho', 'msi' => 'DFAgentSetup-CanNho.msi'],
        'large' => ['nhan' => 'can-to', 'msi' => 'DFAgentSetup-CanTo.msi'],
        'large-inout' => ['nhan' => 'can-to-IN-OUT', 'msi' => 'DFAgentSetup-CanTo-InOut.msi'],
    ];
    $chon = $boCai[strtolower($kind)] ?? $boCai['small'];
    $role = $chon['nhan'];
    $msiFile = $chon['msi'];

    // Cong 8501 rieng (php -S tinh, khong qua Laravel/artisan serve) chi de phuc vu file
    // trong public/downloads/ — KHONG dung chung cong 8500 (backend API chinh). Ly do: tren
    // CS-SERVER, backend API chay bang `php artisan serve` don luong (single-threaded, xem
    // run-backend.bat) — trong luc truyen file .msi 28MB no khong xu ly duoc request nao
    // khac, va nguoc lai request khac chen vao lam dut ket noi giua chung file lon (loi that
    // "An existing connection was forcibly closed by the remote host", 2026-07-30). Tach
    // rieng downloads server (run-downloads.bat, scheduled task DFWeb-Downloads, firewall
    // port 8501) de tai file lon khong con dung do/bi dung do boi cac request khac cua he
    // thong. Dev/localhost KHONG co server 8501 rieng — fallback ve dung cong 8500 nhu cu
    // (chua can thiet lap them tren may dev, chi may that ngoai xuong moi gap van de nay).
    $host = $request->getHost();
    $msiUrl = $host === 'localhost' || $host === '127.0.0.1'
        ? $request->getSchemeAndHttpHost() . '/downloads/' . $msiFile
        : $request->getScheme() . '://' . $host . ':8501/' . $msiFile;

    // $ProgressPreference='SilentlyContinue' + -UseBasicParsing: Invoke-WebRequest tren
    // Windows PowerShell 5.1 (may tram xuong) ve mac dinh render progress bar rat cham cho
    // file lon (~28MB), tren mang LAN xuong khong on dinh gay dut ket noi giua chung
    // (IOException) — loi thuc te nguoi dung gap 2026-07-30. Them 3 lan thu lai + kiem tra
    // dung luong file tai ve truoc khi goi msiexec, tranh hien loi MSI kho hieu "could not
    // be opened" khi nguyen nhan that la tai thieu du lieu.
    $template = <<<'CMD'
@echo off
echo Dang tai va cai DF Agent (%s)...
echo Se hien hop thoai xin quyen Administrator - bam Yes va nhap mat khau admin neu duoc hoi.
powershell -NoProfile -Command "$ProgressPreference='SilentlyContinue'; $msi = Join-Path $env:TEMP '%s'; $ok=$false; for($i=1; $i -le 3 -and -not $ok; $i++){ try { Invoke-WebRequest -Uri '%s' -OutFile $msi -UseBasicParsing; if((Get-Item $msi -ErrorAction SilentlyContinue).Length -gt 1000000){ $ok=$true } else { Write-Host ('Lan ' + $i + ': tai thieu du lieu, thu lai...') } } catch { Write-Host ('Lan ' + $i + ' loi: ' + $_.Exception.Message) } }; if($ok){ Start-Process msiexec.exe -ArgumentList @('/i', $msi) -Verb RunAs -Wait } else { Write-Host 'KHONG THE TAI FILE CAI DAT SAU 3 LAN THU - kiem tra mang hoac chay lai script nay.' }"
echo.
echo Cai dat hoan tat (hoac da bi huy neu ban bam No o hop thoai quyen admin, hoac tai bi loi mang - xem thong bao ben tren).
pause
CMD;

    $script = sprintf($template, $role, $msiFile, $msiUrl);

    return response($script, 200, [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => "attachment; filename=\"Cai-DFAgent-{$role}.cmd\"",
    ]);
})->name('agent.launcher');
