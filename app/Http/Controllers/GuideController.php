<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    /**
     * Display the comprehensive illustrated software manual and knowledge base.
     */
    public function index(Request $request): View
    {
        $activeTab = $request->query('topic', 'overview');

        $topics = [
            'overview' => [
                'title' => 'آشنایی کلی و شروع سریع',
                'icon' => 'home',
                'badge' => 'مفاهیم پایه',
                'desc' => 'معرفی ساختار دخل، تفاوت حالت ساده و پیشرفته و گام‌های نخستین راه‌اندازی.',
            ],
            'sales' => [
                'title' => 'فروش، فاکتور و صندوق POS',
                'icon' => 'sales',
                'badge' => 'معاملات',
                'desc' => 'صدور انواع فاکتور فروش، کار با صندوق سریع POS، تخفیفات و تهاتر با همکاران.',
            ],
            'treasury' => [
                'title' => 'خزانه‌داری و چرخه چک صیادی',
                'icon' => 'treasury',
                'badge' => 'نقد و اسناد',
                'desc' => 'ثبت دریافت/پرداخت، تخصیص به فاکتور و مراحل ۷ گانه وصول و گردش چک صیادی.',
            ],
            'warehouse' => [
                'title' => 'انبار، ردیابی سریال و بچ (Lot)',
                'icon' => 'warehouse',
                'badge' => 'موجودی و لجستیک',
                'desc' => 'کاردکس کالا، مدیریت انبارهای چندگانه، ردیابی سریال تکی و بچ ساخت تاریخ‌دار.',
            ],
            'accounting' => [
                'title' => 'حسابداری دوبل و اسناد مالی',
                'icon' => 'accounting',
                'badge' => 'استاندارد دوبل',
                'desc' => 'کدینگ سرفصل‌ها (کل، معین، تفصیلی)، موازنه سند، گردش دفاتر و بستن سال مالی.',
            ],
            'tax' => [
                'title' => 'سامانه مودیان و پایانه‌های فروشگاهی',
                'icon' => 'tax',
                'badge' => 'قوانین مالیاتی',
                'desc' => 'الگوهای صورتحساب نوع اول و دوم، کلید خصوصی/عمومی و ارسال مستقیم به کارپوشه.',
            ],
            'copilot' => [
                'title' => 'دستیار مالی هوش مصنوعی (AI) و مظنه بازار',
                'icon' => 'ai',
                'badge' => 'فناوری نوین',
                'desc' => 'پرسش صوتی و متنی از دفاتر مالی، گزارش‌گیری فوری و تحلیل نوسانات لحظه‌ای طلا و ارز.',
            ],
            'shortcuts' => [
                'title' => 'کلیدهای میانبر و ابزارهای سریع',
                'icon' => 'settings',
                'badge' => 'سرعت کاربری',
                'desc' => 'جستجوی پیشرفته با Ctrl+K، میانبرهای صفحه‌کلید و شخصی‌سازی پوسته‌ها.',
            ],
        ];

        if (! array_key_exists($activeTab, $topics)) {
            $activeTab = 'overview';
        }

        return view('guide.index', compact('topics', 'activeTab'));
    }
}
