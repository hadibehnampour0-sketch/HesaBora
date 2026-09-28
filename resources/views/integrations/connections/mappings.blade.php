<x-app-layout :title="__('Product mapping')">
    <x-show-message-bags />

    <div class="mx-auto max-w-3xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Product mapping') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Map every product to its platform id here, before any order comes in, instead of one at a time from the review queue.') }} — {{ $connection->name }}</p>
        </div>

        @can('integrations.connections.mappings.store')
            <form method="POST" action="{{ route('integrations.connections.mappings.store', $connection) }}">
                @csrf
                <div class="today-panel overflow-x-auto p-0">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Product') }}</th><th>{{ __('Platform id') }}</th></tr></thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->name }}</td>
                                    <td>
                                        <input type="text" name="external_ids[{{ $product->id }}]" class="input input-bordered input-sm w-40"
                                            value="{{ $mapped[$product->id]->external_id ?? '' }}" placeholder="{{ __('Not mapped') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex justify-end">
                    <button class="btn btn-sm btn-primary">{{ __('Save mappings') }}</button>
                </div>
            </form>
        @endcan
    </div>
</x-app-layout>
