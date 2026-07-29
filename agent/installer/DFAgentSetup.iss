#define MyAppName "DF Agent"
#define MyAppVersion "1.0.1"
#define MyAppPublisher "DF Project"
#define MyAppExeName "DFAgent.exe"
#define MyServiceName "DFAgent"

[Setup]
AppId={{8F3B2A10-4C5D-4E6F-9A1B-2C3D4E5F6A7B}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\DFAgent
DefaultGroupName=DF Agent
DisableProgramGroupPage=yes
OutputDir=..\..\backend\public\downloads
OutputBaseFilename=DFAgentSetup
Compression=lzma
SolidCompression=yes
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
UninstallDisplayIcon={app}\{#MyAppExeName}
DisableWelcomePage=no

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "..\publish_release\*"; DestDir: "{app}"; Excludes: "appsettings.json"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "appsettings.template.json"; DestDir: "{tmp}"; Flags: dontcopy

[Code]
var
  ConfigPage: TInputQueryWizardPage;

procedure InitializeWizard;
begin
  ConfigPage := CreateInputQueryPage(wpSelectDir,
    'Cau hinh Tram lam viec (Workstation)',
    'Nhap thong tin trong dung cho MAY NAY',
    'Moi may tram can Ma tram va Token rieng - lay tu Admin tren he thong Web (menu Workstation & Tai khoan) truoc khi cai. Duong dan file log PuTTY phai khop dung file PuTTY dang ghi tren may nay.');

  ConfigPage.Add('Ma tram (Workstation Id), vd WS-DYE-01:', False);
  ConfigPage.Add('Token dang ky tram:', False);
  ConfigPage.Add('Dia chi Backend API, vd http://10.0.200.248:8500/api:', False);
  ConfigPage.Add('Duong dan file log PuTTY, vd D:\SCALE\putty_log.txt:', False);

  ConfigPage.Values[2] := 'http://10.0.200.248:8500/api';
  ConfigPage.Values[3] := 'D:\SCALE\putty_log.txt';
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = ConfigPage.ID then
  begin
    if Trim(ConfigPage.Values[0]) = '' then
    begin
      MsgBox('Vui long nhap Ma tram (Workstation Id).', mbError, MB_OK);
      Result := False;
    end
    else if Trim(ConfigPage.Values[1]) = '' then
    begin
      MsgBox('Vui long nhap Token dang ky tram.', mbError, MB_OK);
      Result := False;
    end
    else if Trim(ConfigPage.Values[3]) = '' then
    begin
      MsgBox('Vui long nhap duong dan file log PuTTY.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function JsonEscape(S: String): String;
begin
  Result := S;
  StringChangeEx(Result, '\', '\\', True);
  StringChangeEx(Result, '"', '\"', True);
end;

procedure GenerateAppSettings;
var
  RawContent: AnsiString;
  Content: String;
  DestFile: String;
begin
  ExtractTemporaryFile('appsettings.template.json');
  LoadStringFromFile(ExpandConstant('{tmp}\appsettings.template.json'), RawContent);
  Content := String(RawContent);

  StringChangeEx(Content, '{{WORKSTATION_ID}}', JsonEscape(Trim(ConfigPage.Values[0])), True);
  StringChangeEx(Content, '{{WORKSTATION_TOKEN}}', JsonEscape(Trim(ConfigPage.Values[1])), True);
  StringChangeEx(Content, '{{BACKEND_URL}}', JsonEscape(Trim(ConfigPage.Values[2])), True);
  StringChangeEx(Content, '{{PUTTY_LOG_PATH}}', JsonEscape(Trim(ConfigPage.Values[3])), True);

  DestFile := ExpandConstant('{app}\appsettings.json');
  SaveStringToFile(DestFile, Content, False);
end;

procedure RegisterAndStartService;
var
  ResultCode: Integer;
  ExePath: String;
begin
  ExePath := ExpandConstant('{app}\{#MyAppExeName}');

  // Go qua service cu neu dang cai lai/nang cap - bo qua loi neu chua ton tai.
  Exec('sc.exe', 'stop {#MyServiceName}', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Exec('sc.exe', 'delete {#MyServiceName}', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

  Exec('sc.exe', 'create {#MyServiceName} binPath= "' + ExePath + '" start= auto DisplayName= "DF Local Agent (Can & In tem)"',
    '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Exec('sc.exe', 'description {#MyServiceName} "DF Project - Local Agent doc can dien tu qua PuTTY log va dieu khien may in tem TSC"',
    '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Exec('sc.exe', 'failure {#MyServiceName} reset= 86400 actions= restart/5000/restart/5000/restart/5000',
    '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Exec('sc.exe', 'start {#MyServiceName}', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
  begin
    GenerateAppSettings;
    RegisterAndStartService;
  end;
end;

[UninstallRun]
Filename: "sc.exe"; Parameters: "stop {#MyServiceName}"; Flags: runhidden; RunOnceId: "StopDFAgentSvc"
Filename: "sc.exe"; Parameters: "delete {#MyServiceName}"; Flags: runhidden; RunOnceId: "DeleteDFAgentSvc"
