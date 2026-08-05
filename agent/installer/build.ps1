# Build bo cai DF Agent - vai tro DUY NHAT: nhan can (SCALE_ONLY).
#
# Tu 2026-08-03 sinh cac file MSI DOC LAP tu cung mot ma nguon (yeu cau nguoi dung "tach ra
# lam 2 agent, 2 bo cai khong lien quan den nhau"); tu 2026-08-05 them bo IN/OUT rieng cho
# tram can to (yeu cau "tach thanh 2 bo cai rieng biet cho can to"):
#
#   DFAgentSetup-CanNho.msi      -> service DFAgentSmall | DFAgent-Small      | nhan can (SMALL)
#   DFAgentSetup-CanTo.msi       -> service DFAgentLarge | DFAgent-Large      | nhan can (LARGE)
#   DFAgentSetup-CanTo-InOut.msi -> khong service        | DFAgent-Large-InOut| chi IN/OUT
#
# Khac UpgradeCode nen cai/go/nang cap ban nay khong dung gi toi ban kia; cai CA HAI len cung
# mot may van chay song song duoc. Chi tiet xem dau file DFAgentSetup.wxs.
#
# Truoc do (2026-07-31 -> 2026-08-02) chi co 1 file DFAgentSetup-Scale.msi; truoc nua co
# build-all.ps1 sinh 3 file theo 3 vai tro (print-station / weighing-printer / weighing-scale)
# — phan may in cua Agent khong con duoc dung o dau nua vi ca Print Station lan Weighing
# Station deu in bang hop thoai in cua trinh duyet.
#
# Yeu cau: .NET SDK + WiX Toolset v5 (dotnet tool install --global wix).
#
#   powershell -ExecutionPolicy Bypass -File agent\installer\build.ps1
#
# Ket qua: 2 file .msi trong agent\installer\, tu copy sang backend\public\downloads\ de
# trang "Tai cong cu" phuc vu tai ve.

$ErrorActionPreference = 'Stop'

$installerDir = $PSScriptRoot
$agentDir     = Split-Path $installerDir -Parent
$repoRoot     = Split-Path $agentDir -Parent
$publishDir   = Join-Path $agentDir 'publish_release'
$downloadsDir = Join-Path $repoRoot 'backend\public\downloads'

# UpgradeCode: ban CAN NHO giu Guid lich su de tu go sach cac ban Agent da cai ngoai xuong
# (1.4.x theo vai tro cu va 2.x service "DFAgent"). Ban CAN TO dung Guid rieng hoan toan —
# day chinh la thu lam hai bo cai khong lien quan gi den nhau. TUYET DOI khong doi 2 Guid nay
# sau khi da phat hanh: doi la may da cai khong nhan ra ban nang cap, sinh ra 2 ban song song.
#
# RunMode (tu 4.0.0.0) — CACH CHAY sau khi cai:
#   service -> Windows Service LocalSystem, tu chay khi bat may, khong can ai dang nhap.
#   session -> khong cai service; shortcut Startup + Start Menu, chay bang tai khoan dang
#              dang nhap.
#
# Tram CAN TO can CA HAI, nen co 2 bo cai rieng cai chong len nhau tren cung mot may:
#   DFAgentSetup-CanTo.msi        (service) -> chi NHAN CAN.
#   DFAgentSetup-CanTo-InOut.msi  (session) -> chi lam IN/OUT (SEND OVER 6).
# Khong gop lam mot duoc: IN/OUT mo phong chuot + clipboard, ma tien trinh cua Windows Service
# nam o session 0, tach biet voi desktop nguoi dung nen khong bao gio cham toi ung dung pha
# mau. Chi tiet day du xem ghi chu RunMode dau file DFAgentSetup.wxs.
$builds = @(
    @{
        Label      = 'Can nho'
        Service    = 'DFAgentSmall'
        Folder     = 'DFAgent-Small'
        Settings   = 'appsettings.small.json'
        Upgrade    = 'CD108F1A-FCE9-46F2-B991-372D89E0E9D1'
        RunMode    = 'service'
        Msi        = 'DFAgentSetup-CanNho.msi'
    },
    @{
        Label      = 'Can to'
        Service    = 'DFAgentLarge'
        Folder     = 'DFAgent-Large'
        Settings   = 'appsettings.large.json'
        Upgrade    = '2FDBACF6-D829-4539-9B40-FB257321E68C'
        RunMode    = 'service'
        Msi        = 'DFAgentSetup-CanTo.msi'
    },
    @{
        Label      = 'Can to - IN OUT'
        Service    = 'DFAgentLargeInOut'
        Folder     = 'DFAgent-Large-InOut'
        Settings   = 'appsettings.large-inout.json'
        Upgrade    = 'AC5DC759-9675-4596-AAC3-22CD03A053BD'
        RunMode    = 'session'
        Msi        = 'DFAgentSetup-CanTo-InOut.msi'
    }
)

Write-Host '==> [1/3] Publish DFAgent (win-x64, self-contained)...' -ForegroundColor Cyan
dotnet publish (Join-Path $agentDir 'DFAgent.csproj') `
    -c Release -r win-x64 --self-contained true `
    -o $publishDir
if ($LASTEXITCODE -ne 0) { throw "dotnet publish that bai (exit $LASTEXITCODE)" }

Write-Host '==> [2/3] Dong goi 2 file MSI bang WiX...' -ForegroundColor Cyan
Push-Location $installerDir
try {
    foreach ($b in $builds) {
        Write-Host ("    - {0} ({1})" -f $b.Msi, $b.Label) -ForegroundColor DarkCyan
        wix build DFAgentSetup.wxs -ext WixToolset.UI.wixext `
            -d ProductLabel="$($b.Label)" `
            -d ServiceName="$($b.Service)" `
            -d InstallFolderName="$($b.Folder)" `
            -d AppSettingsFile="$($b.Settings)" `
            -d UpgradeGuid="$($b.Upgrade)" `
            -d RunMode="$($b.RunMode)" `
            -o (Join-Path $installerDir $b.Msi)
        if ($LASTEXITCODE -ne 0) { throw "wix build $($b.Msi) that bai (exit $LASTEXITCODE)" }
    }
}
finally { Pop-Location }

Write-Host '==> [3/3] Copy sang backend\public\downloads...' -ForegroundColor Cyan
if (-not (Test-Path $downloadsDir)) { New-Item -ItemType Directory -Path $downloadsDir | Out-Null }
foreach ($b in $builds) {
    Copy-Item (Join-Path $installerDir $b.Msi) -Destination $downloadsDir -Force
}

Write-Host 'HOAN TAT:' -ForegroundColor Green
foreach ($b in $builds) {
    $path = Join-Path $installerDir $b.Msi
    $size = [math]::Round((Get-Item $path).Length / 1MB, 1)
    $cach = if ($b.RunMode -eq 'service') { "service $($b.Service)" } else { 'chay trong phien nguoi dung (Startup + Start Menu)' }
    Write-Host ("  {0} ({1} MB) - {2}" -f $b.Msi, $size, $cach)
    Write-Host ("    -> {0}" -f $path)
    Write-Host ("    -> {0}" -f (Join-Path $downloadsDir $b.Msi))
}
