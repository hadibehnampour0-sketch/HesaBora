<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Clean-room implementation of Parsian transaction ingestion pipeline.
 *
 * Coordinates parsing, grouping, and transactional dispatch of Parsian ledgers.
 *
 * License: MIT
 */
final class ParsianTransactionIngestor
{
    public function __construct(
        private readonly ImportSubjectResolver $resolver,
        private readonly DocumentImportFormat $format
    ) {}

    public function process(array $entries, User $author): array
    {
        $rows = collect($entries);
        $bridge = new ParsianAccountBridge($this->resolver, $rows);
        $batches = $this->partitionVoucherBatches($rows);

        foreach ($batches as $batchRef => $batchItems) {
            try {
                $this->ingestVoucherBatch($batchItems, $author, $bridge);
            } catch (\Throwable $e) {
                $this->format->recordEncounteredError(
                    __('Document :key was not imported: :reason', [
                        'key' => $batchRef,
                        'reason' => $e->getMessage(),
                    ])
                );
                $this->format->tallySkippedDocument();
                Log::warning("Parsian batch failed {$batchRef}: {$e->getMessage()}");
            }
        }

        return $this->format->getExecutionMetrics();
    }

    private function partitionVoucherBatches(Collection $rows): Collection
    {
        $groups = collect();

        foreach ($rows as $row) {
            $num = trim((string) ($row['Sanad_Num'] ?? ''));
            $date = trim((string) ($row['SanadDate'] ?? ''));

            if ($num === '' || $date === '') {
                $this->format->tallySkippedRow();
                continue;
            }

            $tag = $num.':'.$date;
            if (! $groups->has($tag)) {
                $groups->put($tag, collect());
            }
            $groups->get($tag)->push($row);
        }

        return $groups;
    }

    private function ingestVoucherBatch(Collection $lines, User $author, ParsianAccountBridge $bridge): void
    {
        $first = $lines->first();
        $journalNo = (float) ($first['Sanad_Num'] ?? 0);
        $jalaliDate = trim((string) ($first['SanadDate'] ?? ''));

        if ($journalNo <= 0.0 || $jalaliDate === '') {
            $this->format->tallySkippedDocument();

            return;
        }

        $solarToMiladi = jalali_to_gregorian_date($jalaliDate, '-');
        if ($solarToMiladi === '' || Document::query()->where('number', $journalNo)->where('date', $solarToMiladi)->exists()) {
            $this->format->tallySkippedDocument();

            return;
        }

        $transactions = [];
        foreach ($lines as $item) {
            $transactions[] = $bridge->transformRowToTransactionLine($item);
        }

        DocumentService::createDocument(
            $author,
            [
                'number' => $journalNo,
                'date' => $solarToMiladi,
                'title' => '',
            ],
            $transactions
        );

        $this->format->tallyCreatedDocument();
    }
}
