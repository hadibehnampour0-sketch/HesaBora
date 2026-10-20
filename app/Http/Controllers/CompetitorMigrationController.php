<?php

namespace App\Http\Controllers;

use App\Services\Migration\CompetitorMigrationService;
use Illuminate\Http\Request;

class CompetitorMigrationController extends Controller
{
    public function __construct(
        protected CompetitorMigrationService $migrationService
    ) {}

    public function index()
    {
        return view('migration.index');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string|in:holoo,sepidar,universal_json',
            'file' => 'nullable|file|mimes:json,txt',
            'raw_json' => 'nullable|string',
        ]);

        $rawContent = '';
        if ($request->hasFile('file')) {
            $rawContent = file_get_contents($request->file('file')->getRealPath());
        } elseif ($request->filled('raw_json')) {
            $rawContent = $request->input('raw_json');
        } else {
            return back()->with('error', 'لطفاً یک فایل پشتیبان یا داده معتبر ارائه دهید.');
        }

        try {
            $payload = $this->migrationService->parse($rawContent, $request->input('source_type'));
            $healthCheck = $this->migrationService->analyzeAndHealthCheck($payload);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'summary' => $payload->toArray(),
                    'health' => [
                        'passed' => $healthCheck->passed,
                        'errors' => $healthCheck->errors,
                        'warnings' => $healthCheck->warnings,
                        'reconciliation' => $healthCheck->reconciliation,
                    ],
                ]);
            }

            return view('migration.preview', [
                'payload' => $payload,
                'health' => $healthCheck,
                'sourceType' => $request->input('source_type'),
                'rawJson' => $rawContent,
            ]);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', 'خطا در خواندن داده‌ها: ' . $e->getMessage());
        }
    }

    public function execute(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string',
            'raw_json' => 'required|string',
            'auto_repair' => 'nullable|boolean',
        ]);

        $companyId = (int) getActiveCompany();
        $payload = $this->migrationService->parse($request->input('raw_json'), $request->input('source_type'));
        $autoRepair = $request->boolean('auto_repair', true);

        $result = $this->migrationService->commitMigration(
            user: $request->user(),
            companyId: $companyId,
            payload: $payload,
            autoRepair: $autoRepair
        );

        if (! $result->successful) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $result->errors], 422);
            }
            return back()->with('error', implode(' - ', $result->errors));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'مهاجرت با موفقیت تکمیل شد.',
                'imported_customers' => $result->importedCustomersCount,
                'imported_products' => $result->importedProductsCount,
                'imported_cheques' => $result->importedChequesCount,
                'opening_document_id' => $result->openingDocument?->id,
                'repaired' => $result->repairedIssues,
            ]);
        }

        return redirect()->route('migration.index')->with('success', sprintf(
            'اطلاعات با موفقیت مهاجرت داده شد: %d مشتری، %d کالا، %d فقره چک و سند افتتاحیه متوازن #%s ایجاد گردید.',
            $result->importedCustomersCount,
            $result->importedProductsCount,
            $result->importedChequesCount,
            $result->openingDocument?->id
        ));
    }
}
