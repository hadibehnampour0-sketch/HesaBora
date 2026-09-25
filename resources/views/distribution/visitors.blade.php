<x-app-layout :title="__('Visitors')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        @include('distribution._nav')

        @can('distribution.visitors.store')
            <form method="POST" action="{{ route('distribution.visitors.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('Add a visitor') }}</div>
                <select name="user_id" class="select select-bordered" required>
                    <option value="">{{ __('Choose a user') }}</option>
                    @foreach ($candidates as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                    @endforeach
                </select>
                <select name="warehouse_id" class="select select-bordered">
                    <option value="">{{ __('No warehouse yet') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="phone" maxlength="30" class="input input-bordered" placeholder="{{ __('Phone') }}">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="create_van_warehouse" value="1" class="checkbox checkbox-sm" checked>{{ __('Create a van warehouse for this visitor') }}</label>
                <input type="text" name="notes" maxlength="1000" class="input input-bordered sm:col-span-3" placeholder="{{ __('Notes') }}">
                <div class="flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Add visitor') }}</button></div>
            </form>
        @endcan

        @forelse ($visitors as $visitor)
            <div class="today-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">{{ $visitor->user?->name }}</span>
                        @unless ($visitor->is_active)<span class="badge badge-warning badge-sm">{{ __('Inactive') }}</span>@endunless
                        <div class="text-xs text-base-content/60">
                            {{ __('Van warehouse') }}:
                            @if ($visitor->warehouse)
                                @can('warehouses.show')<a class="link" href="{{ route('warehouses.show', $visitor->warehouse) }}">{{ $visitor->warehouse->name }}</a>@else{{ $visitor->warehouse->name }}@endcan
                            @else
                                {{ __('None') }}
                            @endif
                        </div>
                    </div>
                    @can('distribution.visitors.destroy')
                        <form method="POST" action="{{ route('distribution.visitors.destroy', $visitor) }}" onsubmit="return confirm('{{ __('Remove this visitor?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-xs text-error">{{ __('Remove') }}</button>
                        </form>
                    @endcan
                </div>
                @can('distribution.visitors.update')
                    <form method="POST" action="{{ route('distribution.visitors.update', $visitor) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-5">
                        @csrf @method('PUT')
                        <select name="warehouse_id" class="select select-bordered select-sm">
                            <option value="">{{ __('No warehouse yet') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($visitor->warehouse_id === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="phone" value="{{ $visitor->phone }}" maxlength="30" class="input input-bordered input-sm" placeholder="{{ __('Phone') }}">
                        <input type="text" name="notes" value="{{ $visitor->notes }}" maxlength="1000" class="input input-bordered input-sm" placeholder="{{ __('Notes') }}">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked($visitor->is_active)>{{ __('Active') }}</label>
                        <button class="btn btn-sm btn-outline">{{ __('Save') }}</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="alert text-sm">{{ __('No visitors yet. Add the first one above.') }}</div>
        @endforelse
    </div>
</x-app-layout>
