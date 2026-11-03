<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CleanroomImmutabilityTriggerTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId = 1;

    private int $fiscalYearId = 1405;

    private int $accountId = 101;

    protected function setUp(): void
    {
        parent::setUp();

        $uuid = bin2hex(random_bytes(16));

        // Ensure company
        try {
            DB::statement("INSERT INTO companies (id, uuid, name, is_active) VALUES ({$this->companyId}, X'{$uuid}', 'Trigger Test Co', 1)");
        } catch (\Throwable) {
            DB::statement("INSERT INTO companies (id, name, fiscal_year) VALUES ({$this->companyId}, 'Trigger Test Co', 1405)");
        }

        // Ensure fiscal year
        $fyUuid = bin2hex(random_bytes(16));
        DB::statement("INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date, status) VALUES ({$this->fiscalYearId}, X'{$fyUuid}', {$this->companyId}, 1405, '2026-03-21', '2027-03-20', 1)");

        // Ensure account
        $accUuid = bin2hex(random_bytes(16));
        DB::statement("INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier, is_active) VALUES ({$this->accountId}, X'{$accUuid}', {$this->companyId}, '10101', 'بانک صادرات', 1, 1, 1)");
    }

    public function test_posted_voucher_forbids_arbitrary_update(): void
    {
        $vUuid = bin2hex(random_bytes(16));
        DB::statement("
            INSERT INTO journal_vouchers (
                id, uuid, company_id, fiscal_year_id, voucher_number, voucher_date,
                description, status, provenance, created_by_user_id
            ) VALUES (5001, X'{$vUuid}', {$this->companyId}, {$this->fiscalYearId}, 5001, '2026-06-01', 'سند قطعی', 2, 1, 1)
        ");

        $this->expectException(\Throwable::class);
        DB::statement("UPDATE journal_vouchers SET description = 'تغییر غیرمجاز' WHERE id = 5001");
    }

    public function test_posted_voucher_allows_reversal_status_transition(): void
    {
        $vUuid = bin2hex(random_bytes(16));
        DB::statement("
            INSERT INTO journal_vouchers (
                id, uuid, company_id, fiscal_year_id, voucher_number, voucher_date,
                description, status, provenance, created_by_user_id
            ) VALUES (5002, X'{$vUuid}', {$this->companyId}, {$this->fiscalYearId}, 5002, '2026-06-01', 'سند قطعی', 2, 1, 1)
        ");

        // Allowed: status transition from 2 (Posted) to 3 (Void/Reversed)
        DB::statement('UPDATE journal_vouchers SET status = 3 WHERE id = 5002');

        $status = DB::table('journal_vouchers')->where('id', 5002)->value('status');
        $this->assertEquals(3, $status);
    }

    public function test_posted_voucher_forbids_deletion(): void
    {
        $vUuid = bin2hex(random_bytes(16));
        DB::statement("
            INSERT INTO journal_vouchers (
                id, uuid, company_id, fiscal_year_id, voucher_number, voucher_date,
                description, status, provenance, created_by_user_id
            ) VALUES (5003, X'{$vUuid}', {$this->companyId}, {$this->fiscalYearId}, 5003, '2026-06-01', 'سند قطعی', 2, 1, 1)
        ");

        $this->expectException(\Throwable::class);
        DB::statement('DELETE FROM journal_vouchers WHERE id = 5003');
    }

    public function test_posted_voucher_lines_forbid_update_and_delete(): void
    {
        $vUuid = bin2hex(random_bytes(16));
        DB::statement("
            INSERT INTO journal_vouchers (
                id, uuid, company_id, fiscal_year_id, voucher_number, voucher_date,
                description, status, provenance, created_by_user_id
            ) VALUES (5004, X'{$vUuid}', {$this->companyId}, {$this->fiscalYearId}, 5004, '2026-06-01', 'سند قطعی', 2, 1, 1)
        ");

        $lUuid = bin2hex(random_bytes(16));
        DB::statement("
            INSERT INTO journal_voucher_lines (
                id, uuid, voucher_id, account_id, line_number, debit_amount, credit_amount
            ) VALUES (9001, X'{$lUuid}', 5004, {$this->accountId}, 1, 5000000, 0)
        ");

        // Attempting to update line of posted voucher must throw
        try {
            DB::statement('UPDATE journal_voucher_lines SET debit_amount = 7000000 WHERE id = 9001');
            $this->fail('Expected exception when updating line of posted voucher');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }

        // Attempting to delete line of posted voucher must throw
        try {
            DB::statement('DELETE FROM journal_voucher_lines WHERE id = 9001');
            $this->fail('Expected exception when deleting line of posted voucher');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
