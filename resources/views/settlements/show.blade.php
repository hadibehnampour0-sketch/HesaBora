<x-app-layout :title="__('Settlement')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section>
            <h1 class="text-2xl font-bold text-base-content">{{ __('Settlement') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Original amount') }}: {{ number_format($obligation->original_amount) }} —
                {{ __('Remaining') }}: {{ number_format($remainingAmount) }} —
                <span class="badge {{ $isSettled ? 'badge-success' : 'badge-warning' }}">
                    {{ $isSettled ? __('Settled') : __('Open') }}
                </span>
            </p>
        </section>

        @if ($obligation->installments->isEmpty())
            <section class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Create installment plan') }}</h2>
                    <form action="{{ route('settlements.installments.store', $obligation) }}" method="post" x-data="{ rows: [{ due_date: '', amount: '' }] }">
                        @csrf
                        <template x-for="(row, index) in rows" :key="index">
                            <div class="mb-2 flex items-center gap-2">
                                <input type="date" :name="`installments[${index}][due_date]`" class="input input-bordered input-sm" required>
                                <input type="number" step="0.01" :name="`installments[${index}][amount]`" class="input input-bordered input-sm" placeholder="{{ __('Amount') }}" required>
                            </div>
                        </template>
                        <button type="button" class="btn btn-xs btn-outline" @click="rows.push({ due_date: '', amount: '' })">{{ __('Add installment') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save plan') }}</button>
                    </form>
                </div>
            </section>
        @else
            <section class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Installments') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Due date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Paid') }}</th>
                                    <th>{{ __('Remaining') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($installments as $row)
                                    <tr>
                                        <td>{{ $row['model']->due_date->format('Y-m-d') }}</td>
                                        <td>{{ number_format($row['model']->amount) }}</td>
                                        <td>{{ number_format($row['paid']) }}</td>
                                        <td>{{ number_format($row['remaining']) }}</td>
                                        <td>{{ __($row['status']) }}</td>
                                        <td>
                                            @if ($row['remaining'] > 0)
                                                <form action="{{ route('settlements.installments.record-payment', $row['model']) }}" method="post" class="flex items-center gap-1">
                                                    @csrf
                                                    <input type="number" step="0.01" name="amount" class="input input-bordered input-xs w-24" placeholder="{{ __('Amount') }}" required>
                                                    <select name="subject_id" class="select select-bordered select-xs" required>
                                                        @foreach ($settlementSubjects as $subject)
                                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-xs btn-outline">{{ __('Record payment') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </main>
</x-app-layout>
