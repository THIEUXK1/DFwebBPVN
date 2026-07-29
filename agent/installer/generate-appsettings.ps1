<#
  Chay boi WiX Custom Action "GenerateAppSettings" (deferred, LocalSystem) luc cai DF Agent.
  Nhan du lieu qua tham so -Data dang "WorkstationId|Role|BackendUrl|PuttyLogPath|InstallFolder"
  (WiX chuyen CustomActionData qua token [~] cho custom action kieu exe deferred). Khong con
  Token — backend tu nhan workstation theo Id, xem AgentAuth.php (2026-07-29).
#>
param(
    [Parameter(Mandatory = $true)][string]$TemplatePath,
    [Parameter(Mandatory = $true)][string]$Data
)

$parts = $Data -split '\|'
if ($parts.Count -lt 5) {
    throw "Du lieu CustomActionData khong hop le: '$Data'"
}
$workstationId = $parts[0]
$role = $parts[1]
$backendUrl = $parts[2]
$puttyLogPath = $parts[3]
$installFolder = $parts[4]

function JsonEscape([string]$value) {
    $value = $value.Replace('\', '\\')
    $value = $value.Replace('"', '\"')
    return $value
}

$content = Get-Content -Path $TemplatePath -Raw -Encoding UTF8
$content = $content.Replace('{{WORKSTATION_ID}}', (JsonEscape $workstationId))
$content = $content.Replace('{{WORKSTATION_ROLE}}', (JsonEscape $role))
$content = $content.Replace('{{BACKEND_URL}}', (JsonEscape $backendUrl))
$content = $content.Replace('{{PUTTY_LOG_PATH}}', (JsonEscape $puttyLogPath))

$outPath = Join-Path $installFolder 'appsettings.json'
Set-Content -Path $outPath -Value $content -Encoding UTF8 -NoNewline
