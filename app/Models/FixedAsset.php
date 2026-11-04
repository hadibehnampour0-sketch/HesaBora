<?php

namespace App\Models;

use App\Enums\FixedAssetStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'serial_or_plate',
        'location',
        'description',
        'image_path',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'asset_subject_id',
        'depreciation_expense_subject_id',
        'accumulated_depreciation_subject_id',
        'status',
        'disposed_at',
        'created_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'status' => FixedAssetStatus::class,
        'disposed_at' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (self $asset) {
            $asset->company_id ??= getActiveCompany();
            $asset->status ??= FixedAssetStatus::ACTIVE;
            $asset->category ??= 'equipment';
        });
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class)->orderBy('period_date');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class)->orderByDesc('date');
    }

    public function assetSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'asset_subject_id');
    }

    public function depreciationExpenseSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'depreciation_expense_subject_id');
    }

    public function accumulatedDepreciationSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'accumulated_depreciation_subject_id');
    }

    public static function categoryLabels(): array
    {
        return [
            'equipment' => 'تجهیزات و ماشین‌آلات',
            'vehicle' => 'وسایل نقلیه و خودرو',
            'facility' => 'تاسیسات و سرمایش/گرمایش',
            'furniture' => 'دکور، ویترین و اثاثیه',
            'tools' => 'ابزارآلات و وسایل کارگاهی',
            'digital' => 'رایانه، لپ‌تاپ و تجهیزات الکترونیک',
            'other' => 'سایر اموال و تجهیزات',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? 'تجهیزات و اموال';
    }

    public function depreciableAmount(): float
    {
        return max((float) $this->acquisition_cost - (float) $this->salvage_value, 0.0);
    }

    public function monthlyDepreciation(): float
    {
        if ($this->useful_life_months <= 0) {
            return 0.0;
        }

        return round($this->depreciableAmount() / $this->useful_life_months, 2);
    }

    public function totalMaintenanceCost(): float
    {
        return (float) $this->maintenances()->sum('amount');
    }

    public function maintenanceCount(): int
    {
        return (int) $this->maintenances()->count();
    }

    public function maintenanceToAssetRatio(): float
    {
        $cost = (float) $this->acquisition_cost;
        if ($cost <= 0) {
            return 0.0;
        }

        return round(($this->totalMaintenanceCost() / $cost) * 100, 1);
    }

    /**
     * Compute health and TCO status based on maintenance costs vs asset acquisition value.
     *
     * @return array{
     *     status: string,
     *     label: string,
     *     badge_class: string,
     *     color: string,
     *     ratio: float,
     *     total_maintenance: float
     * }
     */
    public function healthStatus(): array
    {
        $ratio = $this->maintenanceToAssetRatio();
        $total = $this->totalMaintenanceCost();

        if ($ratio >= 35.0) {
            return [
                'status' => 'critical',
                'label' => 'فرسایش سرمایه (غیراقتصادی)',
                'badge_class' => 'badge-error text-white',
                'color' => 'text-error',
                'ratio' => $ratio,
                'total_maintenance' => $total,
            ];
        }

        if ($ratio >= 15.0) {
            return [
                'status' => 'moderate',
                'label' => 'نیازمند پایش هزینه‌ها',
                'badge_class' => 'badge-warning text-white',
                'color' => 'text-warning',
                'ratio' => $ratio,
                'total_maintenance' => $total,
            ];
        }

        return [
            'status' => 'healthy',
            'label' => 'اقتصادی و مطلوب',
            'badge_class' => 'badge-success text-white',
            'color' => 'text-success',
            'ratio' => $ratio,
            'total_maintenance' => $total,
        ];
    }

    /**
     * AI-Powered TCO Recommendation for store/business owners.
     */
    public function aiRecommendation(): array
    {
        $ratio = $this->maintenanceToAssetRatio();
        $total = $this->totalMaintenanceCost();
        $count = $this->maintenanceCount();
        $formattedTotal = number_format($total);

        if ($ratio >= 35.0) {
            return [
                'level' => 'critical',
                'title' => 'هشدار پایش هوش مصنوعی دخل: هزینه مکرر و غیراقتصادی',
                'message' => "مجموع مخارج و تعمیرات این وسیله طی {$count} فقره به مبلغ {$formattedTotal} ریال رسیده است که معادل {$ratio}٪ بهای خرید اولیه آن می‌باشد. ادامه تعمیر و صرف هزینه برای این وسیله از نظر مالی توجیه‌پذیر نبوده و سرمایه شما را فرسایش می‌دهد. پیشنهاد هوش مصنوعی دخل: اسقاط یا فروش قطعات و جایگزینی با مدل نو جهت توقف هدررفت نقدینگی.",
                'action_label' => 'پیشنهاد جایگزینی یا اسقاط',
                'is_alert' => true,
            ];
        }

        if ($ratio >= 15.0) {
            return [
                'level' => 'warning',
                'title' => 'تذکر هوش مصنوعی: شیب افزایشی مخارج نگهداری',
                'message' => "این وسیله تاکنون {$count} بار تعمیر یا سرویس شده و مجموعاً {$formattedTotal} ریال ({$ratio}٪ ارزش خرید) هزینه داشته است. روند خرابی‌ها رو به افزایش است؛ بررسی کیفیت قطعات تعویضی و نظارت بر شرایط بهره‌برداری توصیه می‌گردد.",
                'action_label' => 'پایش دوره‌ای',
                'is_alert' => false,
            ];
        }

        return [
            'level' => 'healthy',
            'title' => 'تحلیل هوش مصنوعی: وضعیت نگهداری مطلوب و باصرفه',
            'message' => "هزینه‌های تعمیر و نگهداری این وسیله با مجموع {$formattedTotal} ریال در بازه کاملاً اقتصادی قرار دارد و دارایی دارای بازدهی و سلامت مطلوب است.",
            'action_label' => 'وضعیت نرمال',
            'is_alert' => false,
        ];
    }
}
