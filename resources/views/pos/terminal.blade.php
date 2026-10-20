<x-app-layout :title="__('صندوق فروشگاهی هوشمند (POS)')">
<div class="h-[calc(100vh-80px)] flex flex-col md:flex-row gap-4 p-2 bg-slate-100" x-data="posTerminal()">

    <!-- Left / Center: Order Cart & Checkout -->
    <div class="flex-1 flex flex-col bg-base-100 rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        <!-- Top Bar: Barcode Input & Terminal Status -->
        <div class="p-4 bg-slate-900 text-white flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="badge badge-warning font-mono font-bold">{{ $terminalId }}</span>
                <span class="font-bold text-sm hidden md:inline">{{ $company?->name ?? 'فروشگاه دخل' }}</span>
                <span class="badge badge-success badge-xs"></span>
                <span class="text-xs text-slate-400">شبکه محلی متصل (LAN Real-Time)</span>
            </div>

            <!-- Barcode Scanner Input -->
            <div class="flex-1 max-w-md relative">
                <input
                    type="text"
                    x-ref="barcodeInput"
                    x-model="barcodeQuery"
                    @keydown.enter.prevent="scanBarcode()"
                    placeholder="بارکد کالا را اسکن کنید یا کلید Enter بزنید..."
                    class="input input-bordered input-sm w-full bg-slate-800 text-white border-slate-700 font-mono text-sm focus:border-warning focus:outline-none placeholder:text-slate-500"
                    autofocus
                />
                <button @click="scanBarcode()" class="absolute left-2 top-1.5 text-xs text-warning hover:text-white">
                    ثبت
                </button>
            </div>

            <div class="text-xs font-mono text-slate-300">
                اقلام: <span class="font-bold text-warning" x-text="cart.length"></span>
            </div>
        </div>

        <!-- Cart Table -->
        <div class="flex-1 overflow-y-auto p-4">
            <template x-if="cart.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-slate-400 space-y-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 stroke-slate-300" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <p class="font-medium">سبد خرید صندوق خالی است. بارکد کالا را اسکن کنید.</p>
                </div>
            </template>

            <table x-show="cart.length > 0" class="table table-zebra w-full text-sm">
                <thead>
                    <tr class="text-slate-600 bg-slate-50">
                        <th>#</th>
                        <th>نام کالا</th>
                        <th class="text-center">تعداد / وزن</th>
                        <th class="text-left">قیمت واحد</th>
                        <th class="text-left">مبلغ کل</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in cart" :key="item.product_id">
                        <tr>
                            <td x-text="index + 1" class="text-slate-400"></td>
                            <td>
                                <div class="font-bold text-slate-800" x-text="item.name"></div>
                                <div class="text-xs text-slate-400 font-mono" x-text="item.barcode || item.code"></div>
                            </td>
                            <td class="text-center">
                                <div class="join border border-slate-200 rounded-lg">
                                    <button @click="updateQty(item, -1)" class="join-item btn btn-xs btn-ghost">-</button>
                                    <input type="number" step="any" x-model.number="item.quantity" @change="recalculateItem(item)" class="join-item input input-xs w-16 text-center font-bold font-mono" />
                                    <button @click="updateQty(item, 1)" class="join-item btn btn-xs btn-ghost">+</button>
                                </div>
                            </td>
                            <td class="text-left font-mono font-medium" x-text="formatNumber(item.price)"></td>
                            <td class="text-left font-mono font-black text-slate-900" x-text="formatNumber(item.total)"></td>
                            <td>
                                <button @click="removeItem(index)" class="btn btn-ghost btn-xs text-rose-500 hover:bg-rose-50">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Bottom Payment & Total Footer -->
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-6">
                <div>
                    <div class="text-xs text-slate-500">مبلغ نهایی قابل پرداخت:</div>
                    <div class="text-2xl font-black text-emerald-600 font-mono mt-0.5">
                        <span x-text="formatNumber(cartTotal)"></span> <span class="text-xs font-normal text-slate-500">ریال</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <label class="cursor-pointer flex items-center gap-1.5 text-xs text-slate-600">
                        <input type="checkbox" x-model="openDrawer" class="checkbox checkbox-xs checkbox-primary" />
                        باز کردن کشوی پول نقد
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <button
                    @click="checkout()"
                    :disabled="cart.length === 0 || isProcessing"
                    class="btn btn-success btn-lg text-white font-black px-10 flex-1 md:flex-initial shadow-lg hover:shadow-xl transition-all"
                >
                    <span x-show="!isProcessing">تسویه و چاپ رسید (F12)</span>
                    <span x-show="isProcessing" class="loading loading-spinner"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Right Side: Quick Add Product Grid -->
    <div class="w-full md:w-80 bg-base-100 rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-col">
        <h3 class="font-bold text-sm text-slate-700 pb-2 border-b">کالاهای پرفروش / سریع</h3>
        <div class="flex-1 overflow-y-auto grid grid-cols-2 gap-2 pt-3">
            @foreach($quickProducts as $p)
                <button
                    type="button"
                    @click="addProduct({{ $p->id }}, '{{ addslashes($p->name) }}', {{ (float)$p->selling_price }}, '{{ $p->barcode ?? $p->code }}')"
                    class="btn btn-outline btn-sm h-auto py-2.5 flex flex-col items-center justify-center border-slate-200 hover:border-primary text-center group"
                >
                    <div class="text-xs font-bold text-slate-800 line-clamp-1 group-hover:text-primary">{{ $p->name }}</div>
                    <div class="text-[11px] font-mono text-emerald-600 mt-1">{{ number_format($p->selling_price) }} ریال</div>
                </button>
            @endforeach
        </div>
    </div>

</div>

<script>
function posTerminal() {
    return {
        barcodeQuery: '',
        terminalId: '{{ $terminalId }}',
        cart: [],
        openDrawer: true,
        isProcessing: false,

        get cartTotal() {
            return this.cart.reduce((sum, item) => sum + (item.total || 0), 0);
        },

        formatNumber(num) {
            return new Intl.NumberFormat('fa-IR').format(num || 0);
        },

        scanBarcode() {
            if (!this.barcodeQuery.trim()) return;

            fetch('{{ route("pos.scan") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    barcode: this.barcodeQuery.trim(),
                    terminal_id: this.terminalId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.item) {
                    this.addItemToCart(data.item);
                    this.barcodeQuery = '';
                } else {
                    alert(data.message || 'کالا یافت نشد');
                }
            })
            .catch(() => alert('خطا در برقراری ارتباط با سرور'))
            .finally(() => this.$refs.barcodeInput.focus());
        },

        addItemToCart(item) {
            let existing = this.cart.find(i => i.product_id === item.product_id);
            if (existing) {
                existing.quantity += (item.quantity || 1);
                this.recalculateItem(existing);
            } else {
                this.cart.push({
                    product_id: item.product_id,
                    name: item.name,
                    barcode: item.barcode,
                    code: item.code,
                    price: item.unit_price,
                    quantity: item.quantity || 1,
                    total: (item.quantity || 1) * item.unit_price
                });
            }
        },

        addProduct(id, name, price, barcode) {
            this.addItemToCart({
                product_id: id,
                name: name,
                unit_price: price,
                quantity: 1,
                barcode: barcode
            });
            this.$refs.barcodeInput.focus();
        },

        updateQty(item, delta) {
            item.quantity = Math.max(1, item.quantity + delta);
            this.recalculateItem(item);
        },

        recalculateItem(item) {
            item.total = item.quantity * item.price;
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            this.$refs.barcodeInput.focus();
        },

        checkout() {
            if (this.cart.length === 0 || this.isProcessing) return;
            this.isProcessing = true;

            const payload = {
                terminal_id: this.terminalId,
                open_drawer: this.openDrawer,
                items: this.cart.map(i => ({
                    product_id: i.product_id,
                    quantity: i.quantity,
                    price: i.price
                }))
            };

            fetch('{{ route("pos.checkout") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('فاکتور شماره ' + data.invoice_number + ' با موفقیت ثبت شد و رسید صادر گردید.');
                    this.cart = [];
                } else {
                    alert(data.message || 'خطا در ثبت فاکتور');
                }
            })
            .catch(() => alert('خطا در تسویه'))
            .finally(() => {
                this.isProcessing = false;
                this.$refs.barcodeInput.focus();
            });
        }
    };
}
</script>
</x-app-layout>
