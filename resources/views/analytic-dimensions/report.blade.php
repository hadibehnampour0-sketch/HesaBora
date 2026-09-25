<x-app-layout :title="$dimension->name">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ $dimension->name }} <span class="badge badge-outline">{{ $dimension->type->label() }}</span></h1>
            <p class="text-sm text-base-content/60">{{ __('Accounting lines tagged with this dimension, totalled per account.') }}</p>
        </div>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($breakdown as $row)
                        <tr><td>{{ $row['subject_code'] }} — {{ $row['subject_name'] }}</td><td>{{ number_format($row['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-sm text-base-content/60">{{ __('No accounting lines are tagged with this dimension yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('analytic-dimensions.index')
            <a href="{{ route('analytic-dimensions.index') }}" class="btn btn-sm btn-ghost">{{ __('Back') }}</a>
        @endcan
    </div>
</x-app-layout>
