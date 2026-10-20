<?php

namespace App\Services\Migration\DTO;

class MigrationPayload
{
    public function __construct(
        public array $customers = [],
        public array $products = [],
        public array $cheques = [],
        public array $chartOfAccounts = [],
        public array $journalEntries = [],
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'customers_count' => count($this->customers),
            'products_count' => count($this->products),
            'cheques_count' => count($this->cheques),
            'accounts_count' => count($this->chartOfAccounts),
            'journal_entries_count' => count($this->journalEntries),
            'metadata' => $this->metadata,
        ];
    }
}
