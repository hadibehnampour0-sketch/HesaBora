<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePrintTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'paper_size',
        'is_default',
        'header_config',
        'columns_config',
        'footer_config',
        'watermark_text',
        'custom_css',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'header_config' => 'array',
        'columns_config' => 'array',
        'footer_config' => 'array',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get default header structure.
     */
    public static function defaultHeaderConfig(): array
    {
        return [
            'show_logo' => true,
            'logo_position' => 'right', // right, center, left
            'show_company_name' => true,
            'show_economic_code' => true,
            'show_national_code' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_qr' => true,
            'custom_title' => 'صورتحساب فروش کالا و خدمات',
            'font_size_pt' => 10,
        ];
    }

    /**
     * Get default columns list with order and visibility.
     */
    public static function defaultColumnsConfig(): array
    {
        return [
            ['key' => 'row_num', 'label' => 'ردیف', 'visible' => true, 'width_percent' => 5],
            ['key' => 'code', 'label' => 'کد کالا', 'visible' => true, 'width_percent' => 10],
            ['key' => 'description', 'label' => 'شرح کالا یا خدمات', 'visible' => true, 'width_percent' => 35],
            ['key' => 'quantity', 'label' => 'تعداد/مقدار', 'visible' => true, 'width_percent' => 10],
            ['key' => 'unit_price', 'label' => 'مبلغ واحد (ریال)', 'visible' => true, 'width_percent' => 12],
            ['key' => 'discount', 'label' => 'تخفیف', 'visible' => true, 'width_percent' => 8],
            ['key' => 'vat', 'label' => 'مالیات و عوارض', 'visible' => true, 'width_percent' => 8],
            ['key' => 'total_amount', 'label' => 'مبلغ کل (ریال)', 'visible' => true, 'width_percent' => 12],
        ];
    }

    /**
     * Get default footer structure.
     */
    public static function defaultFooterConfig(): array
    {
        return [
            'show_words_amount' => true,
            'show_bank_sheba' => true,
            'show_signatures' => true,
            'signature_labels' => [
                'امضاء و مهر خریدار',
                'امضاء و مهر فروشنده',
            ],
            'legal_notice' => 'کالای فروخته شده در صورت عدم دخل و تصرف تا ۴۸ ساعت قابل استرداد یا تعویض می‌باشد.',
        ];
    }
}
