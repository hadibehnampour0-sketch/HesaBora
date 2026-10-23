<x-app-layout :title="__('تشکیل پرونده تبادل جدید با همکار')">
    <x-show-message-bags />

<div class="container mx-auto px-4 py-6 max-w-4xl space-y-6" x-data="tradeForm()">
    <!-- Header -->
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">تشکیل پرونده تبادل و تهاتر کالا با همکار</h1>
            <p class="text-sm text-slate-500 mt-1">تعیین همکار و ثبت اقلام اولیه تحویل داده شده یا تحویل گرفته شده</p>
        </div>
        <a href="{{ route('trades.index') }}" class="btn btn-outline btn-sm">بازگشت به لیست</a>
    </div>

    <form action="{{ route('trades.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl">
            <div class="card-body p-6 space-y-4">
                <h2 class="text-base font-bold text-slate-800">۱. اطلاعات همکار و عنوان پرونده</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Partner Customer -->
                    <div>
                        <label class="label font-bold text-xs text-slate-700">مغازه یا همکار طرف‌حساب:</label>
                        <select name="customer_id" required class="select select-bordered w-full">
                            <option value="">انتخاب همکار...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->mobile ?: 'بدون شماره' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="label font-bold text-xs text-slate-700">عنوان تبادل:</label>
                        <input type="text" name="title" value="{{ old('title', 'تبادل و تهاتر اجناس همکار') }}" required class="input input-bordered w-full" />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="label font-bold text-xs text-slate-700">توضیحات و شرایط توافق:</label>
                    <textarea name="notes" rows="2" placeholder="مثلاً: تبادل کاور با گلس، تسویه مابه‌التفاوت آخر وقت به صورت نقدی..." class="textarea textarea-bordered w-full text-xs"></textarea>
                </div>
            </div>
        </div>

        <!-- Initial Items Section -->
        <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl">
            <div class="card-body p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">۲. ثبت اقلام مبادله‌شده (اختیاری)</h2>
                        <p class="text-xs text-slate-400 mt-0.5">می‌توانید همین الان یا بعداً در صفحه پرونده، اقلام را اضافه کنید.</p>
                    </div>
                    <button type="button" @click="addItem()" class="btn btn-sm btn-outline btn-primary gap-1">
                        + افزودن قلم کالا
                    </button>
                </div>

                <template x-for="(item, index) in items" :key="index">
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3 relative">
                        <div class="flex items-center justify-between border-b pb-2">
                            <span class="font-bold text-xs text-slate-600" x-text="'ردیف شماره ' + (index + 1)"></span>
                            <button type="button" @click="removeItem(index)" class="text-error text-xs hover:underline">حذف این ردیف</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <!-- Direction -->
                            <div>
                                <label class="label text-[11px] font-bold text-slate-600">جهت جابه‌جایی:</label>
                                <select :name="`items[${index}][direction]`" x-model="item.direction" class="select select-sm select-bordered w-full font-bold">
                                    <option value="out">📤 تحویل دادم (خروج / امانی)</option>
                                    <option value="in">📥 تحویل گرفتم (ورود / امانی)</option>
                                </select>
                            </div>

                            <!-- Item Name -->
                            <div>
                                <label class="label text-[11px] font-bold text-slate-600">نام کالا:</label>
                                <input type="text" :name="`items[${index}][item_name]`" x-model="item.item_name" required placeholder="مثلاً: کاور آیفون، گلس..." class="input input-sm input-bordered w-full" />
                            </div>

                            <!-- Quantity -->
                            <div>
                                <label class="label text-[11px] font-bold text-slate-600">تعداد:</label>
                                <input type="number" :name="`items[${index}][quantity]`" x-model="item.quantity" min="0.01" step="any" required placeholder="1" class="input input-sm input-bordered w-full font-mono text-left font-bold" />
                            </div>

                            <!-- Unit Price -->
                            <div>
                                <label class="label text-[11px] font-bold text-slate-600">ارزش واحد تخمینی (ریال):</label>
                                <input type="number" :name="`items[${index}][unit_price]`" x-model="item.unit_price" min="0" placeholder="0" class="input input-sm input-bordered w-full font-mono text-left" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('trades.index') }}" class="btn btn-ghost">انصراف</a>
            <button type="submit" class="btn btn-primary px-10">تشکیل پرونده تبادل و ذخیره</button>
        </div>
    </form>
</div>

<script>
function tradeForm() {
    return {
        items: [
            { direction: 'out', item_name: 'کاور سیلیکونی', quantity: 2, unit_price: 2000000 },
            { direction: 'in', item_name: 'گلس سرامیکی', quantity: 5, unit_price: 500000 }
        ],
        addItem() {
            this.items.push({ direction: 'out', item_name: '', quantity: 1, unit_price: 0 });
        },
        removeItem(index) {
            this.items.splice(index, 1);
        }
    }
}
</script>
</x-app-layout>
