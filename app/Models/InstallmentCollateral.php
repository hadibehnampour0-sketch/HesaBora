<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentCollateral extends Model
{
    use HasFactory;

    protected $table = 'installment_collaterals';

    protected $fillable = [
        'company_id',
        'contract_id',
        'type',
        'title',
        'purpose',
        'estimated_value',
        'status',
        'cheque_sayad_number',
        'cheque_bank',
        'cheque_account_owner',
        'cheque_due_date',
        'cheque_amount',
        'cheque_installment_number',
        'gold_weight_grams',
        'gold_karat',
        'property_registry_code',
        'property_address',
        'property_area',
        'vehicle_model',
        'vehicle_plate',
        'vehicle_chassis_number',
        'vehicle_color',
        'promissory_note_number',
        'attachment_path',
        'notes',
        'received_at',
        'released_at',
        'release_notes',
    ];

    protected $casts = [
        'estimated_value' => 'integer',
        'cheque_amount' => 'integer',
        'cheque_due_date' => 'date:Y-m-d',
        'cheque_installment_number' => 'integer',
        'gold_weight_grams' => 'float',
        'gold_karat' => 'integer',
        'property_area' => 'float',
        'received_at' => 'date:Y-m-d',
        'released_at' => 'date:Y-m-d',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(InstallmentContract::class, 'contract_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'CHEQUE' => 'چک صیادی',
            'GOLD' => 'طلا و مسکوکات',
            'PROPERTY' => 'ملک و مستغلات',
            'VEHICLE' => 'خودرو و نقلیه',
            'PROMISSORY_NOTE' => 'سفته تجاری',
            default => 'سایر وثایق',
        };
    }

    public function getPurposeLabelAttribute(): string
    {
        return match ($this->purpose) {
            'PAYMENT' => 'پرداخت مستقیم قسط',
            default => 'وثیقه و ضمانت پرداخت',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'RELEASED' => 'عودت داده شده به مشتری',
            'EXECUTED' => 'به اجرا گذاشته شده',
            default => 'نزد صندوق / امانت',
        };
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return asset('storage/' . $this->attachment_path);
    }
}
