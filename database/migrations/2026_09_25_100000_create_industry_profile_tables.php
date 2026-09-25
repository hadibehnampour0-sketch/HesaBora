<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Industry Profiles (per-trade settings dashboards). Definitions of what a
 * trade (gold, iron, ...) can configure live as data files under
 * resources/industry-profiles/*.json -- NOT in these tables -- so adding a
 * trade or a setting needs no code change. These tables only hold a company's
 * overrides, its activation of a profile, and an audit trail of changes.
 *
 * Deliberately NOT a Trade Engine table set (D-099 §12 / guardrail #5): no
 * quantity, valuation, ownership or settlement data lives here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_profile_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('profile_key', 60);
            $table->timestamp('activated_at');
            $table->timestamps();

            $table->unique(['company_id', 'profile_key'], 'ind_prof_act_company_profile_unique');
        });

        Schema::create('industry_setting_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('profile_key', 60);
            $table->string('setting_key', 80);
            $table->text('value')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'profile_key', 'setting_key'], 'ind_set_val_company_profile_key_unique');
        });

        Schema::create('industry_setting_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('profile_key', 60);
            $table->string('setting_key', 80);
            $table->string('action', 20);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'profile_key', 'created_at'], 'ind_set_hist_company_profile_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_setting_histories');
        Schema::dropIfExists('industry_setting_values');
        Schema::dropIfExists('industry_profile_activations');
    }
};
