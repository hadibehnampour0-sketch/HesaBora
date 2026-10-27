@can('copilot.chat')
<div class="fixed bottom-5 left-5 z-40" x-data="{ hovered: false }">
    <a href="{{ route('copilot.index') }}"
       @mouseenter="hovered = true"
       @mouseleave="hovered = false"
       class="relative group flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900/90 dark:bg-slate-800/90 text-white shadow-lg shadow-black/15 hover:shadow-black/25 hover:scale-105 active:scale-95 transition-all duration-200 border border-slate-700/60 backdrop-blur-md"
       title="{{ __('دستیار مالی هوش مصنوعی دخل') }}">

        <!-- AI Icon -->
        <span class="w-6 h-6 rounded-lg bg-primary/20 text-primary-content flex items-center justify-center text-xs shrink-0">
            ✨
        </span>

        <!-- Collapsible text label on desktop -->
        <div class="relative hidden sm:flex items-center gap-1.5 text-right">
            <span class="font-bold text-xs tracking-tight">{{ __('دستیار مالی AI') }}</span>
            <span class="badge badge-primary badge-xs py-0.5 px-1 text-[9px] font-mono">LIVE</span>
        </div>

        <span class="relative flex h-2 w-2 mr-0.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
    </a>
</div>
@endcan
