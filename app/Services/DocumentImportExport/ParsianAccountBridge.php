<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * Clean-room Subject Hierarchy Resolver for Parsian Accounting Software.
 *
 * Resolves Kol, Moeen, and Tafsili codes and builds parent-child trees.
 *
 * License: MIT
 */
final class ParsianAccountBridge
{
    private readonly array $subsidiaryTitles;

    public function __construct(
        private readonly ImportSubjectResolver $resolver,
        Collection $rawRows
    ) {
        $dir = [];
        foreach ($rawRows as $r) {
            $taf = (int) ($r['TafsiliCode'] ?? 0);
            $kol = (int) ($r['KolCode'] ?? 0);
            $moe = (int) ($r['MoeenCode'] ?? 0);
            $name = trim((string) ($r['HesabName'] ?? ''));

            if ($taf === 0 && $kol > 0 && $moe > 0 && $name !== '') {
                $dir["{$kol}.{$moe}"] ??= $name;
            }
        }
        $this->subsidiaryTitles = $dir;
    }

    public function transformRowToTransactionLine(array $row): array
    {
        $kol = (int) ($row['KolCode'] ?? 0);
        $moe = (int) ($row['MoeenCode'] ?? 0);
        $taf = (int) ($row['TafsiliCode'] ?? 0);
        $title = trim((string) ($row['HesabName'] ?? ''));
        $bed = (float) ($row['Bed'] ?? 0.0);
        $bes = (float) ($row['Bes'] ?? 0.0);
        $memo = trim((string) ($row['Comment'] ?? ''));

        $subject = $this->findAccountNode($kol, $moe, $taf, $title);

        return [
            'subject_id' => $subject->id,
            'value' => $bes - $bed,
            'desc' => $memo,
        ];
    }

    public function findAccountNode(int $kol, int $moe, int $taf, string $title): Subject
    {
        $kolCode = sprintf('%03d', $kol);
        $moeCode = $kolCode . sprintf('%03d', $moe);

        $root = $this->findRootSubject($kolCode);
        $permanent = (bool) $root->is_permanent;
        $nature = $root->type->valueName();

        if ($taf > 0) {
            $tafCode = $moeCode . sprintf('%03d', $taf);
            $moeTitle = $this->subsidiaryTitles["{$kol}.{$moe}"] ?? ImportSubjectResolver::synthesizeName($moeCode);

            $this->resolver->findOrCreate($moeCode, $moeTitle, $kolCode, $permanent, $nature);

            return $this->resolver->findOrCreate($tafCode, $title, $moeCode, $permanent, $nature);
        }

        return $this->resolver->findOrCreate($moeCode, $title, $kolCode, $permanent, $nature);
    }

    private function findRootSubject(string $kolCode): Subject
    {
        $definition = ParsianRootCatalogue::find($kolCode);

        if ($definition === null) {
            return $this->resolver->findOrCreate(
                $kolCode,
                ImportSubjectResolver::synthesizeName($kolCode),
                ''
            );
        }

        return $this->resolver->resolveTopLevel(
            $kolCode,
            $definition['title'],
            $definition['permanent'],
            $definition['nature']
        );
    }
}
