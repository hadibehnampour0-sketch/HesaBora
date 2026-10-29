<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use Illuminate\Http\Request;

/**
 * Clean-Room Validation Handler for Document Import/Export HTTP Payloads.
 *
 * Verifies request parameters against system column constraints and registered formats.
 *
 * License: MIT
 */
class DocumentImportExportValidator
{
    public function __construct(
        protected readonly DocumentImportFormatRegistry $registry
    ) {}

    public function validateExportPayload(Request $request): array
    {
        $validColumns = implode(',', AccountingFileSchema::ALL_COLUMNS);

        return $request->validate([
            'number' => 'nullable|numeric',
            'date' => 'nullable|string',
            'text' => 'nullable|string|max:255',
            'status' => 'nullable|in:all,approved,unapproved',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string',
            'start_document_number' => 'nullable|numeric',
            'end_document_number' => 'nullable|numeric',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'columns_selected' => 'nullable|boolean',
            'columns' => 'nullable|array',
            'columns.*' => 'string|in:'.$validColumns,
        ]);
    }

    public function validateImportPayload(Request $request): void
    {
        $registeredDrivers = implode(',', $this->registry->keys());

        $request->validate([
            'format' => 'required|in:'.$registeredDrivers,
            'file' => 'required|file|mimes:csv,txt|max:51200',
        ], [
            'format.required' => __('Please select an import format.'),
            'format.in' => __('Please select a valid import format.'),
            'file.required' => __('Please select a CSV file to import.'),
            'file.mimes' => __('Only CSV files are accepted.'),
            'file.max' => __('The file may not be larger than 50 MB.'),
        ]);
    }
}
