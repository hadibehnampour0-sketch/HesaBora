<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Authorization & Workflow Governance phase (r12, Workstream R,
 * revisited from first principles after an initial attempt was reverted).
 *
 * `company_user` had no uniqueness constraint at all. Every PRODUCTION code
 * path (`UserController`, `CompanyController`) already uses `sync()`/
 * `syncWithoutDetaching()` exclusively (verified: zero raw `attach()` calls
 * on this relation anywhere in `app/`), so this was never a reachable
 * production bug -- only a missing defense-in-depth constraint.
 *
 * The first attempt at this migration was reverted after it broke ~95
 * tests. Root-caused, not just observed: `CompanyFactory`'s `afterCreating`
 * hook silently attaches `User::first()` to every company it creates
 * (`syncWithoutDetaching`, itself duplicate-safe), and a large number of
 * test files separately called the raw, non-deduplicating `attach()` on the
 * SAME `Company::users()`/`User::companies()` relation for a user that can
 * coincide with `User::first()` in that test's data -- producing a genuine
 * second pivot row for the same (company, user) pair. This was a
 * test-construction pattern, not a production one. Fixed at the root: every
 * test-only `->companies()->attach(...)` / `->users()->attach(...)` call
 * (94 occurrences across 59 files, all single-argument, no pivot data) was
 * changed to `syncWithoutDetaching(...)`, matching the same safe pattern
 * already used everywhere in production code and by the factory hook
 * itself -- not by loosening or working around the new constraint.
 *
 * Dedupes any remaining accidental duplicates (keeping the lowest id per
 * pair) before adding the constraint, so this is also safe against real,
 * pre-existing production data, not only a clean test schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicateIds = DB::table('company_user')
            ->select('id')
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('MIN(id)')
                    ->from('company_user')
                    ->groupBy('company_id', 'user_id');
            })
            ->pluck('id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('company_user')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('company_user', function (Blueprint $table) {
            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'user_id']);
        });
    }
};
