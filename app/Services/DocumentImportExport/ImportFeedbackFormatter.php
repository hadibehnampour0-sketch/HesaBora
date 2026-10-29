<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

/**
 * Clean-Room User Feedback Generator for Batch Import Operations.
 *
 * Formats operation summary metrics into user-facing status messages.
 *
 * License: MIT
 */
class ImportFeedbackFormatter
{
    public function format(array $outcome): array
    {
        $importedDocs = (int) ($outcome['documents_created'] ?? 0);
        $importedSubjects = (int) ($outcome['subjects_created'] ?? 0);
        $bypassedDocs = (int) ($outcome['documents_skipped'] ?? 0);
        $invalidRows = (int) ($outcome['rows_skipped'] ?? 0);
        $failureMessages = (array) ($outcome['errors'] ?? []);

        $notificationMessages = $this->assembleNotificationText(
            $importedDocs,
            $importedSubjects,
            $bypassedDocs,
            $invalidRows,
            $failureMessages
        );

        $severityTier = $this->calculateSeverityTier($importedDocs, $bypassedDocs, $invalidRows, $failureMessages);

        return [
            'type' => $severityTier,
            'lines' => $notificationMessages,
        ];
    }

    protected function assembleNotificationText(
        int $docs,
        int $subjects,
        int $skipped,
        int $corruptRows,
        array $errors
    ): array {
        $lines = [];

        if ($docs === 0 && $skipped <= 0) {
            $lines[] = __('No documents were imported.');
        } elseif ($docs > 0) {
            $lines[] = __(':docs documents and :subjects subjects imported successfully.', [
                'docs' => formatNumber($docs),
                'subjects' => formatNumber($subjects),
            ]);
        }

        if ($skipped > 0) {
            $lines[] = __(':skipped documents were skipped.', ['skipped' => formatNumber($skipped)]);
        }

        if ($corruptRows > 0) {
            $lines[] = __(':rows rows were skipped because their column count did not match the header.', [
                'rows' => $corruptRows,
            ]);
        }

        $sampleErrors = array_slice($errors, 0, 5);
        foreach ($sampleErrors as $err) {
            $lines[] = (string) $err;
        }

        $excess = count($errors) - 5;
        if ($excess > 0) {
            $lines[] = __('… and :n more.', ['n' => $excess]);
        }

        return $lines;
    }

    protected function calculateSeverityTier(int $docs, int $skipped, int $corruptRows, array $errors): string
    {
        if ($docs === 0) {
            return 'error';
        }

        if ($skipped > 0 || $corruptRows > 0 || ! empty($errors)) {
            return 'warning';
        }

        return 'success';
    }
}
