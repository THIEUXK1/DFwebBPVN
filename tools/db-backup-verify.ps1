<#
    Kiểm chứng bản backup mới nhất — CHỈ ĐỌC, không đụng vào database.

    Kiểm tra 4 điều kiện của bản dump mới nhất:
      1. Có tồn tại và mới hơn ngưỡng tuổi cho phép (mặc định 26 giờ).
      2. Kích thước hợp lý.
      3. Đọc được mục lục archive bằng pg_restore -l (chứng minh file không hỏng).
      4. Số bảng có dữ liệu trong archive đạt ngưỡng tối thiểu.

    KHÔNG restore thật vào server production: việc đó cần tạo/xóa database,
    mà DROP DATABASE nằm trong danh sách lệnh bị cấm (.claude/rules/database-safety.md
    mục 2). Muốn chạy restore test đầy đủ (yêu cầu của Phase 13), copy file .dump
    về máy dev và restore ở đó.

    Chạy tay:
      powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\db-backup-verify.ps1
#>
[CmdletBinding()]
param(
    [string]$PgBin     = 'C:\web\tools\postgresql\bin',
    [string]$BackupDir = 'C:\web\tools\backups',
    [int]$MaxAgeHours  = 26,
    [int]$MinDumpSizeKB = 100,
    [int]$MinTableCount = 20
)

$ErrorActionPreference = 'Stop'

function Write-Log([string]$Message, [string]$Level = 'INFO') {
    Write-Output ("[{0}] [{1}] {2}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Level, $Message)
}

$problems = @()

try {
    $latest = Get-ChildItem -LiteralPath $BackupDir -Filter '*.dump' -File |
              Sort-Object LastWriteTime -Descending |
              Select-Object -First 1

    if ($null -eq $latest) { throw "Không có file .dump nào trong $BackupDir" }

    Write-Log "Bản mới nhất: $($latest.Name)"

    $ageHours = [math]::Round(((Get-Date) - $latest.LastWriteTime).TotalHours, 1)
    $sizeKB   = [math]::Round($latest.Length / 1KB, 1)
    Write-Log "Tuổi: $ageHours giờ — Kích thước: $sizeKB KB"

    if ($ageHours -gt $MaxAgeHours) {
        $problems += "Backup quá cũ ($ageHours giờ > $MaxAgeHours giờ) — task DFWeb-Backup có thể đã ngừng chạy"
    }
    if ($sizeKB -lt $MinDumpSizeKB) {
        $problems += "Dump quá nhỏ ($sizeKB KB < $MinDumpSizeKB KB) — nghi ngờ dump lỗi"
    }

    $pgRestore = Join-Path $PgBin 'pg_restore.exe'
    if (-not (Test-Path -LiteralPath $pgRestore)) { throw "Không tìm thấy $pgRestore" }

    $toc = & $pgRestore -l $latest.FullName
    if ($LASTEXITCODE -ne 0) {
        $problems += "pg_restore -l thất bại (exit $LASTEXITCODE) — file dump hỏng, KHÔNG khôi phục được"
    } else {
        $tableData = @($toc | Where-Object { $_ -match 'TABLE DATA' })
        Write-Log "Archive đọc được — $($tableData.Count) bảng có dữ liệu"
        if ($tableData.Count -lt $MinTableCount) {
            $problems += "Chỉ có $($tableData.Count) bảng dữ liệu (< $MinTableCount) — dump có thể thiếu schema"
        }
    }

    $globals = Get-ChildItem -LiteralPath $BackupDir -Filter 'globals_*.sql' -File |
               Sort-Object LastWriteTime -Descending |
               Select-Object -First 1
    if ($null -eq $globals) {
        $problems += "Không có file globals_*.sql — restore sang máy mới sẽ mất role/quyền"
    } else {
        Write-Log "Globals mới nhất: $($globals.Name) ($([math]::Round($globals.Length / 1KB, 1)) KB)"
    }
}
catch {
    $problems += $_.Exception.Message
}

if ($problems.Count -eq 0) {
    Write-Log '=== ĐẠT: bản backup mới nhất hợp lệ ==='
    exit 0
}

foreach ($p in $problems) { Write-Log $p 'ERROR' }
Write-Log "=== KHÔNG ĐẠT: $($problems.Count) vấn đề ===" 'ERROR'
exit 1
