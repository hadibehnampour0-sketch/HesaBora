<?php

namespace App\Http\Controllers;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Services\BackupEngineService;
use App\Services\FiscalYearService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Display the Enterprise Backup & Disaster Recovery Center dashboard.
     */
    public function index(BackupEngineService $backupEngine): View
    {
        $backups = $backupEngine->listBackups();
        $settings = $backupEngine->getSettings();
        $activeCompany = Company::first();

        return view('backups.index', compact('backups', 'settings', 'activeCompany'));
    }

    /**
     * Create and download a full system backup (.dakhl package).
     */
    public function fullExport(Request $request, BackupEngineService $backupEngine): BinaryFileResponse|RedirectResponse
    {
        try {
            $includeDocs = $request->boolean('include_documents', true);
            $sendTelegram = $request->boolean('send_telegram', false);
            $targetDir = $request->input('target_dir');

            $result = $backupEngine->createFullBackup([
                'include_documents' => $includeDocs,
                'send_telegram' => $sendTelegram,
                'target_path' => $targetDir,
            ]);

            if ($request->input('action') === 'save_only') {
                return redirect()->route('backups.index')
                    ->with('success', __('Full backup :name created successfully (:size MB).', [
                        'name' => $result['filename'],
                        'size' => $result['size_mb'],
                    ]));
            }

            return response()->download($result['path'], $result['filename'], [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (Exception $e) {
            return redirect()->route('backups.index')
                ->with('error', __('Backup failed: :msg', ['msg' => $e->getMessage()]));
        }
    }

    /**
     * Download an existing backup file.
     */
    public function download(Request $request, string $filename): BinaryFileResponse|RedirectResponse
    {
        $safeName = basename($filename);
        $path = storage_path("app/backups/{$safeName}");

        if (! File::exists($path)) {
            return redirect()->route('backups.index')
                ->with('error', __('Backup file does not exist.'));
        }

        return response()->download($path, $safeName);
    }

    /**
     * Safe 1-Click Restore with pre-restore safety snapshot.
     */
    public function fullRestore(Request $request, BackupEngineService $backupEngine): RedirectResponse
    {
        $validated = $request->validate([
            'backup_file' => 'nullable|string',
            'upload_file' => 'nullable|file|max:204800',
        ]);

        try {
            $filePath = null;

            if ($request->hasFile('upload_file')) {
                $file = $request->file('upload_file');
                $filePath = $file->getRealPath();
            } elseif (! empty($validated['backup_file'])) {
                $filePath = storage_path('app/backups/' . basename($validated['backup_file']));
            }

            if (! $filePath || ! File::exists($filePath)) {
                return redirect()->route('backups.index')
                    ->with('error', __('Please specify a valid backup file to restore.'));
            }

            $result = $backupEngine->restoreFullBackup($filePath);

            return redirect()->route('backups.index')
                ->with('success', $result['message']);
        } catch (Exception $e) {
            return redirect()->route('backups.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Save backup preferences (Auto-backup, flash drive path, Telegram).
     */
    public function saveSettings(Request $request, BackupEngineService $backupEngine): RedirectResponse
    {
        $validated = $request->validate([
            'auto_backup_on_exit' => 'nullable|boolean',
            'daily_scheduled_backup' => 'nullable|boolean',
            'local_target_path' => 'nullable|string|max:255',
            'telegram_enabled' => 'nullable|boolean',
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_chat_id' => 'nullable|string|max:255',
            'include_documents' => 'nullable|boolean',
        ]);

        $backupEngine->saveSettings([
            'auto_backup_on_exit' => $request->boolean('auto_backup_on_exit'),
            'daily_scheduled_backup' => $request->boolean('daily_scheduled_backup'),
            'local_target_path' => trim((string) ($validated['local_target_path'] ?? '')),
            'telegram_enabled' => $request->boolean('telegram_enabled'),
            'telegram_bot_token' => trim((string) ($validated['telegram_bot_token'] ?? '')),
            'telegram_chat_id' => trim((string) ($validated['telegram_chat_id'] ?? '')),
            'include_documents' => $request->boolean('include_documents', true),
        ]);

        return redirect()->route('backups.index')
            ->with('success', __('Backup preferences updated successfully.'));
    }

    /**
     * Test sending backup alert to Telegram Bot.
     */
    public function testTelegram(Request $request, BackupEngineService $backupEngine): JsonResponse
    {
        $backups = $backupEngine->listBackups();
        if (empty($backups)) {
            $created = $backupEngine->createFullBackup(['include_documents' => false]);
            $testFile = $created['path'];
        } else {
            $testFile = $backups[0]['path'];
        }

        $sent = $backupEngine->dispatchToTelegram($testFile);

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? __('Test backup successfully sent to Telegram!')
                : __('Could not connect to Telegram. Please check your Bot Token and Chat ID.'),
        ]);
    }
    public function create()
    {
        $previousYears = auth()->user()->companies()->orderByDesc('fiscal_year')->get();
        $currentYear = toEnglish(jdate('Y'));

        return view('backups.create', compact('previousYears', 'currentYear'));
    }

    public function documentFilesSize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => [
                'required',
                Rule::exists('company_user', 'company_id')->where('user_id', auth()->user()->id),
            ],
        ]);

        $bytes = FiscalYearService::documentFilesSizeBytes($validated['source_id']);

        return response()->json(['size_mb' => round($bytes / (1024 * 1024), 2)]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'source_id' => [
                'required',
                Rule::exists('company_user', 'company_id')->where('user_id', auth()->user()->id),
            ],
            'tables_to_backup' => 'required|array',
            'tables_to_backup.*' => 'string|in:'.implode(',', array_map(fn ($case) => $case->value, FiscalYearSection::cases())),
        ]);

        $tables = $validated['tables_to_backup'];
        $documentsVal = FiscalYearSection::DOCUMENTS->value;
        $documentFilesVal = FiscalYearSection::DOCUMENT_FILES->value;

        if (in_array($documentFilesVal, $tables) && ! in_array($documentsVal, $tables)) {
            $tables = array_values(array_filter($tables, fn ($s) => $s !== $documentFilesVal));
        }

        $includeDocumentFiles = in_array($documentFilesVal, $tables);

        $company = Company::findOrFail($validated['source_id']);
        $exportData = FiscalYearService::exportData($validated['source_id'], $tables);

        if ($includeDocumentFiles) {
            FiscalYearService::documentFilesInBase64($exportData);
        }

        $safeName = preg_replace('/\s+/', '-', trim($company->name));
        $fileBaseName = "Amir-{$safeName}-".now()->format('Y-m-d-H-i');

        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (! $jsonContent) {
            return redirect()->back()->with('error', __('Failed to encode backup data: :message', ['message' => json_last_error_msg()]));
        }

        $zipFilePath = tempnam(sys_get_temp_dir(), 'backup_zip_');
        if (! $zipFilePath || ! file_exists($zipFilePath)) {
            return redirect()->back()->with('error', __('Failed to create temporary file for ZIP.'));
        }

        $zip = new ZipArchive;
        if (! $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            @unlink($zipFilePath);

            return redirect()->back()->with('error', __('Failed to open ZIP archive.'));
        }

        if (! $zip->addFromString("{$fileBaseName}.json", $jsonContent)) {
            $zip->close();
            @unlink($zipFilePath);

            return redirect()->back()->with('error', __('Failed to add JSON to ZIP.'));
        }

        $zip->close();

        return response()->download($zipFilePath, "{$fileBaseName}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function upload()
    {
        return view('backups.upload');
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:zip|max:102400',
            'fiscal_year' => 'required|integer|min:1', // positive integer
            'company_name' => 'required|max:50|string|regex:/^[\w\d\s]*$/u',
        ]);

        $uploadedFile = $request->file('file');
        $zipPath = $uploadedFile->getPathname();
        $zip = new ZipArchive;

        if (! $zip->open($zipPath)) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Uploaded backup file is invalid.'));
        }

        $jsonContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            if (! is_array($entry) || ! isset($entry['name'])) {
                continue;
            }

            $entryName = $entry['name'];
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) === 'json') {
                $stream = $zip->getStream($entryName);

                if (! $stream) {
                    if ($zipPath && file_exists($zipPath)) {
                        @unlink($zipPath);
                    }

                    return redirect()->back()->with('error', __('Failed to read JSON file from ZIP archive.'));
                }

                $jsonContent = stream_get_contents($stream);
                fclose($stream);
                break;
            }
        }

        if (! $jsonContent) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('No JSON file found in ZIP archive.'));
        }

        $importData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Invalid JSON: :msg', ['msg' => json_last_error_msg()]));
        }

        if (! is_array($importData)) {
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return redirect()->back()->with('error', __('Invalid JSON structure in backup file.'));
        }

        $newFiscalYearData = [
            'name' => $validated['company_name'],
            'fiscal_year' => (int) $validated['fiscal_year'],
        ];
        try {
            FiscalYearService::importData($importData, $newFiscalYearData);
        } finally {
            $zip->close();

            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }

        return redirect()->route('home')->with('success', __('Company backup file imported successfully.'));
    }
}
