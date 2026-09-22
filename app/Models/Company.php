<?php

namespace App\Models;

use App\Enums\FinancialProvenance;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Company extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * D-085: provenance-aware Company deletion. A Company may be deleted
     * only when every financially effective record it contains is
     * demonstrably part of its copied/imported baseline history, with no
     * native/local financial activity since. `approved_at` alone (the
     * pre-D-085 guard) proved too broad -- a fiscal-year copy legitimately
     * carries approved Documents over from its source year, and that alone
     * must not make the freshly-copied Company permanently undeletable.
     *
     * Two independent signals, either one blocks deletion:
     *
     * 1. Document: any approved Document whose `provenance` is NATIVE (the
     *    column default -- every existing/legacy row and every ordinarily
     *    created Document, since only FiscalYearService::_importDocuments()
     *    ever sets BASELINE) is local financial activity by definition.
     *    Unknown/legacy provenance collapses to NATIVE and blocks deletion
     *    -- there is no separate "unknown" state to special-case.
     * 2. PaymentAllocation: FiscalYearService never copies this table at
     *    all (confirmed by inspection -- no `_importPaymentAllocations()`
     *    exists), so ANY existing allocation is, by construction, always
     *    locally created in this Company. This is the one financially
     *    effective mutation that creates no Document of its own (D-081),
     *    so the Document check alone cannot see it.
     */
    public static function booted(): void
    {
        static::deleting(function (Company $company) {
            $hasNativeApprovedDocument = Document::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNotNull('approved_at')
                ->where('provenance', FinancialProvenance::NATIVE)
                ->exists();

            $hasLocalAllocation = PaymentAllocation::whereHas(
                'payment',
                fn ($query) => $query->where('company_id', $company->id)
            )->exists();

            if ($hasNativeApprovedDocument || $hasLocalAllocation) {
                throw ValidationException::withMessages([
                    'company' => [__('This company has locally-originated financial activity and cannot be deleted.')],
                ]);
            }
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Return the inclusive Gregorian boundaries of this Jalali fiscal year.
     */
    public function fiscalYearRange(): array
    {
        $year = (int) $this->fiscal_year;

        $start = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'))->startOfDay();
        $end = Carbon::parse(jalali_to_gregorian($year + 1, 1, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * Decrypted Moadian SSL certificate contents, or null if not set.
     */
    public function decryptedCertificate(): ?string
    {
        return $this->readKeyFile($this->certificate_path);
    }

    /**
     * Decrypted Moadian private key contents, or null if not set.
     */
    public function decryptedPrivateKey(): ?string
    {
        return $this->readKeyFile($this->private_key_path);
    }

    private function readKeyFile(?string $path): ?string
    {
        if (! $path || ! Storage::exists($path)) {
            return null;
        }

        $raw = Storage::get($path);

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException $e) {
            return $raw;
        }
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * The Income Summary / P&L document created in Step 1 (closing temporary accounts).
     */
    public function plDocument()
    {
        return $this->belongsTo(Document::class, 'pl_document_id');
    }

    /**
     * The closing document created in Step 3 (closing permanent accounts).
     */
    public function closingDocument()
    {
        return $this->belongsTo(Document::class, 'closing_document_id');
    }
}
