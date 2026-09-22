<x-app-layout :title="__('Sync Conflicts')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section>
            <h1 class="text-2xl font-bold text-base-content">{{ __('Sync Conflicts') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('A conflict is never silently overwritten -- review and choose a resolution for each.') }}</p>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Entity') }}</th>
                                <th>{{ __('Local (device) version') }}</th>
                                <th>{{ __('Server version') }}</th>
                                <th>{{ __('Base version') }}</th>
                                <th>{{ __('Resolution') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($conflicts as $conflict)
                                <tr>
                                    <td>
                                        {{ class_basename($conflict->entity_type) }} —
                                        {{ $conflict->local_snapshot['name'] ?? $conflict->entity_uuid }}
                                    </td>
                                    <td><pre class="text-xs">{{ json_encode($conflict->local_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                                    <td><pre class="text-xs">{{ json_encode($conflict->server_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></td>
                                    <td>{{ $conflict->base_version }} / {{ $conflict->server_version }}</td>
                                    <td class="flex flex-wrap gap-1">
                                        <form action="{{ route('sync-conflicts.resolve', $conflict) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="resolution" value="keep_server">
                                            <button type="submit" class="btn btn-xs btn-outline">{{ __('Keep server version') }}</button>
                                        </form>
                                        <form action="{{ route('sync-conflicts.resolve', $conflict) }}" method="post">
                                            @csrf
                                            <input type="hidden" name="resolution" value="keep_local">
                                            <button type="submit" class="btn btn-xs btn-outline">{{ __('Keep local (device) version') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-base-content/50">{{ __('No open conflicts.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
