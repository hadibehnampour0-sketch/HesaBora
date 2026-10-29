<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Enums\SubjectType;

/**
 * Clean-room syntax decoder for standard double-entry journal interchange format.
 *
 * Implements code splitting/merging, financial nature detection, and permanence parsing.
 *
 * License: MIT
 */
final class StandardJournalDecoder
{
    public static function splitCode(string $raw): array
    {
        $sanitized = trim($raw);
        if ($sanitized === '') {
            return ['', '', ''];
        }

        $segments = str_split($sanitized, 3);

        return [
            $segments[0] ?? '',
            $segments[1] ?? '',
            $segments[2] ?? '',
        ];
    }

    public static function mergeSegments(string $root, string $moein, string $tafsili): array
    {
        $tokens = array_values(array_filter([
            trim(toEnglish($root)),
            trim(toEnglish($moein)),
            trim(toEnglish($tafsili)),
        ], fn (string $item): bool => $item !== ''));

        if (empty($tokens)) {
            return ['', ''];
        }

        $compound = implode('', $tokens);
        $parent = count($tokens) > 1 ? implode('', array_slice($tokens, 0, -1)) : '';

        return [$compound, $parent];
    }

    public static function parseNature(string $raw): ?string
    {
        $lowered = mb_strtolower(trim(toEnglish($raw)));
        if ($lowered === '') {
            return null;
        }

        $enumVal = SubjectType::tryFromName($lowered);
        if ($enumVal !== null) {
            return $enumVal->valueName();
        }

        return match (true) {
            in_array($lowered, ['debtor', 'بدهکار', mb_strtolower(__('Debtor'))], true) => 'debtor',
            in_array($lowered, ['creditor', 'بستانکار', mb_strtolower(__('Creditor'))], true) => 'creditor',
            in_array($lowered, ['both', 'هر دو', 'هردو', mb_strtolower(__('Both'))], true) => 'both',
            default => null,
        };
    }

    public static function parsePermanence(string $raw): ?bool
    {
        $lowered = mb_strtolower(trim(toEnglish($raw)));
        if ($lowered === '') {
            return null;
        }

        return match (true) {
            in_array($lowered, ['1', 'true', 'yes', 'permanent', 'دائم', 'دائمی', mb_strtolower(__('Permanent'))], true) => true,
            in_array($lowered, ['0', 'false', 'no', 'temporary', 'موقت', mb_strtolower(__('Temporary'))], true) => false,
            default => null,
        };
    }
}
