<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\HasSyncEnvelope;
use App\Models\Concerns\SyncsInvoiceDraftsToOutbox;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use HasFactory, HasSyncEnvelope, SyncsInvoiceDraftsToOutbox;

    public $timestamps = true;

    protected $fillable = [
        'number',
        'date',
        'document_id',
        'customer_id',
        'creator_id',
        'first_approved_by',
        'subtraction',
        'tax',
        'ship_date',
        'ship_via',
        'description',
        'invoice_type',
        'status',
        'active',
        'vat',
        'amount',
        'title',
        'returned_invoice_id',
        'recurring_invoice_template_id',
        'company_id',
        'warehouse_id',
        'taxID',
        'sync_version',
        'sync_status',
        'origin_device_id',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'status' => InvoiceStatus::class,
        'date' => 'date',
        'ship_date' => 'date',
        'active' => 'boolean',
        // D-075: NOT in $fillable -- only ever set via forceFill() inside
        // SyncPilotService::applyFinalize(), never through mass assignment.
        'is_number_finalized' => 'boolean',
        'number_finalized_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
        static::creating(function ($model) {
            $model->company_id = getActiveCompany();
        });
        static::deleting(function (Invoice $invoice) {
            // D-084 hardening (post-Phase-2 audit): deleting an Invoice must
            // never be a back door around Payment/PaymentAllocation
            // immutability -- neither has a draft state (D-078/D-081), so
            // any existing row here is already a posted financial event.
            // Cheque-linked payments are excluded, exactly as D-084 excludes
            // them from PaymentService::deletePayment()'s own guard: deleting
            // an invoice with a cheque payment cascades through the existing,
            // independent ChequeService::delete() lifecycle (D-070) BEFORE
            // this hook runs (see InvoiceService::deleteInvoice()), which
            // already removes that cheque's own Payment row.
            if ($invoice->payments()->whereNull('cheque_id')->exists() || $invoice->paymentAllocations()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('This invoice has posted payments or allocations and cannot be deleted; it is a permanent financial record.')],
                ]);
            }

            // payment_allocations targets Invoice polymorphically (no DB-level FK
            // to cascade), so it must be cleaned up explicitly here. Unreachable
            // when allocations exist (the guard above already threw) -- kept as
            // a harmless no-op cleanup for the allocation-free case.
            $invoice->paymentAllocations()->delete();
        });
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function ancillaryCosts()
    {
        return $this->hasMany(AncillaryCost::class, 'invoice_id');
    }

    public function moadianHistories()
    {
        return $this->hasMany(MoadianHistory::class, 'invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function paymentAllocations()
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function latestMoadianHistory()
    {
        // P12.1 hardening: latestOfMany() defaults to ordering by
        // created_at, which ties (undefined order) when two history rows
        // are created within the same second -- this app's OWN established
        // convention for "latest" elsewhere (MoadianHistoryController
        // ::index()'s $latestHistoryIds) already uses MAX(id) instead.
        // Explicit 'id' here matches that convention and is immune to
        // timestamp-precision ties.
        return $this->hasOne(MoadianHistory::class, 'invoice_id')->latestOfMany('id');
    }

    public function returnedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'returned_invoice_id');
    }

    /**
     * Return invoice for the current returned invoice (If it is returned). e.g. sell -> return sell
     */
    public function getReturnInvoice()
    {
        return Invoice::where('returned_invoice_id', $this->id)->whereNot('invoice_type', InvoiceType::VOID)->get();
    }

    /**
     *  Returned invoice for the current return invoice. e.g. return sell -> sell
     */
    public function getReturnedInvoice()
    {
        return Invoice::whereNot('invoice_type', InvoiceType::VOID)->find($this->returned_invoice_id);
    }

    /**
     * Void invoice for the current voided invoice (If it is voided). e.g. sell -> void
     */
    public function voidInvoice()
    {
        return $this->hasOne(Invoice::class, 'returned_invoice_id')->where('invoice_type', InvoiceType::VOID);
    }

    /**
     *  The original invoice that was voided by this void invoice (if any). Example: void -> sell
     */
    public function voidedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'returned_invoice_id')->where('invoice_type', '!=', InvoiceType::VOID);
    }
}
