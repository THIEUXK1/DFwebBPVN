<#
    Đăng ký Scheduled Task DFWeb-Backup trên CS-SERVER.
    Chạy MỘT LẦN, bằng PowerShell quyền Administrator, ngay trên server:

      powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\register-db-backup-task.ps1

    Nếu task cùng tên đã tồn tại (ví dụ bản cũ trỏ tới script trong C:\web\tools),
    script sẽ in ra cấu hình cũ rồi ghi đè bằng cấu hình chuẩn trong repo.
    Muốn xem trước mà chưa ghi gì, thêm -WhatIfOnly.
#>
[CmdletBinding()]
param(
    [string]$TaskName = 'DFWeb-Backup',
    [string]$BatPath  = 'C:\DFwebBPVN\tools\db-backup.bat',
    [string]$AtTime   = '02:00',
    [switch]$WhatIfOnly
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $BatPath)) {
    throw "Không tìm thấy $BatPath — cần git pull code mới trên server trước."
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
    $existing.Actions | ForEach-Object { Write-Output "  Action : $($_.Execute) $($_.Arguments)" }
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

$action  = New-ScheduledTaskAction -Execute $BatPath
$trigger = New-ScheduledTaskTrigger -Daily -At $AtTime

# SYSTEM: chạy được cả khi không ai đăng nhập. Đổi sang tài khoản miền nếu
# cần mirror backup sang ổ mạng (SYSTEM không có quyền trên share).
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2) `
    -RestartCount 2 `
    -RestartInterval (New-TimeSpan -Minutes 15) `
    -DontStopOnIdleEnd

Register-ScheduledTask -TaskName $TaskName `
    -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Backup PostgreSQL production_web hang ngay (pg_dump -Fc + globals), giu 14 ngay + moi thang 6 thang' `
    -Force | Out-Null

Write-Output "Đã đăng ký task '$TaskName' chạy hàng ngày lúc $AtTime bằng tài khoản SYSTEM."
Write-Output "Chạy thử ngay: Start-ScheduledTask -TaskName '$TaskName'"
Write-Output "Xem log     : Get-Content C:\DFwebBPVN\tools\logs\db-backup.log -Tail 40"
