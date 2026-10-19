<x-app-layout :title="__('SMS Automation & Notifications')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-xl font-bold">{{ __('SMS Automation & Notifications') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Configure SMS gateways to send automated receipts, cheque due-date reminders, and invoice notifications to customers.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Gateway Configuration -->
            <div class="today-panel">
                <div class="today-panel-title mb-4 font-bold text-base">{{ __('Gateway Settings') }}</div>
                <form method="POST" action="{{ route('sms.config.update') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="label text-xs font-semibold">{{ __('SMS Provider') }}</label>
                        <select name="provider" class="select select-bordered w-full">
                            <option value="log" {{ ($config?->provider ?? 'log') === 'log' ? 'selected' : '' }}>Log / Test Simulator</option>
                            <option value="kavenegar" {{ ($config?->provider ?? '') === 'kavenegar' ? 'selected' : '' }}>کاوه نگار (Kavenegar)</option>
                            <option value="melipayamak" {{ ($config?->provider ?? '') === 'melipayamak' ? 'selected' : '' }}>ملی پیامک (Melipayamak)</option>
                            <option value="farazsms" {{ ($config?->provider ?? '') === 'farazsms' ? 'selected' : '' }}>فراز اس‌ام‌اس / IPPanel</option>
                        </select>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('API Key / Token') }}</label>
                        <input type="password" name="api_key" class="input input-bordered w-full" placeholder="{{ $config?->api_key ? '••••••••••••••••' : __('Enter API Key') }}">
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('Sender Number (Optional for Pattern)') }}</label>
                        <input type="text" name="sender_number" class="input input-bordered w-full" value="{{ $config?->sender_number }}" placeholder="e.g. 10008000">
                    </div>

                    <div class="divider my-2"></div>
                    <div class="today-panel-title mb-2 text-xs font-bold">{{ __('Automated Trigger Switches') }}</div>

                    <div class="space-y-2">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary checkbox-sm" {{ ($config?->is_active ?? false) ? 'checked' : '' }}>
                            <span class="label-text font-medium">{{ __('Activate SMS Service') }}</span>
                        </label>

                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_invoice_approved" value="1" class="checkbox checkbox-sm" {{ ($config?->auto_invoice_approved ?? true) ? 'checked' : '' }}>
                            <span class="label-text">{{ __('Send SMS when a Sell Invoice is approved') }}</span>
                        </label>

                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_payment_received" value="1" class="checkbox checkbox-sm" {{ ($config?->auto_payment_received ?? true) ? 'checked' : '' }}>
                            <span class="label-text">{{ __('Send SMS receipt when a payment is registered') }}</span>
                        </label>

                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_cheque_due_reminder" value="1" class="checkbox checkbox-sm" {{ ($config?->auto_cheque_due_reminder ?? true) ? 'checked' : '' }}>
                            <span class="label-text">{{ __('Daily reminder for cheques approaching due date') }}</span>
                        </label>

                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_debt_reminder" value="1" class="checkbox checkbox-sm" {{ ($config?->auto_debt_reminder ?? false) ? 'checked' : '' }}>
                            <span class="label-text">{{ __('Overdue customer debt notifications') }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save Configuration') }}</button>
                    </div>
                </form>
            </div>

            <!-- Test SMS & Notification Templates -->
            <div class="space-y-6">
                <!-- Test SMS -->
                <div class="today-panel">
                    <div class="today-panel-title mb-3 font-bold text-base">{{ __('Quick Test Dispatch') }}</div>
                    <form method="POST" action="{{ route('sms.test-send') }}" class="space-y-3" onsubmit="event.preventDefault(); fetch(this.action, { method: 'POST', body: new FormData(this), headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()).then(d=>alert(d.message)).catch(()=>alert('Failed'));">
                        @csrf
                        <div>
                            <input type="text" name="recipient" class="input input-bordered w-full" placeholder="{{ __('Mobile (e.g. 09121234567)') }}" required>
                        </div>
                        <div>
                            <textarea name="message" class="textarea textarea-bordered w-full" rows="2" placeholder="{{ __('Test message content...') }}" required>تست ارسال پیامک از سامانه حسابداری دخل</textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('Send Test') }}</button>
                        </div>
                    </form>
                </div>

                <!-- Template Customization -->
                <div class="today-panel">
                    <div class="today-panel-title mb-3 font-bold text-base">{{ __('Event Templates') }}</div>
                    <div class="space-y-4 text-xs">
                        <!-- Invoice Approved Template -->
                        <form method="POST" action="{{ route('sms.template.update', 'invoice_approved') }}" class="rounded border border-base-content/10 p-3 space-y-2">
                            @csrf
                            <div class="font-bold text-primary">{{ __('Invoice Approved Template') }}</div>
                            <input type="text" name="pattern_code" value="{{ $templates['invoice_approved']->pattern_code ?? '' }}" class="input input-bordered input-xs w-full" placeholder="{{ __('Pattern/Template Code for Lookup API') }}">
                            <textarea name="template_text" class="textarea textarea-bordered text-xs w-full" rows="2">{{ $templates['invoice_approved']->template_text ?? 'مشتری گرامی {customer_name}، فاکتور {invoice_number} به مبلغ {amount} ریال صادر شد.' }}</textarea>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/50">{customer_name}, {invoice_number}, {amount}, {date}</span>
                                <button type="submit" class="btn btn-ghost btn-xs text-primary">{{ __('Save') }}</button>
                            </div>
                        </form>

                        <!-- Cheque Due Reminder Template -->
                        <form method="POST" action="{{ route('sms.template.update', 'cheque_due') }}" class="rounded border border-base-content/10 p-3 space-y-2">
                            @csrf
                            <div class="font-bold text-primary">{{ __('Cheque Due Date Reminder') }}</div>
                            <input type="text" name="pattern_code" value="{{ $templates['cheque_due']->pattern_code ?? '' }}" class="input input-bordered input-xs w-full" placeholder="{{ __('Pattern/Template Code for Lookup API') }}">
                            <textarea name="template_text" class="textarea textarea-bordered text-xs w-full" rows="2">{{ $templates['cheque_due']->template_text ?? 'یادآوری: چک شماره {cheque_number} به مبلغ {amount} ریال در تاریخ {due_date} سررسید می‌شود.' }}</textarea>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/50">{customer_name}, {cheque_number}, {amount}, {due_date}</span>
                                <button type="submit" class="btn btn-ghost btn-xs text-primary">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Delivery History / Logs -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base">{{ __('Recent Dispatches & Logs') }}</div>
            <div class="overflow-x-auto">
                <table class="table table-xs w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Recipient') }}</th>
                            <th>{{ __('Event') }}</th>
                            <th>{{ __('Provider') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('Y/m/d H:i') }}</td>
                                <td class="font-mono">{{ $log->recipient }}</td>
                                <td><span class="badge badge-ghost badge-xs">{{ $log->event_type ?? 'manual' }}</span></td>
                                <td>{{ $log->provider }}</td>
                                <td>
                                    <span class="badge badge-xs {{ $log->status === 'sent' ? 'badge-success' : ($log->status === 'failed' ? 'badge-error' : 'badge-warning') }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td class="max-w-xs truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-base-content/50">{{ __('No SMS logs found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
