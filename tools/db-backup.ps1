<#
    Sao lưu định kỳ database PostgreSQL của DFWeb trên CS-SERVER.
    Chạy qua Scheduled Task DFWeb-Backup -> tools\db-backup.bat -> script này.

    Script chỉ ghi ra stdout; file .bat gọi nó chịu trách nhiệm redirect vào log
    (giống bpdb-sync.bat) để tránh 2 nơi cùng ghi 1 file log.

    Định dạng dump: custom format (-Fc) đã nén — cho phép pg_restore chọn lọc
    từng bảng khi khôi phục, và kiểm tra được tính toàn vẹn bằng pg_restore -l
    mà không cần restore thật.
#>
[CmdletBinding()]
param(
    [string]$EnvFile   = 'C:\DFwebBPVN\backend\.env',
    [string]$PgBin     = 'C:\web\tools\postgresql\bin',
    [string]$BackupDir = 'C:\web\tools\backups',

    # Bản sao thứ 2 sang ổ khác/ổ mạng. Để trống = không sao chép.
    # LƯU Ý: task chạy bằng tài khoản SYSTEM không truy cập được ổ mạng
    # (kể cả UNC) — muốn mirror sang share phải đăng ký task bằng tài khoản
    # miền có quyền ghi lên share đó.
    [string]$MirrorDir = '',

    [int]$RetentionDays           = 14,
    [int]$MonthlyRetentionMonths  = 6,

    # Dump nhỏ hơn ngưỡng này coi như hỏng. Dump thật ngày 07/08/2026 là 841 KB
    # và đang tăng nhanh theo dữ liệu cân (300 KB ngày 05/08) — 100 KB là sàn an
    # toàn, thấp hơn cả bản nhỏ nhất từng có (218 KB, 25/07).
    [int]$MinDumpSizeKB = 100,

    # File mật khẩu do bản backup cũ (pg-backup.bat) dựng sẵn và đã chạy tốt.
    # Có file này thì dùng, khỏi phải đọc mật khẩu ra từ .env.
    [string]$PgPassFile = 'C:\web\tools\.pgpass'
)

$ErrorActionPreference = 'Stop'

function Write-Log([string]$Message, [string]$Level = 'INFO') {
    Write-Output ("[{0}] [{1}] {2}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Level, $Message)
}

function Read-DotEnv([string]$Path) {
    $map = @{}
    foreach ($line in (Get-Content -LiteralPath $Path -Encoding UTF8)) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }
        $idx = $trimmed.IndexOf('=')
        if ($idx -lt 1) { continue }
        $key = $trimmed.Substring(0, $idx).Trim()
        $val = $trimmed.Substring($idx + 1).Trim()
        if ($val.Length -ge 2) {
            if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
                $val = $val.Substring(1, $val.Length - 2)
            }
        }
        $map[$key] = $val
    }
    return $map
}

$exitCode = 0
$started  = Get-Date
Write-Log "=== Bắt đầu backup DFWeb ==="

try {
    if (-not (Test-Path -LiteralPath $EnvFile)) { throw "Không tìm thấy file .env: $EnvFile" }
    $cfg = Read-DotEnv $EnvFile

    $dbName = $cfg['DB_DATABASE']
    $dbUser = $cfg['DB_USERNAME']
    $dbPort = $cfg['DB_PORT']
    if (-not $dbName) { throw 'Thiếu DB_DATABASE trong .env' }
    if (-not $dbUser) { throw 'Thiếu DB_USERNAME trong .env' }
    if (-not $dbPort) { $dbPort = '5433' }

    # Chạy ngay trên CS-SERVER nên luôn dùng loopback, không đi qua LAN.
    $dbHost = '127.0.0.1'

    $pgDump    = Join-Path $PgBin 'pg_dump.exe'
    $pgDumpAll = Join-Path $PgBin 'pg_dumpall.exe'
    foreach ($exe in @($pgDump, $pgDumpAll)) {
        if (-not (Test-Path -LiteralPath $exe)) { throw "Không tìm thấy $exe" }
    }

    if (-not (Test-Path -LiteralPath $BackupDir)) {
        New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
        Write-Log "Đã tạo thư mục backup: $BackupDir"
    }

    # Đặt cả 2: PGPASSWORD thắng thế, PGPASSFILE là lưới đỡ nếu .env thiếu mật khẩu.
    # Riêng pg_dumpall nối vào database `postgres` (không phải production_web) nên
    # dòng .pgpass viết cứng tên database sẽ KHÔNG khớp — đó là lý do lần chạy
    # 07/08/2026 treo vô hạn ở bước globals.
    if ($cfg['DB_PASSWORD']) {
        $env:PGPASSWORD = $cfg['DB_PASSWORD']
        Write-Log 'Xác thực bằng DB_PASSWORD trong .env'
    }
    if (Test-Path -LiteralPath $PgPassFile) {
        $env:PGPASSFILE = $PgPassFile
        Write-Log "Có sẵn $PgPassFile làm phương án dự phòng"
    }

    $stamp    = Get-Date -Format 'yyyyMMdd_HHmmss'
    $dumpFile = Join-Path $BackupDir ("{0}_{1}.dump" -f $dbName, $stamp)
    $globFile = Join-Path $BackupDir ("globals_{0}.sql" -f $stamp)

    Write-Log "pg_dump $dbName -> $dumpFile"
    # -w: tuyệt đối không hỏi mật khẩu. Task chạy nền không có ai gõ vào, thiếu cờ
    # này thì sai mật khẩu = treo đến hết ExecutionTimeLimit thay vì báo lỗi ngay.
    & $pgDump -w -h $dbHost -p $dbPort -U $dbUser -d $dbName -Fc -Z 6 -f $dumpFile
    if ($LASTEXITCODE -ne 0) { throw "pg_dump thất bại (exit $LASTEXITCODE)" }

    $sizeKB = [math]::Round((Get-Item -LiteralPath $dumpFile).Length / 1KB, 1)
    if ($sizeKB -lt $MinDumpSizeKB) {
        throw "Dump chỉ $sizeKB KB (< $MinDumpSizeKB KB) — nghi ngờ backup hỏng, giữ lại file để điều tra"
    }
    Write-Log ("pg_dump xong: {0} KB" -f $sizeKB)

    # Roles/quyền nằm ở cấp cluster, không có trong dump của 1 database —
    # thiếu file này thì restore sang máy mới sẽ mất user/permission.
    Write-Log "pg_dumpall --globals-only -> $globFile"
    & $pgDumpAll -w -h $dbHost -p $dbPort -U $dbUser --globals-only -f $globFile
    if ($LASTEXITCODE -ne 0) { throw "pg_dumpall thất bại (exit $LASTEXITCODE)" }

    if ($MirrorDir -ne '') {
        try {
            if (-not (Test-Path -LiteralPath $MirrorDir)) {
                New-Item -ItemType Directory -Path $MirrorDir -Force | Out-Null
            }
            Copy-Item -LiteralPath $dumpFile -Destination $MirrorDir -Force
            Copy-Item -LiteralPath $globFile -Destination $MirrorDir -Force
            Write-Log "Đã sao chép bản sao thứ 2 sang $MirrorDir"
        } catch {
            # Mirror hỏng không được làm hỏng backup chính đã thành công.
            Write-Log ("Sao chép sang {0} thất bại: {1}" -f $MirrorDir, $_.Exception.Message) 'WARN'
            $exitCode = 2
        }
    }

    # Dọn bản cũ: giữ RetentionDays ngày gần nhất, riêng bản chạy ngày mùng 1
    # giữ thêm tới MonthlyRetentionMonths tháng để có điểm khôi phục dài hạn.
    $dailyCutoff   = (Get-Date).AddDays(-$RetentionDays)
    $monthlyCutoff = (Get-Date).AddMonths(-$MonthlyRetentionMonths)
    $deleted = 0
    $kept    = 0

    Get-ChildItem -LiteralPath $BackupDir -File |
        Where-Object { $_.Name -match '_(\d{8})_\d{6}\.(dump|sql)$' } |
        ForEach-Object {
            $null = $_.Name -match '_(\d{8})_\d{6}\.(dump|sql)$'
            $fileDate = [datetime]::ParseExact($Matches[1], 'yyyyMMdd', $null)
            $isMonthly = ($fileDate.Day -eq 1)

            $expired = $false
            if ($isMonthly) {
                if ($fileDate -lt $monthlyCutoff) { $expired = $true }
            } else {
                if ($fileDate -lt $dailyCutoff) { $expired = $true }
            }

            if ($expired) {
                Remove-Item -LiteralPath $_.FullName -Force
                $deleted++
            } else {
                $kept++
            }
        }
    Write-Log "Dọn dẹp: xóa $deleted file hết hạn, còn giữ $kept file"

    $totalMB = [math]::Round(((Get-ChildItem -LiteralPath $BackupDir -File | Measure-Object -Property Length -Sum).Sum / 1MB), 1)
    $freeGB  = [math]::Round(((Get-PSDrive -Name ($BackupDir.Substring(0,1))).Free / 1GB), 1)
    Write-Log "Tổng dung lượng backup: $totalMB MB — ổ đĩa còn trống: $freeGB GB"
    if ($freeGB -lt 5) { Write-Log "Ổ đĩa sắp đầy (< 5 GB)" 'WARN' }
}
catch {
    Write-Log $_.Exception.Message 'ERROR'
    $exitCode = 1
}
finally {
    $env:PGPASSWORD = $null
    $env:PGPASSFILE = $null
}

$elapsed = [math]::Round(((Get-Date) - $started).TotalSeconds, 1)
if ($exitCode -eq 0) {
    Write-Log "=== Backup hoàn tất trong $elapsed giây ==="
} else {
    Write-Log "=== Backup KẾT THÚC VỚI LỖI (exit $exitCode) sau $elapsed giây ===" 'ERROR'
}
exit $exitCode
