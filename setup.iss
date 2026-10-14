#define MyAppName "NMS Accounting System"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Naqeebs Multi Services"
#define LoginUrl "http://localhost/nms/login.php"

[Setup]
AppId={{8D2F3D3B-5D79-4BB0-9A7F-6E0B9D3A6F12}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\NMS Accounting System
DisableProgramGroupPage=yes
OutputDir=installer-output
OutputBaseFilename=NMS-Accounting-Setup
Compression=lzma2
SolidCompression=yes
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64
Uninstallable=no
WizardStyle=modern

[Files]
Source: "*"; DestDir: "{code:GetWebRoot}\nms"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: ".git\*;.vs\*;.idea\*;.github\*;installer-output\*;setup.iss;*.iss"

[Icons]
Name: "{autodesktop}\NMS Accounting System"; Filename: "{#LoginUrl}"; WorkingDir: "{code:GetWebRoot}\nms"; Comment: "Open NMS Accounting System"
Name: "{autoprograms}\NMS Accounting System"; Filename: "{#LoginUrl}"; WorkingDir: "{code:GetWebRoot}\nms"; Comment: "Open NMS Accounting System"

[Code]
var
  ServerPage: TInputDirWizardPage;
  DatabasePasswordPage: TInputQueryWizardPage;
  XamppRoot: string;
  LaragonRoot: string;
  SelectedServerRoot: string;

function DefaultServerRoot: string;
begin
  XamppRoot := 'C:\xampp';
  LaragonRoot := 'C:\laragon';

  if DirExists(XamppRoot + '\htdocs') then
	Result := XamppRoot + '\htdocs'
  else if DirExists(LaragonRoot + '\www') then
	Result := LaragonRoot + '\www'
  else
	Result := '';
end;

function GetWebRoot(Param: string): string;
begin
  Result := ServerPage.Values[0];
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;

  if CurPageID = ServerPage.ID then begin
	SelectedServerRoot := RemoveBackslashUnlessRoot(Trim(ServerPage.Values[0]));
	if (SelectedServerRoot = '') or (not DirExists(SelectedServerRoot)) then begin
	  MsgBox('Select an existing XAMPP htdocs or Laragon www directory before continuing.', mbError, MB_OK);
	  Result := False;
	end;
  end;
end;

procedure InitializeWizard;
var
  DefaultRoot: string;
begin
  DefaultRoot := DefaultServerRoot;

  ServerPage := CreateInputDirPage(wpSelectDir,
	'Local PHP server directory',
	'Choose the public directory for your local PHP server',
	'NMS will be copied into a new nms subfolder in this directory.' + #13#10 +
	'Typical locations are C:\xampp\htdocs and C:\laragon\www.',
	False, 'Web root');
  ServerPage.Add('');
  ServerPage.Values[0] := DefaultRoot;

  if DefaultRoot = '' then
	MsgBox('XAMPP (C:\xampp) and Laragon (C:\laragon) were not detected.' + #13#10#13#10 +
	  'Select your local server public directory manually, or install XAMPP/Laragon before continuing.',
	  mbInformation, MB_OK);

  DatabasePasswordPage := CreateInputQueryPage(ServerPage.ID,
	'MySQL credentials',
	'Enter the local MySQL root password',
	'The default XAMPP/Laragon root password is usually empty. The password is used only during setup.');
  DatabasePasswordPage.Add('MySQL root password:', True);
end;

function QuoteArgument(Value: string): string;
begin
  Result := '"' + Value + '"';
end;

function FindMySqlExecutable: string;
var
  FindData: TWin32FindData;
	SearchHandle: Integer;
  LaragonMySqlRoot: string;
begin
  Result := '';

  if FileExists(XamppRoot + '\mysql\bin\mysql.exe') then begin
	Result := XamppRoot + '\mysql\bin\mysql.exe';
	exit;
  end;

  LaragonMySqlRoot := LaragonRoot + '\bin\mysql';
  SearchHandle := FindFirst(LaragonMySqlRoot + '\*\bin\mysql.exe', FindData);
	if SearchHandle <> -1 then begin
	Result := LaragonMySqlRoot + '\' + FindData.Name + '\bin\mysql.exe';
	FindClose(SearchHandle);
  end;
end;

function RunMySql(Arguments: string; var ExitCode: Integer): Boolean;
var
  MySqlExecutable: string;
  Password: string;
begin
  MySqlExecutable := FindMySqlExecutable;
  if MySqlExecutable = '' then begin
	Result := False;
	Exit;
  end;

  Password := DatabasePasswordPage.Values[0];
  Result := Exec(MySqlExecutable,
	'--user=root --password=' + QuoteArgument(Password) + ' ' + Arguments,
	'', SW_HIDE, ewWaitUntilTerminated, ExitCode);
end;

function DatabaseIsInitialized: Boolean;
var
  ExitCode: Integer;
begin
  Result := RunMySql('--database=nms_accounting --execute="SELECT 1 FROM users LIMIT 1"', ExitCode) and (ExitCode = 0);
end;

function SqlPath(FileName: string): string;
begin
  Result := AddBackslash(GetWebRoot('')) + 'nms\sql\' + FileName;
end;

function ImportSqlFile(FileName: string): Boolean;
var
  ExitCode: Integer;
  PathForMySql: string;
begin
  Result := True;
  if not FileExists(SqlPath(FileName)) then
	Exit;

  PathForMySql := StringChangeEx(SqlPath(FileName), '\', '/', True);
  Result := RunMySql('--database=nms_accounting --execute="source ''' + PathForMySql + '''"', ExitCode) and (ExitCode = 0);
end;

function InitializeDatabase: Boolean;
var
  ExitCode: Integer;
  SqlFiles: array[0..10] of string;
  I: Integer;
begin
  Result := True;

  if DatabaseIsInitialized then
	Exit;

  if not RunMySql('--execute="CREATE DATABASE IF NOT EXISTS nms_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"', ExitCode) or (ExitCode <> 0) then begin
	Result := False;
	Exit;
  end;

  SqlFiles[0] := 'schema.sql';
  SqlFiles[1] := 'auth.sql';
  SqlFiles[2] := 'inventory.sql';
  SqlFiles[3] := 'audit_trail.sql';
  SqlFiles[4] := 'budgeting.sql';
  SqlFiles[5] := 'reconciliation.sql';
  SqlFiles[6] := 'multi_currency.sql';
  SqlFiles[7] := 'recurring_invoices.sql';
  SqlFiles[8] := 'pakistan_tax_updates.sql';
  SqlFiles[9] := 'fix_schema_inconsistencies.sql';
  SqlFiles[10] := 'nms_custom_setup.sql';

  for I := 0 to 10 do begin
	if not ImportSqlFile(SqlFiles[I]) then begin
	  Result := False;
	  Exit;
	end;
  end;
end;

procedure StartLocalServer;
var
  ExitCode: Integer;
  LaragonExecutable: string;
begin
  if FileExists(XamppRoot + '\xampp_start.exe') then
	Exec(XamppRoot + '\xampp_start.exe', '', XamppRoot, SW_HIDE, ewNoWait, ExitCode);

  if FileExists(LaragonRoot + '\laragon.exe') then begin
	LaragonExecutable := LaragonRoot + '\laragon.exe';
	Exec(LaragonExecutable, 'start', LaragonRoot, SW_HIDE, ewNoWait, ExitCode);
  end;

  Exec(ExpandConstant('{sys}\sc.exe'), 'start Apache2.4', '', SW_HIDE, ewWaitUntilTerminated, ExitCode);
  Exec(ExpandConstant('{sys}\sc.exe'), 'start mysql', '', SW_HIDE, ewWaitUntilTerminated, ExitCode);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  DatabaseReady: Boolean;
  ExitCode: Integer;
  LoginPage: string;
begin
  if CurStep <> ssPostInstall then
	Exit;

  DatabaseReady := InitializeDatabase;
  StartLocalServer;

  if not DatabaseReady then
	MsgBox('NMS was copied successfully, but the automatic database setup could not complete.' + #13#10#13#10 +
	  'Start Apache and MySQL, then open the NMS setup page to finish configuration:' + #13#10 +
	  'http://localhost/nms/setup.php', mbError, MB_OK)
  else
	MsgBox('NMS database setup completed. Default login: admin / admin123' + #13#10#13#10 +
	  'If the page does not load immediately, confirm that Apache and MySQL are running in XAMPP or Laragon.',
	  mbInformation, MB_OK);

  LoginPage := '{#LoginUrl}';
  ShellExec('', LoginPage, '', '', SW_SHOWNORMAL, ewNoWait, ExitCode);
end;
