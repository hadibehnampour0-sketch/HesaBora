<aside class="app-sidebar flex h-full w-72 max-w-[85vw] flex-col" aria-label="{{ __('Main navigation') }}">
    <a href="/" class="flex items-center gap-3 px-5 py-5" aria-label="{{ config('app.name') }}">
        <img src="/images/logo.svg" alt="" class="h-10 w-10 object-contain">
        <span class="text-lg font-bold text-primary">{{ config('app.name') }}</span>
    </a>

    <nav class="app-sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-4">
        @if (auth()->user()->isSimpleMode())
            <div x-data="{ all: false }">
                <ul class="menu menu-md w-full gap-0.5 p-0" data-sidebar-menu>
                    <x-menu-simple />
                </ul>
                <button type="button" class="btn btn-ghost btn-xs mt-3 w-full text-base-content/60" @click="all = ! all">
                    <span x-show="! all">{{ __('Show all features') }}</span>
                    <span x-show="all" style="display: none">{{ __('Hide extra features') }}</span>
                </button>
                <ul x-show="all" style="display: none" class="menu menu-md mt-2 w-full gap-0.5 border-t border-[var(--sidebar-border)] p-0 pt-2" data-sidebar-menu>
                    <x-menu :sidebar="true" />
                </ul>
            </div>
        @else
            <ul class="menu menu-md w-full gap-0.5 p-0" data-sidebar-menu>
                <x-menu :sidebar="true" />
            </ul>
        @endif
    </nav>

    <div class="border-t border-[var(--sidebar-border)] px-5 py-3 text-xs text-base-content/50">
        {{ __('Version :version', ['version' => config('app.version')]) }}
    </div>
</aside>

<script>
    // Highlight the current page and open the group that contains it.
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-sidebar-menu]').forEach((menu) => {
        const here = location.pathname.replace(/\/+$/, '') || '/';
        let best = null;
        menu.querySelectorAll('a[href]').forEach((a) => {
            let path;
            try { path = new URL(a.href).pathname.replace(/\/+$/, '') || '/'; } catch (e) { return; }
            if (path === here || (path !== '/' && here.startsWith(path + '/'))) {
                if (!best || path.length > best.path.length) best = { a, path };
            }
        });
        if (best) {
            best.a.classList.add('menu-active');
            let node = best.a.parentElement;
            while (node && node !== menu) {
                if (node.tagName === 'DETAILS') node.setAttribute('open', '');
                node = node.parentElement;
            }
        }
        });
    });
</script>
