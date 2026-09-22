<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9.2 (Tax Rules engine). System-wide (not company-scoped), because Iran's
 * VAT rate/exemption rules are set by the government, not chosen per
 * company -- a company's own VAT_REGISTRATION_STATUS (P9.1, on `companies`)
 * decides whether a given company charges VAT at all; this table decides
 * WHAT RATE applies, as of a given date, for a given product/service tax
 * category. `sstid` mirrors the Moadian "Standard Sales Table ID"
 * classification already used by `products`/`services`/`product_groups`
 * (see P9's Moadian integration) -- null `sstid` means "the general/default
 * rate," matching `products.vat`'s own existing flat-rate convention.
 *
 * Modeled directly on `ExchangeRate`/`CurrencyService::rateOn()` (P3.4):
 * a row per validity period rather than a single mutable "current rate."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('sstid', 20)->nullable();
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_exempt')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            // Guardrail #33: name explicitly rather than trust the
            // auto-generated name.
            $table->index(['sstid', 'effective_from', 'effective_to'], 'tax_rules_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
