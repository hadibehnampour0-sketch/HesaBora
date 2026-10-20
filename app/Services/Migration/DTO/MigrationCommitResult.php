<?php

namespace App\Services\Migration\DTO;

use App\Models\Document;

class MigrationCommitResult
{
    public function __construct(
        public bool $successful,
        public int $importedCustomersCount = 0,
        public int $importedProductsCount = 0,
        public int $importedChequesCount = 0,
        public ?Document $openingDocument = null,
        public array $errors = [],
        public array $repairedIssues = []
    ) {}

    public static function success(
        int $customersCount,
        int $productsCount,
        int $chequesCount,
        Document $openingDocument,
        array $repaired = []
    ): self {
        return new self(
            successful: true,
            importedCustomersCount: $customersCount,
            importedProductsCount: $productsCount,
            importedChequesCount: $chequesCount,
            openingDocument: $openingDocument,
            repairedIssues: $repaired
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            successful: false,
            errors: [$error]
        );
    }
}
