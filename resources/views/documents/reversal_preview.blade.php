<x-app-layout title="{{ __('پیش‌نمایش ابطال سند حسابداری') }} #{{ $document->number }}">
    <div class="card bg-base-100 shadow-xl max-w-4xl mx-auto">
        <div class="card-header bg-gradient-to-r from-red-50 to-orange-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-error/30 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ __('ابطال و صدور سند معکوس (Reversal Document)') }}
                </h2>
                <div class="text-xs text-base-content/70 mt-1">
                    {{ __('سند حسابداری شماره :number مورخ :date', ['number' => $document->number, 'date' => $document->date]) }}
                </div>
            </div>
            <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost btn-sm">{{ __('بازگشت') }}</a>
        </div>

        <div class="card-body space-y-6">
            <x-show-message-bags />

            @if (! $inspection['can_reverse'])
                <div class="alert alert-error">
                    <div>
                        <h4 class="font-bold">{{ __('امکان ابطال این سند وجود ندارد:') }}</h4>
                        <ul class="list-disc list-inside text-xs mt-2 space-y-1">
                            @foreach ($inspection['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @else
                <div class="alert alert-warning text-xs">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        {{ __('طبق استانداردهای حسابداری، اسناد قطعی حذف فیزیکی نمی‌شوند؛ بلکه یک سند معکوس با جابجایی ستون‌های بدهکار و بستانکار صادر شده و اثر مالی آن صفر می‌گردد.') }}
                    </div>
                </div>

                {{-- Reversal Form --}}
                <form method="POST" action="{{ route('documents.reverse', $document) }}" class="space-y-4">
                    @csrf
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-bold text-sm">{{ __('علت استاندارد ابطال سند') }} <span class="text-error">*</span></span>
                        </label>
                        <select name="reversal_reason_code" class="select select-bordered w-full" required>
                            <option value="">{{ __('انتخاب دلیل ابطال...') }}</option>
                            @foreach ($inspection['reason_options'] as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-bold text-sm">{{ __('شرح تکمیلی و توضیحات عطف') }}</span>
                        </label>
                        <textarea name="reversal_reason" class="textarea textarea-bordered h-24" placeholder="{{ __('شرح دقیق علت ابطال برای ثبت در دفتر روزنامه و پیگیری حسابرس...') }}"></textarea>
                    </div>

                    <div class="pt-4 border-t flex justify-end gap-2">
                        <a href="{{ route('documents.show', $document) }}" class="btn btn-ghost btn-sm">{{ __('انصراف') }}</a>
                        <button type="submit" class="btn btn-error btn-sm gap-2" onclick="return confirm('آیا از صدور قطعی سند معکوس اطمینان دارید؟');">
                            {{ __('تایید و صدور سند معکوس') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
