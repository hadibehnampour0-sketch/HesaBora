<x-app-layout :title="__('Integration review queue')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Integration review queue') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Every imported order Dakhl could not auto-approve without guessing stays here until you fix it and retry -- it is never silently dropped or force-posted.') }}</p>
        </div>

        @if ($events->isEmpty())
            <div class="alert alert-success text-sm">{{ __('Nothing waiting for review.') }}</div>
        @else
            <div class="today-panel overflow-x-auto p-0">
                <table class="table table-sm">
                    <thead>
                        <tr><th>{{ __('Source') }}</th><th>{{ __('External id') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reason') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td>{{ $event->source_system }}</td>
                                <td>{{ $event->external_id }}</td>
                                <td><span class="badge badge-sm badge-warning">{{ $event->status }}</span></td>
                                <td class="max-w-sm text-xs text-base-content/70">{{ $event->message }}</td>
                                <td class="text-end">
                                    @can('integrations.queue.retry')
                                        <form method="POST" action="{{ route('integrations.queue.retry', $event) }}" class="inline">
                                            @csrf
                                            <button class="btn btn-xs btn-primary">{{ __('Retry') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $events->links() }}
        @endif

        @can('integrations.queue.map-product')
            <div class="today-panel">
                <div class="today-panel-title">{{ __('Map a platform product to a Dakhl product') }}</div>
                <p class="mt-1 text-xs text-base-content/60">{{ __('If the reason above says a product is not mapped, map it here with its platform id from the event, then retry.') }}</p>
                <form method="POST" action="{{ route('integrations.queue.map-product') }}" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-5">
                    @csrf
                    <input type="text" name="source_system" class="input input-bordered input-sm" placeholder="{{ __('Source (e.g. woocommerce)') }}" required>
                    <input type="text" name="external_id" class="input input-bordered input-sm" placeholder="{{ __('Platform product id') }}" required>
                    <input type="number" name="product_id" class="input input-bordered input-sm" placeholder="{{ __('Dakhl product id') }}" required>
                    <input type="number" name="event_id" class="input input-bordered input-sm" placeholder="{{ __('Event id to retry') }}" required>
                    <button class="btn btn-sm btn-outline">{{ __('Map & retry') }}</button>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>
