<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-075 stage 2.7C-A -- the Numbering Lease Contract. A lease is a
 * server-authoritative, disjoint range of official numbers reserved to ONE
 * device for ONE (company, invoice_type) numbering series. Reservation
 * (this table gaining a row) is a separate event from consumption (a
 * Finalize operation advancing `next_number`, see
 * `add_finalization_to_invoices_table`) -- see D-075 decision 6.
 *
 * Ranges are never recycled (D-075 decision 5): once issued, a range's
 * unused tail stays permanently assigned to that lease, even if the device
 * never finishes consuming it. The unique index on
 * (company_id, invoice_type, range_start) is the reservation-side collision
 * guard (D-075 decision 7) -- the analogous guard on `invoices.number`
 * itself already exists via `invoices_number_invoice_type_company_id_unique`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('invoice_type'); // InvoiceType -- the numbering series for 2.7C
            $table->foreignId('origin_device_id')->constrained('device_installations')->cascadeOnDelete();
            $table->unsignedBigInteger('range_start');
            $table->unsignedBigInteger('range_end');
            $table->unsignedBigInteger('next_number'); // the cursor: next value Finalize will consume
            $table->unsignedTinyInteger('status')->default(1); // NumberingLeaseStatus::ACTIVE
            $table->timestamps();

            $table->unique(['company_id', 'invoice_type', 'range_start']);
            // Explicit short name: the auto-generated name exceeds MySQL/MariaDB's
            // 64-character identifier limit (found while testing the D-091
            // migration's upgrade path against real MariaDB).
            $table->index(['company_id', 'invoice_type', 'origin_device_id', 'status'], 'numbering_leases_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_leases');
    }
};
