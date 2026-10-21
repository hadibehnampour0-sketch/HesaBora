@can('copilot.chat')
<div class="fixed bottom-6 left-6 z-40" x-data="{ hovered: false }">
    <a href="{{ route('copilot.index') }}"
       @mouseenter="hovered = true"
       @mouseleave="hovered = false"
       class="relative group flex items-center gap-2.5 p-3.5 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-primary text-white shadow-xl shadow-blue-500/25 hover:shadow-blue-500/40 hover:scale-105 active:scale-95 transition-all duration-200 border border-white/20"
       title="{{ __('دستیار مالی هوش مصنوعی دخل') }}">

        <!-- Glowing Pulse Effect -->
        <span class="absolute -inset-1 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 opacity-30 group-hover:opacity-60 blur transition duration-300"></span>

        <!-- AI Icon -->
        <div class="relative w-7 h-7 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-sm shadow-inner">
            ✨
        </div>

        <!-- Collapsible text label on desktop -->
        <div class="relative hidden sm:flex flex-col text-right pr-1">
            <span class="font-extrabold text-xs tracking-tight">{{ __('دستیار مالی AI') }}</span>
            <span class="text-[10px] text-blue-100 font-medium opacity-90">{{ __('تحلیل لحظه‌ای دفاتر') }}</span>
        </div>

        <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
    </a>
</div>
@endcan
