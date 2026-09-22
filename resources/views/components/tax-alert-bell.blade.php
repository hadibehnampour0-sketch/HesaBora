{{--
    P12.2 (Tax Alert & Smart Notification Engine). Reuses the header's own
    `<details>`/`app-main-menu-dropdown` chrome (app.css) for consistent
    positioning with every other nav dropdown here, rather than
    introducing daisyUI's own `dropdown` classes for just this one widget.
    No csrf-token meta tag exists anywhere in this codebase's layouts (see
    tax/moadian/inquiry-widget.blade.php), so the hidden @csrf form below
    is this widget's own token source for its fetch() calls.
--}}
<li x-data="{
        unreadCount: 0,
        alerts: [],
        async load() {
            try {
                const res = await fetch('{{ route('tax-alerts.index') }}', { headers: { 'Accept': 'application/json' } });
                if (! res.ok) return;
                const data = await res.json();
                this.unreadCount = data.unread_count;
                this.alerts = data.alerts;
            } catch (e) {}
        },
        async markRead(alert) {
            const url = '{{ route('tax-alerts.read', ['tax_alert' => '__ID__']) }}'.replace('__ID__', alert.id);
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.$refs.csrf.value } });
                if (! res.ok) return;
                alert.is_read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            } catch (e) {}
        },
        async markAllRead() {
            try {
                const res = await fetch('{{ route('tax-alerts.read-all') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.$refs.csrf.value } });
                if (! res.ok) return;
                this.alerts.forEach(a => a.is_read = true);
                this.unreadCount = 0;
            } catch (e) {}
        },
        severityBadgeClass(severity) {
            return { critical: 'badge-error', warning: 'badge-warning', info: 'badge-info' }[severity] ?? 'badge-ghost';
        },
    }"
    x-init="load(); setInterval(() => load(), 60000)">
    <form x-ref="csrf" class="hidden">@csrf</form>
    <details class="app-main-menu-dropdown" data-main-menu-dropdown @toggle="$event.target.open && load()">
        <summary class="btn btn-ghost btn-square btn-sm" aria-label="{{ __('Tax Alerts') }}">
            <span class="indicator">
                <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount" class="indicator-item badge badge-error badge-xs"></span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </span>
        </summary>
        <div class="app-main-menu-panel z-50 w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <span class="font-medium text-sm">{{ __('Tax Alerts') }}</span>
                <button type="button" x-show="unreadCount > 0" @click="markAllRead()" class="link link-hover text-xs">{{ __('Mark all read') }}</button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <template x-if="alerts.length === 0">
                    <p class="text-xs text-gray-500 px-3 py-4 text-center">{{ __('No alerts.') }}</p>
                </template>
                <template x-for="alert in alerts" :key="alert.id">
                    <div class="px-3 py-2 border-t border-base-content/10" :class="alert.is_read ? 'opacity-60' : ''">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-xs" :class="severityBadgeClass(alert.severity)" x-text="alert.severity"></span>
                            <span class="text-xs font-medium" x-text="alert.title"></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" x-text="alert.message"></p>
                        <button type="button" x-show="!alert.is_read" @click="markRead(alert)" class="link link-hover text-xs mt-1">{{ __('Mark as read') }}</button>
                    </div>
                </template>
            </div>
        </div>
    </details>
</li>
