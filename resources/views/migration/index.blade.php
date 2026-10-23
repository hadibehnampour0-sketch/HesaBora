<x-app-layout :title="__('مهاجرت هوشمند از نرم‌افزارهای حسابداری رقیب')">
    <x-show-message-bags />
<div class="container mx-auto px-4 py-6 max-w-4xl space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">موتور مهاجرت و عیب‌یابی حسابداری</h1>
            <p class="text-sm text-slate-500 mt-1">انتقال بدون دردسر اطلاعات از نرم‌افزارهای هلو، سپیدار و اکسل به نرم‌افزار دخل به همراه اصلاح و متوازن‌سازی تراز افتتاحیه</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm rounded-xl">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-error shadow-sm rounded-xl">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 shadow border border-slate-200">
        <div class="card-body p-6 space-y-6">
            <h2 class="text-lg font-bold text-primary">مرحله ۱: انتخاب نرم‌افزار مبدا و بارگذاری داده‌ها</h2>

            <form action="{{ route('migration.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- Source System Selection -->
                <div class="space-y-2">
                    <label class="label font-bold text-sm">نرم‌افزار حسابداری یا نوع داده‌های مبدا شما چیست؟</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="holoo" checked class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-slate-800">نرم‌افزار هلو (Holoo)</div>
                                <div class="text-xs text-slate-400">بک‌آپ یا اکسپورت کالا، طرف‌حساب و چک</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="sepidar" class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-slate-800">سپیدار همکاران سیستم</div>
                                <div class="text-xs text-slate-400">خروجی اکسل یا ساختار داده سپیدار</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="sql_dump" class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-slate-800">فایل دیتابیس (SQL Dump)</div>
                                <div class="text-xs text-slate-400">فایل‌های .sql استخراج شده از بانک اطلاعاتی</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="csv" class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-slate-800">فایل اکسل و متنی (CSV)</div>
                                <div class="text-xs text-slate-400">خروجی‌های متنی صفحه‌گسترده و اکسل</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="auto_unknown" class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-emerald-700 flex items-center gap-1">
                                    <span>تشخیص هوشمند با هوش مصنوعی</span>
                                    <span class="badge badge-success badge-xs text-white">AI</span>
                                </div>
                                <div class="text-xs text-slate-400">سیستم‌های متفرقه و دیتابیس‌های ناشناخته</div>
                            </div>
                        </label>

                        <label class="border border-slate-200 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                            <input type="radio" name="source_type" value="universal_json" class="radio radio-primary" />
                            <div>
                                <div class="font-bold text-slate-800">قالب جامع دخل (JSON)</div>
                                <div class="text-xs text-slate-400">فرمت استاندارد و ساختاریافته نرم‌افزار</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- File Upload or Raw Data -->
                <div class="space-y-2">
                    <label class="label font-bold text-sm">بارگذاری فایل پشتیبان / داده‌ها (.json, .txt, .sql, .csv):</label>
                    <input type="file" name="file" class="file-input file-input-bordered file-input-primary w-full" accept=".json,.txt,.sql,.csv" />
                    <div class="text-xs text-slate-400">پشتیبانی از فایل‌های متنی، اس‌کیوال دیتابیس، CSV و JSON تا سقف ۵۰ مگابایت</div>
                </div>

                <div class="divider text-xs text-slate-400">یا متن داده‌ها یا کدهای SQL را مستقیم وارد کنید</div>

                <div class="space-y-2">
                    <label class="label font-bold text-sm">متن داده‌ها، JSON یا دستورات INSERT دیتابیس:</label>
                    <textarea name="raw_json" rows="6" dir="ltr" class="textarea textarea-bordered w-full font-mono text-xs" placeholder='{"customers": [...], "products": [...]} یا کدهای SQL INSERT INTO ...'></textarea>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn btn-primary px-8">
                        تحلیل داده‌ها و عیب‌یابی حسابداری (مرحله بعد)
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
