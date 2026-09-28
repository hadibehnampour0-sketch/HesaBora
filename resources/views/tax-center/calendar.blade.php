<x-app-layout :title="__('Filing calendar')">
    <x-show-message-bags />

    @php
        $statusMeta = [
            'filed' => ['label' => __('Filed'), 'badge' => 'badge-success'],
            'overdue' => ['label' => __('Overdue'), 'badge' => 'badge-error'],
            'prepared' => ['label' => __('Ready to file'), 'badge' => 'badge-info'],
            'pending' => ['label' => __('Not started'), 'badge' => 'badge-ghost'],
        ];
    @endphp

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Filing calendar') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('One row per quarter: when it is due, and whether you have filed it.') }}</p>
        </div>

        <form method="GET" class="flex items-end gap-2">
            <label class="form-control"><span class="label-text mb-1 text-xs">{{ __('Fiscal Year') }}</span>
                <input type="number" name="jalali_year" value="{{ $jalaliYear }}" class="input input-bordered input-sm w-28"></label>
            <button class="btn btn-sm btn-outline">{{ __('Show') }}</button>
        </form>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead>
                    <tr><th>{{ __('Quarter') }}</th><th>{{ __('Period') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $meta = $statusMeta[$row['status']]; @endphp
                        <tr>
                            <td>{{ $row['quarter_label'] }}</td>
                            <td class="text-xs">{{ formatDate($row['start_date']) }} – {{ formatDate($row['end_date']) }}</td>
                            <td>{{ formatDate($row['due_date']) }}</td>
                            <td><span class="badge badge-sm {{ $meta['badge'] }}">{{ $meta['label'] }}</span></td>
                            <td class="text-end">
                                @can('tax-center.prepare')
                                    <a href="{{ route('tax-center.prepare', ['jalali_year' => $jalaliYear, 'quarter' => $row['quarter']]) }}" class="btn btn-xs btn-outline">{{ __('Prepare') }}</a>
                                @endcan
                                @if ($row['status'] !== 'filed')
                                    @can('tax-center.calendar.mark-filed')
                                        <form method="POST" action="{{ route('tax-center.calendar.mark-filed') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="jalali_year" value="{{ $jalaliYear }}">
                                            <input type="hidden" name="quarter" value="{{ $row['quarter'] }}">
                                            <button class="btn btn-xs btn-primary">{{ __('Mark as filed') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="alert text-xs">
            {{ __('The due date shown is a common convention (15 days after the quarter ends), not a confirmed legal deadline. Verify with an accountant.') }}
        </div>
    </div>
</x-app-layout>
