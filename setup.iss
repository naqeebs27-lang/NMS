[Setup]
AppName=NMS Accounting System
AppVersion=1.0
DefaultDirName={pf}\NMS Accounting System
DefaultGroupName=NMS Accounting System
OutputBaseFilename=NMS_Setup
Compression=lzma
SolidCompression=yes
PrivilegesRequired=admin
OutputDir=Output

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Dirs]
; Allow custom selection folder or use default
Name: "{app}"

[Files]
; Copy your website source folder 'nms' into the installation folder temporarily
Source: "nms\*"; DestDir: "{app}\website"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
; Desktop Shortcut pointing directly to the local login page URL
Name: "{commondesktop}\NMS Accounting System"; Filename: "http://localhost/nms/login.php"; IconFilename: "{app}\website\favicon.ico"; IconIndex: 0; WorkingDir: "{app}\website"

[Code]
var
  ServerPage: TInputOptionWizardPage;

procedure InitializeWizard;
begin
  // Create a selection page for the user to choose their local server environment
  ServerPage := CreateInputOptionPage(wpSelectDir,
    'Select Local Server Environment', 'Where is your local server installed?',
    'Please select whether you are using XAMPP or Laragon so the website files can be copied to the correct public folder.',
    True, False);
  
  ServerPage.Add('XAMPP (Copies to C:\xampp\htdocs\nms)');
  ServerPage.Add('Laragon (Copies to C:\laragon\www\nms)');
  
  // Set default selection based on what folder actually exists on disk
  if DirExists('C:\laragon') then
    ServerPage.Values[1] := True
  else
    ServerPage.Values[0] := True; // Default to XAMPP
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = ServerPage.ID then
  begin
    // Ensure the user picked one option
    if not ServerPage.Values[0] and not ServerPage.Values[1] then
    begin
      MsgBox('Please select either XAMPP or Laragon to continue.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  TargetPublicDir: String;
  ResultCode: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    // Determine target public directory based on user selection
    if ServerPage.Values[0] then
      TargetPublicDir := 'C:\xampp\htdocs\nms'
    else
      TargetPublicDir := 'C:\laragon\www\nms';

    // Create the public folder if it doesn't exist
    ForceDirectories(TargetPublicDir);

    // Copy website files directly to XAMPP htdocs or Laragon www
    Exec('cmd.exe', '/c xcopy /E /I /Y "' + ExpandConstant('{app}\website') + '" "' + TargetPublicDir + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

    // Prompt user to ensure services are running
    MsgBox('Installation complete!' + #13#13 + 
           'Please ensure your Apache and MySQL services are started in XAMPP or Laragon, ' + 
           'then use the desktop shortcut to open your accounting system login page.', mbInformation, MB_OK);

    // Automatically open the login page in browser
    Exec('rundll32.exe', 'url.dll,FileProtocolHandler http://localhost/nms/login.php', '', SW_SHOW, ewNoWait, ResultCode);
  end;
end;