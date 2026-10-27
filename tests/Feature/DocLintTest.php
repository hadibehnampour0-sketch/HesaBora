<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocLintTest extends TestCase
{
    public function test_master_context_sections_are_unique_and_sequential(): void
    {
        $path = base_path('docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        preg_match_all('/^#\s+(\d+)\./m', $content, $matches);

        $sections = $matches[1];
        $this->assertNotEmpty($sections);

        $seen = [];
        $duplicates = [];
        foreach ($sections as $s) {
            if (isset($seen[$s])) {
                $duplicates[] = $s;
            }
            $seen[$s] = true;
        }

        $this->assertEmpty($duplicates, 'Duplicate section numbers found: ' . implode(', ', $duplicates));
        $this->assertCount(55, $sections, 'Master Context r15 must contain exactly 55 top-level sections.');
    }

    public function test_current_state_line_count_is_within_budget(): void
    {
        $path = base_path('docs/project-context/CURRENT_STATE.md');
        $this->assertFileExists($path);

        $lines = file($path);
        $this->assertLessThanOrEqual(400, count($lines), 'CURRENT_STATE.md exceeds the 400-line budget.');
    }

    public function test_all_referenced_decisions_exist_in_register(): void
    {
        $decisionLogPath = base_path('docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md');
        $currentStatePath = base_path('docs/project-context/CURRENT_STATE.md');
        $masterContextPath = base_path('docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md');

        $this->assertFileExists($decisionLogPath);
        $this->assertFileExists($currentStatePath);
        $this->assertFileExists($masterContextPath);

        $decisionLog = file_get_contents($decisionLogPath);
        $currentState = file_get_contents($currentStatePath);
        $masterContext = file_get_contents($masterContextPath);

        preg_match_all('/D-\d+/', $decisionLog, $dlMatches);
        preg_match_all('/D-\d+/', $currentState, $csMatches);

        $known = array_unique(array_merge($dlMatches[0], $csMatches[0]));
        $knownMap = array_flip($known);

        // Expand ranges (use /u flag for multibyte en-dash / em-dash)
        preg_match_all('/D-(\d+)\s*[–\-—]\s*D-(\d+)/u', $currentState, $csRanges, PREG_SET_ORDER);
        preg_match_all('/D-(\d+)\s*[–\-—]\s*D-(\d+)/u', $decisionLog, $dlRanges, PREG_SET_ORDER);

        foreach (array_merge($csRanges, $dlRanges) as $range) {
            $start = (int) $range[1];
            $end = (int) $range[2];
            for ($i = $start; $i <= $end; $i++) {
                $code = sprintf('D-%03d', $i);
                $knownMap[$code] = true;
            }
        }

        preg_match_all('/D-\d+/', $masterContext, $mcMatches);
        $referenced = array_unique($mcMatches[0]);

        $missing = [];
        foreach ($referenced as $d) {
            if (!isset($knownMap[$d])) {
                $missing[] = $d;
            }
        }

        $this->assertEmpty($missing, 'Decisions referenced in Master Context but missing from register: ' . implode(', ', $missing));
    }
}
