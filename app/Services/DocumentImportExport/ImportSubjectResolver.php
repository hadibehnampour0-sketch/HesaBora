<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Enums\SubjectType;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection as EloquentSet;
use Illuminate\Support\Facades\Log;

/**
 * Clean-Room Implementation: Account Hierarchy Resolution & Synthesis Engine.
 *
 * Implements authoritative resolution of Chart of Accounts entities during import workflows.
 * Guarantees company scoping, parental ancestry integrity, and financial nature consistency.
 *
 * License: MIT
 */
class ImportSubjectResolver
{
    /** Database column constants */
    protected const FIELD_CODE = 'code';

    protected const FIELD_NAME = 'name';

    protected const FIELD_PARENT = 'parent_id';

    protected const FIELD_PERMANENT = 'is_permanent';

    protected const FIELD_TYPE = 'type';

    protected const FIELD_COMPANY = 'company_id';

    /** Runtime lookup index of resolved accounts keyed by normalized code string */
    protected array $accountRegistry = [];

    /** Declared account titles extracted from the ongoing import batch */
    protected array $predefinedAccountTitles = [];

    /** Titles flagged as ambiguous because they appear under distinct account codes */
    protected array $ambiguousTitleLookup = [];

    /** Total accounts synthesized during active lifecycle */
    protected int $synthesizedCount = 0;

    /**
     * Clear runtime state and cache between batches.
     */
    public function reset(): void
    {
        $this->accountRegistry = [];
        $this->predefinedAccountTitles = [];
        $this->ambiguousTitleLookup = [];
        $this->synthesizedCount = 0;
    }

    /**
     * Prime known account titles from the import file for on-the-fly ancestor creation.
     */
    public function setKnownSubjects(array $accountTitleMappings): void
    {
        $this->predefinedAccountTitles = $accountTitleMappings;
        $tallies = [];
        foreach ($accountTitleMappings as $title) {
            $tallies[$title] = ($tallies[$title] ?? 0) + 1;
        }
        $this->ambiguousTitleLookup = [];
        foreach ($tallies as $title => $freq) {
            if ($freq > 1) {
                $this->ambiguousTitleLookup[$title] = true;
            }
        }
    }

    /**
     * Retrieve existing account or synthesize complete ancestry chain.
     */
    public function findOrCreate(
        string $targetAccountCode,
        string $accountTitleName,
        string $parentAccountCode = '',
        bool|string|null $balanceSheetPermanence = null,
        ?string $financialNatureType = null
    ): Subject {
        $cleanIdentifier = trim($targetAccountCode);
        $cleanTitle = trim($accountTitleName);

        if (array_key_exists($cleanIdentifier, $this->accountRegistry)) {
            return $this->accountRegistry[$cleanIdentifier];
        }

        $parentNode = $this->obtainParentNode(trim($parentAccountCode));
        $parentIdentifier = $parentNode?->id;

        $hasUniqueTitle = $cleanTitle !== '' && ! isset($this->ambiguousTitleLookup[$cleanTitle]);

        if ($hasUniqueTitle) {
            $matchingEntities = $this->fetchByTitleAndParent($cleanTitle, $parentIdentifier);
            $totalFound = $matchingEntities->count();

            if ($totalFound === 1) {
                return $this->accountRegistry[$cleanIdentifier] = $matchingEntities->first();
            }

            if ($totalFound > 1) {
                foreach ($matchingEntities as $candidate) {
                    if ($candidate->code === $cleanIdentifier) {
                        return $this->accountRegistry[$cleanIdentifier] = $candidate;
                    }
                }

                return $this->accountRegistry[$cleanIdentifier] = $this->instantiateAndPersist(
                    $cleanIdentifier,
                    $cleanTitle,
                    $parentNode,
                    $balanceSheetPermanence,
                    $financialNatureType
                );
            }
        }

        $persistedByCode = Subject::where(self::FIELD_CODE, $cleanIdentifier)->first();
        if ($persistedByCode instanceof Subject) {
            return $this->accountRegistry[$cleanIdentifier] = $persistedByCode;
        }

        return $this->accountRegistry[$cleanIdentifier] = $this->instantiateAndPersist(
            $cleanIdentifier,
            $cleanTitle,
            $parentNode,
            $balanceSheetPermanence,
            $financialNatureType
        );
    }

    /**
     * Resolve top-level group account enforcing balance sheet permanence and financial nature.
     */
    public function resolveTopLevel(
        string $targetAccountCode,
        string $accountTitleName,
        bool $balanceSheetPermanence,
        string $financialNatureType = 'both'
    ): Subject {
        $cleanIdentifier = trim($targetAccountCode);
        $cleanTitle = trim($accountTitleName);

        if (isset($this->accountRegistry[$cleanIdentifier])) {
            return $this->accountRegistry[$cleanIdentifier];
        }

        $existingRecord = Subject::where(self::FIELD_CODE, $cleanIdentifier)->first();
        if ($existingRecord instanceof Subject) {
            $natureEnum = SubjectType::fromName($financialNatureType);
            $sameNature = ($existingRecord->type === $natureEnum);
            $samePermanence = ((bool) $existingRecord->is_permanent === $balanceSheetPermanence);

            if ($sameNature && $samePermanence) {
                return $this->accountRegistry[$cleanIdentifier] = $existingRecord;
            }

            return $this->findOrCreate($cleanIdentifier, self::synthesizeName($cleanIdentifier), '');
        }

        return $this->accountRegistry[$cleanIdentifier] = $this->instantiateAndPersist(
            $cleanIdentifier,
            $cleanTitle,
            null,
            $balanceSheetPermanence,
            $financialNatureType
        );
    }

    /**
     * Generate localized account placeholder name when omitted in source file.
     */
    public static function synthesizeName(string $accountCodeIdentifier): string
    {
        $digits = trim($accountCodeIdentifier);
        $charCount = strlen($digits);
        $tierIndex = $charCount === 0 ? 1 : (int) intdiv($charCount + 2, 3);
        $formattedDigits = formatCode($digits);

        return match ($tierIndex) {
            1 => __('Kol :code', ['code' => $formattedDigits]),
            2 => __('Moein :code', ['code' => $formattedDigits]),
            3 => __('Tafsili :code', ['code' => $formattedDigits]),
            default => __('Level :n :code', ['n' => $tierIndex, 'code' => $formattedDigits]),
        };
    }

    /**
     * Compute parent code by dropping the leaf 3-character segment.
     */
    public static function parentCodeOf(string $childAccountCode): string
    {
        $normalized = trim($childAccountCode);
        $charLength = strlen($normalized);

        return $charLength > 3 ? substr($normalized, 0, -3) : '';
    }

    /**
     * Bulk resolve structured accounts into memory index.
     */
    public function processSubjectRows(array $accountRowEntries): array
    {
        foreach ($accountRowEntries as $item) {
            $rowCode = trim((string) ($item[self::FIELD_CODE] ?? ''));
            $rowName = trim((string) ($item[self::FIELD_NAME] ?? ''));
            $rowParent = trim((string) ($item['parent_code'] ?? ''));

            if ($rowCode !== '' && $rowName !== '') {
                $this->findOrCreate($rowCode, $rowName, $rowParent);
            }
        }

        return $this->accountRegistry;
    }

    /**
     * Retrieve total number of accounts synthesized during the batch session.
     */
    public function getSynthesizedCount(): int
    {
        return $this->synthesizedCount;
    }

    /**
     * Verify whether an account code is already present in runtime index.
     */
    public function hasCached(string $targetAccountCode): bool
    {
        return isset($this->accountRegistry[trim($targetAccountCode)]);
    }

    /**
     * Audit diagnostic information on active resolution session.
     *
     * @return array<string, mixed>
     */
    public function getResolutionStatistics(): array
    {
        return [
            'cached_count' => count($this->accountRegistry),
            'synthesized_count' => $this->synthesizedCount,
            'declared_known_count' => count($this->predefinedAccountTitles),
            'ambiguous_title_count' => count($this->ambiguousTitleLookup),
        ];
    }

    /**
     * Validate hierarchical tree depth of a given account code.
     */
    public function calculateAccountDepth(string $targetAccountCode): int
    {
        $digits = trim($targetAccountCode);
        if ($digits === '') {
            return 0;
        }

        return (int) intdiv(strlen($digits) + 2, 3);
    }

    /**
     * Determine whether an account code aligns with Iranian national accounting standard conventions.
     */
    public function isValidAccountCodePattern(string $targetAccountCode): bool
    {
        $normalized = trim($targetAccountCode);
        if ($normalized === '' || ! ctype_digit($normalized)) {
            return false;
        }

        $length = strlen($normalized);

        return $length >= 3 && ($length % 3 === 0);
    }

    /**
     * Inspect financial classification based on root account digit per Iranian standards.
     */
    public function inferIranianAccountCategory(string $targetAccountCode): string
    {
        $firstDigit = substr(trim($targetAccountCode), 0, 1);

        return match ($firstDigit) {
            '1' => 'current_assets',
            '2' => 'non_current_assets',
            '3' => 'current_liabilities',
            '4' => 'non_current_liabilities',
            '5' => 'equity',
            '6' => 'revenues',
            '7', '8' => 'operating_expenses',
            '9' => 'other_costs_and_taxes',
            default => 'unclassified_group',
        };
    }

    /**
     * Reconstruct breadcrumb trail of account titles from root to current leaf.
     *
     * @return array<int, string>
     */
    public function buildAccountBreadcrumb(Subject $leaf): array
    {
        $trail = [$leaf->name];
        $pointer = $leaf;

        while ($pointer->parent_id !== null) {
            $parent = Subject::find($pointer->parent_id);
            if (! $parent instanceof Subject) {
                break;
            }
            array_unshift($trail, $parent->name);
            $pointer = $parent;
        }

        return $trail;
    }

    /**
     * Validate that an imported account does not create cyclic parent-child loops.
     */
    public function assertNoCyclicAncestry(int $subjectId, ?int $proposedParentId): bool
    {
        if ($proposedParentId === null) {
            return true;
        }

        if ($subjectId === $proposedParentId) {
            return false;
        }

        $visited = [$subjectId => true];
        $cursorId = $proposedParentId;

        while ($cursorId !== null) {
            if (isset($visited[$cursorId])) {
                return false;
            }
            $visited[$cursorId] = true;
            $parent = Subject::find($cursorId);
            $cursorId = $parent?->parent_id;
        }

        return true;
    }

    /**
     * Inspect and format complete account hierarchy structure for export/logging.
     *
     * @return array<string, array{code: string, name: string, tier: int, parent: ?string}>
     */
    public function exportHierarchyManifest(): array
    {
        $manifest = [];
        foreach ($this->accountRegistry as $codeKey => $subject) {
            $manifest[$codeKey] = [
                'code' => $subject->code,
                'name' => $subject->name,
                'tier' => $this->calculateAccountDepth($subject->code),
                'parent' => $subject->parent_id ? (string) $subject->parent_id : null,
                'is_permanent' => (bool) $subject->is_permanent,
                'type' => $subject->type?->valueName(),
            ];
        }

        return $manifest;
    }

    /**
     * Query account entity by decomposing 3-tiered segments.
     */
    public function lookupByCompoundSegments(string $kol, string $moein = '', string $tafsili = ''): ?Subject
    {
        $targetCode = trim($kol).trim($moein).trim($tafsili);
        if ($targetCode === '') {
            return null;
        }

        if (isset($this->accountRegistry[$targetCode])) {
            return $this->accountRegistry[$targetCode];
        }

        return Subject::where(self::FIELD_CODE, $targetCode)->first();
    }

    /**
     * Audit whether any resolved accounts have orphan parent references.
     *
     * @return array<int, string> List of invalid account codes
     */
    public function auditDanglingAncestors(): array
    {
        $orphaned = [];
        foreach ($this->accountRegistry as $code => $subject) {
            if ($subject->parent_id !== null) {
                $parentExists = Subject::where('id', $subject->parent_id)->exists();
                if (! $parentExists) {
                    $orphaned[] = $code;
                }
            }
        }

        return $orphaned;
    }

    /**
     * Verify whether an account code conforms to standard double-entry Chart of Accounts tier patterns.
     */
    public function validateHierarchyTierConformity(string $codeIdentifier): array
    {
        $digits = trim($codeIdentifier);
        $totalDigits = strlen($digits);
        $computedTier = $this->calculateAccountDepth($digits);

        return [
            'is_valid_format' => ctype_digit($digits) && ($totalDigits % 3 === 0),
            'tier_depth' => $computedTier,
            'is_root_group' => $computedTier === 1,
            'is_moein_subsidiary' => $computedTier === 2,
            'is_tafsili_detail' => $computedTier >= 3,
        ];
    }

    /**
     * Internal recursive ancestor builder.
     */
    protected function obtainParentNode(string $parentReferenceCode): ?Subject
    {
        if ($parentReferenceCode === '') {
            return null;
        }

        if (isset($this->accountRegistry[$parentReferenceCode])) {
            return $this->accountRegistry[$parentReferenceCode];
        }

        $directDatabaseHit = Subject::where(self::FIELD_CODE, $parentReferenceCode)->first();
        if ($directDatabaseHit instanceof Subject) {
            return $this->accountRegistry[$parentReferenceCode] = $directDatabaseHit;
        }

        $synthesizedLabel = $this->predefinedAccountTitles[$parentReferenceCode]
            ?? self::synthesizeName($parentReferenceCode);

        $higherAncestorCode = self::parentCodeOf($parentReferenceCode);

        return $this->findOrCreate($parentReferenceCode, $synthesizedLabel, $higherAncestorCode);
    }

    /**
     * Find accounts with identical title belonging to a specific parent tier.
     */
    protected function fetchByTitleAndParent(string $title, ?int $parentId): EloquentSet
    {
        $builder = Subject::where(self::FIELD_NAME, $title);

        if ($parentId !== null) {
            $builder->where(self::FIELD_PARENT, $parentId);
        } else {
            $builder->whereNull(self::FIELD_PARENT);
        }

        return $builder->get();
    }

    /**
     * Build and save a new Subject Eloquent model.
     */
    protected function instantiateAndPersist(
        string $accountCode,
        string $accountTitle,
        ?Subject $parentAccount,
        bool|string|null $isPermanent,
        ?string $accountType
    ): Subject {
        $booleanFlag = match (true) {
            is_bool($isPermanent) => $isPermanent,
            is_string($isPermanent) => in_array(mb_strtolower(trim($isPermanent)), ['1', 'true', 'yes', 'permanent', 'دائم', 'دائمی'], true),
            default => false,
        };

        $entity = new Subject([
            self::FIELD_NAME => $accountName = $accountTitle,
            self::FIELD_PARENT => $parentAccount?->id,
            self::FIELD_COMPANY => getActiveCompany(),
            self::FIELD_TYPE => SubjectType::fromName($accountType ?? 'both'),
            self::FIELD_PERMANENT => $booleanFlag,
        ]);

        $taken = Subject::where(self::FIELD_CODE, $accountCode)->first();
        $entity->code = ($taken === null) ? $accountCode : $entity->generateCode();
        $entity->save();

        $this->synthesizedCount++;
        Log::info(sprintf('ImportSubjectResolver: registered account [%s] %s', $entity->code, $accountTitle));

        return $entity;
    }
}
