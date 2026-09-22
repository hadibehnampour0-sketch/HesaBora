<x-app-layout :title="__('Paired Devices')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section class="flex flex-col gap-2 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">{{ __('Paired Devices') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('Desktop installations paired with this company for offline sync.') }}</p>
            </div>
            @can('devices.activation-codes.store')
                <form action="{{ route('devices.activation-codes.store') }}" method="post">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Generate activation code') }}</button>
                </form>
            @endcan
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Paired at') }}</th>
                                <th>{{ __('Last seen') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($installations as $installation)
                                <tr>
                                    <td>{{ $installation->name ?? '-' }}</td>
                                    <td>{{ $installation->status->label() }}</td>
                                    <td>{{ $installation->paired_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $installation->last_seen_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        @can('devices.revoke')
                                            <form action="{{ route('devices.revoke', $installation) }}" method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-error btn-outline">{{ __('Revoke') }}</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-base-content/50">{{ __('No paired devices yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
