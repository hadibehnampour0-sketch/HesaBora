<x-app-layout :title="__('نگاشت کالاها') . ' — ' . $connection->name">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('integrations.connections.index') }}" class="btn btn-sm btn-ghost">←</a>
                    <h1 class="text-xl font-bold">{{ __('نگاشت کالاها و تنوع‌ها') }} — {{ $connection->name }}</h1>
                    <span class="badge badge-outline">{{ strtoupper($connection->source_system) }}</span>
                </div>
                <p class="text-sm text-base-content/70 mt-1">
                    @if ($connection->source_system === 'digikala')
                        {{ __('شناسه تنوع کالای دیجی‌کالا (Variant ID) را در مقابل کالای متناظر در نپتون وارد کنید تا موجودی انبار به طور خودکار به دیجی‌کالا ارسال شود.') }}
                    @else
                        {{ __('شناسه کالای ووکامرس (Product ID) را در مقابل کالای متناظر در نپتون وارد کنید تا سفارش‌های آنلاین و موجودی هماهنگ شوند.') }}
                    @endif
                </p>
            </div>
            <a href="{{ route('integrations.connections.index') }}" class="btn btn-sm btn-outline">
                {{ __('بازگشت به اتصالات') }}
            </a>
        </div>

        @can('integrations.connections.mappings.store')
            <form method="POST" action="{{ route('integrations.connections.mappings.store', $connection) }}">
                @csrf
                <div class="today-panel overflow-x-auto p-0">
                    <table class="table table-sm w-full">
                        <thead class="bg-base-200">
                            <tr>
                                <th>{{ __('کد کالا') }}</th>
                                <th>{{ __('نام کالا در نپتون') }}</th>
                                <th>
                                    {{ $connection->source_system === 'digikala' ? __('کد تنوع در دیجی‌کالا (Variant ID)') : __('شناسه کالا در ووکامرس (Product ID)') }}
                                </th>
                                <th>{{ __('وضعیت نگاشت') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                @php
                                    $extId = $mapped[$product->id]->external_id ?? '';
                                @endphp
                                <tr class="hover">
                                    <td class="font-mono text-xs">{{ $product->code ?? '-' }}</td>
                                    <td class="font-medium">{{ $product->name }}</td>
                                    <td>
                                        <input type="text" name="external_ids[{{ $product->id }}]" class="input input-bordered input-sm w-48 font-mono text-xs"
                                            value="{{ $extId }}" placeholder="{{ $connection->source_system === 'digikala' ? 'مثال: 9876543' : 'مثال: 1250' }}">
                                    </td>
                                    <td>
                                        @if ($extId)
                                            <span class="badge badge-success badge-xs">{{ __('متصل') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-xs">{{ __('متصل نشده') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-between items-center">
                    <span class="text-xs text-base-content/60">
                        {{ __('برای حذف اتصال یک کالا، فیلد شناسه را خالی بگذارید و ذخیره کنید.') }}
                    </span>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <span>💾</span>
                        <span>{{ __('ذخیره تغییرات نگاشت') }}</span>
                    </button>
                </div>
            </form>
        @endcan
    </div>
</x-app-layout>
