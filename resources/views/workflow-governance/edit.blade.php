<x-app-layout :title="__('Workflow Governance')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Workflow Governance') }}</span>

            <form method="POST" action="{{ route('workflow-governance.update') }}" class="mt-4 space-y-6">
                @csrf
                @method('PUT')

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="self_approval_prevented" value="1" class="checkbox"
                            {{ old('self_approval_prevented', $selfApprovalPrevented) ? 'checked' : '' }} />
                        <span class="label-text">{{ __('Prevent a document from being approved by the user who created it') }}</span>
                    </label>
                    <p class="text-sm text-base-content/60 mt-1">
                        {{ __('Applies to invoices, ancillary costs, personnel requests, and payroll approvals. Off by default.') }}
                    </p>
                </div>

                <div class="form-control max-w-sm">
                    <x-input type="number" step="0.01" min="0" name="invoice_approval_chain_threshold"
                        id="invoice_approval_chain_threshold"
                        title="{{ __('Invoice approval-chain threshold (leave empty to disable)') }}"
                        :value="old('invoice_approval_chain_threshold', $approvalChainThreshold)" />
                    <p class="text-sm text-base-content/60 mt-1">
                        {{ __('Invoices at or above this amount require a second, independent approval before posting.') }}
                    </p>
                </div>

                <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
