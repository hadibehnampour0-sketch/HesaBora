<?php

namespace App\Services\Sync;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SyncOperation;
use App\Enums\SyncStatus;
use App\Models\Customer;
use App\Models\DeviceInstallation;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\Subject;
use App\Models\SyncInboxEntry;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * D-071 stage 2.5 -- the gated Customer/Product Sync Pilot. Deliberately
 * scoped to exactly these two entities (SYNCABLE_ENTITIES below); extending
 * this to Document/Invoice/Payment/Transaction is stages 2.7/2.8, not this
 * one, per the owner's explicit gate.
 *
 * D-073 stage 2.7A adds Document, but ONLY under the locked Document Draft
 * Sync boundary enforced by documentDraftSyncViolation(): no hard delete, no
 * syncing an approval, no documentable-linked (automatic) documents, and no
 * touching an already-approved document.
 *
 * D-073 stage 2.7B adds Invoice under the same Draft-only spirit
 * (invoiceDraftSyncViolation()), plus the first real exercise of decision 6
 * (dependency-aware sync): an Invoice CREATE/UPDATE that references a
 * `customer_uuid` not yet present on this side becomes PENDING_DEPENDENCY,
 * never a rejected/dropped record -- see resolveInvoiceDependency().
 * Payment/Transaction/Posting/Cheque remain out of scope until 2.8.
 *
 * Idempotency: every outbox/inbox entry carries a client-generated
 * operation_id that is stable across retries. Resubmitting the same
 * operation_id returns the ORIGINAL outcome without reapplying anything --
 * this is what makes "the same batch pushed twice" safe. The one exception
 * is PENDING_DEPENDENCY: that outcome is not terminal, so a resent
 * operation_id re-attempts resolution instead of replaying the cached
 * "still waiting" result forever.
 *
 * Conflict policy: an UPDATE/DELETE must state the sync_version it was
 * based on. If the server's current version for that entity has moved on,
 * the operation is a CONFLICT, not a silent last-write-wins overwrite --
 * per D-071 decision 5, this must be an explicit, reviewable state.
 *
 * D-075 stage 2.7C adds a fourth operation, FINALIZE (Invoice only): it
 * consumes exactly one number from the device's own NumberingLeaseService
 * range and makes it immutable (`is_number_finalized`) -- never a value
 * the client supplies. Routed through the same idempotent operation_id/
 * version-conflict machinery as CREATE/UPDATE/DELETE, not a parallel
 * mechanism. See applyFinalize().
 */
class SyncPilotService
{
    private const SYNCABLE_ENTITIES = [
        Customer::class => Customer::class,
        Product::class => Product::class,
        Document::class => Document::class,
        Invoice::class => Invoice::class,
        Payment::class => Payment::class,
        PaymentAllocation::class => PaymentAllocation::class,
    ];

    private const NON_FILLABLE_ON_APPLY = ['id', 'uuid', 'sync_version', 'sync_status', 'origin_device_id', 'created_at', 'updated_at', 'company_id'];

    /**
     * D-073 2.7A: fields whose cross-device commit semantics are not yet
     * decided for Document -- `number` is finalized at issuance, not now
     * (stage 2.7C), and approval/documentable linkage are out of scope
     * until 2.7D/2.7B. Never applied from an incoming payload.
     */
    private const DOCUMENT_EXCLUDED_ON_APPLY = ['number', 'creator_id', 'approver_id', 'approved_at', 'documentable_id', 'documentable_type'];

    /**
     * D-073 2.7B: `customer_id` is never taken from the payload directly --
     * `customer_uuid` is resolved to a local id by resolveInvoiceDependency()
     * and injected as a forced attribute instead. `number`/`document_id`
     * (finalization/2.7D) and `returned_invoice_id` (a cross-device invoice
     * chain, not yet supported) are excluded the same way Document excludes
     * its own out-of-scope fields.
     */
    private const INVOICE_EXCLUDED_ON_APPLY = ['number', 'document_id', 'customer_id', 'customer_uuid', 'creator_id', 'warehouse_id', 'returned_invoice_id', 'is_number_finalized', 'number_finalized_at'];

    public function __construct(
        private readonly SyncInboxService $inboxService,
        private readonly NumberingLeaseService $numberingLeaseService
    ) {}

    public function isSyncable(string $entityType): bool
    {
        return array_key_exists($entityType, self::SYNCABLE_ENTITIES);
    }

    public function modelClassFor(string $entityType): ?string
    {
        return self::SYNCABLE_ENTITIES[$entityType] ?? null;
    }

    /**
     * @param  array{operation_id: string, entity_type: string, entity_uuid: string, operation: int, payload: array, base_sync_version: ?int}  $entry
     * @return array{operation_id: string, status: string, server_version: ?int, local_version: ?int, reason: ?string}
     */
    public function pushEntry(DeviceInstallation $device, array $entry): array
    {
        $existingByOperationId = SyncInboxEntry::where('operation_id', $entry['operation_id'])->first();
        if ($existingByOperationId) {
            if ($existingByOperationId->status === SyncStatus::PENDING_DEPENDENCY) {
                // Not a terminal outcome -- re-attempt resolution against the
                // SAME stored inbox entry rather than replaying a cached
                // "still waiting" result forever (D-073 decision 6).
                return DB::transaction(function () use ($existingByOperationId, $device) {
                    $this->apply($existingByOperationId, $device);

                    return $this->resultFor($existingByOperationId->fresh());
                });
            }

            // Idempotent replay: return the ORIGINAL outcome, do not reapply.
            return $this->resultFor($existingByOperationId);
        }

        if (! $this->isSyncable($entry['entity_type'])) {
            return [
                'operation_id' => $entry['operation_id'],
                'status' => 'rejected',
                'server_version' => null,
                'local_version' => $entry['base_sync_version'] ?? null,
                'reason' => __('Entity type :type is not part of the sync pilot.', ['type' => $entry['entity_type']]),
            ];
        }

        return DB::transaction(function () use ($device, $entry) {
            $inboxEntry = SyncInboxEntry::create([
                'operation_id' => $entry['operation_id'],
                'entity_type' => $entry['entity_type'],
                'entity_uuid' => $entry['entity_uuid'],
                'operation' => $entry['operation'],
                'payload' => $entry['payload'],
                'base_sync_version' => $entry['base_sync_version'] ?? null,
                'origin_device_id' => $device->id,
                'company_id' => $device->company_id,
                'status' => \App\Enums\SyncStatus::PENDING,
            ]);

            $this->apply($inboxEntry, $device);

            return $this->resultFor($inboxEntry->fresh());
        });
    }

    private function resultFor(SyncInboxEntry $entry): array
    {
        return [
            'operation_id' => $entry->operation_id,
            'status' => match ($entry->status) {
                \App\Enums\SyncStatus::SYNCED => 'accepted',
                \App\Enums\SyncStatus::CONFLICT => 'conflict',
                \App\Enums\SyncStatus::REJECTED => 'rejected',
                \App\Enums\SyncStatus::PENDING_DEPENDENCY => 'pending_dependency',
                default => 'pending',
            },
            'server_version' => $entry->server_version,
            'local_version' => $entry->local_version,
            'reason' => $entry->rejection_reason,
        ];
    }

    private function apply(SyncInboxEntry $inboxEntry, DeviceInstallation $device): void
    {
        $modelClass = self::SYNCABLE_ENTITIES[$inboxEntry->entity_type];
        /** @var Model $existing */
        $existing = $modelClass::where('uuid', $inboxEntry->entity_uuid)->first();

        // Payment/PaymentAllocation have no FiscalYearScope (no company_id
        // column of their own -- Payment::getCompanyIdAttribute() derives it
        // via document/invoice/cheque/payer). A uuid collision with another
        // company's row here is not company-filtered by the query above like
        // every other syncable entity is, so it must be checked explicitly --
        // otherwise a device could learn of another company's payment's
        // existence/sync_version via the conflict path this lookup feeds
        // (the same defect class as guardrail #32: a model with no scope of
        // its own protects nothing on its own). Treat a cross-company match
        // as no match at all, exactly as if the uuid had never collided.
        if ($existing instanceof Payment || $existing instanceof PaymentAllocation) {
            if ((int) $existing->resolvedCompanyId() !== (int) $device->company_id) {
                $existing = null;
            }
        }

        // D-075: Finalize (numbering) is Invoice-only for 2.7C.
        if ($inboxEntry->operation === SyncOperation::FINALIZE && $inboxEntry->entity_type !== Invoice::class) {
            $this->inboxService->reject($inboxEntry, __('Finalize is only supported for Invoice (stage 2.7C).'));

            return;
        }

        // D-076: Approve (posting) is Invoice-only for 2.7D. It has its own
        // complete, version-check-first validation inside applyApprove() --
        // deliberately NOT the generic Draft Sync violation/dependency
        // checks below, which are about protecting Drafts, not about
        // deciding whether an approval may proceed.
        if ($inboxEntry->operation === SyncOperation::APPROVE) {
            if ($inboxEntry->entity_type !== Invoice::class) {
                $this->inboxService->reject($inboxEntry, __('Approve is only supported for Invoice (stage 2.7D).'));

                return;
            }

            $this->applyApprove($inboxEntry, $existing, $device);

            return;
        }

        // D-079 stage 2.8B -- Payment never goes through the generic
        // create-by-filling-attributes dispatch below (unlike Customer/
        // Product/Document/Invoice): it must call the EXISTING
        // PaymentService exactly once so creation and posting stay the
        // same atomic domain operation they already are (D-078 decision 1).
        if ($inboxEntry->entity_type === Payment::class) {
            $violation = $this->paymentSyncViolation($inboxEntry);
            if ($violation !== null) {
                $this->inboxService->reject($inboxEntry, $violation);

                return;
            }

            $this->applyPaymentCreate($inboxEntry, $existing, $device);

            return;
        }

        // D-081 stage 2.8D -- PaymentAllocation, like Payment, never goes
        // through the generic dispatch: it must call the EXISTING
        // PaymentService::allocatePayment() exactly once (decision 2), never
        // insert into payment_allocations directly.
        if ($inboxEntry->entity_type === PaymentAllocation::class) {
            $violation = $this->paymentAllocationSyncViolation($inboxEntry);
            if ($violation !== null) {
                $this->inboxService->reject($inboxEntry, $violation);

                return;
            }

            $this->applyPaymentAllocationCreate($inboxEntry, $existing, $device);

            return;
        }

        if ($inboxEntry->entity_type === Document::class) {
            $violation = $this->documentDraftSyncViolation($inboxEntry, $existing);
            if ($violation !== null) {
                $this->inboxService->reject($inboxEntry, $violation);

                return;
            }
        }

        $forcedAttributes = [];

        if ($inboxEntry->entity_type === Invoice::class) {
            $violation = $this->invoiceDraftSyncViolation($inboxEntry, $existing);
            if ($violation !== null) {
                $this->inboxService->reject($inboxEntry, $violation);

                return;
            }

            $dependency = $this->resolveInvoiceDependency($inboxEntry);
            if ($dependency['blocked']) {
                $this->inboxService->markPendingDependency($inboxEntry, $dependency['reason']);

                return;
            }
            $forcedAttributes = $dependency['forced_attributes'];
        }

        match ($inboxEntry->operation) {
            SyncOperation::CREATE => $this->applyCreate($inboxEntry, $modelClass, $existing, $device, $forcedAttributes),
            SyncOperation::UPDATE => $this->applyUpdate($inboxEntry, $existing, $forcedAttributes),
            SyncOperation::DELETE => $this->applyDelete($inboxEntry, $existing),
            SyncOperation::FINALIZE => $this->applyFinalize($inboxEntry, $existing, $device),
            SyncOperation::APPROVE => $this->applyApprove($inboxEntry, $existing, $device),
        };
    }

    /**
     * D-076 stage 2.7D -- Approve/Post a Sales Invoice. Never invents
     * accounting logic: revalidates via the EXISTING
     * InvoiceService::getChangeStatusValidation() and posts via the
     * EXISTING InvoiceService::changeInvoiceStatus() (decision 8) -- the
     * exact same code path the non-sync UI approval flow already uses.
     */
    private function applyApprove(SyncInboxEntry $inboxEntry, ?Model $existing, DeviceInstallation $device): void
    {
        if (! $existing instanceof Invoice) {
            $this->inboxService->reject($inboxEntry, __('The target entity does not exist on the server.'));

            return;
        }

        if ($existing->invoice_type !== InvoiceType::SELL) {
            $this->inboxService->reject($inboxEntry, __('Stage 2.7D only supports approving a Sales Invoice.'));

            return;
        }

        if (! $existing->is_number_finalized) {
            $this->inboxService->reject($inboxEntry, __('This invoice must be finalized (stage 2.7C) before it can be approved.'));

            return;
        }

        $serverVersion = (int) $existing->sync_version;
        $baseVersion = $inboxEntry->base_sync_version;

        if ($baseVersion !== null && $baseVersion !== $serverVersion) {
            $this->inboxService->markConflict(
                $inboxEntry,
                __('The server version has changed since this approval was requested.'),
                (string) $serverVersion,
                (string) $baseVersion
            );
            $this->recordConflict($inboxEntry, $existing, $serverVersion, $baseVersion);

            return;
        }

        if ($existing->status->isApprovedOrSettled() || $existing->status === InvoiceStatus::APPROVED_INACTIVE) {
            // D-076 decision 6: a repeat Approve whose caller's version
            // DOES match current reality is a safe idempotent no-op, never
            // a second posting. Deliberately checked only AFTER the version
            // check above -- a caller with a STALE version gets a CONFLICT
            // instead (D-076's own two-devices gate scenario), not a silent
            // "it's fine, someone already did it."
            $this->inboxService->markApplied($inboxEntry);

            return;
        }

        // D-076 decision 3: real business revalidation via the EXISTING
        // accounting service, not a sync-invented check.
        $decision = InvoiceService::getChangeStatusValidation($existing);
        if (! $decision->canProceed) {
            $this->inboxService->reject($inboxEntry, __('The server rejected this approval: the invoice failed accounting validation.'));

            return;
        }

        $actingUser = $device->pairedByUser;
        if ($actingUser === null) {
            $this->inboxService->reject($inboxEntry, __('This device has no acting user on record and cannot approve invoices.'));

            return;
        }

        try {
            SyncContext::applyingRemote(function () use ($existing, $actingUser, $inboxEntry) {
                $previousUser = Auth::user();
                Auth::setUser($actingUser);
                try {
                    (new InvoiceService)->changeInvoiceStatus($existing, 'approved');
                } finally {
                    if ($previousUser !== null) {
                        Auth::setUser($previousUser);
                    } else {
                        Auth::logout();
                    }
                }

                $existing->forceFill([
                    'sync_status' => \App\Enums\SyncStatus::SYNCED,
                    'origin_device_id' => $inboxEntry->origin_device_id,
                ])->save();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // D-076 decision 5: a genuine collision (e.g. the number was
            // somehow reused outside this lease's guarantee) is a Business
            // Rejection, never an unhandled 500 and never a partial post --
            // the whole attempt is inside one transaction (pushEntry()'s
            // outer DB::transaction), so nothing here is left half-applied.
            $this->inboxService->reject($inboxEntry, __('The server rejected this approval due to a data collision.'));

            return;
        }

        $this->inboxService->markApplied($inboxEntry);
    }

    /**
     * D-079 stage 2.8B -- the locked Payment Sync boundary. Returns a
     * rejection reason for anything outside 2.8B's scope, or null when the
     * (always-CREATE, by this point) operation may proceed to dependency
     * resolution.
     */
    private function paymentSyncViolation(SyncInboxEntry $inboxEntry): ?string
    {
        if ($inboxEntry->operation === SyncOperation::DELETE) {
            return __('Hard delete is not supported for financial records; posted payments are immutable.');
        }

        if ($inboxEntry->operation === SyncOperation::UPDATE) {
            return __('Updating a payment via sync is not supported yet.');
        }

        $payload = $inboxEntry->payload;
        $paymentType = $payload['payment_type'] ?? null;

        if (! in_array($paymentType, ['credit', 'invoice'], true)) {
            return __('payment_type must be "credit" or "invoice".');
        }

        if (empty($payload['amount'] ?? null) || (float) $payload['amount'] <= 0) {
            return __('Payment amount must be greater than zero.');
        }

        if (empty($payload['subject_uuid'] ?? null)) {
            return __('subject_uuid is required to create a payment.');
        }

        if ($paymentType === 'credit' && empty($payload['customer_uuid'] ?? null)) {
            return __('customer_uuid is required to create a credit payment.');
        }

        if ($paymentType === 'invoice' && empty($payload['invoice_uuid'] ?? null)) {
            return __('invoice_uuid is required to create an invoice-linked payment.');
        }

        if (! empty($payload['cheque_id'] ?? null) || ! empty($payload['cheque_uuid'] ?? null)) {
            return __('Cheque-linked payments are not yet part of Payment Sync (stage 2.8B).');
        }

        return null;
    }

    /**
     * D-079 decision 6: both domain-supported Payment shapes -- Credit
     * Payment and Invoice-linked Payment -- resolve their cross-device
     * references (`subject_uuid` always; `customer_uuid` or `invoice_uuid`
     * depending on `payment_type`) the same dependency-aware way Invoice's
     * `customer_uuid` already does (D-073 decision 6): a missing reference
     * becomes PENDING_DEPENDENCY, never a rejection or a dropped record.
     *
     * @return array{blocked: bool, reason?: string, payment_type?: string, customer?: Customer, invoice?: Invoice, subject?: Subject}
     */
    private function resolvePaymentDependencies(SyncInboxEntry $inboxEntry): array
    {
        $payload = $inboxEntry->payload;
        $paymentType = $payload['payment_type'];

        $subject = Subject::where('uuid', $payload['subject_uuid'])->first();
        if ($subject === null) {
            return ['blocked' => true, 'reason' => __('Waiting for the settlement subject (:uuid) to sync first.', ['uuid' => $payload['subject_uuid']])];
        }

        if ($paymentType === 'credit') {
            $customer = Customer::where('uuid', $payload['customer_uuid'])->first();
            if ($customer === null) {
                return ['blocked' => true, 'reason' => __('Waiting for the customer (:uuid) to sync first.', ['uuid' => $payload['customer_uuid']])];
            }

            return ['blocked' => false, 'payment_type' => 'credit', 'customer' => $customer, 'subject' => $subject];
        }

        $invoice = Invoice::where('uuid', $payload['invoice_uuid'])->first();
        if ($invoice === null) {
            return ['blocked' => true, 'reason' => __('Waiting for the invoice (:uuid) to sync first.', ['uuid' => $payload['invoice_uuid']])];
        }

        return ['blocked' => false, 'payment_type' => 'invoice', 'invoice' => $invoice, 'subject' => $subject];
    }

    /**
     * D-079 -- the audited materialization answer: call the EXISTING
     * PaymentService exactly once (one posting, by construction -- never a
     * second accounting engine, D-078 decision 1/8), then fix up the
     * resulting row's identity to the client's `financial_event_uuid`.
     * This is an identity correction on the row that was already created,
     * not a second creation.
     */
    private function applyPaymentCreate(SyncInboxEntry $inboxEntry, ?Model $existing, DeviceInstallation $device): void
    {
        if ($existing instanceof Payment) {
            // D-080 scenario 1/2: a different operation_id claiming the
            // SAME financial_event_uuid is not automatically a conflict --
            // if the details genuinely match, this is the same economic
            // event being redeclared (e.g. a device that lost track of its
            // own operation_id but remembers the entity uuid) and must
            // converge idempotently, never a second posting. Only a
            // MISMATCHED payload under the same claimed identity is a
            // tamper/data conflict.
            if ($this->paymentMatchesExisting($inboxEntry->payload, $existing)) {
                $this->inboxService->markApplied($inboxEntry);

                return;
            }

            $this->inboxService->markConflict($inboxEntry, __('An entity with this identity already exists with different details.'), (string) $existing->sync_version, '0');

            return;
        }

        $dependency = $this->resolvePaymentDependencies($inboxEntry);
        if ($dependency['blocked']) {
            $this->inboxService->markPendingDependency($inboxEntry, $dependency['reason']);

            return;
        }

        $actingUser = $device->pairedByUser;
        if ($actingUser === null) {
            $this->inboxService->reject($inboxEntry, __('This device has no acting user on record and cannot create payments.'));

            return;
        }

        $payload = $inboxEntry->payload;
        $data = [
            'amount' => (float) $payload['amount'],
            'subject_id' => $dependency['subject']->id,
            'date' => $payload['date'] ?? null,
            'description' => $payload['description'] ?? null,
            'reference_number' => $payload['reference_number'] ?? null,
        ];

        $rejectionReason = null;

        try {
            SyncContext::applyingRemote(function () use ($dependency, $actingUser, $data, $inboxEntry, &$rejectionReason) {
                $previousUser = Auth::user();
                Auth::setUser($actingUser);
                $payment = null;

                try {
                    if ($dependency['payment_type'] === 'credit') {
                        $payment = (new PaymentService)->createCreditPayment($actingUser, $dependency['customer'], $data);
                    } else {
                        $decision = (new PaymentService)->createPayment($actingUser, $dependency['invoice'], $data);
                        if ($decision->hasErrors()) {
                            $rejectionReason = $decision->messages->pluck('text')->join('; ');

                            return;
                        }
                        $payment = $dependency['invoice']->payments()->latest('id')->firstOrFail();
                    }
                } finally {
                    if ($previousUser !== null) {
                        Auth::setUser($previousUser);
                    } else {
                        Auth::logout();
                    }
                }

                $payment->forceFill([
                    'uuid' => $inboxEntry->entity_uuid,
                    'sync_version' => 1,
                    'sync_status' => \App\Enums\SyncStatus::SYNCED,
                    'origin_device_id' => $inboxEntry->origin_device_id,
                ])->save();
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            $rejectionReason = collect($e->errors())->flatten()->join('; ');
        } catch (\Illuminate\Database\QueryException $e) {
            $rejectionReason = __('The server rejected this payment due to a data collision.');
        }

        if ($rejectionReason !== null) {
            $this->inboxService->reject($inboxEntry, $rejectionReason);

            return;
        }

        $this->inboxService->markApplied($inboxEntry);
    }

    /**
     * D-080 scenario 1/2 -- is a re-declared CREATE for an already-existing
     * Payment the SAME economic event, or a different one masquerading
     * under the same claimed `financial_event_uuid`? Compares only the
     * fields that define what the event actually IS (amount, target,
     * settlement subject) -- never a fuzzy/partial match, and never
     * compares against a different `financial_event_uuid` (D-080 scenario
     * 3: identity is the uuid alone, two different uuids are always two
     * different events no matter how similar their payloads look).
     */
    private function paymentMatchesExisting(array $payload, Payment $existing): bool
    {
        $amount = (float) ($payload['amount'] ?? 0);
        if (abs($amount - (float) $existing->amount) > 0.01) {
            return false;
        }

        $subject = Subject::where('uuid', $payload['subject_uuid'] ?? null)->first();
        if ($subject === null || (int) $subject->id !== (int) $existing->settlement_subject_id) {
            return false;
        }

        if (($payload['payment_type'] ?? null) === 'credit') {
            $customer = Customer::where('uuid', $payload['customer_uuid'] ?? null)->first();

            return $existing->invoice_id === null
                && $customer !== null
                && (int) $customer->id === (int) $existing->payer_id;
        }

        $invoice = Invoice::where('uuid', $payload['invoice_uuid'] ?? null)->first();

        return $invoice !== null && (int) $invoice->id === (int) $existing->invoice_id;
    }

    /**
     * D-081 stage 2.8D -- the locked PaymentAllocation Sync boundary.
     * Returns a rejection reason for anything outside 2.8D's scope
     * (decision 7: CREATE-only, Invoice targets only), or null when the
     * operation may proceed to dependency resolution.
     */
    private function paymentAllocationSyncViolation(SyncInboxEntry $inboxEntry): ?string
    {
        if ($inboxEntry->operation !== SyncOperation::CREATE) {
            return __('Only creating a payment allocation via sync is supported (stage 2.8D); deletion/reversal is out of scope.');
        }

        $payload = $inboxEntry->payload;

        if (empty($payload['payment_uuid'] ?? null)) {
            return __('payment_uuid is required to create a payment allocation.');
        }

        if (($payload['allocatable_type'] ?? null) !== 'invoice') {
            return __('Only Invoice-targeted allocations are part of Payment Allocation Sync (stage 2.8D).');
        }

        if (empty($payload['allocatable_uuid'] ?? null)) {
            return __('allocatable_uuid is required to create a payment allocation.');
        }

        if (empty($payload['amount'] ?? null) || (float) $payload['amount'] <= 0) {
            return __('Allocation amount must be greater than zero.');
        }

        return null;
    }

    /**
     * D-081 decision 1: dependency-aware, the same shape already proven for
     * Invoice's `customer_uuid` and Payment's own dependencies -- a missing
     * Payment or Invoice becomes PENDING_DEPENDENCY, never a rejection.
     *
     * @return array{blocked: bool, reason?: string, payment?: Payment, invoice?: Invoice}
     */
    private function resolvePaymentAllocationDependencies(SyncInboxEntry $inboxEntry): array
    {
        $payload = $inboxEntry->payload;

        $payment = Payment::where('uuid', $payload['payment_uuid'])->first();
        if ($payment === null) {
            return ['blocked' => true, 'reason' => __('Waiting for the payment (:uuid) to sync first.', ['uuid' => $payload['payment_uuid']])];
        }

        $invoice = Invoice::where('uuid', $payload['allocatable_uuid'])->first();
        if ($invoice === null) {
            return ['blocked' => true, 'reason' => __('Waiting for the invoice (:uuid) to sync first.', ['uuid' => $payload['allocatable_uuid']])];
        }

        return ['blocked' => false, 'payment' => $payment, 'invoice' => $invoice];
    }

    /**
     * D-081 -- mirrors paymentMatchesExisting()'s idempotent-convergence-
     * vs-tamper-conflict distinction (D-080), applied to Allocation: a
     * re-declared CREATE for an already-existing allocation uuid converges
     * idempotently only if it is genuinely the same allocation (same
     * Payment, same target, same amount); anything else is a conflict.
     */
    private function paymentAllocationMatchesExisting(array $payload, PaymentAllocation $existing): bool
    {
        $amount = (float) ($payload['amount'] ?? 0);
        if (abs($amount - (float) $existing->amount) > 0.01) {
            return false;
        }

        $payment = Payment::where('uuid', $payload['payment_uuid'] ?? null)->first();
        if ($payment === null || (int) $payment->id !== (int) $existing->payment_id) {
            return false;
        }

        if ($existing->allocatable_type !== Invoice::class) {
            return false;
        }

        $invoice = Invoice::where('uuid', $payload['allocatable_uuid'] ?? null)->first();

        return $invoice !== null && (int) $invoice->id === (int) $existing->allocatable_id;
    }

    /**
     * D-081 decision 2 -- the locked execution boundary: never insert into
     * `payment_allocations` directly. Calls the EXISTING
     * PaymentService::allocatePayment() exactly once, which already owns
     * the only locking (`lockForUpdate()` on the Payment) and business
     * validation (approved/partially-paid status, remaining-balance check)
     * that matters -- decision 3: a capacity failure surfaces through that
     * existing validation as a Business Rejection, never a sync-invented
     * remaining-amount recalculation.
     */
    private function applyPaymentAllocationCreate(SyncInboxEntry $inboxEntry, ?Model $existing, DeviceInstallation $device): void
    {
        if ($existing instanceof PaymentAllocation) {
            if ($this->paymentAllocationMatchesExisting($inboxEntry->payload, $existing)) {
                $this->inboxService->markApplied($inboxEntry);

                return;
            }

            $this->inboxService->markConflict($inboxEntry, __('An entity with this identity already exists with different details.'), (string) $existing->sync_version, '0');

            return;
        }

        $dependency = $this->resolvePaymentAllocationDependencies($inboxEntry);
        if ($dependency['blocked']) {
            $this->inboxService->markPendingDependency($inboxEntry, $dependency['reason']);

            return;
        }

        $actingUser = $device->pairedByUser;
        if ($actingUser === null) {
            $this->inboxService->reject($inboxEntry, __('This device has no acting user on record and cannot allocate payments.'));

            return;
        }

        $payload = $inboxEntry->payload;
        $amount = (float) $payload['amount'];
        $reference = $payload['reference'] ?? null;
        $rejectionReason = null;

        try {
            SyncContext::applyingRemote(function () use ($dependency, $actingUser, $amount, $reference, $inboxEntry, &$rejectionReason) {
                $allocation = (new PaymentService)->allocatePayment($actingUser, $dependency['payment'], $dependency['invoice'], $amount, $reference);

                $allocation->forceFill([
                    'uuid' => $inboxEntry->entity_uuid,
                    'sync_version' => 1,
                    'sync_status' => \App\Enums\SyncStatus::SYNCED,
                    'origin_device_id' => $inboxEntry->origin_device_id,
                ])->save();
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            $rejectionReason = collect($e->errors())->flatten()->join('; ');
        } catch (\Illuminate\Database\QueryException $e) {
            $rejectionReason = __('The server rejected this allocation due to a data collision.');
        }

        if ($rejectionReason !== null) {
            $this->inboxService->reject($inboxEntry, $rejectionReason);

            return;
        }

        $this->inboxService->markApplied($inboxEntry);
    }

    /**
     * D-075 stage 2.7C-D -- Finalize consumes exactly one number from the
     * device's OWN active numbering lease and makes it immutable. Unlike a
     * plain UPDATE, this is never satisfied by anything in the payload --
     * the number always comes from NumberingLeaseService, never the client.
     */
    private function applyFinalize(SyncInboxEntry $inboxEntry, ?Model $existing, DeviceInstallation $device): void
    {
        if (! $existing) {
            $this->inboxService->reject($inboxEntry, __('The target entity does not exist on the server.'));

            return;
        }

        $serverVersion = (int) $existing->sync_version;
        $baseVersion = $inboxEntry->base_sync_version;

        if ($baseVersion !== null && $baseVersion !== $serverVersion) {
            $this->inboxService->markConflict(
                $inboxEntry,
                __('The server version has changed since this finalize was requested.'),
                (string) $serverVersion,
                (string) $baseVersion
            );
            $this->recordConflict($inboxEntry, $existing, $serverVersion, $baseVersion);

            return;
        }

        if ($existing->is_number_finalized) {
            // D-076/D-077: an already-finalized invoice whose caller's
            // version DOES match current reality is a safe idempotent
            // no-op, never a second consumption (D-075 decision 6).
            // Deliberately checked only AFTER the version check above -- a
            // caller with a STALE version gets a deterministic CONFLICT
            // instead, never a silent "it's fine, someone already did it."
            // (This is the exact ordering bug D-076's own Approve gate
            // caught; Finalize had the same bug until D-077 fixed it.)
            $this->inboxService->markApplied($inboxEntry);

            return;
        }

        $number = $this->numberingLeaseService->consumeNext($device, $existing->invoice_type);

        if ($number === null) {
            // D-075 decision 3: exhaustion blocks offline finalization --
            // never a provisional number. The client must reserve a new
            // range and finalize again (a fresh operation_id, since this is
            // an actionable rejection, not a passively-resolving dependency).
            $this->inboxService->reject($inboxEntry, __('Numbering unavailable: this device has no available numbering range. Reserve a new range after reconnecting, then finalize again.'));

            return;
        }

        try {
            SyncContext::applyingRemote(function () use ($existing, $number, $serverVersion, $inboxEntry) {
                $existing->forceFill([
                    'number' => $number,
                    'is_number_finalized' => true,
                    'number_finalized_at' => now(),
                    'sync_version' => $serverVersion + 1,
                    'sync_status' => \App\Enums\SyncStatus::SYNCED,
                    'origin_device_id' => $inboxEntry->origin_device_id,
                ])->save();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // D-076 decision 5: the lease guarantees uniqueness among the
            // numbers IT hands out, but the underlying `invoices` unique
            // constraint (D-075 decision 7) is the true final guard -- a
            // number occupied outside the lease system entirely (e.g. a
            // legacy/manually-created row) is a Business Rejection here,
            // never an unhandled 500.
            $this->inboxService->reject($inboxEntry, __('The server rejected this finalize due to a numbering collision.'));

            return;
        }

        $this->inboxService->markApplied($inboxEntry);
    }

    /**
     * D-073 2.7B: the locked Invoice Draft Sync boundary -- mirrors
     * documentDraftSyncViolation()'s shape. Returns a rejection reason when
     * the operation falls outside 2.7B's scope, or null when it may
     * proceed to dependency resolution and the normal dispatch.
     */
    private function invoiceDraftSyncViolation(SyncInboxEntry $inboxEntry, ?Model $existing): ?string
    {
        if ($inboxEntry->operation === SyncOperation::DELETE) {
            return __('Hard delete is not supported for financial documents; use void/cancel instead.');
        }

        if ($inboxEntry->operation === SyncOperation::CREATE && empty($inboxEntry->payload['customer_uuid'] ?? null)) {
            return __('customer_uuid is required to create a draft invoice.');
        }

        if ($inboxEntry->operation === SyncOperation::CREATE && empty($inboxEntry->payload['invoice_type'] ?? null)) {
            return __('invoice_type is required to create a draft invoice.');
        }

        $statusValue = $inboxEntry->payload['status'] ?? null;
        $status = $statusValue !== null ? InvoiceStatus::tryFromName($statusValue) : null;
        if ($status !== null && ($status->isApprovedOrSettled() || $status === InvoiceStatus::APPROVED_INACTIVE)) {
            return __('Syncing an approved or settled invoice is not enabled yet (stage 2.7D).');
        }

        if (! empty($inboxEntry->payload['document_id'] ?? null) || ! empty($inboxEntry->payload['document_uuid'] ?? null)) {
            return __('Invoices linked to a Document are not yet part of Invoice Draft Sync (stage 2.7B/2.7D).');
        }

        if (! empty($inboxEntry->payload['returned_invoice_id'] ?? null)) {
            return __('Return/void invoices are not yet part of Invoice Draft Sync (stage 2.7B).');
        }

        $typeValue = $inboxEntry->payload['invoice_type'] ?? null;
        $type = $typeValue !== null ? InvoiceType::tryFromName($typeValue) : null;
        if ($type !== null && in_array($type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
            return __('Return/void invoices are not yet part of Invoice Draft Sync (stage 2.7B).');
        }

        if ($existing !== null && $existing->document_id !== null) {
            return __('This invoice is already linked to a Document and can no longer be synced as a draft (stage 2.7D).');
        }

        if ($existing !== null && ($existing->status->isApprovedOrSettled() || $existing->status === InvoiceStatus::APPROVED_INACTIVE)) {
            return __('This invoice has already been approved or settled and can no longer be synced as a draft (stage 2.7D).');
        }

        return null;
    }

    /**
     * D-073 decision 6: dependency-aware sync, first real exercise. An
     * Invoice's `customer_id` is a required (NOT NULL) foreign key, so a
     * CREATE literally cannot be attempted without resolving the parent
     * Customer first. Returns either resolved attributes to force into the
     * applied row, or a `blocked` outcome that must become
     * PENDING_DEPENDENCY -- never a rejection, never a dropped record.
     *
     * Also resolves `number` for a CREATE: `invoices.number` is a legacy
     * NOT NULL column (unlike Document's, which is nullable), so it cannot
     * simply be left unset the way `DOCUMENT_EXCLUDED_ON_APPLY` defers
     * Document's numbering to stage 2.7C. Until 2.7C's real reserved-range
     * protocol exists, a synced Draft invoice gets THIS device's own next
     * local number -- the exact same scheme every other local Invoice
     * creation already uses -- never the remote device's own number
     * (which would be meaningless here, or collide).
     *
     * @return array{blocked: bool, reason?: string, forced_attributes?: array}
     */
    private function resolveInvoiceDependency(SyncInboxEntry $inboxEntry): array
    {
        $forcedAttributes = [];

        // Invoice's `invoice_type`/`status` are Eloquent native enum casts
        // (backed by int) -- PHP's Enum::from() used internally requires
        // the raw int, not the `valueName()` string a client payload sends
        // (e.g. "sell", "pending"). invoiceDraftSyncViolation() already
        // guarantees whatever is present here is draft-safe; this just
        // resolves it to the actual enum instance before fill()/save().
        $type = null;
        if (array_key_exists('invoice_type', $inboxEntry->payload)) {
            $type = InvoiceType::tryFromName($inboxEntry->payload['invoice_type']);
            if ($type !== null) {
                $forcedAttributes['invoice_type'] = $type;
            }
        }
        if (array_key_exists('status', $inboxEntry->payload)) {
            $status = InvoiceStatus::tryFromName($inboxEntry->payload['status']);
            if ($status !== null) {
                $forcedAttributes['status'] = $status;
            }
        }

        $customerUuid = $inboxEntry->payload['customer_uuid'] ?? null;
        if ($customerUuid !== null) {
            $customer = Customer::where('uuid', $customerUuid)->first();

            if ($customer === null) {
                return [
                    'blocked' => true,
                    'reason' => __('Waiting for the customer (:uuid) to sync first.', ['uuid' => $customerUuid]),
                ];
            }

            $forcedAttributes['customer_id'] = $customer->id;
        }

        if ($inboxEntry->operation === SyncOperation::CREATE) {
            if ($type !== null) {
                $forcedAttributes['number'] = (string) ((int) (Invoice::where('invoice_type', $type)->max('number') ?? 0) + 1);
            }

            // `vat`/`subtraction`/`amount` are legacy NOT NULL columns with
            // no database default. 2.7B syncs the Invoice header only (no
            // items -- see the trait docblock), so a Draft invoice created
            // via sync legitimately starts at zero, same as
            // InvoiceService::createInvoiceWithoutApproval() before any
            // items are attached. Only fills the gap the payload leaves --
            // an explicit payload value always wins.
            $forcedAttributes['vat'] = $inboxEntry->payload['vat'] ?? 0;
            $forcedAttributes['subtraction'] = $inboxEntry->payload['subtraction'] ?? 0;
            $forcedAttributes['amount'] = $inboxEntry->payload['amount'] ?? 0;
        }

        return ['blocked' => false, 'forced_attributes' => $forcedAttributes];
    }

    /**
     * D-073 2.7A: the locked Document Draft Sync boundary. Returns a
     * rejection reason when the operation falls outside 2.7A's scope, or
     * null when it may proceed through the normal create/update dispatch.
     */
    private function documentDraftSyncViolation(SyncInboxEntry $inboxEntry, ?Model $existing): ?string
    {
        if ($inboxEntry->operation === SyncOperation::DELETE) {
            return __('Hard delete is not supported for financial documents; use void/cancel instead.');
        }

        if (! empty($inboxEntry->payload['approved_at'] ?? null)) {
            return __('Syncing an approved document is not enabled yet (stage 2.7D).');
        }

        if (! empty($inboxEntry->payload['documentable_type'] ?? null) || ! empty($inboxEntry->payload['documentable_id'] ?? null)) {
            return __('Documents linked to another record are not yet part of Document Draft Sync (stage 2.7A).');
        }

        if ($existing !== null && $existing->approved_at !== null) {
            return __('This document has already been approved and can no longer be synced as a draft (stage 2.7D).');
        }

        return null;
    }

    private function excludedFieldsFor(string $entityType): array
    {
        return match ($entityType) {
            Document::class => [...self::NON_FILLABLE_ON_APPLY, ...self::DOCUMENT_EXCLUDED_ON_APPLY],
            Invoice::class => [...self::NON_FILLABLE_ON_APPLY, ...self::INVOICE_EXCLUDED_ON_APPLY],
            default => self::NON_FILLABLE_ON_APPLY,
        };
    }

    private function applyCreate(SyncInboxEntry $inboxEntry, string $modelClass, ?Model $existing, DeviceInstallation $device, array $forcedAttributes = []): void
    {
        if ($existing) {
            $this->inboxService->markConflict($inboxEntry, __('An entity with this identity already exists.'), (string) $existing->sync_version, '0');

            return;
        }

        SyncContext::applyingRemote(function () use ($inboxEntry, $modelClass, $device, $forcedAttributes) {
            $attributes = collect($inboxEntry->payload)->except($this->excludedFieldsFor($inboxEntry->entity_type))->all();
            $attributes = array_merge($attributes, $forcedAttributes);
            $model = new $modelClass($attributes);
            $model->uuid = $inboxEntry->entity_uuid;
            $model->company_id = $device->company_id;
            $model->sync_version = 1;
            $model->sync_status = \App\Enums\SyncStatus::SYNCED;
            $model->origin_device_id = $device->id;
            $model->save();
        });

        $this->inboxService->markApplied($inboxEntry);
    }

    private function applyUpdate(SyncInboxEntry $inboxEntry, ?Model $existing, array $forcedAttributes = []): void
    {
        if (! $existing) {
            $this->inboxService->reject($inboxEntry, __('The target entity does not exist on the server.'));

            return;
        }

        $serverVersion = (int) $existing->sync_version;
        $baseVersion = $inboxEntry->base_sync_version;

        if ($baseVersion !== null && $baseVersion !== $serverVersion) {
            $this->inboxService->markConflict(
                $inboxEntry,
                __('The server version has changed since this edit was made.'),
                (string) $serverVersion,
                (string) $baseVersion
            );
            $this->recordConflict($inboxEntry, $existing, $serverVersion, $baseVersion);

            return;
        }

        SyncContext::applyingRemote(function () use ($inboxEntry, $existing, $serverVersion, $forcedAttributes) {
            $attributes = collect($inboxEntry->payload)->except($this->excludedFieldsFor($inboxEntry->entity_type))->all();
            $attributes = array_merge($attributes, $forcedAttributes);
            $existing->fill($attributes);
            $existing->sync_version = $serverVersion + 1;
            $existing->sync_status = \App\Enums\SyncStatus::SYNCED;
            $existing->origin_device_id = $inboxEntry->origin_device_id;
            $existing->save();
        });

        $this->inboxService->markApplied($inboxEntry);
    }

    private function applyDelete(SyncInboxEntry $inboxEntry, ?Model $existing): void
    {
        if (! $existing) {
            // Already gone -- the end state matches intent, so this is an
            // idempotent accept, not an error.
            $this->inboxService->markApplied($inboxEntry);

            return;
        }

        $serverVersion = (int) $existing->sync_version;
        $baseVersion = $inboxEntry->base_sync_version;

        if ($baseVersion !== null && $baseVersion !== $serverVersion) {
            $this->inboxService->markConflict(
                $inboxEntry,
                __('The server version has changed since this delete was requested.'),
                (string) $serverVersion,
                (string) $baseVersion
            );
            $this->recordConflict($inboxEntry, $existing, $serverVersion, $baseVersion);

            return;
        }

        SyncContext::applyingRemote(function () use ($existing) {
            $existing->delete();
        });

        $this->inboxService->markApplied($inboxEntry);
    }

    /**
     * D-072: a dedicated, reviewable/resolvable record per conflict,
     * separate from the generic inbox staging row. Snapshots are limited to
     * NON_FILLABLE_ON_APPLY-excluded, non-envelope attributes -- no
     * sensitive data belongs in a snapshot (locked now, matters more once
     * 2.7/2.8 bring in financial entities).
     */
    private function recordConflict(SyncInboxEntry $inboxEntry, Model $existing, int $serverVersion, ?int $baseVersion): void
    {
        \App\Models\SyncConflict::create([
            'inbox_entry_id' => $inboxEntry->id,
            'entity_type' => $inboxEntry->entity_type,
            'entity_uuid' => $inboxEntry->entity_uuid,
            'local_snapshot' => collect($inboxEntry->payload)->except($this->excludedFieldsFor($inboxEntry->entity_type))->all(),
            'server_snapshot' => collect($existing->attributesToArray())->except($this->excludedFieldsFor($inboxEntry->entity_type))->all(),
            'base_version' => $baseVersion,
            'server_version' => $serverVersion,
            'origin_device_id' => $inboxEntry->origin_device_id,
            'company_id' => $inboxEntry->company_id,
            'status' => \App\Enums\SyncConflictStatus::OPEN,
        ]);
    }

    /**
     * @return array<int, array{id: int, uuid: string, sync_version: int, attributes: array}>
     */
    public function pull(DeviceInstallation $device, string $entityType, int $afterId, int $limit = 100): array
    {
        if (! $this->isSyncable($entityType)) {
            return [];
        }

        $modelClass = self::SYNCABLE_ENTITIES[$entityType];

        if ($entityType === Payment::class) {
            // D-079 2.8B: Payment has no reliable top-level company_id of
            // its own (nullable; scoping is derived through its Document,
            // which every sync-eligible Payment always has). Cheque-linked
            // payments are excluded -- out of scope for 2.8B.
            $query = Payment::query()
                ->whereHas('document', fn ($q) => $q->where('company_id', $device->company_id))
                ->whereNull('cheque_id')
                ->where('id', '>', $afterId);

            return $query->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (Model $row) => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'sync_version' => $row->sync_version,
                    'attributes' => $row->attributesToArray(),
                ])
                ->all();
        }

        if ($entityType === PaymentAllocation::class) {
            // D-081 2.8D: PaymentAllocation has no company_id of its own
            // either -- scoped via its Payment's own Document.
            $query = PaymentAllocation::query()
                ->whereHas('payment.document', fn ($q) => $q->where('company_id', $device->company_id))
                ->where('id', '>', $afterId);

            return $query->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (Model $row) => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'sync_version' => $row->sync_version,
                    'attributes' => $row->attributesToArray(),
                ])
                ->all();
        }

        $query = $modelClass::where('company_id', $device->company_id)->where('id', '>', $afterId);

        if ($entityType === Document::class) {
            // D-073 2.7A: only Draft, non-documentable-linked documents are
            // part of Document Draft Sync -- Approved/automatic documents
            // wait for stage 2.7D/2.7B.
            $query->whereNull('approved_at')->whereNull('documentable_type');
        }

        if ($entityType === Invoice::class) {
            // D-073 2.7B: only Draft, document-less, non-return/void
            // invoices are part of Invoice Draft Sync.
            $query->whereNull('document_id')
                ->whereNotIn('status', [InvoiceStatus::APPROVED, InvoiceStatus::APPROVED_INACTIVE, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::PAID])
                ->whereNotIn('invoice_type', [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID]);
        }

        return $query->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Model $row) => [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'sync_version' => $row->sync_version,
                'attributes' => $row->attributesToArray(),
            ])
            ->all();
    }
}
