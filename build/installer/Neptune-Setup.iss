; =====================================================================
; Neptune Financial OS - Inno Setup 6 Installation Script
; Architecture: Standalone Portable Desktop ERP (100% Offline-First)
; Designer & Owner: Hadi Behnampour
; =====================================================================

#define AppName "نپتون"
#define AppEnglishName "Neptune Financial OS"
#define AppVersion "2.5.0"
#define AppPublisher "هادی بهنام‌پور"
#define AppURL "https://dakhl-erp.vercel.app"
#define AppExeName "Neptune.exe"
#define SourceDir "C:\Projects\HesaBora"

[Setup]
; Unique Application ID
AppId={{C6B5A1A2-7777-4D2A-98C1-8E2E8F4B91E3}}
AppName={#AppName} ({#AppEnglishName})
AppVersion={#AppVersion}
AppVerName={#AppName} - نسخه {#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
AppSupportURL={#AppURL}
AppUpdatesURL={#AppURL}
DefaultDirName=C:\Neptune-ERP
DisableDirPage=no
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
LicenseFile={#SourceDir}\build\installer\license.txt
OutputDir={#SourceDir}\build\output
OutputBaseFilename=Neptune-ERP-Setup-v2.5
SetupIconFile={#SourceDir}\build\installer\assets\neptune.ico
UninstallDisplayIcon={app}\{#AppExeName}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
WizardImageFile={#SourceDir}\build\installer\assets\wizard_banner.bmp
WizardSmallImageFile={#SourceDir}\build\installer\assets\wizard_small.bmp
WizardImageStretch=no
DisableWelcomePage=no
CloseApplications=force
RestartApplications=no
PrivilegesRequired=admin

[Languages]
Name: "persian"; MessagesFile: "compiler:Default.isl"

[LangOptions]
LanguageName=فارسی (Persian)
LanguageID=$0429
RightToLeft=yes
DialogFontName=Segoe UI, Tahoma
DialogFontSize=9
WelcomeFontName=Segoe UI
WelcomeFontSize=13

[Messages]
SetupAppTitle=برنامه نصب نپتون
SetupWindowTitle=برنامه نصب سامانه جامع مالی و اداری نپتون (Neptune Financial OS)
UninstallAppTitle=حذف سامانه نپتون
UninstallAppFullTitle=حذف سامانه مالی نپتون از روی رایانه
InformationTitle=اطلاعات
ConfirmTitle=تأیید
ErrorTitle=خطا

; Welcome Page
WelcomeLabel1=به جادوی مدیریت مالی و ابری نپتون خوش آمدید!
WelcomeLabel2=این برنامه سامانه جامع مالی و ERP نپتون (نسخه %1) را روی رایانه شما نصب خواهد کرد.%n%nنپتون بر مبنای اصل حاکمیت کامل شما بر داده‌ها و معماری ۱۰۰٪ محلی (Offline-First) طراحی شده و تمامی پایگاه‌داده‌ها و رویدادهای مالی بر روی همین سیستم به صورت رمزنگاری‌شده نگهداری می‌شوند.%n%nجهت آغاز عملیات استقرار، لطفاً دکمه «بعدی» را کلیک فرمایید.

; Directory Selection
WizardSelectDir=انتخاب مسیر نصب برنامه نپتون
SelectDirDesc=سامانه مالی نپتون در چه مسیری نصب شود؟
SelectDirLabel3=برنامه نپتون در پوشه زیر مستقر خواهد شد:
SelectDirBrowseLabel=جهت ادامه دکمه «بعدی» را بزنید. در صورتی که تمایل دارید مسیر دیگری را انتخاب نمایید، دکمه «مرور...» را کلیک کنید:
DiskSpaceMBLabel=حداقل [mb] مگابایت فضای خالی دیسک مورد نیاز است.

; Ready to install
WizardReady=آماده برای آغاز نصب
ReadyLabel1=برنامه نصب اکنون آماده است تا پرونده‌های سامانه نپتون را روی رایانه شما مستقر نماید.
ReadyLabel2a=برای شروع عملیات، دکمه «نصب» را انتخاب کنید:
ReadyLabel2b=برای بررسی یا تغییر تنظیمات، دکمه «قبلی» را کلیک نمایید.

; Installing
WizardInstalling=در حال استقرار سامانه مالی نپتون
InstallingLabel=لطفاً تا اتمام انتقال پرونده‌ها، آماده‌سازی پایگاه‌داده محلی و پیکربندی موتور پردازشی شکیبا باشید...
StatusRunProgram=در حال تولید کلید رمزنگاری امن و بهینه‌سازی سامانه...

; Finished
FinishedHeadingLabel=نصب نپتون با موفقیت به پایان رسید!
FinishedLabelNoIcons=سامانه جامع مالی نپتون با موفقیت بر روی رایانه شما مستقر گردید.
FinishedLabel=سامانه جامع مالی نپتون با موفقیت بر روی رایانه شما نصب و آماده بهره‌برداری شد.%n%nمی‌توانید با انتخاب گزینه زیر، سامانه را اجرا نموده و از طریق میزکار هوشمند شروع به کار فرمایید.
ClickFinish=جهت خروج از برنامه نصب و اجرای نپتون، دکمه «پایان» را کلیک فرمایید.

; Common Buttons
ButtonNext=بعدی >
ButtonBack=< قبلی
ButtonInstall=نصب نپتون
ButtonCancel=انصراف
ButtonFinish=پایان و اجرا
ExitSetupTitle=انصراف از نصب نپتون
ExitSetupMessage=فرآیند نصب سامانه نپتون هنوز کامل نشده است. اگر اکنون خارج شوید، برنامه نصب نخواهد شد.%n%nشما می‌توانید در زمان دیگری برنامه نصب را برای تکمیل استقرار اجرا نمایید.%n%nآیا مایل به خروج از برنامه نصب هستید؟

[Tasks]
Name: "desktopicon"; Description: "ایجاد میانبر نپتون روی میزکار (Desktop Shortcut)"; GroupDescription: "میانبرها:"; Flags: checkablealone

[Files]
; Main Launcher & Stopper
Source: "{#SourceDir}\build\installer\Neptune.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceDir}\build\installer\StopNeptune.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceDir}\build\installer\assets\neptune.ico"; DestDir: "{app}\public\images"; Flags: ignoreversion
Source: "{#SourceDir}\build\installer\.env.standalone"; DestDir: "{app}"; DestName: ".env"; Flags: onlyifdoesntexist
Source: "{#SourceDir}\build\installer\.env.standalone"; DestDir: "{app}"; DestName: ".env.default"; Flags: ignoreversion
Source: "{#SourceDir}\build\installer\database.sqlite"; DestDir: "{app}\database"; Flags: onlyifdoesntexist

; Embedded PHP Portable Runtime
Source: "{#SourceDir}\build\php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs

; Laravel Application Structure
Source: "{#SourceDir}\app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\bootstrap\*"; DestDir: "{app}\bootstrap"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "cache\*.php"
Source: "{#SourceDir}\config\*"; DestDir: "{app}\config"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\database\*"; DestDir: "{app}\database"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "*.sqlite,*.db"
Source: "{#SourceDir}\lang\*"; DestDir: "{app}\lang"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\public\*"; DestDir: "{app}\public"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\resources\*"; DestDir: "{app}\resources"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\routes\*"; DestDir: "{app}\routes"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\artisan"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourceDir}\composer.json"; DestDir: "{app}"; Flags: ignoreversion

; Vendor Dependencies (Excluding bloated node_modules & old experimental binaries)
Source: "{#SourceDir}\vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "nativephp\php-bin\*,nativephp\desktop\node_modules\*,*.git*,tests\*,test\*"

[Dirs]
Name: "{app}\storage"
Name: "{app}\storage\app"
Name: "{app}\storage\app\public"
Name: "{app}\storage\framework"
Name: "{app}\storage\framework\cache"
Name: "{app}\storage\framework\cache\data"
Name: "{app}\storage\framework\sessions"
Name: "{app}\storage\framework\views"
Name: "{app}\storage\logs"
Name: "{app}\bootstrap\cache"

[Icons]
Name: "{autoprograms}\سامانه مالی نپتون"; Filename: "{app}\{#AppExeName}"; IconFilename: "{app}\{#AppExeName}"
Name: "{autodesktop}\سامانه مالی نپتون"; Filename: "{app}\{#AppExeName}"; IconFilename: "{app}\{#AppExeName}"; Tasks: desktopicon
Name: "{autoprograms}\توقف سرویس نپتون"; Filename: "{app}\StopNeptune.bat"; IconFilename: "{app}\{#AppExeName}"

[Run]
Filename: "{app}\{#AppExeName}"; Description: "اجرای فوری سامانه مالی نپتون (Neptune Financial OS)"; Flags: nowait postinstall skipifsilent

[Code]
procedure InitializeWizard;
begin
  WizardForm.WelcomeLabel1.Font.Name := 'Segoe UI';
  WizardForm.WelcomeLabel1.Font.Size := 13;
  WizardForm.WelcomeLabel1.Font.Style := [fsBold];

  WizardForm.FinishedHeadingLabel.Font.Name := 'Segoe UI';
  WizardForm.FinishedHeadingLabel.Font.Size := 13;
  WizardForm.FinishedHeadingLabel.Font.Style := [fsBold];
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  AppDir: string;
  PhpExe: string;
  ResultCode: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    AppDir := ExpandConstant('{app}');
    PhpExe := AppDir + '\php\php.exe';

    // Generate Encryption Key and clear stale cache on install
    if FileExists(PhpExe) and FileExists(AppDir + '\artisan') then
    begin
      Exec(PhpExe, 'artisan key:generate --force', AppDir, SW_HIDE, ewWaitUntilTerminated, ResultCode);
      Exec(PhpExe, 'artisan optimize:clear', AppDir, SW_HIDE, ewWaitUntilTerminated, ResultCode);
    end;
  end;
end;
