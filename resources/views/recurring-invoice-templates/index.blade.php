<x-app-layout :title="__('Recurring invoices')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Recurring invoices') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Set up an invoice once (rent, subscription, retainer) and have it issued automatically every period.') }}</p>
        </div>

        @can('recurring-invoice-templates.store')
            <form method="POST" action="{{ route('recurring-invoice-templates.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('New recurring invoice') }}</div>
                <input type="text" name="name" class="input input-bordered sm:col-span-2" placeholder="{{ __('Title') }}" maxlength="150" required>
                <select name="invoice_type" class="select select-bordered">
                    <option value="2">{{ __('Sell invoice') }}</option>
                    <option value="1">{{ __('Buy invoice') }}</option>
                </select>
                <select name="customer_id" class="select select-bordered" required>
                    <option value="">{{ __('Customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
                <select name="warehouse_id" class="select select-bordered">
                    <option value="">{{ __('No warehouse (services only)') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <select name="frequency" class="select select-bordered">
                    @foreach (\App\Enums\RecurringFrequency::cases() as $frequency)
                        <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" name="interval" class="input input-bordered" placeholder="{{ __('Every N periods') }}" value="1">
                <input type="text" name="start_date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                <input type="text" name="end_date" data-jdp autocomplete="off" readonly class="input input-bordered" placeholder="{{ __('End date (optional)') }}">

                <div class="grid grid-cols-2 gap-2 sm:col-span-4 sm:grid-cols-4">
                    <select name="items[0][item]" class="select select-bordered select-sm sm:col-span-2" required>
                        <option value="">{{ __('Item') }}</option>
                        <optgroup label="{{ __('Products') }}">
                            @foreach ($products as $product)
                                <option value="product:{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="{{ __('Services') }}">
                            @foreach ($services as $service)
                                <option value="service:{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <input type="number" step="any" min="0" name="items[0][quantity]" class="input input-bordered input-sm" placeholder="{{ __('Quantity') }}" required>
                    <input type="number" step="any" min="0" name="items[0][unit]" class="input input-bordered input-sm" placeholder="{{ __('Unit price') }}" required>
                    <p class="col-span-full text-xs text-base-content/60">{{ __('The invoice is issued and approved automatically on each due date.') }}</p>
                </div>
                <div class="sm:col-span-4 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Create') }}</button></div>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Repeats') }}</th><th>{{ __('Next run') }}</th><th>{{ __('Last generated') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td>{{ $customerNames[$template->customer_id] ?? '—' }}</td>
                            <td>{{ $template->frequency->label() }} × {{ $template->interval }}</td>
                            <td>{{ formatDate($template->next_run_date) }}</td>
                            <td>{{ $template->last_generated_at ? formatDate($template->last_generated_at) : '—' }}</td>
                            <td class="text-end">
                                @can('recurring-invoice-templates.generate')
                                    <form method="POST" action="{{ route('recurring-invoice-templates.generate', $template) }}">@csrf<button class="btn btn-xs btn-outline">{{ __('Issue now') }}</button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-sm text-base-content/60">{{ __('No recurring invoices yet.') }}</td></tr>
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
