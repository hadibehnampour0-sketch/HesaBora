<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend companies table if needed
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'uuid')) {
                    $table->binary('uuid', 36)->nullable()->unique();
                }
                if (! Schema::hasColumn('companies', 'is_active')) {
                    $table->tinyInteger('is_active')->default(1);
                }
            });
        }

        // 2. fiscal_years table
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->unsignedInteger('year_number');
                $table->date('start_date');
                $table->date('end_date');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->unique(['company_id', 'year_number']);
            });
        }

        // 3. fiscal_periods table
        if (! Schema::hasTable('fiscal_periods')) {
            Schema::create('fiscal_periods', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
                $table->unsignedInteger('period_number');
                $table->date('start_date');
                $table->date('end_date');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->unique(['fiscal_year_id', 'period_number']);
            });
        }

        // 4. accounts table (Clean-Room Chart of Accounts)
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('code', 32);
                $table->string('title', 128);
                $table->tinyInteger('account_nature')->comment('1=DebitNormal, 2=CreditNormal');
                $table->tinyInteger('account_tier')->default(1)->comment('1=Group, 2=General, 3=Subsidiary, 4=Detail');
                $table->unsignedBigInteger('parent_account_id')->nullable();
                $table->tinyInteger('is_active')->default(1);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }

        // 5. journal_vouchers table
        if (! Schema::hasTable('journal_vouchers')) {
            Schema::create('journal_vouchers', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->unsignedBigInteger('fiscal_year_id')->nullable();
                $table->unsignedBigInteger('voucher_number');
                $table->date('voucher_date');
                $table->text('description')->nullable();
                $table->tinyInteger('status')->default(1)->comment('1=Draft, 2=Posted, 3=Void');
                $table->tinyInteger('provenance')->default(1)->comment('1=Native, 2=Baseline, 3=Imported');
                $table->tinyInteger('is_reversal')->default(0);
                $table->unsignedBigInteger('reversed_voucher_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'fiscal_year_id', 'voucher_number']);
            });
        }

        // 6. journal_voucher_lines table
        if (! Schema::hasTable('journal_voucher_lines')) {
            Schema::create('journal_voucher_lines', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('voucher_id')->constrained('journal_vouchers')->restrictOnDelete();
                $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
                $table->unsignedInteger('line_number');
                $table->unsignedBigInteger('debit_amount')->default(0);
                $table->unsignedBigInteger('credit_amount')->default(0);
                $table->text('description')->nullable();
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();

                $table->unique(['voucher_id', 'line_number']);
            });
        }

        // 7. cleanroom_cheques table (clean-room cheque instrument ledger, distinct from legacy cheques)
        if (! Schema::hasTable('cleanroom_cheques')) {
            Schema::create('cleanroom_cheques', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->tinyInteger('cheque_type')->comment('1=Received, 2=Issued');
                $table->string('cheque_number', 64);
                $table->string('sayad_number', 16)->nullable();
                $table->string('bank_name', 128);
                $table->date('due_date');
                $table->unsignedBigInteger('amount');
                $table->foreignId('drawer_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('payee_account_id')->constrained('accounts')->restrictOnDelete();
                $table->tinyInteger('current_status')->default(1)->comment('1=Registered, 2=Deposited, 3=Cleared, 4=Bounced, 5=Endorsed, 6=Returned, 7=Cancelled, 8=Guarantee');
                $table->unsignedBigInteger('journal_voucher_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'cheque_type', 'cheque_number']);
            });
        }

        // 8. numbering_leases table
        if (! Schema::hasTable('numbering_leases')) {
            Schema::create('numbering_leases', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('document_type', 64);
                $table->string('device_id', 64);
                $table->unsignedBigInteger('range_start');
                $table->unsignedBigInteger('range_end');
                $table->unsignedBigInteger('current_value');
                $table->dateTime('expires_at');
                $table->timestamps();

                $table->unique(['company_id', 'document_type', 'device_id']);
            });
        }

        // 9. fixed_assets table
        if (! Schema::hasTable('fixed_assets')) {
            Schema::create('fixed_assets', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('asset_code', 64);
                $table->string('name', 255);
                $table->unsignedBigInteger('cost_basis');
                $table->unsignedBigInteger('salvage_value')->default(0);
                $table->unsignedInteger('useful_life_months');
                $table->string('depreciation_method', 32);
                $table->date('in_service_date');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();

                $table->unique(['company_id', 'asset_code']);
            });
        }

        // 10. stock_movements table
        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->string('item_code', 64);
                $table->tinyInteger('movement_type')->comment('1=Inbound, 2=Outbound, 3=Adjustment');
                $table->date('movement_date');
                $table->decimal('quantity', 18, 6);
                $table->unsignedBigInteger('unit_cost');
                $table->unsignedBigInteger('total_cost');
                $table->decimal('remaining_quantity', 18, 6)->default(0);
                $table->unsignedBigInteger('moving_average_cost')->default(0);
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'item_code']);
            });
        }

        // 11. posting_settings table
        if (! Schema::hasTable('posting_settings')) {
            Schema::create('posting_settings', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->string('setting_key', 64);
                $table->unsignedBigInteger('account_id');
                $table->timestamps();

                $table->unique(['company_id', 'setting_key']);
            });
        }

        // 12. audit_events table
        if (! Schema::hasTable('audit_events')) {
            Schema::create('audit_events', function (Blueprint $table) {
                $table->id();
                $table->binary('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->string('event_type', 64);
                $table->string('entity_type', 64);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('user_id');
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('posting_settings');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('numbering_leases');
        Schema::dropIfExists('cleanroom_cheques');
        Schema::dropIfExists('journal_voucher_lines');
        Schema::dropIfExists('journal_vouchers');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');

        Schema::enableForeignKeyConstraints();
    }
};
