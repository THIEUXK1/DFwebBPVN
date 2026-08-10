<#
    Đăng ký Scheduled Task DFWeb-MesSync trên CS-SERVER — đồng bộ giờ kết thúc
    nhuộm thật của mẻ (máy VD) từ VN-MES về app.mes_batch_completions.

    Chạy MỘT LẦN, bằng PowerShell quyền Administrator, ngay trên server:

      powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\register-mes-sync-task.ps1

    TRƯỚC KHI đăng ký, phải tạo file credential (ngoài repo, ngoài .env):
      C:\web\tools\mes-batch-creds.bat   (xem mẫu tools\mes-batch-creds.example.bat)

    Task chạy mỗi 15 phút, bằng tài khoản SYSTEM (chạy cả khi không ai đăng nhập),
    không chồng lần chạy (IgnoreNew). Muốn xem trước mà chưa ghi gì: -WhatIfOnly.
#>
[CmdletBinding()]
param(
    [string]$TaskName        = 'DFWeb-MesSync',
    [string]$BatPath         = 'C:\DFwebBPVN\tools\mes-sync-batch.bat',
    [string]$CredsPath       = 'C:\web\tools\mes-batch-creds.bat',
    [int]$IntervalMinutes    = 15,
    [switch]$WhatIfOnly
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $BatPath)) {
    throw "Không tìm thấy $BatPath — cần git pull code mới trên server trước."
}

if (-not (Test-Path -LiteralPath $CredsPath)) {
    Write-Output "CẢNH BÁO: chưa có file credential $CredsPath."
    Write-Output "  Copy tools\mes-batch-creds.example.bat -> $CredsPath và điền tài khoản MES."
    Write-Output "  (Vẫn đăng ký task được, nhưng lần chạy sẽ thiếu quyền tới batchView.)"
    Write-Output ''
}

$logDir = 'C:\DFwebBPVN\tools\logs'
if (-not (Test-Path -LiteralPath $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    Write-Output "Đã tạo $logDir"
}

$existing = $null
try { $existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop } catch { }

if ($null -ne $existing) {
    Write-Output "Task '$TaskName' ĐÃ TỒN TẠI — cấu hình hiện tại:"
    $existing.Actions  | ForEach-Object { Write-Output "  Action : $($_.Execute) $($_.Arguments)" }
    $existing.Triggers | ForEach-Object { Write-Output "  Trigger: $($_.CimClass.CimClassName) lúc $($_.StartBoundary)" }
    Write-Output "  State  : $($existing.State)"
    $info = Get-ScheduledTaskInfo -TaskName $TaskName
    Write-Output "  Lần chạy gần nhất: $($info.LastRunTime) — kết quả: $($info.LastTaskResult)"
    Write-Output ''
}

if ($WhatIfOnly) {
    Write-Output 'WhatIfOnly: dừng tại đây, không thay đổi gì.'
    return
}

$action = New-ScheduledTaskAction -Execute $BatPath

# Lặp mỗi $IntervalMinutes phút, vô thời hạn. Dùng thời điểm mốc trong quá khứ để
# task bắt đầu vòng lặp ngay khi đăng ký, không phải chờ tới mốc tương lai.
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes) `
    -RepetitionDuration (New-TimeSpan -Days 3650)

# SYSTEM: chạy được cả khi không ai đăng nhập (giống DFWeb-Backup).
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

# ExecutionTimeLimit 10 phút < chu kỳ 15 phút: một lần chạy treo cũng bị cắt trước
# lần kế tiếp; IgnoreNew để không chồng nhiều lần chạy nếu MES chậm.
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10) `
    -RestartCount 1 `
    -RestartInterval (New-TimeSpan -Minutes 2) `
    -DontStopOnIdleEnd

Register-ScheduledTask -TaskName $TaskName `
    -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description "Dong bo gio ket thuc nhuom that cua me (may VD) tu VN-MES moi $IntervalMinutes phut" `
    -Force | Out-Null

Write-Output "Đã đăng ký task '$TaskName' chạy mỗi $IntervalMinutes phút bằng tài khoản SYSTEM."
Write-Output "Chạy thử ngay: Start-ScheduledTask -TaskName '$TaskName'"
Write-Output "Xem log      : Get-Content C:\DFwebBPVN\tools\logs\mes-sync-batch.log -Tail 40"
