<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>{{ __('دخل همراه | پخش مویرگی و انبارداری') }}</title>
    <link rel="manifest" href="{{ route('mobile.manifest') }}">
    <meta name="theme-color" content="#06b6d4">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        body { -webkit-tap-highlight-color: transparent; }
        .safe-bottom { padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans select-none"
      x-data="mobileApp()"
      x-init="initApp()">

    <!-- Top App Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 text-white font-bold text-sm">
                د
            </div>
            <div>
                <h1 class="text-sm font-bold tracking-tight text-white flex items-center gap-1.5">
                    <span>دخل همراه</span>
                    <span class="text-xs font-normal text-cyan-400" x-text="roleTitle()"></span>
                </h1>
                <p class="text-[10px] text-slate-400" x-text="routeTitle"></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Online / Offline Badge -->
            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full"
                  :class="isOnline ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'">
                <span class="w-1.5 h-1.5 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
                <span x-text="isOnline ? 'آنلاین' : 'آفلاین'"></span>
            </span>

            <a href="{{ route('home') }}" class="btn btn-ghost btn-xs text-slate-400 hover:text-white" title="بازگشت به پنل اصلی">
                ✕
            </a>
        </div>
    </header>

    <!-- Main Content Views -->
    <main class="flex-1 overflow-y-auto px-4 py-3 safe-bottom space-y-4">

        <!-- 1. TAB: VISITS & ROUTES (پخش مویرگی و مسیرهای ویزیت) -->
        <section x-show="activeTab === 'visits'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-200 flex items-center gap-1.5">
                    <span>🗺️</span>
                    <span>مسیر ویزیت امروز</span>
                </h2>
                <span class="text-xs text-cyan-400 font-mono" x-text="customers.length + ' مشتری'"></span>
            </div>

            <!-- Customer Search -->
            <div class="relative">
                <input type="text" x-model="customerSearch" placeholder="جستجوی نام، تلفن یا آدرس مشتری..."
                       class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-500">
                <span class="absolute left-3 top-2.5 text-slate-500 text-sm">🔍</span>
            </div>

            <!-- Customer Visit Cards List -->
            <div class="space-y-2.5">
                <template x-for="cust in filteredCustomers" :key="cust.id">
                    <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-3.5 space-y-2.5 transition active:scale-[0.99]"
                         :class="selectedCustomer && selectedCustomer.id === cust.id ? 'ring-2 ring-cyan-500/50 bg-slate-850' : ''">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-sm text-white" x-text="cust.name"></h3>
                                <p class="text-xs text-slate-400 mt-0.5 line-clamp-1" x-text="cust.address || 'آدرس ثبت نشده'"></p>
                            </div>
                            <span class="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded-full" x-text="'اولویت ' + cust.priority"></span>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-slate-800/60">
                            <div class="flex items-center gap-2">
                                <a :href="'tel:' + (cust.mobile || cust.phone)" x-show="cust.mobile || cust.phone"
                                   class="inline-flex items-center gap-1 text-[11px] text-cyan-400 bg-cyan-950/60 border border-cyan-800/50 px-2.5 py-1 rounded-lg">
                                    <span>📞</span>
                                    <span x-text="cust.mobile || cust.phone"></span>
                                </a>
                            </div>

                            <button type="button" @click="startOrderForCustomer(cust)"
                                    class="btn btn-xs bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white border-none rounded-lg px-3 gap-1">
                                <span>🛒</span>
                                <span>ثبت سفارش</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- 2. TAB: TOUCH ORDER / VAN SALES (کاتالوگ لمسی و فاکتور فروش) -->
        <section x-show="activeTab === 'order'" x-cloak class="space-y-3">
            <!-- Selected Customer Header -->
            <div class="bg-cyan-950/40 border border-cyan-800/40 rounded-xl p-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">👤</span>
                    <div>
                        <div class="text-[10px] text-cyan-400 font-medium">مشتری منتخب فاکتور:</div>
                        <div class="text-xs font-bold text-white" x-text="selectedCustomer ? selectedCustomer.name : 'انتخاب نشده (لطفاً انتخاب کنید)'"></div>
                    </div>
                </div>
                <button type="button" @click="activeTab = 'visits'" class="btn btn-ghost btn-xs text-cyan-400">
                    تغییر ↻
                </button>
            </div>

            <!-- Warehouse Selector & Product Search -->
            <div class="grid grid-cols-2 gap-2">
                <select x-model="warehouseId" @change="loadCatalog()" class="bg-slate-900 border border-slate-800 rounded-xl px-2.5 py-2 text-xs text-slate-200 focus:outline-none focus:border-cyan-500">
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>

                <div class="relative">
                    <input type="text" x-model="productSearch" placeholder="جستجوی کالا..."
                           class="w-full bg-slate-900 border border-slate-800 rounded-xl px-2.5 py-2 text-xs text-slate-200 focus:outline-none focus:border-cyan-500">
                    <span class="absolute left-2.5 top-2 text-slate-500 text-xs">🔍</span>
                </div>
            </div>

            <!-- Product Touch Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <template x-for="prod in filteredProducts" :key="prod.id">
                    <div class="bg-slate-900 border border-slate-800/80 rounded-xl p-3 flex flex-col justify-between space-y-2">
                        <div>
                            <div class="flex items-start justify-between">
                                <h4 class="font-bold text-xs text-slate-100 line-clamp-1" x-text="prod.name"></h4>
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded"
                                      :class="prod.on_hand > 0 ? 'bg-emerald-950/60 text-emerald-400' : 'bg-rose-950/60 text-rose-400'"
                                      x-text="'موجودی: ' + prod.on_hand"></span>
                            </div>
                            <div class="text-[11px] font-mono text-cyan-400 font-bold mt-1" x-text="formatMoney(prod.selling_price) + ' ریال'"></div>
                        </div>

                        <!-- Stepper / Add to Cart -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800/60">
                            <template x-if="getCartItem(prod.id)">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="decrementCart(prod.id)" class="w-7 h-7 rounded-lg bg-slate-800 text-white font-bold flex items-center justify-center active:scale-95">
                                        -
                                    </button>
                                    <span class="text-xs font-bold text-cyan-400 font-mono w-6 text-center" x-text="getCartItem(prod.id).quantity"></span>
                                    <button type="button" @click="incrementCart(prod)" class="w-7 h-7 rounded-lg bg-cyan-600 text-white font-bold flex items-center justify-center active:scale-95">
                                        +
                                    </button>
                                </div>
                            </template>
                            <template x-if="!getCartItem(prod.id)">
                                <button type="button" @click="incrementCart(prod)"
                                        class="btn btn-xs bg-slate-800 hover:bg-cyan-600 text-slate-200 hover:text-white border-none rounded-lg w-full gap-1">
                                    <span>+ افزودن به فاکتور</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Floating Cart Summary Bar -->
            <div x-show="cart.length > 0" x-cloak
                 class="fixed bottom-16 left-4 right-4 z-30 bg-slate-900/95 backdrop-blur-md border border-cyan-500/40 shadow-2xl shadow-cyan-950/60 rounded-2xl p-3 flex items-center justify-between">
                <div>
                    <div class="text-[10px] text-slate-400" x-text="cart.length + ' قلم کالا' "></div>
                    <div class="text-xs font-bold text-cyan-400 font-mono" x-text="formatMoney(cartTotal()) + ' ریال'"></div>
                </div>

                <button type="button" @click="showCheckoutModal = true"
                        class="btn btn-sm bg-gradient-to-r from-cyan-500 to-indigo-600 hover:from-cyan-400 hover:to-indigo-500 text-white border-none rounded-xl px-4 gap-1.5 shadow-lg shadow-cyan-500/20">
                    <span>تکمیل و صدور فاکتور</span>
                    <span>←</span>
                </button>
            </div>
        </section>

        <!-- 3. TAB: WMS & BARCODE SCANNER (انبارداری هوشمند و بارکدخوان) -->
        <section x-show="activeTab === 'wms'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-200 flex items-center gap-1.5">
                    <span>📦</span>
                    <span>انبارداری و اسکن بارکد (WMS)</span>
                </h2>
                <span class="text-xs text-indigo-400">اسکن دوربین / لیزری</span>
            </div>

            <!-- Barcode Input Box with Camera Trigger -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                <label class="text-xs text-slate-300 font-medium block">کد یا بارکد کالا را اسکن یا وارد کنید:</label>
                <div class="flex gap-2">
                    <input type="text" x-model="barcodeQuery" @keyup.enter="lookupBarcode()"
                           placeholder="مثال: 6260123456789"
                           class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-slate-100 font-mono placeholder-slate-600 focus:outline-none focus:border-cyan-500">

                    <button type="button" @click="lookupBarcode()" :disabled="isLookingUp"
                            class="btn btn-primary btn-sm rounded-xl px-4">
                        <span x-show="!isLookingUp">استعلام</span>
                        <span x-show="isLookingUp" class="loading loading-spinner loading-xs"></span>
                    </button>
                </div>

                <!-- Camera Scanner Trigger -->
                <button type="button" @click="toggleCameraScanner()"
                        class="w-full py-2.5 bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 rounded-xl text-xs text-slate-200 font-medium flex items-center justify-center gap-2">
                    <span>📷</span>
                    <span x-text="cameraActive ? 'بستن دوربین اسکنر' : 'فعال‌سازی اسکن بارکد با دوربین گوشی'"></span>
                </button>

                <!-- Camera Viewfinder -->
                <div x-show="cameraActive" x-cloak class="relative rounded-xl overflow-hidden bg-black aspect-video border border-cyan-500/50">
                    <video id="barcode-video" class="w-full h-full object-cover"></video>
                    <div class="absolute inset-0 border-2 border-dashed border-cyan-400/60 m-8 rounded-lg pointer-events-none flex items-center justify-center">
                        <span class="text-[10px] text-cyan-300 bg-black/60 px-2 py-0.5 rounded">بارکد را در این کادر قرار دهید</span>
                    </div>
                </div>
            </div>

            <!-- Scanned Product Card -->
            <div x-show="scannedProduct" x-cloak class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
                <div class="flex items-start justify-between border-b border-slate-800 pb-3">
                    <div>
                        <span class="text-[10px] text-cyan-400 font-mono" x-text="'کد کالا: ' + (scannedProduct?.code || '-')"></span>
                        <h3 class="font-bold text-sm text-white mt-0.5" x-text="scannedProduct?.name"></h3>
                        <p class="text-xs text-slate-400" x-text="'مکان در انبار: ' + (scannedProduct?.location || 'تعیین نشده')"></p>
                    </div>
                    <div class="text-end">
                        <div class="text-xs font-mono font-bold text-emerald-400" x-text="scannedProduct?.total_on_hand + ' موجودی کل'"></div>
                        <div class="text-[10px] font-mono text-slate-400 mt-1" x-text="formatMoney(scannedProduct?.selling_price) + ' ریال'"></div>
                    </div>
                </div>

                <!-- Multi-Warehouse Stock Breakdown -->
                <div class="space-y-1.5">
                    <div class="text-[11px] font-bold text-slate-300">موجودی به تفکیک انبار:</div>
                    <template x-for="stk in (scannedProduct?.stocks || [])" :key="stk.warehouse_name">
                        <div class="flex items-center justify-between text-xs py-1 px-2.5 rounded-lg bg-slate-950 border border-slate-800/60">
                            <span class="text-slate-300" x-text="stk.warehouse_name"></span>
                            <span class="font-mono font-bold text-cyan-400" x-text="stk.quantity"></span>
                        </div>
                    </template>
                </div>

                <!-- Instant Cycle Count Form (انبارگردانی لحظه‌ای) -->
                <div class="bg-slate-950/80 border border-indigo-900/40 rounded-xl p-3 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-indigo-300">ثبت شمارش فیزیکی (انبارگردانی):</span>
                        <span class="text-[10px] text-slate-400">انبار فعال</span>
                    </div>

                    <div class="flex gap-2">
                        <input type="number" step="1" min="0" x-model="countedQuantity" placeholder="تعداد شمارش‌شده"
                               class="flex-1 bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs font-mono text-white focus:outline-none focus:border-indigo-500">

                        <button type="button" @click="submitStockCount()" :disabled="isCounting"
                                class="btn btn-xs bg-indigo-600 hover:bg-indigo-500 text-white border-none rounded-lg px-3">
                            <span x-show="!isCounting">ثبت شمارش</span>
                            <span x-show="isCounting" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>

                    <div x-show="countFeedback" x-cloak class="text-[11px] text-emerald-400 bg-emerald-950/40 p-2 rounded-lg" x-text="countFeedback"></div>
                </div>
            </div>
        </section>

        <!-- 4. TAB: DAILY SETTLEMENT (تسویه حساب روزانه ویزیتور) -->
        <section x-show="activeTab === 'settlement'" x-cloak class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-200 flex items-center gap-1.5">
                    <span>💼</span>
                    <span>تسویه حساب شیفت کاری ویزیتور</span>
                </h2>
                <span class="text-xs text-slate-400">{{ formatDate(today()) }}</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3.5">
                <div class="space-y-1">
                    <label class="text-xs text-slate-300">مجموع دریافتی نقدی (ریال):</label>
                    <input type="number" x-model="settlementCash" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-white">
                </div>

                <div class="space-y-1">
                    <label class="text-xs text-slate-300">مجموع پرداختی با کارتخوان POS سیار (ریال):</label>
                    <input type="number" x-model="settlementPos" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-white">
                </div>

                <div class="space-y-1">
                    <label class="text-xs text-slate-300">مجموع چک‌های دریافتی (ریال):</label>
                    <input type="number" x-model="settlementCheque" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs font-mono text-white">
                </div>

                <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                    <span class="text-xs text-slate-400">جمع کل تحویلی:</span>
                    <span class="text-sm font-mono font-bold text-cyan-400" x-text="formatMoney(Number(settlementCash || 0) + Number(settlementPos || 0) + Number(settlementCheque || 0)) + ' ریال'"></span>
                </div>

                <button type="button" @click="submitDailySettlement()" :disabled="isSubmittingSettlement"
                        class="btn btn-primary w-full rounded-xl gap-2">
                    <span x-show="!isSubmittingSettlement">ارسال تسویه به حسابداری</span>
                    <span x-show="isSubmittingSettlement" class="loading loading-spinner loading-xs"></span>
                </button>

                <div x-show="settlementFeedback" x-cloak class="text-xs text-emerald-400 bg-emerald-950/40 p-2.5 rounded-xl text-center" x-text="settlementFeedback"></div>
            </div>
        </section>

    </main>

    <!-- Checkout Modal -->
    <div x-show="showCheckoutModal" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="bg-slate-900 border border-slate-800 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl p-5 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="font-bold text-sm text-white">تأیید و صدور فاکتور</h3>
                <button type="button" @click="showCheckoutModal = false" class="btn btn-ghost btn-xs text-slate-400">✕</button>
            </div>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>مشتری:</span>
                    <span class="font-bold text-white" x-text="selectedCustomer?.name"></span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>تعداد اقلام:</span>
                    <span class="font-mono text-white" x-text="cart.length"></span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>مبلغ کل فاکتور:</span>
                    <span class="font-mono font-bold text-cyan-400 text-sm" x-text="formatMoney(cartTotal()) + ' ریال'"></span>
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs text-slate-300 font-medium">نحوه تسویه:</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300'" class="py-2 text-xs rounded-xl font-medium">💵 نقد</button>
                    <button type="button" @click="paymentMethod = 'pos'" :class="paymentMethod === 'pos' ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300'" class="py-2 text-xs rounded-xl font-medium">💳 پوز سیار</button>
                    <button type="button" @click="paymentMethod = 'cheque'" :class="paymentMethod === 'cheque' ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300'" class="py-2 text-xs rounded-xl font-medium">📝 چک</button>
                    <button type="button" @click="paymentMethod = 'credit'" :class="paymentMethod === 'credit' ? 'bg-cyan-600 text-white' : 'bg-slate-800 text-slate-300'" class="py-2 text-xs rounded-xl font-medium">⏳ نسیه / دفتری</button>
                </div>
            </div>

            <button type="button" @click="submitOrder()" :disabled="isSubmittingOrder"
                    class="btn btn-primary w-full rounded-xl gap-2 text-sm shadow-xl shadow-cyan-500/20">
                <span x-show="!isSubmittingOrder">ثبت نهایی و صدور سند در دخل</span>
                <span x-show="isSubmittingOrder" class="loading loading-spinner loading-xs"></span>
            </button>
        </div>
    </div>

    <!-- Success Order Receipt Modal -->
    <div x-show="lastCreatedInvoice" x-cloak class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-emerald-500/50 w-full max-w-sm rounded-3xl p-5 space-y-4 text-center">
            <div class="w-12 h-12 bg-emerald-500/20 border border-emerald-500/40 rounded-full flex items-center justify-center mx-auto text-2xl text-emerald-400">
                ✓
            </div>
            <div>
                <h3 class="font-bold text-base text-white">فاکتور با موفقیت ثبت شد</h3>
                <p class="text-xs text-slate-400 mt-1" x-text="'شماره فاکتور: ' + lastCreatedInvoice?.invoice_number"></p>
                <p class="text-xs font-mono font-bold text-cyan-400 mt-1" x-text="formatMoney(lastCreatedInvoice?.total_amount) + ' ریال'"></p>
            </div>

            <div class="flex gap-2">
                <button type="button" @click="printReceipt()" class="btn btn-outline btn-sm flex-1 rounded-xl gap-1">
                    <span>🖨️</span>
                    <span>چاپ فیش</span>
                </button>
                <button type="button" @click="closeReceiptModal()" class="btn btn-primary btn-sm flex-1 rounded-xl">
                    <span>فاکتور بعدی</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation Bar (Thumb ergonomic mobile shell) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-lg border-t border-slate-800/80 px-2 py-1.5 flex items-center justify-around">
        <button type="button" @click="activeTab = 'visits'"
                class="flex flex-col items-center py-1 px-3 rounded-xl transition"
                :class="activeTab === 'visits' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'">
            <span class="text-lg">🗺️</span>
            <span class="text-[10px] font-medium mt-0.5">مسیر ویزیت</span>
        </button>

        <button type="button" @click="activeTab = 'order'"
                class="flex flex-col items-center py-1 px-3 rounded-xl transition relative"
                :class="activeTab === 'order' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'">
            <span class="text-lg">🛒</span>
            <span class="text-[10px] font-medium mt-0.5">فروش و فاکتور</span>
            <span x-show="cart.length > 0" class="absolute top-0 right-2 w-4 h-4 bg-cyan-500 text-slate-950 font-bold rounded-full text-[9px] flex items-center justify-center font-mono" x-text="cart.length"></span>
        </button>

        <button type="button" @click="activeTab = 'wms'"
                class="flex flex-col items-center py-1 px-3 rounded-xl transition"
                :class="activeTab === 'wms' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'">
            <span class="text-lg">📦</span>
            <span class="text-[10px] font-medium mt-0.5">انبار و بارکد</span>
        </button>

        <button type="button" @click="activeTab = 'settlement'"
                class="flex flex-col items-center py-1 px-3 rounded-xl transition"
                :class="activeTab === 'settlement' ? 'text-cyan-400' : 'text-slate-400 hover:text-slate-200'">
            <span class="text-lg">💼</span>
            <span class="text-[10px] font-medium mt-0.5">تسویه شیفت</span>
        </button>
    </nav>

    <script>
        function mobileApp() {
            return {
                activeTab: 'visits',
                isOnline: navigator.onLine,
                warehouseId: '{{ $defaultWarehouseId }}',
                routeTitle: 'در حال بارگذاری...',
                customers: [],
                customerSearch: '',
                selectedCustomer: null,

                products: [],
                productSearch: '',
                cart: [],
                showCheckoutModal: false,
                paymentMethod: 'cash',
                isSubmittingOrder: false,
                lastCreatedInvoice: null,

                barcodeQuery: '',
                isLookingUp: false,
                scannedProduct: null,
                cameraActive: false,
                cameraStream: null,
                countedQuantity: '',
                isCounting: false,
                countFeedback: '',

                settlementCash: '',
                settlementPos: '',
                settlementCheque: '',
                isSubmittingSettlement: false,
                settlementFeedback: '',

                initApp() {
                    window.addEventListener('online', () => this.isOnline = true);
                    window.addEventListener('offline', () => this.isOnline = false);

                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('{{ route('mobile.sw') }}').catch(() => {});
                    }

                    this.loadVisits();
                    this.loadCatalog();
                },

                roleTitle() {
                    return this.activeTab === 'wms' ? '| انبارداری WMS' : '| ویزیتور پخش';
                },

                get filteredCustomers() {
                    if (!this.customerSearch.trim()) return this.customers;
                    const q = this.customerSearch.toLowerCase();
                    return this.customers.filter(c =>
                        (c.name && c.name.toLowerCase().includes(q)) ||
                        (c.mobile && c.mobile.includes(q)) ||
                        (c.address && c.address.toLowerCase().includes(q))
                    );
                },

                get filteredProducts() {
                    if (!this.productSearch.trim()) return this.products;
                    const q = this.productSearch.toLowerCase();
                    return this.products.filter(p =>
                        (p.name && p.name.toLowerCase().includes(q)) ||
                        (p.code && p.code.toLowerCase().includes(q))
                    );
                },

                async loadVisits() {
                    try {
                        const res = await fetch('{{ route('mobile.visits') }}');
                        const data = await res.json();
                        if (data.success) {
                            this.customers = data.customers || [];
                            this.routeTitle = data.route_title || 'مشتریان فعال';
                            if (this.customers.length > 0 && !this.selectedCustomer) {
                                this.selectedCustomer = this.customers[0];
                            }
                        }
                    } catch (e) {
                        // Offline fallback from localStorage
                        const cached = localStorage.getItem('dakhl_mobile_customers');
                        if (cached) this.customers = JSON.parse(cached);
                    }
                },

                async loadCatalog() {
                    try {
                        const url = '{{ route('mobile.catalog') }}' + (this.warehouseId ? '?warehouse_id=' + this.warehouseId : '');
                        const res = await fetch(url);
                        const data = await res.json();
                        if (data.success) {
                            this.products = data.products || [];
                            localStorage.setItem('dakhl_mobile_catalog', JSON.stringify(this.products));
                        }
                    } catch (e) {
                        const cached = localStorage.getItem('dakhl_mobile_catalog');
                        if (cached) this.products = JSON.parse(cached);
                    }
                },

                startOrderForCustomer(cust) {
                    this.selectedCustomer = cust;
                    this.activeTab = 'order';
                },

                getCartItem(productId) {
                    return this.cart.find(item => item.product.id === productId);
                },

                incrementCart(prod) {
                    const item = this.getCartItem(prod.id);
                    if (item) {
                        item.quantity += 1;
                    } else {
                        this.cart.push({
                            product: prod,
                            quantity: 1,
                            price: prod.selling_price
                        });
                    }
                },

                decrementCart(productId) {
                    const idx = this.cart.findIndex(item => item.product.id === productId);
                    if (idx > -1) {
                        if (this.cart[idx].quantity > 1) {
                            this.cart[idx].quantity -= 1;
                        } else {
                            this.cart.splice(idx, 1);
                        }
                    }
                },

                cartTotal() {
                    return this.cart.reduce((sum, item) => sum + (item.quantity * item.price), 0);
                },

                async submitOrder() {
                    if (!this.selectedCustomer) {
                        alert('لطفاً ابتدا مشتری را انتخاب نمایید.');
                        return;
                    }
                    if (this.cart.length === 0) {
                        alert('سبد خرید خالی است.');
                        return;
                    }

                    this.isSubmittingOrder = true;
                    try {
                        const payload = {
                            customer_id: this.selectedCustomer.id,
                            warehouse_id: this.warehouseId,
                            payment_method: this.paymentMethod,
                            items: this.cart.map(item => ({
                                product_id: item.product.id,
                                quantity: item.quantity,
                                price: item.price
                            }))
                        };

                        const res = await fetch('{{ route('mobile.orders.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.lastCreatedInvoice = data;
                            this.cart = [];
                            this.showCheckoutModal = false;
                        } else {
                            alert(data.message || 'خطا در ثبت فاکتور');
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    } finally {
                        this.isSubmittingOrder = false;
                    }
                },

                printReceipt() {
                    window.print();
                },

                closeReceiptModal() {
                    this.lastCreatedInvoice = null;
                    this.activeTab = 'visits';
                },

                async lookupBarcode() {
                    if (!this.barcodeQuery.trim()) return;
                    this.isLookingUp = true;
                    this.scannedProduct = null;
                    this.countFeedback = '';

                    try {
                        const res = await fetch('{{ route('mobile.scan') }}?code=' + encodeURIComponent(this.barcodeQuery));
                        const data = await res.json();
                        if (data.success) {
                            this.scannedProduct = data.product;
                            this.countedQuantity = data.product.total_on_hand;
                        } else {
                            alert(data.message || 'کالا یافت نشد.');
                        }
                    } catch (e) {
                        alert('خطا در استعلام بارکد.');
                    } finally {
                        this.isLookingUp = false;
                    }
                },

                toggleCameraScanner() {
                    this.cameraActive = !this.cameraActive;
                    if (this.cameraActive) {
                        this.startCamera();
                    } else {
                        this.stopCamera();
                    }
                },

                startCamera() {
                    const video = document.getElementById('barcode-video');
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        alert('دوربین در این مرورگر پشتیبانی نمی‌شود.');
                        this.cameraActive = false;
                        return;
                    }

                    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                        .then(stream => {
                            this.cameraStream = stream;
                            video.srcObject = stream;
                            video.play();
                        })
                        .catch(() => {
                            alert('دسترسی به دوربین داده نشد.');
                            this.cameraActive = false;
                        });
                },

                stopCamera() {
                    if (this.cameraStream) {
                        this.cameraStream.getTracks().forEach(track => track.stop());
                        this.cameraStream = null;
                    }
                },

                async submitStockCount() {
                    if (!this.scannedProduct || this.countedQuantity === '') return;
                    this.isCounting = true;
                    this.countFeedback = '';

                    try {
                        const res = await fetch('{{ route('mobile.stock-count') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                product_id: this.scannedProduct.id,
                                warehouse_id: this.warehouseId,
                                counted_quantity: this.countedQuantity
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.countFeedback = 'شمارش ثبت شد. مغایرت: ' + (data.variance >= 0 ? '+' : '') + data.variance;
                        }
                    } catch (e) {
                        alert('خطا در ثبت انبارگردانی.');
                    } finally {
                        this.isCounting = false;
                    }
                },

                async submitDailySettlement() {
                    this.isSubmittingSettlement = true;
                    this.settlementFeedback = '';

                    try {
                        const res = await fetch('{{ route('mobile.settlement.submit') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                cash_collected: this.settlementCash || 0,
                                pos_collected: this.settlementPos || 0,
                                cheque_collected: this.settlementCheque || 0
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.settlementFeedback = 'تسویه حساب با موفقیت ثبت شد و به صندوق ارسال گردید.';
                        }
                    } catch (e) {
                        alert('خطا در ثبت تسویه.');
                    } finally {
                        this.isSubmittingSettlement = false;
                    }
                },

                formatMoney(amount) {
                    return new Intl.NumberFormat('fa-IR').format(amount || 0);
                }
            };
        }
    </script>
</body>
</html>
