<x-app-layout :title="__('Visit plans')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        @include('distribution._nav')

        @can('distribution.plans.store')
            <form method="POST" action="{{ route('distribution.plans.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('Plan a visit day') }}</div>
                <select name="user_id" class="select select-bordered" required>
                    <option value="">{{ __('Choose a visitor') }}</option>
                    @foreach ($visitors as $visitor)
                        <option value="{{ $visitor->user_id }}">{{ $visitor->user?->name }}</option>
                    @endforeach
                </select>
                <select name="route_id" class="select select-bordered">
                    <option value="">{{ __('No route') }}</option>
                    @foreach ($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->title }}</option>
                    @endforeach
                </select>
                <input type="text" name="plan_date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                <button class="btn btn-primary">{{ __('Add plan') }}</button>
            </form>
        @endcan

        <div class="today-panel space-y-3">
            <div class="flex items-center justify-between">
                <div class="today-panel-title">{{ __('Route-day calendar') }}</div>
                <div class="flex gap-2">
                    <a class="btn btn-xs btn-outline" href="{{ route('distribution.plans.index', ['week' => $weekStart->copy()->subDays(7)->toDateString()]) }}">{{ __('Previous week') }}</a>
                    <a class="btn btn-xs btn-outline" href="{{ route('distribution.plans.index') }}">{{ __('This week') }}</a>
                    <a class="btn btn-xs btn-outline" href="{{ route('distribution.plans.index', ['week' => $weekStart->copy()->addDays(7)->toDateString()]) }}">{{ __('Next week') }}</a>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-7">
                @foreach ($weekDays as $day)
                    @php $dayPlans = $weekPlans->get($day->toDateString(), collect()); @endphp
                    <div class="rounded border border-base-300 p-2 space-y-2 {{ $day->isToday() ? 'bg-base-200' : '' }}">
                        <div class="text-xs font-bold text-center">{{ formatDate($day, 'l') }}</div>
                        <div class="text-xs text-center text-base-content/60">{{ formatDate($day) }}</div>

                        @foreach ($dayPlans as $plan)
                            <div class="rounded bg-base-100 border border-base-300 p-1 text-xs space-y-1">
                                <div class="font-medium">{{ $plan->user?->name }}</div>
                                <div class="text-base-content/60">{{ $plan->route?->title ?? '—' }}</div>
                                @can('distribution.plans.update-status')
                                    <form method="POST" action="{{ route('distribution.plans.update-status', $plan) }}">
                                        @csrf @method('PATCH')
                                        <select name="status" class="select select-bordered select-xs w-full" onchange="this.form.submit()">
                                            @foreach (\App\Enums\VisitPlanStatus::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($plan->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <div>{{ $plan->status->label() }}</div>
                                @endcan
                                @can('distribution.plans.destroy')
                                    <form method="POST" action="{{ route('distribution.plans.destroy', $plan) }}" onsubmit="return confirm('{{ __('Delete this plan?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-xs text-error w-full">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach

                        @can('distribution.plans.store')
                            <form method="POST" action="{{ route('distribution.plans.store') }}" class="space-y-1">
                                @csrf
                                <input type="hidden" name="plan_date" value="{{ convertToJalali($day, true) }}">
                                <select name="user_id" class="select select-bordered select-xs w-full" required>
                                    <option value="">{{ __('Choose a visitor') }}</option>
                                    @foreach ($visitors as $visitor)
                                        <option value="{{ $visitor->user_id }}">{{ $visitor->user?->name }}</option>
                                    @endforeach
                                </select>
                                <select name="route_id" class="select select-bordered select-xs w-full">
                                    <option value="">{{ __('No route') }}</option>
                                    @foreach ($routes as $route)
                                        <option value="{{ $route->id }}">{{ $route->title }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-xs btn-outline w-full">{{ __('Add plan') }}</button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="date" data-jdp autocomplete="off" readonly class="input input-bordered input-sm" value="{{ $filterDate }}" placeholder="{{ __('Filter by date') }}">
            <button class="btn btn-sm btn-outline">{{ __('Filter') }}</button>
            @if ($filterDate)<a href="{{ route('distribution.plans.index') }}" class="btn btn-sm btn-ghost">{{ __('Clear') }}</a>@endif
        </form>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Visitor') }}</th><th>{{ __('Route') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>{{ formatDate($plan->plan_date) }}</td>
                            <td>{{ $plan->user?->name }}</td>
                            <td>{{ $plan->route?->title ?? '—' }}</td>
                            <td>
                                @can('distribution.plans.update-status')
                                    <form method="POST" action="{{ route('distribution.plans.update-status', $plan) }}">
                                        @csrf @method('PATCH')
                                        <select name="status" class="select select-bordered select-xs" onchange="this.form.submit()">
                                            @foreach (\App\Enums\VisitPlanStatus::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($plan->status === $status)>{{ $status->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    {{ $plan->status->label() }}
                                @endcan
                            </td>
                            <td class="text-end">
                                @can('distribution.plans.destroy')
                                    <form method="POST" action="{{ route('distribution.plans.destroy', $plan) }}" onsubmit="return confirm('{{ __('Delete this plan?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-sm text-base-content/60">{{ __('No visit plans found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endpush
</x-app-layout>
