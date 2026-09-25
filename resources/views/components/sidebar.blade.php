<aside class="app-sidebar flex h-full w-72 max-w-[85vw] flex-col" aria-label="{{ __('Main navigation') }}">
    <a href="/" class="flex items-center gap-3 px-5 py-5" aria-label="{{ config('app.name') }}">
        <img src="/images/logo.svg" alt="" class="h-10 w-10 object-contain">
        <span class="text-lg font-bold text-primary">{{ config('app.name') }}</span>
    </a>

    <nav class="app-sidebar-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-4">
        <ul class="menu menu-md w-full gap-0.5 p-0" data-sidebar-menu>
            <x-menu :sidebar="true" />
        </ul>
    </nav>

    <div class="border-t border-[var(--sidebar-border)] px-5 py-3 text-xs text-base-content/50">
        {{ __('Version :version', ['version' => config('app.version')]) }}
    </div>
</aside>

<script>
    // Highlight the current page and open the group that contains it.
    document.addEventListener('DOMContentLoaded', () => {
        const menu = document.querySelector('[data-sidebar-menu]');
        if (!menu) return;
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
</script>
