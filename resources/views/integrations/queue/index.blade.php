<x-app-layout :title="__('صف بررسی سفارش‌های آنلاین')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('integrations.connections.index') }}" class="btn btn-sm btn-ghost">←</a>
                    <h1 class="text-xl font-bold flex items-center gap-2">
                        <span>📋</span>
                        <span>{{ __('صف بررسی سفارش‌های آنلاین (Review Queue)') }}</span>
                    </h1>
                </div>
                <p class="text-sm text-base-content/70 mt-1">
                    {{ __('سفارش‌هایی که به دلیل عدم نگاشت کالا، اختلاف قیمت فراتر از حد مجاز یا کمبود موجودی انبار به صورت خودکار تایید نشده‌اند، در این صف قرار می‌گیرند تا توسط کاربر بررسی و حل شوند.') }}
                </p>
            </div>
            <a href="{{ route('integrations.connections.index') }}" class="btn btn-sm btn-outline">
                {{ __('مدیریت اتصالات') }}
            </a>
        </div>

        @if ($events->isEmpty())
            <div class="alert alert-success text-sm flex items-center gap-2">
                <span>✅</span>
                <span>{{ __('هیچ سفارشی در صف انتظار وجود ندارد. تمام سفارش‌های دریافتی با موفقیت پردازش شده‌اند.') }}</span>
            </div>
        @else
            <div class="today-panel overflow-x-auto p-0">
                <table class="table table-sm w-full">
                    <thead class="bg-base-200">
                        <tr>
                            <th>{{ __('پلتفرم') }}</th>
                            <th>{{ __('شناسه سفارش خارجی') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('علت توقف') }}</th>
                            <th class="text-end">{{ __('عملیات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr class="hover">
                                <td>
                                    <span class="badge badge-sm {{ $event->source_system === 'digikala' ? 'badge-error' : 'badge-primary' }}">
                                        {{ strtoupper($event->source_system) }}
                                    </span>
                                </td>
                                <td class="font-mono text-xs font-bold">{{ $event->external_id }}</td>
                                <td>
                                    <span class="badge badge-sm badge-warning">{{ $event->status }}</span>
                                </td>
                                <td class="max-w-md text-xs text-base-content/80">{{ $event->message }}</td>
                                <td class="text-end">
                                    @can('integrations.queue.retry')
                                        <form method="POST" action="{{ route('integrations.queue.retry', $event) }}" class="inline">
                                            @csrf
                                            <button class="btn btn-xs btn-primary gap-1">
                                                <span>🔄</span>
                                                <span>{{ __('پردازش مجدد') }}</span>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $events->links() }}
        @endif

        @can('integrations.queue.map-product')
            <div class="today-panel space-y-3">
                <div class="today-panel-title font-bold text-sm flex items-center gap-2">
                    <span>🔗</span>
                    <span>{{ __('نگاشت سریع کالا و پردازش مجدد رویداد') }}</span>
                </div>
                <p class="text-xs text-base-content/70">
                    {{ __('اگر دلیل توقف عدم تطابق کالا بوده است، شناسه کالای خارجی (ووکامرس یا دیجی‌کالا) را به شناسه کالای دخل متصل کنید و دکمه نگاشت را بزنید.') }}
                </p>
                <form method="POST" action="{{ route('integrations.queue.map-product') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                    @csrf
                    <div>
                        <select name="source_system" class="select select-bordered select-sm w-full" required>
                            <option value="woocommerce">ووکامرس (woocommerce)</option>
                            <option value="digikala">دیجی‌کالا (digikala)</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="external_id" class="input input-bordered input-sm w-full" placeholder="{{ __('شناسه خارجی کالا / کد تنوع') }}" required>
                    </div>
                    <div>
                        <input type="number" name="product_id" class="input input-bordered input-sm w-full" placeholder="{{ __('شناسه کالای دخل (ID)') }}" required>
                    </div>
                    <div>
                        <input type="number" name="event_id" class="input input-bordered input-sm w-full" placeholder="{{ __('شناسه رویداد (Event ID)') }}" required>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline btn-primary w-full">{{ __('نگاشت و تلاش مجدد') }}</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
