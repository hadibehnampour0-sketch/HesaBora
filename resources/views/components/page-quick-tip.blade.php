@props([
    'title' => __('راهنمای کاربردی این بخش'),
    'badge' => __('نکات مهم'),
    'defaultOpen' => false,
])

<div x-data="{ expanded: @js($defaultOpen) }" class="mb-5 rounded-2xl border border-sky-500/20 bg-sky-50/40 dark:bg-sky-950/20 text-xs overflow-hidden shadow-2xs">
    <div @click="expanded = !expanded" class="flex items-center justify-between p-3 cursor-pointer select-none hover:bg-sky-500/5 transition">
        <div class="flex items-center gap-2">
            <span class="size-6 rounded-lg bg-sky-500/15 text-sky-600 flex items-center justify-center shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <span class="font-bold text-sky-950 dark:text-sky-200">{{ $title }}</span>
            @if($badge)
                <span class="badge badge-info badge-xs py-0.5 px-1.5 text-[9px] font-semibold">{{ $badge }}</span>
            @endif
        </div>
        <div class="flex items-center gap-1.5 text-[11px] text-sky-700 dark:text-sky-300">
            <span x-text="expanded ? 'بستن راهنما' : 'مشاهده نکات کلیدی'"></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <div x-show="expanded" x-collapse style="display: none;" class="px-4 pb-3.5 pt-1 text-slate-700 dark:text-slate-300 border-t border-sky-500/15">
        <div class="mt-2 text-xs leading-relaxed space-y-1.5">
            {{ $slot }}
        </div>
    </div>
</div>
