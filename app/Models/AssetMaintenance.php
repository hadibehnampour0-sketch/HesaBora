<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetMaintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'fixed_asset_id',
        'title',
        'maintenance_type',
        'date',
        'amount',
        'service_provider_id',
        'odometer_or_hours',
        'description',
        'invoice_attachment',
        'document_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'odometer_or_hours' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $m) {
            $m->company_id ??= getActiveCompany();
            $m->created_by ??= auth()->id();
        });
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'service_provider_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabels(): array
    {
        return [
            'service' => 'سرویس دوره‌ای و نگهداری',
            'repair' => 'تعمیرات و رفع خرابی',
            'part_replacement' => 'تعویض قطعه و لوازم یدکی',
            'capital_improvement' => 'بهبود و ارتقای اساسی دارایی',
            'inspection_insurance' => 'بیمه، معاینه فنی و تست دوره‌ای',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->maintenance_type] ?? 'تعمیرات و نگهداری';
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->invoice_attachment) {
            return null;
        }

        return Storage::disk('public')->url($this->invoice_attachment);
    }
}
