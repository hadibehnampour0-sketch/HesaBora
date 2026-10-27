<x-app-layout :title="__('اتصال به فروشگاه‌های آنلاین')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <span class="text-primary">🌐</span>
                    <span>{{ __('فروشگاه‌های اینترنتی و بازارگاه‌ها') }}</span>
                </h1>
                <p class="text-sm text-base-content/70 mt-1">
                    {{ __('اتصال دوطرفه به ووکامرس (WooCommerce) و دیجی‌کالا (Digikala): ثبت خودکار سفارش‌ها به صورت فاکتور و همگام‌سازی لحظه‌ای موجودی انبار.') }}
                </p>
            </div>
            @can('integrations.queue.index')
                <a href="{{ route('integrations.queue.index') }}" class="btn btn-sm btn-outline gap-2">
                    <span>📋</span>
                    <span>{{ __('صف بررسی سفارش‌ها') }}</span>
                </a>
            @endcan
        </div>

        @can('integrations.connections.store')
            <div x-data="{ platform: 'woocommerce' }" class="today-panel">
                <div class="today-panel-title flex items-center justify-between border-b pb-3 mb-4">
                    <span class="font-bold text-base flex items-center gap-2">
                        <span class="text-primary">➕</span>
                        <span>{{ __('افزودن اتصال جدید') }}</span>
                    </span>
                    <div class="flex gap-2">
                        <button type="button" @click="platform = 'woocommerce'" :class="platform === 'woocommerce' ? 'btn-primary' : 'btn-ghost'" class="btn btn-xs">
                            {{ __('ووکامرس (WooCommerce)') }}
                        </button>
                        <button type="button" @click="platform = 'digikala'" :class="platform === 'digikala' ? 'btn-primary' : 'btn-ghost'" class="btn btn-xs">
                            {{ __('دیجی‌کالا (Digikala)') }}
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('integrations.connections.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="source_system" :value="platform">

                    <div>
                        <label class="label label-text font-medium">{{ __('نام اتصال') }}</label>
                        <input type="text" name="name" class="input input-bordered w-full" placeholder="مثلاً: فروشگاه آنلاین اصلی یا پنل دیجی‌کالا" maxlength="150" required>
                    </div>

                    <div x-show="platform === 'woocommerce'">
                        <label class="label label-text font-medium">{{ __('آدرس فروشگاه ووکامرس (Store URL)') }}</label>
                        <input type="url" name="site_url" x-bind:required="platform === 'woocommerce'" class="input input-bordered w-full" placeholder="https://myshop.ir">
                    </div>

                    <div x-show="platform === 'woocommerce'">
                        <label class="label label-text font-medium">Consumer Key</label>
                        <input type="text" name="consumer_key" x-bind:required="platform === 'woocommerce'" class="input input-bordered w-full" placeholder="ck_xxxxxxxxxxxx">
                    </div>

                    <div x-show="platform === 'woocommerce'">
                        <label class="label label-text font-medium">Consumer Secret</label>
                        <input type="text" name="consumer_secret" x-bind:required="platform === 'woocommerce'" class="input input-bordered w-full" placeholder="cs_xxxxxxxxxxxx">
                    </div>

                    <div x-show="platform === 'woocommerce'" class="sm:col-span-2">
                        <label class="label label-text font-medium">{{ __('کلید امنیتی وب‌هوک (Webhook Secret)') }}</label>
                        <input type="text" name="webhook_secret" x-bind:required="platform === 'woocommerce'" class="input input-bordered w-full" placeholder="یک عبارت امن دلخواه جهت اعتبارسنجی HMAC">
                    </div>

                    <div x-show="platform === 'digikala'">
                        <label class="label label-text font-medium">کلید API دیجی‌کالا (Seller API Key)</label>
                        <input type="text" name="api_key" x-bind:required="platform === 'digikala'" class="input input-bordered w-full" placeholder="دریافت شده از پنل سلر سنتر دیجی‌کالا">
                    </div>

                    <div x-show="platform === 'digikala'">
                        <label class="label label-text font-medium">کد فروشنده در دیجی‌کالا (Seller ID)</label>
                        <input type="text" name="seller_id" x-bind:required="platform === 'digikala'" class="input input-bordered w-full" placeholder="مثلاً: 51234">
                    </div>

                    <div x-show="platform === 'digikala'" class="sm:col-span-2">
                        <label class="label label-text font-medium">{{ __('آدرس پایه Open API (اختیاری)') }}</label>
                        <input type="url" name="site_url" class="input input-bordered w-full" placeholder="https://seller.digikala.com" value="https://seller.digikala.com">
                    </div>

                    <div>
                        <label class="label label-text font-medium">{{ __('انبار پیش‌فرض کسر موجودی') }}</label>
                        <select name="default_warehouse_id" class="select select-bordered w-full">
                            <option value="">{{ __('انتخاب انبار...') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label label-text font-medium">{{ __('گروه مشتری پیش‌فرض خریداران آنلاین') }}</label>
                        <select name="default_customer_group_id" class="select select-bordered w-full">
                            <option value="">{{ __('انتخاب گروه مشتری...') }}</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label label-text font-medium">{{ __('تلورانس مجاز اختلاف قیمت (%)') }}</label>
                        <input type="number" step="0.01" min="0" max="100" name="price_tolerance_percent" class="input input-bordered w-full" value="2.00">
                    </div>

                    <div class="sm:col-span-2 flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <span>🔗</span>
                            <span>{{ __('ایجاد اتصال و دریافت لینک وب‌هوک') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="space-y-4">
            <h2 class="text-lg font-bold">{{ __('اتصالات فعال') }}</h2>

            @forelse ($connections as $connection)
                <div class="today-panel space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">{{ $connection->source_system === 'digikala' ? '🔴' : '🟣' }}</span>
                            <div>
                                <span class="font-bold text-base">{{ $connection->name }}</span>
                                <span class="badge badge-outline badge-sm ml-2">{{ strtoupper($connection->source_system) }}</span>
                                <span class="badge badge-sm {{ $connection->is_active ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $connection->is_active ? __('فعال') : __('غیرفعال') }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @can('integrations.connections.destroy')
                                <form method="POST" action="{{ route('integrations.connections.destroy', $connection) }}" onsubmit="return confirm('{{ __('آیا از حذف این اتصال اطمینان دارید؟') }}')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-xs text-error">{{ __('حذف') }}</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-xs text-base-content/70 sm:grid-cols-2">
                        <div>
                            <span class="font-semibold">{{ __('آدرس وب‌هوک سفارش‌ها:') }}</span>
                            <code class="select-all block mt-1 p-1 bg-base-200 rounded text-xs break-all">
                                {{ route($connection->source_system === 'digikala' ? 'integrations.digikala.webhook' : 'integrations.woocommerce.webhook', $connection->webhook_token) }}
                            </code>
                        </div>
                        <div class="space-y-1">
                            <div><span class="font-semibold">{{ __('انبار پیش‌فرض:') }}</span> {{ $connection->warehouse?->name ?? __('تنظیم نشده') }}</div>
                            <div><span class="font-semibold">{{ __('تلورانس قیمت:') }}</span> {{ $connection->price_tolerance_percent }}%</div>
                            <div><span class="font-semibold">{{ __('آخرین همگام‌سازی:') }}</span> {{ $connection->updated_at ? formatDateTime($connection->updated_at) : '-' }}</div>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-wrap items-center gap-2 border-t">
                        @can('integrations.connections.push-inventory')
                            <form method="POST" action="{{ route('integrations.connections.push-inventory', $connection) }}">
                                @csrf
                                <button class="btn btn-xs btn-primary gap-1">
                                    <span>🔄</span>
                                    <span>{{ __('ارسال موجودی انبار به سایت') }}</span>
                                </button>
                            </form>
                        @endcan

                        <form method="POST" action="{{ route('integrations.connections.test', $connection) }}">
                            @csrf
                            <button class="btn btn-xs btn-outline gap-1">
                                <span>⚡</span>
                                <span>{{ __('تست زنده اتصال') }}</span>
                            </button>
                        </form>

                        @can('integrations.connections.mappings.index')
                            <a href="{{ route('integrations.connections.mappings.index', $connection) }}" class="btn btn-xs btn-outline gap-1">
                                <span>🏷️</span>
                                <span>{{ __('نگاشت کالاها و تنوع‌ها') }}</span>
                            </a>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="alert bg-base-200 text-sm">
                    <span>💡</span>
                    <span>{{ __('هنوز هیچ اتصالی ثبت نشده است. از فرم بالا برای اتصال ووکامرس یا دیجی‌کالا استفاده کنید.') }}</span>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
