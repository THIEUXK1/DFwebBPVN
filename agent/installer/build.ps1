# Build bo cai DF Agent - vai tro DUY NHAT: nhan can (SCALE_ONLY).
#
# Tu 2026-07-31 chi con 1 file MSI. Truoc do co build-all.ps1 sinh 3 file theo 3 vai tro
# (print-station / weighing-printer / weighing-scale); phan may in cua Agent khong con duoc
# dung o dau nua vi ca Print Station lan Weighing Station deu in bang hop thoai in cua
# trinh duyet.
#
# Yeu cau: .NET SDK + WiX Toolset v5 (dotnet tool install --global wix).
#
#   powershell -ExecutionPolicy Bypass -File agent\installer\build.ps1
#
# Ket qua: agent\installer\DFAgentSetup-Scale.msi, tu copy sang
# backend\public\downloads\ de trang "Tai cong cu" phuc vu tai ve.

$ErrorActionPreference = 'Stop'

$installerDir = $PSScriptRoot
$agentDir     = Split-Path $installerDir -Parent
$repoRoot     = Split-Path $agentDir -Parent
$publishDir   = Join-Path $agentDir 'publish_release'
$msiPath      = Join-Path $installerDir 'DFAgentSetup-Scale.msi'
$downloadsDir = Join-Path $repoRoot 'backend\public\downloads'

Write-Host '==> [1/3] Publish DFAgent (win-x64, self-contained)...' -ForegroundColor Cyan
dotnet publish (Join-Path $agentDir 'DFAgent.csproj') `
    -c Release -r win-x64 --self-contained true `
    -o $publishDir
if ($LASTEXITCODE -ne 0) { throw "dotnet publish that bai (exit $LASTEXITCODE)" }

Write-Host '==> [2/3] Dong goi MSI bang WiX...' -ForegroundColor Cyan
Push-Location $installerDir
try {
    wix build DFAgentSetup.wxs -ext WixToolset.UI.wixext -o $msiPath
    if ($LASTEXITCODE -ne 0) { throw "wix build that bai (exit $LASTEXITCODE)" }
}
finally { Pop-Location }

Write-Host '==> [3/3] Copy sang backend\public\downloads...' -ForegroundColor Cyan
if (-not (Test-Path $downloadsDir)) { New-Item -ItemType Directory -Path $downloadsDir | Out-Null }
Copy-Item $msiPath -Destination $downloadsDir -Force

$size = [math]::Round((Get-Item $msiPath).Length / 1MB, 1)
Write-Host "HOAN TAT: DFAgentSetup-Scale.msi ($size MB)" -ForegroundColor Green
Write-Host "  -> $msiPath"
Write-Host "  -> $(Join-Path $downloadsDir 'DFAgentSetup-Scale.msi')"
