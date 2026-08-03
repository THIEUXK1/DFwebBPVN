# Build bo cai DF Agent - vai tro DUY NHAT: nhan can (SCALE_ONLY).
#
# Tu 2026-08-03 sinh HAI file MSI DOC LAP tu cung mot ma nguon (yeu cau nguoi dung "tach ra
# lam 2 agent, 2 bo cai khong lien quan den nhau"):
#
#   DFAgentSetup-CanNho.msi -> service DFAgentSmall | ProgramFiles\DFAgent-Small | ScaleKind SMALL
#   DFAgentSetup-CanTo.msi  -> service DFAgentLarge | ProgramFiles\DFAgent-Large | ScaleKind LARGE
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
$builds = @(
    @{
        Label      = 'Can nho'
        Service    = 'DFAgentSmall'
        Folder     = 'DFAgent-Small'
        Settings   = 'appsettings.small.json'
        Upgrade    = 'CD108F1A-FCE9-46F2-B991-372D89E0E9D1'
        Msi        = 'DFAgentSetup-CanNho.msi'
    },
    @{
        Label      = 'Can to'
        Service    = 'DFAgentLarge'
        Folder     = 'DFAgent-Large'
        Settings   = 'appsettings.large.json'
        Upgrade    = '2FDBACF6-D829-4539-9B40-FB257321E68C'
        Msi        = 'DFAgentSetup-CanTo.msi'
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
    Write-Host ("  {0} ({1} MB) - service {2}" -f $b.Msi, $size, $b.Service)
    Write-Host ("    -> {0}" -f $path)
    Write-Host ("    -> {0}" -f (Join-Path $downloadsDir $b.Msi))
}
