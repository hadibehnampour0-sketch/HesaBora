<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementObligation extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'original_amount',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
    ];

    /**
     * The instrument or agreement this obligation originated from (e.g. a
     * bounced Cheque). D-070: this source's own lifecycle is never touched
     * by settlement activity.
     */
    public function source()
    {
        return $this->morphTo();
    }

    public function installments()
    {
        return $this->hasMany(SettlementInstallment::class, 'obligation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
