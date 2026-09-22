{{--
    P12.1 (Moadian Live Status Inquiry). A small, reusable partial --
    @include this wherever an invoice's Moadian status is shown (e.g.
    moadian-histories/show.blade.php) with an `$invoice` variable in scope.

    Deliberately a normal form submission (not fetch/AJAX): no
    csrf-token meta tag exists anywhere in this codebase's layouts, and
    every other form here uses a plain @csrf POST -- Alpine here only
    toggles a loading/disabled state on submit, it never intercepts the
    request itself.
--}}
<form method="POST" action="{{ route('invoices.moadian-check-status', $invoice) }}"
    x-data="{ loading: false }" @submit="loading = true">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline" :disabled="loading">
        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
        <span x-text="loading ? '{{ __('Checking...') }}' : '{{ __('Live Karpooshe Inquiry') }}'"></span>
    </button>
</form>
