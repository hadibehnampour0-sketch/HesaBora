<x-app-layout :title="__('Bank Reconciliation')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section class="flex flex-col gap-2 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">{{ __('Bank Reconciliation') }}</h1>
                <p class="text-sm text-base-content/60">{{ $bankAccount->name }}</p>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Import bank statement') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('CSV file with at least "date" and "amount" columns; "description" and "reference" are optional.') }}</p>
                <form action="{{ route('bank-reconciliation.import', $bankAccount) }}" method="post" enctype="multipart/form-data" class="flex items-center gap-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,.txt" required class="file-input file-input-bordered file-input-sm">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Import') }}</button>
                </form>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Unmatched statement lines') }}</h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Suggested match') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr>
                                    <td>{{ $line->date->format('Y-m-d') }}</td>
                                    <td>{{ $line->description }}</td>
                                    <td>{{ $line->reference }}</td>
                                    <td>{{ number_format($line->amount, 2) }}</td>
                                    <td>
                                        <form action="{{ route('bank-reconciliation.match', $line) }}" method="post" class="flex items-center gap-2">
                                            @csrf
                                            <select name="transaction_id" class="select select-bordered select-xs" required>
                                                <option value="">{{ __('Select a transaction') }}</option>
                                                @foreach ($candidatesByLine[$line->id] as $candidate)
                                                    <option value="{{ $candidate->id }}">
                                                        #{{ $candidate->id }} — {{ number_format($candidate->value, 2) }} — {{ $candidate->desc }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-xs btn-outline">{{ __('Match') }}</button>
                                        </form>
                                    </td>
                                    <td></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-base-content/50">{{ __('No unmatched lines.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
