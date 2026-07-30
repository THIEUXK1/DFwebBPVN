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
Route::get('/downloads/agent-launcher/{role}', function (Request $request, string $role) {
    $files = [
        'print-station' => 'DFAgentSetup-PrintStation.msi',
        'weighing-printer' => 'DFAgentSetup-WeighingPrinter.msi',
        'weighing-scale' => 'DFAgentSetup-WeighingScale.msi',
    ];

    if (!isset($files[$role])) {
        abort(404);
    }

    $msiFile = $files[$role];
    $msiUrl = $request->getSchemeAndHttpHost() . '/downloads/' . $msiFile;

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
