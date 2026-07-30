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

    $template = <<<'CMD'
@echo off
echo Dang tai va cai DF Agent (%s)...
echo Se hien hop thoai xin quyen Administrator - bam Yes va nhap mat khau admin neu duoc hoi.
powershell -NoProfile -Command "$msi = Join-Path $env:TEMP '%s'; Invoke-WebRequest -Uri '%s' -OutFile $msi; Start-Process msiexec.exe -ArgumentList @('/i', $msi) -Verb RunAs -Wait"
echo.
echo Cai dat hoan tat (hoac da bi huy neu ban bam No o hop thoai quyen admin).
pause
CMD;

    $script = sprintf($template, $role, $msiFile, $msiUrl);

    return response($script, 200, [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => "attachment; filename=\"Cai-DFAgent-{$role}.cmd\"",
    ]);
})->name('agent.launcher');
