<?php

namespace App\Models;

use App\Enums\FinancialProvenance;
use App\Models\Concerns\HasSyncEnvelope;
use App\Models\Concerns\SyncsDocumentDraftsToOutbox;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory, HasSyncEnvelope, SyncsDocumentDraftsToOutbox;

    public $timestamps = true;

    protected $casts = [
        // Explicit Y-m-d format so the stored value is date-only on every
        // driver: MySQL's DATE column silently strips any time component on
        // insert, but SQLite's dynamic typing does not, so a plain 'date'
        // cast (no format) leaves a "00:00:00" suffix in the raw stored
        // string on SQLite while MySQL stores pure "Y-m-d" — breaking any
        // exact-string date lookup (e.g. import de-duplication) on SQLite.
        'date' => 'date:Y-m-d',
        'approved_at' => 'date:Y-m-d',
        // D-085: deliberately NOT in $fillable -- provenance is only ever
        // set explicitly by a copy/import path (direct property
        // assignment, never mass assignment), never by ordinary
        // controller/request input. Every other creation path gets the
        // column's own NATIVE default.
        'provenance' => FinancialProvenance::class,
    ];

    protected $fillable = [
        'number',
        'date',
        'title',
        'creator_id',
        'company_id',
        'documentable_id',
        'documentable_type',
        'approved_at',
        'approver_id',
        'sync_version',
        'sync_status',
        'origin_device_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getFormattedDateAttribute()
    {
        return formatDate($this->date ?? now());
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'document_id');
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function documentFiles()
    {
        return $this->hasMany(DocumentFile::class);
    }
}
