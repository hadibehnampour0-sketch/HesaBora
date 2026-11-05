using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

namespace NeptuneERP
{
    static class Program
    {
        private const int Port = 8000;
        private const string Host = "127.0.0.1";
        private const string AppUrl = "http://127.0.0.1:8000";
        private const string MutexId = "Global\\NeptuneFinancialOS_SingleInstanceMutex_2026";

        private static Process _phpProcess = null;
        private static NotifyIcon _trayIcon = null;
        private static string _baseDir;

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            _baseDir = AppDomain.CurrentDomain.BaseDirectory;

            // Handle --stop command
            if (args.Length > 0 && (args[0] == "--stop" || args[0] == "-stop" || args[0] == "/stop"))
            {
                StopAllPhpInstances(_baseDir);
                return;
            }

            // Ensure Single Instance
            bool isFirstInstance;
            using (Mutex mutex = new Mutex(true, MutexId, out isFirstInstance))
            {
                if (!isFirstInstance)
                {
                    // If another instance is already running, just launch the browser window and exit
                    LaunchBrowserApp(AppUrl);
                    return;
                }

                // Verify PHP executable exists
                string phpExe = Path.Combine(_baseDir, @"php\php.exe");
                string publicDir = Path.Combine(_baseDir, "public");

                if (!File.Exists(phpExe))
                {
                    MessageBox.Show(
                        "فایل اجرایی موتور پردازشی در مسیر زیر یافت نشد:\n" + phpExe + "\nلطفاً از نصب صحیح برنامه اطمینان حاصل فرمایید.",
                        "سامانه مالی نپتون - خطا",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Error,
                        MessageBoxDefaultButton.Button1,
                        MessageBoxOptions.RtlReading | MessageBoxOptions.RightAlign
                    );
                    return;
                }

                // Start PHP server if not already running
                if (!IsPortOpen(Host, Port))
                {
                    _phpProcess = StartPhpServer(phpExe, publicDir);

                    // Wait up to 6 seconds for server socket to respond
                    bool isUp = false;
                    for (int i = 0; i < 30; i++)
                    {
                        Thread.Sleep(200);
                        if (IsPortOpen(Host, Port))
                        {
                            isUp = true;
                            break;
                        }
                    }

                    if (!isUp)
                    {
                        MessageBox.Show(
                            "راه‌اندازی سرویس داخلی نپتون با تاخیر مواجه شد. لطفاً بررسی کنید پورت ۸۰۰۰ توسط برنامه دیگری اشغال نشده باشد.",
                            "سامانه مالی نپتون - هشدار",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Warning,
                            MessageBoxDefaultButton.Button1,
                            MessageBoxOptions.RtlReading | MessageBoxOptions.RightAlign
                        );
                    }
                }

                // Register process exit cleanup
                AppDomain.CurrentDomain.ProcessExit += OnProcessExit;
                Application.ApplicationExit += OnProcessExit;

                // Setup System Tray Icon
                SetupTrayIcon();

                // Open Browser Window
                LaunchBrowserApp(AppUrl);

                // Run Application Message Loop (Keeps server alive in system tray!)
                Application.Run();
            }
        }

        private static void SetupTrayIcon()
        {
            _trayIcon = new NotifyIcon();

            // Try to load icon from exe or file
            try
            {
                string iconPath = Path.Combine(_baseDir, @"public\images\neptune.ico");
                if (File.Exists(iconPath))
                {
                    _trayIcon.Icon = new Icon(iconPath);
                }
                else
                {
                    _trayIcon.Icon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
                }
            }
            catch
            {
                _trayIcon.Icon = SystemIcons.Application;
            }

            _trayIcon.Text = "سامانه جامع مالی نپتون (در حال اجرا)";
            _trayIcon.Visible = true;

            // Context Menu
            ContextMenu contextMenu = new ContextMenu();

            MenuItem openItem = new MenuItem("🌐 باز کردن سامانه نپتون", (s, e) => LaunchBrowserApp(AppUrl));
            openItem.DefaultItem = true;

            MenuItem restartItem = new MenuItem("🔄 راه‌اندازی مجدد سرور", (s, e) => RestartServer());

            MenuItem exitItem = new MenuItem("❌ خروج کامل از برنامه", (s, e) => ExitApplication());

            contextMenu.MenuItems.Add(openItem);
            contextMenu.MenuItems.Add(restartItem);
            contextMenu.MenuItems.Add("-");
            contextMenu.MenuItems.Add(exitItem);

            _trayIcon.ContextMenu = contextMenu;

            // Double click opens app
            _trayIcon.DoubleClick += (s, e) => LaunchBrowserApp(AppUrl);

            // Show friendly notification balloon
            try
            {
                _trayIcon.ShowBalloonTip(
                    3000,
                    "سامانه مالی نپتون فعال شد",
                    "برای مدیریت مالی، آیکون نپتون در کنار ساعت ویندوز در دسترس شماست.",
                    ToolTipIcon.Info
                );
            }
            catch { }
        }

        private static void RestartServer()
        {
            StopCurrentPhp();
            Thread.Sleep(500);

            string phpExe = Path.Combine(_baseDir, @"php\php.exe");
            string publicDir = Path.Combine(_baseDir, "public");

            if (!IsPortOpen(Host, Port))
            {
                _phpProcess = StartPhpServer(phpExe, publicDir);
            }

            LaunchBrowserApp(AppUrl);
        }

        private static void ExitApplication()
        {
            if (_trayIcon != null)
            {
                _trayIcon.Visible = false;
                _trayIcon.Dispose();
                _trayIcon = null;
            }

            StopCurrentPhp();
            Application.Exit();
            Environment.Exit(0);
        }

        private static void OnProcessExit(object sender, EventArgs e)
        {
            StopCurrentPhp();
        }

        private static void StopCurrentPhp()
        {
            try
            {
                if (_phpProcess != null && !_phpProcess.HasExited)
                {
                    _phpProcess.Kill();
                    _phpProcess = null;
                }
            }
            catch { }

            StopAllPhpInstances(_baseDir);
        }

        private static bool IsPortOpen(string host, int port)
        {
            try
            {
                using (var client = new TcpClient())
                {
                    var result = client.BeginConnect(host, port, null, null);
                    bool success = result.AsyncWaitHandle.WaitOne(300);
                    if (!success) return false;
                    client.EndConnect(result);
                    return true;
                }
            }
            catch
            {
                return false;
            }
        }

        private static Process StartPhpServer(string phpExe, string publicDir)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = string.Format("-S {0}:{1} -t \"{2}\"", Host, Port, publicDir),
                    WorkingDirectory = Path.GetDirectoryName(publicDir),
                    CreateNoWindow = true,
                    WindowStyle = ProcessWindowStyle.Hidden,
                    UseShellExecute = false
                };
                return Process.Start(psi);
            }
            catch (Exception ex)
            {
                MessageBox.Show("خطا در اجرای سرور: " + ex.Message, "نپتون");
                return null;
            }
        }

        private static void LaunchBrowserApp(string url)
        {
            string edge = FindBrowserExecutable("Microsoft\\Edge\\Application\\msedge.exe");
            string chrome = FindBrowserExecutable("Google\\Chrome\\Application\\chrome.exe");

            string browserPath = !string.IsNullOrEmpty(edge) ? edge : chrome;
            string appArgs = string.Format("--app=\"{0}\" --window-size=1366,850 --window-position=center", url);

            if (!string.IsNullOrEmpty(browserPath))
            {
                try
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = browserPath,
                        Arguments = appArgs,
                        UseShellExecute = false
                    };
                    Process.Start(psi);
                    return;
                }
                catch { }
            }

            // Fallback: Default system browser
            try
            {
                Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                MessageBox.Show("خطا در باز کردن مرورگر: " + ex.Message, "نپتون");
            }
        }

        private static string FindBrowserExecutable(string relativePath)
        {
            string[] searchRoots = new string[]
            {
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles),
                Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86),
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData)
            };

            foreach (string root in searchRoots)
            {
                if (string.IsNullOrEmpty(root)) continue;
                string candidate = Path.Combine(root, relativePath);
                if (File.Exists(candidate))
                {
                    return candidate;
                }
            }

            return null;
        }

        private static void StopAllPhpInstances(string baseDir)
        {
            try
            {
                var processes = Process.GetProcessesByName("php");
                string expectedExe = Path.Combine(baseDir, @"php\php.exe").ToLowerInvariant();

                foreach (var p in processes)
                {
                    try
                    {
                        if (p.MainModule != null && p.MainModule.FileName.ToLowerInvariant() == expectedExe)
                        {
                            p.Kill();
                        }
                    }
                    catch { }
                }
            }
            catch { }
        }
    }
}
