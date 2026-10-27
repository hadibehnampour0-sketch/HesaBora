@props([
    'text' => '',
    'position' => 'top',
    'title' => null,
])

<span class="inline-flex items-center align-middle relative group/hint ml-1 mr-0.5 cursor-help" x-data="{ open: false }">
    <button type="button"
            @click.stop="open = !open"
            @mouseenter="open = true"
            @mouseleave="open = false"
            @keydown.escape.window="open = false"
            class="inline-flex size-4 items-center justify-center rounded-full bg-base-300/80 text-base-content/60 hover:bg-primary/20 hover:text-primary transition-colors focus:outline-none focus:ring-1 focus:ring-primary/40 text-[10px] font-bold font-mono"
            aria-label="{{ $title ?? __('راهنما') }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="width: 12px; height: 12px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>

    {{-- Floating Micro-Hint Popover --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-1"
         @click.outside="open = false"
         style="display: none;"
         class="absolute z-50 bottom-full mb-1.5 start-1/2 -translate-x-1/2 w-64 max-w-[85vw] p-2.5 rounded-xl bg-slate-900 text-white text-xs leading-relaxed shadow-xl border border-slate-700/80 pointer-events-auto text-right font-normal">
        @if($title)
            <div class="font-bold text-cyan-400 mb-1 border-b border-slate-800 pb-1 flex items-center gap-1.5">
                <span class="size-1.5 rounded-full bg-cyan-400"></span>
                <span>{{ $title }}</span>
            </div>
        @endif
        <div class="text-[11px] text-slate-200">
            {{ $text ?: $slot }}
        </div>
        {{-- Tiny Pointer Arrow --}}
        <div class="absolute top-full start-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900"></div>
    </div>
</span>
