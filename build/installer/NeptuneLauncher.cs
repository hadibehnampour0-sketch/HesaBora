using System;
using System.Diagnostics;
using System.IO;
using System.Net.Sockets;
using System.Threading;
using System.Windows.Forms;

namespace NeptuneERP
{
    static class Program
    {
        private const int Port = 8000;
        private const string Host = "127.0.0.1";
        private const string AppUrl = "http://127.0.0.1:8000";

        [STAThread]
        static void Main(string[] args)
        {
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;

            // Handle --stop command
            if (args.Length > 0 && (args[0] == "--stop" || args[0] == "-stop" || args[0] == "/stop"))
            {
                StopServer(baseDir);
                return;
            }

            // Verify PHP executable exists
            string phpExe = Path.Combine(baseDir, @"php\php.exe");
            string publicDir = Path.Combine(baseDir, "public");

            if (!File.Exists(phpExe))
            {
                MessageBox.Show(
                    "فایل اجرایی موتور PHP در مسیر زیر یافت نشد:\n" + phpExe + "\nلطفاً از صحت نصب برنامه اطمینان حاصل فرمایید.",
                    "سامانه مالی نپتون - خطا",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RtlReading | MessageBoxOptions.RightAlign
                );
                return;
            }

            // Check if server is already running
            Process phpProcess = null;
            if (!IsServerRunning(Host, Port))
            {
                phpProcess = StartPhpServer(phpExe, publicDir);

                // Wait up to 5 seconds for server to start listening
                bool isUp = false;
                for (int i = 0; i < 25; i++)
                {
                    Thread.Sleep(200);
                    if (IsServerRunning(Host, Port))
                    {
                        isUp = true;
                        break;
                    }
                }

                if (!isUp)
                {
                    MessageBox.Show(
                        "راه‌اندازی سرویس داخلی نپتون با مشکل مواجه شد. لطفاً بررسی کنید پورت ۸۰۰۰ اشغال نباشد.",
                        "سامانه مالی نپتون - هشدار",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Warning,
                        MessageBoxDefaultButton.Button1,
                        MessageBoxOptions.RtlReading | MessageBoxOptions.RightAlign
                    );
                }
            }

            // Launch Browser in Dedicated App Window Mode
            Process browserProcess = LaunchBrowserApp(AppUrl);

            // If we started both PHP and the browser, monitor browser lifecycle
            if (phpProcess != null && browserProcess != null)
            {
                try
                {
                    browserProcess.WaitForExit();
                }
                catch { }

                // Once the browser window is closed by the user, terminate our background PHP server cleanly
                try
                {
                    if (!phpProcess.HasExited)
                    {
                        phpProcess.Kill();
                    }
                }
                catch { }
            }
        }

        private static bool IsServerRunning(string host, int port)
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

        private static Process LaunchBrowserApp(string url)
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
                    return Process.Start(psi);
                }
                catch { }
            }

            // Fallback: Default system browser
            try
            {
                return Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
            }
            catch (Exception ex)
            {
                MessageBox.Show("خطا در باز کردن مرورگر: " + ex.Message, "نپتون");
                return null;
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

        private static void StopServer(string baseDir)
        {
            try
            {
                var processes = Process.GetProcessesByName("php");
                string expectedExe = Path.Combine(baseDir, @"php\php.exe").ToLowerInvariant();
                int killed = 0;

                foreach (var p in processes)
                {
                    try
                    {
                        if (p.MainModule != null && p.MainModule.FileName.ToLowerInvariant() == expectedExe)
                        {
                            p.Kill();
                            killed++;
                        }
                    }
                    catch { }
                }

                MessageBox.Show(
                    string.Format("سرویس سامانه مالی نپتون با موفقیت متوقف شد ({0} فرایند).", killed),
                    "سامانه مالی نپتون",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information,
                    MessageBoxDefaultButton.Button1,
                    MessageBoxOptions.RtlReading | MessageBoxOptions.RightAlign
                );
            }
            catch (Exception ex)
            {
                MessageBox.Show("خطا در توقف سرویس: " + ex.Message, "نپتون");
            }
        }
    }
}
