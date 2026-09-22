<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * P9.6 (Evidence Vault). A supporting document (invoice copy, payment
 * proof, waybill, contract) linked to any financial record, with a
 * SHA-256 hash captured at upload time so tampering can be detected later
 * (see TaxEvidenceService::verifyIntegrity()).
 */
class TaxEvidence extends Model
{
    // Eloquent's pluralizer treats "evidence" as uncountable and would
    // otherwise derive the table name as `tax_evidence` (singular) --
    // the migration creates `tax_evidences`, so this must be explicit.
    protected $table = 'tax_evidences';

    protected $fillable = [
        'company_id',
        'evidenceable_type',
        'evidenceable_id',
        'document_type',
        'title',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'file_hash',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $evidence) {
            $evidence->company_id ??= getActiveCompany();
        });
    }

    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
