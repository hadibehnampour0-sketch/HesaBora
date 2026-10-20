@extends('layouts.app')

@section('title', 'تحلیل سلامت داده‌ها و تراز افتتاحیه')

@section('content')
<div class="container mx-auto px-4 py-6 max-w-5xl space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">مرحله ۲: گزارش عیب‌یابی و تراز افتتاحیه</h1>
            <p class="text-sm text-slate-500 mt-1">بررسی خودکار ناهمخوانی‌ها، موجودی‌های منفی و برقراری معادله اساسی حسابداری (دارایی = بدهی + سرمایه)</p>
        </div>
        <a href="{{ route('migration.index') }}" class="btn btn-outline btn-sm">بازگشت</a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-base-100 p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500">تعداد مشتریان / طرف‌حساب‌ها</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ count($payload->customers) }}</div>
        </div>
        <div class="bg-base-100 p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500">تعداد کالا و خدمات</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ count($payload->products) }}</div>
        </div>
        <div class="bg-base-100 p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500">تعداد چک‌های در جریان</div>
            <div class="text-2xl font-black text-slate-800 mt-1">{{ count($payload->cheques) }}</div>
        </div>
        <div class="bg-base-100 p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500">ارزش موجودی انبار</div>
            <div class="text-lg font-black text-emerald-600 font-mono mt-1">{{ number_format($health->reconciliation['total_inventory_valuation'] ?? 0) }} ریال</div>
        </div>
    </div>

    <!-- Health Check & Warnings -->
    @if(! empty($health->warnings))
        <div class="card bg-amber-50 border border-amber-200 rounded-xl">
            <div class="card-body p-5">
                <h3 class="font-bold text-amber-900 text-sm flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    هشدارهای شناسایی شده در داده‌های سیستم قدیمی ({{ count($health->warnings) }} مورد):
                </h3>
                <ul class="list-disc list-inside text-xs text-amber-800 space-y-1 mt-2">
                    @foreach($health->warnings as $w)
                        <li>{{ $w['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Accounting Reconciliation Table -->
    <div class="card bg-base-100 shadow border border-slate-200">
        <div class="card-body p-6 space-y-4">
            <h3 class="font-bold text-slate-800 text-base">پیش‌نمایش تراز افتتاحیه سند انتقال (Balance Sheet Equation)</h3>

            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-sm">
                    <thead>
                        <tr>
                            <th>سرفصل حسابداری در دخل</th>
                            <th>ماهیت</th>
                            <th class="text-left">مبلغ (ریال)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-medium">بدهکاران تجاری (مانده بدهی مشتریان)</td>
                            <td>بدهکار (دارایی)</td>
                            <td class="text-left font-mono">{{ number_format($health->reconciliation['total_debtors'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="font-medium">موجودی کالا (ارزش انبار)</td>
                            <td>بدهکار (دارایی)</td>
                            <td class="text-left font-mono">{{ number_format($health->reconciliation['total_inventory_valuation'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="font-medium">اسناد دریافتنی (چک‌های نزد صندوق)</td>
                            <td>بدهکار (دارایی)</td>
                            <td class="text-left font-mono">{{ number_format($health->reconciliation['total_receivable_cheques'] ?? 0) }}</td>
                        </tr>
                        <tr class="bg-slate-50 font-bold">
                            <td colspan="2">مجموع دارایی‌های انتقالی:</td>
                            <td class="text-left font-mono text-emerald-600">{{ number_format($health->reconciliation['total_assets'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="font-medium">بستانکاران تجاری (طلب تامین‌کنندگان)</td>
                            <td>بستانکار (بدهی)</td>
                            <td class="text-left font-mono">{{ number_format($health->reconciliation['total_creditors'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="font-medium">اسناد پرداختنی (چک‌های صادره سررسید نشده)</td>
                            <td>بستانکار (بدهی)</td>
                            <td class="text-left font-mono">{{ number_format($health->reconciliation['total_payable_cheques'] ?? 0) }}</td>
                        </tr>
                        <tr class="bg-primary/10 font-bold text-primary">
                            <td colspan="2">سرمایه و تراز افتتاحیه (تعدیل‌کننده و متوازن‌ساز سند افتتاحیه):</td>
                            <td class="text-left font-mono text-base">{{ number_format($health->reconciliation['opening_equity'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Execute Form -->
            <form action="{{ route('migration.execute') }}" method="POST" class="pt-6 border-t space-y-4">
                @csrf
                <input type="hidden" name="source_type" value="{{ $sourceType }}">
                <input type="hidden" name="raw_json" value="{{ $rawJson }}">

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="auto_repair" value="1" checked class="checkbox checkbox-primary" />
                        <span class="label-text font-medium text-slate-800">
                            فعال‌سازی اصلاح خودکار (Auto-Repair): موجودی‌های منفی صفر شوند و سند افتتاحیه متوازن به صورت سیستمی ثبت گردد.
                        </span>
                    </label>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <span class="text-xs text-slate-400">تمام اسناد ایجاد شده با برچسب BASELINE (سابقه انتقالی) ثبت خواهند شد.</span>
                    <button type="submit" class="btn btn-success text-white px-10">
                        تایید و اجرای کامل مهاجرت
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
