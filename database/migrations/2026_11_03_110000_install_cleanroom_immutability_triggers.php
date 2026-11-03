<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_voucher_update;
                CREATE TRIGGER trg_sqlite_prevent_posted_voucher_update
                BEFORE UPDATE ON journal_vouchers
                FOR EACH ROW
                WHEN (OLD.status = 3) OR
                     (OLD.status = 2 AND (
                         NEW.status != 3 OR
                         NEW.company_id != OLD.company_id OR
                         NEW.fiscal_year_id != OLD.fiscal_year_id OR
                         NEW.voucher_number != OLD.voucher_number OR
                         NEW.voucher_date != OLD.voucher_date OR
                         NEW.description != OLD.description OR
                         NEW.uuid != OLD.uuid OR
                         NEW.created_by_user_id != OLD.created_by_user_id OR
                         NEW.provenance != OLD.provenance OR
                         NEW.is_reversal != OLD.is_reversal
                     ))
                BEGIN
                    SELECT RAISE(ABORT, \'Posted journal vouchers are immutable. Only official reversal status transition is permitted, with no modifications to date, description, accounts, amounts, or voucher identity.\');
                END;

                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_voucher_delete;
                CREATE TRIGGER trg_sqlite_prevent_posted_voucher_delete
                BEFORE DELETE ON journal_vouchers
                FOR EACH ROW
                WHEN OLD.status IN (2, 3)
                BEGIN
                    SELECT RAISE(ABORT, \'Posted or reversed journal vouchers cannot be deleted.\');
                END;

                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_line_update;
                CREATE TRIGGER trg_sqlite_prevent_posted_line_update
                BEFORE UPDATE ON journal_voucher_lines
                FOR EACH ROW
                WHEN (SELECT status FROM journal_vouchers WHERE id = OLD.voucher_id) IN (2, 3)
                BEGIN
                    SELECT RAISE(ABORT, \'Lines of posted journal vouchers cannot be modified.\');
                END;

                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_line_delete;
                CREATE TRIGGER trg_sqlite_prevent_posted_line_delete
                BEFORE DELETE ON journal_voucher_lines
                FOR EACH ROW
                WHEN (SELECT status FROM journal_vouchers WHERE id = OLD.voucher_id) IN (2, 3)
                BEGIN
                    SELECT RAISE(ABORT, \'Lines of posted journal vouchers cannot be deleted.\');
                END;
            ');
        } elseif ($driver === 'mysql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_prevent_posted_voucher_update;
                CREATE TRIGGER trg_prevent_posted_voucher_update
                BEFORE UPDATE ON journal_vouchers
                FOR EACH ROW
                BEGIN
                    IF OLD.status = 3 THEN
                        SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Reversed journal vouchers are immutable. No modifications permitted.\';
                    ELSEIF OLD.status = 2 THEN
                        IF NEW.status != 3
                           OR NEW.company_id != OLD.company_id
                           OR NEW.fiscal_year_id != OLD.fiscal_year_id
                           OR NEW.voucher_number != OLD.voucher_number
                           OR NEW.voucher_date != OLD.voucher_date
                           OR NEW.description != OLD.description
                           OR NEW.uuid != OLD.uuid
                           OR NEW.created_by_user_id != OLD.created_by_user_id
                           OR NEW.provenance != OLD.provenance
                           OR NEW.is_reversal != OLD.is_reversal THEN
                            SIGNAL SQLSTATE \'45000\'
                            SET MESSAGE_TEXT = \'Posted journal vouchers are immutable. Only official reversal status transition is permitted, with no modifications to date, description, accounts, amounts, or voucher identity.\';
                        END IF;
                    END IF;
                END;
            ');

            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_prevent_posted_voucher_delete;
                CREATE TRIGGER trg_prevent_posted_voucher_delete
                BEFORE DELETE ON journal_vouchers
                FOR EACH ROW
                BEGIN
                    IF OLD.status IN (2, 3) THEN
                        SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Posted or reversed journal vouchers cannot be deleted.\';
                    END IF;
                END;
            ');

            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_prevent_posted_line_update;
                CREATE TRIGGER trg_prevent_posted_line_update
                BEFORE UPDATE ON journal_voucher_lines
                FOR EACH ROW
                BEGIN
                    DECLARE v_status TINYINT;
                    SELECT status INTO v_status FROM journal_vouchers WHERE id = OLD.voucher_id;
                    IF v_status IN (2, 3) THEN
                        SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Lines of posted journal vouchers cannot be modified.\';
                    END IF;
                END;
            ');

            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_prevent_posted_line_delete;
                CREATE TRIGGER trg_prevent_posted_line_delete
                BEFORE DELETE ON journal_voucher_lines
                FOR EACH ROW
                BEGIN
                    DECLARE v_status TINYINT;
                    SELECT status INTO v_status FROM journal_vouchers WHERE id = OLD.voucher_id;
                    IF v_status IN (2, 3) THEN
                        SIGNAL SQLSTATE \'45000\'
                        SET MESSAGE_TEXT = \'Lines of posted journal vouchers cannot be deleted.\';
                    END IF;
                END;
            ');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_voucher_update;
                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_voucher_delete;
                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_line_update;
                DROP TRIGGER IF EXISTS trg_sqlite_prevent_posted_line_delete;
            ');
        } elseif ($driver === 'mysql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_prevent_posted_voucher_update;
                DROP TRIGGER IF EXISTS trg_prevent_posted_voucher_delete;
                DROP TRIGGER IF EXISTS trg_prevent_posted_line_update;
                DROP TRIGGER IF EXISTS trg_prevent_posted_line_delete;
            ');
        }
    }
};
