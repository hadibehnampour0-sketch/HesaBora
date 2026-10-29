<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

/**
 * Backward compatibility bridge for legacy FreeAmir import format key.
 *
 * Delegates all parsing, schema normalization, and document persistence
 * to the clean-room DakhlStandardImportFormat implementation.
 *
 * License: MIT
 */
class FreeAmirImportFormat extends DakhlStandardImportFormat
{
    public const FORMAT_KEY = 'free_amir';

    public function key(): string
    {
        return self::FORMAT_KEY;
    }

    public function label(): string
    {
        return __('Free Amir');
    }
}
