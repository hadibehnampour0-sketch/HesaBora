<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Cheque;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\WarehouseProductStock;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Create a document, its transactions, an invoice and invoice items.
     * The document and transactions are generated automatically from invoice data.
     *
     * @param  array  $invoiceData  - Invoice details including customer_id, date, invoice_type, etc.
     * @param  array  $items  - Invoice items with product_id, quantity, unit_discount, etc.
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws ValidationException
     */
    public static function createInvoice(User $user, array $invoiceData, array $items = [], bool $approved = false): array
    {
        $date = $invoiceData['date'] ?? now()->toDateString();
        $items = self::assignWarehouseToItems($items, $invoiceData['warehouse_id'] ?? null);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $createdDocument = null;
        $createdInvoice = self::createInvoiceWithoutApproval($user, $invoiceData, $items, $buildResult, $date);

        if ($approved) {

            if ($createdInvoice->invoice_type === InvoiceType::SELL) {
                $createdInvoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]);

                return [
                    'document' => null,
                    'invoice' => $createdInvoice,
                ];
            }

            if (! self::getChangeStatusValidation($createdInvoice)->canProceed) {
                return [
                    'document' => null,
                    'invoice' => $createdInvoice,
                ];
            }

            $transactions = $buildResult['transactions'];

            $documentData = [
                'date' => $date,
                'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
                'number' => $invoiceData['document_number'] ?? null,
            ];

            DB::transaction(function () use ($documentData, $user, $transactions, $invoiceData, $items, &$createdDocument, &$createdInvoice) {
                $createdDocument = DocumentService::createDocument($user, $documentData, $transactions);
                DocumentService::changeDocumentStatus($createdDocument, $user, 'approved');

                $invoiceData['document_id'] = $createdDocument->id;
                $invoiceData['status'] = InvoiceStatus::APPROVED;

                $createdInvoice->update($invoiceData);

                DocumentService::syncDocumentable($createdDocument, $createdInvoice);

                ProductService::addProductsQuantities($items, $createdInvoice->invoice_type);
                self::syncInvoiceItems($createdInvoice, $items);

                $createdInvoice->refresh();
                CostOfGoodsService::updateProductsAverageCost($createdInvoice);
                ProductService::updateWarehouseAverageCosts($createdInvoice);

                self::syncCOGAfterForInvoiceItems($createdInvoice);
            });
        }

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
        ];
    }

    private static function createInvoiceWithoutApproval(User $user, array $invoiceData, array $items, array $buildResult, string $date)
    {
        $createdInvoice = null;

        DB::transaction(function () use ($user, $invoiceData, $items, $buildResult, $date, &$createdDocument, &$createdInvoice) {
            $invoiceData['document_id'] = null;
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['creator_id'] = $user->id;
            $invoiceData['date'] = $date;
            $invoiceData['title'] = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));
            $invoiceData['status'] = $invoiceData['invoice_type'] === InvoiceType::SELL ? InvoiceStatus::PRE_INVOICE : InvoiceStatus::PENDING;

            $createdInvoice = Invoice::create($invoiceData);

            self::syncInvoiceItems($createdInvoice, $items);
        });

        return $createdInvoice;
    }

    /**
     * Update an existing invoice and its related document/transactions.
     *
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws ValidationException
     */
    public static function updateInvoice(int $invoiceId, array $invoiceData, array $items = [], bool $approved = false): array
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $wasApproved = $invoice->status->isApprovedOrSettled();
        $oldItems = $wasApproved ? $invoice->items->toArray() : [];

        $invoiceData = self::normalizeInvoiceData($invoiceData);
        $items = self::assignWarehouseToItems($items, $invoiceData['warehouse_id'] ?? null);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        /**
         * D-086: once this Invoice has real financially effective activity
         * (a Payment or a PaymentAllocation), its financial content is
         * immutable at the service layer -- requesting the approved-edit
         * rebuild is always rejected outright; a plain (non-rebuild) update
         * is rejected only if it would actually change the financial
         * content (totals, customer, or warehouse -- COG-relevant).
         * Non-financial metadata (title/description/date/document_number)
         * remains editable exactly as before. An Approved invoice with no
         * payment/allocation yet is completely unaffected by this guard.
         */
        if ($wasApproved && self::hasEffectiveFinancialActivity($invoice)) {
            if ($approved) {
                throw ValidationException::withMessages([
                    'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
                ]);
            }

            $financialContentChanged = abs((float) $buildResult['totalAmount'] - (float) $invoice->amount) > 0.01
                || abs((float) $buildResult['totalVat'] - (float) $invoice->vat) > 0.01
                || (int) $invoiceData['customer_id'] !== (int) $invoice->customer_id
                || (int) ($invoiceData['warehouse_id'] ?? 0) !== (int) ($invoice->warehouse_id ?? 0);

            if ($financialContentChanged) {
                throw ValidationException::withMessages([
                    'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
                ]);
            }
        }

        $createdDocument = null;
        $handledApprovedEdit = false;

        // Approved invoices already changed inventory. Keep the reversal and
        // replacement in one transaction so warehouse stock cannot be doubled.
        if ($approved && $wasApproved) {
            $handledApprovedEdit = true;
            DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult, $oldItems, &$createdDocument) {
                ProductService::subProductsQuantities($oldItems, $invoice->invoice_type);

                $invoice->status = InvoiceStatus::UNAPPROVED;
                CostOfGoodsService::updateProductsAverageCost($invoice);
                ProductService::updateWarehouseAverageCosts($invoice);

                if ($invoice->document_id) {
                    DocumentService::deleteDocument($invoice->document_id);
                    $invoice->document_id = null;
                }

                $invoiceData['vat'] = $buildResult['totalVat'];
                $invoiceData['amount'] = $buildResult['totalAmount'];
                $invoiceData['title'] = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));
                $invoiceData['status'] = InvoiceStatus::UNAPPROVED;
                $invoice->update($invoiceData);
                self::syncInvoiceItems($invoice, $items);

                $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
                $invoice->update([
                    ...$invoiceData,
                    'status' => InvoiceStatus::APPROVED,
                    'document_id' => $createdDocument->id,
                ]);

                ProductService::addProductsQuantities($items, $invoice->invoice_type);
                $invoice->refresh();
                CostOfGoodsService::updateProductsAverageCost($invoice);
                ProductService::updateWarehouseAverageCosts($invoice);
                self::syncCOGAfterForInvoiceItems($invoice);
            });
        } else {
            $invoice = self::updateInvoiceWithoutApproval($invoice, $invoiceData, $items, $buildResult);
        }

        if ($approved && ! $handledApprovedEdit) {

            if ($invoice->invoice_type === InvoiceType::SELL) {
                $invoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]);

                return [
                    'document' => null,
                    'invoice' => $invoice,
                ];
            }

            if (! self::getChangeStatusValidation($invoice)->canProceed) {
                return [
                    'document' => null,
                    'invoice' => $invoice,
                ];
            }

            DB::transaction(function () use ($invoice, $invoiceData, $items, &$createdDocument) {
                $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);

                $invoiceData['status'] = InvoiceStatus::APPROVED;
                $invoiceData['document_id'] = $createdDocument->id;
                unset($invoiceData['document_number']); // Don't update invoice with document_number

                $invoice->update($invoiceData);

                // Add new quantities
                ProductService::addProductsQuantities($items, $invoice->invoice_type);

                self::syncInvoiceItems($invoice, $items);

                $invoice->refresh();

                CostOfGoodsService::updateProductsAverageCost($invoice);
                ProductService::updateWarehouseAverageCosts($invoice);
                self::syncCOGAfterForInvoiceItems($invoice);
            });
        }

        return [
            'document' => $createdDocument,
            'invoice' => $invoice,
        ];
    }

    private static function updateInvoiceWithoutApproval(Invoice $invoice, array $invoiceData, array $items, array $buildResult, bool $approved = false)
    {
        DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult) {
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['title'] = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));

            $invoice->update($invoiceData);

            self::syncInvoiceItems($invoice, $items);
        });

        return $invoice;
    }

    public static function syncCOGAfterForInvoiceItems(Invoice $invoice)
    {
        if (! in_array($invoice->invoice_type, [InvoiceType::BUY, InvoiceType::SELL, InvoiceType::RETURN_BUY], true)) {
            return;
        }

        $invoiceItems = $invoice->items;

        if ($invoiceItems->isEmpty()) {
            return;
        }

        foreach ($invoiceItems as $item) {
            if (! $item->itemable) {
                continue;
            }
            if ($item->itemable_type !== Product::class) {
                continue;
            }

            $item->cog_after = $item->itemable->average_cost;
            $item->update();
        }
    }

    /**
     * Delete invoice and its related document and transactions.
     */
    public static function deleteInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::findOrFail($invoiceId);

            $chequeIds = $invoice->payments()->whereNotNull('cheque_id')->pluck('cheque_id')->unique();
            Cheque::whereIn('id', $chequeIds)->each(fn (Cheque $cheque) => app(ChequeService::class)->delete($cheque));

            app(ActivityLogService::class)->deleteModels($invoice->items()->getQuery());

            $invoice->delete();
        });
    }

    private static function normalizeInvoiceData(array $invoiceData): array
    {
        $invoiceData = [
            'title' => $invoiceData['title'],
            'date' => $invoiceData['date'],
            'invoice_type' => $invoiceData['invoice_type'],
            'number' => isset($invoiceData['number']) ? (int) $invoiceData['number'] : null,
            'customer_id' => $invoiceData['customer_id'],
            'warehouse_id' => $invoiceData['warehouse_id'],
            'returned_invoice_id' => $invoiceData['returned_invoice_id'] ?? null,
            'document_number' => $invoiceData['document_number'],
            'description' => $invoiceData['description'] ?? null,
            'subtraction' => floatval($invoiceData['subtraction'] ?? 0),
            'permanent' => isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0,
            'invoice_id' => $invoiceData['invoice_id'] ?? null,
        ];

        return $invoiceData;
    }

    private static function assignWarehouseToItems(array $items, ?int $warehouseId): array
    {
        return array_map(fn (array $item) => [...$item, 'warehouse_id' => $warehouseId], $items);
    }

    /**
     * Persist invoice items for the given invoice, creating or updating each row via updateOrCreate.
     *
     * Called **after** ProductService::addProductsQuantities / subProductsQuantities has already
     * adjusted the product's stock level, so `$product->quantity` reflects the post-invoice state.
     * To recover the pre-invoice stock level (quantity_at) we reverse the effect:
     *
     *   - BUY / RETURN_SELL (stock was increased by this invoice):
     *       quantity_at = product.quantity − item.quantity
     *
     *   - SELL / RETURN_BUY (stock was decreased by this invoice):
     *       quantity_at = product.quantity + item.quantity
     *
     * After syncing, items that are no longer present in the new list are deleted,
     * and CostOfGoodsService::refreshProductCOGAfterItemsDeletion is invoked so that
     * COG snapshots on surviving items remain consistent.
     *
     * @param  Invoice  $invoice  The invoice whose items should be synced.
     * @param  array  $items  Raw item data from the form / service layer.
     *                        Each element must contain at minimum:
     *                        - itemable_type ('product' | 'service')
     *                        - itemable_id
     *                        - quantity
     *                        - unit  (unit price)
     *                        - unit_discount  (optional, defaults to 0)
     *                        - vat            (optional, defaults to 0)
     *                        - vat_is_value   (optional bool – if true, vat is an absolute amount)
     *                        - description    (optional)
     */
    private static function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        $itemId = [];

        foreach ($items as $item) {

            $type = $item['itemable_type'] ?? null;

            if ($type == null) {
                continue;
            }

            $product = $type === 'product' ? Product::findOrFail($item['itemable_id']) : null;
            $service = $type === 'service' ? Service::findOrFail($item['itemable_id']) : null;

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit'];
            $unitDiscount = $item['unit_discount'] ?? 0;

            $vatIsValue = $item['vat_is_value'] ?? false;
            $itemVat = $vatIsValue ? floatval($item['vat'] ?? 0) : (($item['vat'] ?? 0) / 100) * ($quantity * $unitPrice - $unitDiscount);

            // Calculate item amount (price - discount, VAT is separate but included in total)
            $itemAmount = $quantity * $unitPrice - $unitDiscount + $itemVat;

            $itemableType = $type === 'product' ? Product::class : Service::class;
            $itemableId = $item['itemable_id'];

            $quantityAt = 0;
            if ($product) {
                $invoiceType = $invoice->invoice_type;
                if (in_array($invoiceType, [InvoiceType::BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
                    $quantityAt = max(0, $product->quantity - $quantity);
                } elseif (in_array($invoiceType, [InvoiceType::SELL, InvoiceType::RETURN_BUY], true)) {
                    $quantityAt = $product->quantity + $quantity;
                }
            }

            $invoiceItemData = [
                'invoice_id' => $invoice->id,
                'quantity' => $quantity,
                'cog_after' => $product?->average_cost ?? $unitPrice,                                            // must be updated after creating invoice
                'quantity_at' => $quantityAt,                                                                    // stock level before this invoice was applied
                'unit_price' => $unitPrice,
                'unit_discount' => $unitDiscount,
                'vat' => $itemVat,
                'description' => $item['description'] ?? null,
                'amount' => $itemAmount,
                'itemable_type' => $itemableType,
                'itemable_id' => $itemableId,
            ];

            if (in_array($invoice->invoice_type, [InvoiceType::RETURN_SELL, InvoiceType::RETURN_BUY, InvoiceType::VOID]) && $invoice->returned_invoice_id) {
                $originalItem = InvoiceItem::where('invoice_id', $invoice->returned_invoice_id)
                    ->where('itemable_type', $itemableType)->where('itemable_id', $itemableId)->first();

                if ($originalItem) {
                    $invoiceItemData['cog_after'] = $originalItem->cog_after;
                }
            }

            $invoiceItem = InvoiceItem::updateOrCreate([
                'invoice_id' => $invoice->id,
                'itemable_id' => $itemableId,
                'itemable_type' => $itemableType,
            ], $invoiceItemData);

            $itemId[] = $invoiceItem->id;
        }

        CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, $itemId);

        app(ActivityLogService::class)->deleteModels($invoice->items()->whereNotIn('id', $itemId)->getQuery());
    }

    /**
     * Concurrency (Workstream S): two genuinely concurrent requests both
     * binding the same not-yet-approved Invoice before either commits would
     * both read "not approved" and both run approveInvoice() -- a second
     * phantom Document plus double-counted warehouse quantity/COG, the
     * exact disaster class the sequential idempotency guards below exist
     * to prevent, just reached through a different door (empirically
     * reproduced: two independently-fetched Invoice instances both calling
     * this method created two Documents for the same invoice). Fixed with
     * a row lock, re-reading the authoritative status into $invoice's OWN
     * attributes (via setRawAttributes(), not a separate instance) so
     * every match arm below sees fresh state -- and so callers that keep
     * mutating the SAME $invoice object afterward (e.g.
     * SyncPilotService::applyApprove()'s subsequent forceFill()->save() on
     * $existing) still see the changes this method made.
     */
    public function changeInvoiceStatus(Invoice $invoice, string $status): void
    {
        $statusChange = null;

        DB::transaction(function () use ($invoice, $status, &$statusChange) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->setRawAttributes($locked->getAttributes(), true);
            $previousStatus = $invoice->status;

            match ($status) {
                'ready_to_approve' => $invoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]),
                'rejected' => $this->userFacingRejectInvoice($invoice),
                'approved' => $this->userFacingApproveInvoice($invoice),
                'unapproved' => $this->unapproveInvoice($invoice),
                default => null,
            };

            if ($invoice->status !== $previousStatus) {
                $statusChange = $previousStatus;
            }
        });

        // P6 (API v1 Platform): fired only for a genuine transition, never
        // an idempotent no-op re-approve/re-reject, and only after the
        // transaction commits so listeners see committed state.
        if ($statusChange !== null) {
            event(new \App\Events\InvoiceStatusChanged($invoice->fresh(), $statusChange, $invoice->status));
        }
    }

    /**
     * D-086 boundary hardening: the user-facing (re-)approve action. An
     * already-Approved-or-settled invoice must never re-run
     * approveInvoice() -- doing so silently rebuilds a SECOND Document
     * (orphaning the first, double-counting revenue/COG in the ledger) and
     * double-adds warehouse quantities, with no error and no concurrency
     * required at all: a plain double-click/resubmit of the same "approve"
     * request is enough (empirically confirmed, Final Marathon Closure
     * Pass -- the original docblock's claim that
     * `InvoiceController::changeStatus()` already prevents reaching this
     * state was never actually true; that controller has no "already in
     * target status" check of its own).
     *
     * With real financial activity (Payment/PaymentAllocation), this is a
     * hard rejection (D-086's own invariant). With none yet, re-approving
     * an already-approved invoice is instead treated as a safe idempotent
     * no-op -- the exact same reasoning `SyncPilotService::applyApprove()`
     * already uses for a repeat approve whose caller's version matches
     * current reality (D-076 decision 6): "a safe idempotent no-op, never
     * a second posting." This is not new policy, it is finally applying
     * that same already-established principle to the web/service entry
     * point that was missing it.
     *
     * `approveInvoice()` itself stays unguarded, because
     * recycleInvoiceStatusForDependencyRecalculation() (D-087) and
     * voidInvoice() (a brand-new, never-before-approved Invoice row) both
     * legitimately call it directly and must not be blocked.
     */
    private function userFacingApproveInvoice(Invoice $invoice): void
    {
        if ($invoice->status->isApprovedOrSettled()) {
            if (self::hasEffectiveFinancialActivity($invoice)) {
                throw ValidationException::withMessages([
                    'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
                ]);
            }

            return;
        }

        // P7.1 (Approval Chains): the SECOND (final) approval step of a
        // two-step chain. Requires a DIFFERENT user than whoever performed
        // the first step, on top of the ordinary creator-cannot-approve
        // (P7.2) check -- both checked BEFORE any financial effect.
        if ($invoice->status->isPendingFinalApproval()) {
            if ($invoice->first_approved_by !== null && $invoice->first_approved_by === auth()->id()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('The final approval must be performed by a different user than the first approval.')],
                ]);
            }

            app(SegregationOfDutiesService::class)->guardAgainstSelfApproval($invoice, $invoice->creator_id, 'invoice');

            $this->approveInvoice($invoice);

            return;
        }

        app(SegregationOfDutiesService::class)->guardAgainstSelfApproval($invoice, $invoice->creator_id, 'invoice');

        // P7.1 (Approval Chains): only an invoice at/above the company's
        // configured threshold enters the two-step chain -- every invoice
        // below threshold (or when no threshold is configured, the
        // default) takes exactly the same single-step path as before this
        // feature existed.
        $threshold = app(WorkflowGovernanceSettingsService::class)->invoiceApprovalChainThreshold();
        if ($threshold !== null && (float) $invoice->amount >= $threshold) {
            $invoice->update(['status' => InvoiceStatus::PENDING_FINAL_APPROVAL, 'first_approved_by' => auth()->id()]);

            return;
        }

        $this->approveInvoice($invoice);
    }

    /**
     * D-086 boundary hardening (Phase 5 cross-module audit): marking an
     * invoice REJECTED once it has real financial activity is the same
     * "reversal by a different status label" D-086 already forbids for
     * update/re-approve -- the Document, Transactions, Payment(s) and
     * PaymentAllocation(s) all stay exactly as they are; only the label
     * changes, silently detaching the invoice's status from its own
     * ledger. `InvoiceController::changeStatus()` already blocks this
     * today via its own `isPartiallyPaid()/isPaid()` check before calling
     * changeInvoiceStatus(), but -- mirroring the exact reasoning behind
     * D-087/D-088 -- that must not be the only barrier: a future caller
     * (Sync, console command, another controller) must not be able to
     * reach `changeInvoiceStatus($invoice, 'rejected')` and bypass it with
     * a bare status flip. Mechanical, symmetrical extension of the
     * already-locked D-086 invariant to a transition it did not yet
     * cover -- not a new business decision.
     */
    private function userFacingRejectInvoice(Invoice $invoice): void
    {
        if (self::hasEffectiveFinancialActivity($invoice)) {
            throw ValidationException::withMessages([
                'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
            ]);
        }

        $invoice->update(['status' => InvoiceStatus::REJECTED]);
    }

    /**
     * Approve the given invoice: create document, update quantities and COG.
     */
    private function approveInvoice(Invoice $invoice): void
    {
        $invoice->status = InvoiceStatus::APPROVED;
        $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
        $invoice->document_id = $createdDocument->id;
        $invoice->update();
        ProductService::addProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        self::syncInvoiceItems($invoice, self::itemsFormatterForSyncingInvoiceItems($invoice));
        CostOfGoodsService::updateProductsAverageCost($invoice);
        ProductService::updateWarehouseAverageCosts($invoice);
        self::syncCOGAfterForInvoiceItems($invoice);
    }

    /**
     * D-086: once an Invoice has real financially effective activity against
     * it (a Payment, direct or cheque-linked, or a PaymentAllocation), the
     * updateInvoice() paths that could alter its financial content are
     * rejected -- mirroring D-084's Payment/Allocation immutability
     * principle one level up, at the Invoice's own effective amount. An
     * Approved invoice with NO payment/allocation yet is unaffected --
     * editing/correcting it before any money has moved is existing,
     * legitimate, heavily-tested bookkeeping behavior (see
     * COGSCalculationTest) that this Decision does not touch.
     */
    private static function hasEffectiveFinancialActivity(Invoice $invoice): bool
    {
        return $invoice->payments()->exists() || $invoice->paymentAllocations()->exists();
    }

    /**
     * D-087: the user-facing unapprove action. Rejects outright once the
     * Invoice has real financially effective activity (D-086) -- never
     * mutates status, Document, quantities, or COG in that case. This is
     * the ONLY path `changeInvoiceStatus('unapproved')` -- and therefore
     * `InvoiceController::changeStatus()` -- can ever reach.
     *
     * Also guards against re-running the reversal mechanics on an invoice
     * that is already not approved (Final Marathon Closure Pass finding,
     * the exact mirror image of userFacingApproveInvoice()'s double-post
     * bug): `cycleInvoiceStatusMechanics()` unconditionally calls
     * `ProductService::subProductsQuantities()`, with no check that a
     * Document even existed to reverse. A plain double-click/resubmit of
     * the same "unapprove" request against an already-unapproved invoice
     * silently subtracted warehouse quantity a second time, manufacturing
     * phantom inventory out of nothing -- empirically confirmed, no
     * concurrency required. Already-unapproved is now a safe idempotent
     * no-op, the same principle `userFacingApproveInvoice()` now uses.
     */
    private function unapproveInvoice(Invoice $invoice): void
    {
        if (! $invoice->status->isApprovedOrSettled()) {
            return;
        }

        if (self::hasEffectiveFinancialActivity($invoice)) {
            throw ValidationException::withMessages([
                'invoice' => [__('This invoice has posted payments or allocations and its financial content cannot be changed.')],
            ]);
        }

        $this->cycleInvoiceStatusMechanics($invoice, InvoiceStatus::UNAPPROVED);
    }

    /**
     * D-087: the actual unapprove mechanics (delete Document, revert
     * quantities/COG, unapprove ancillary costs) -- shared by the guarded
     * user-facing unapproveInvoice() above and the unguarded internal
     * dependency-recalculation primitive below. Never call this directly
     * from anywhere else; every other caller must go through one of those
     * two named entry points, never this one and never a boolean
     * bypass/force parameter.
     */
    private function cycleInvoiceStatusMechanics(Invoice $invoice, InvoiceStatus $status): void
    {
        $invoice->status = $status;

        if ($invoice->document) {
            DocumentService::deleteDocument($invoice->document_id);
            $invoice->document_id = null;
        }
        $invoice->update();
        self::unapproveAncillaryCostsOfInvoice($invoice);
        ProductService::subProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        CostOfGoodsService::updateProductsAverageCost($invoice);
        ProductService::updateWarehouseAverageCosts($invoice);
    }

    /**
     * D-087: explicit, separately-named internal primitive reserved for
     * GroupActionService::inactivateDependentInvoices()/
     * approveInactiveInvoices() -- the existing, trusted mechanism that
     * temporarily cycles a *downstream* invoice's status (Approved ->
     * unapprove -> APPROVED_INACTIVE -> reapprove) purely to keep COG/
     * average-cost chains consistent when an *earlier* Buy invoice
     * changes. This intentionally never applies the D-086 financial guard
     * -- unlike the user-facing unapproveInvoice(), never through a
     * bypass flag on that method, but because this is a structurally
     * separate method with its own name and its own, single, intended
     * caller.
     *
     * Provably non-financial in the sense D-086/D-087 protect: it never
     * touches the Invoice's own amount, vat, customer_id, or item rows,
     * and never creates/deletes/modifies a Payment or PaymentAllocation
     * row. It DOES delete and later recreate the Document row and
     * recompute warehouse/COG state -- an intrinsic, necessary part of
     * this dependency-recalculation mechanism (it is applied even to an
     * already-Paid invoice, whose Payment/allocation rows are never
     * touched by it) -- the Document's own row identity is not itself the
     * "financial truth" these Decisions protect; the invoice's owed/paid
     * amount and its Payment/Allocation history are, and neither changes
     * across this cycle.
     *
     * Never call this from a controller or any other user-facing action.
     */
    public function recycleInvoiceStatusForDependencyRecalculation(Invoice $invoice, string $status): void
    {
        DB::transaction(function () use ($invoice, $status) {
            match ($status) {
                'approved' => $this->approveInvoice($invoice),
                'unapproved' => $this->cycleInvoiceStatusMechanics($invoice, InvoiceStatus::UNAPPROVED),
                default => throw new \InvalidArgumentException("Unsupported status for dependency recalculation: {$status}"),
            };
        });
    }

    private function unapproveAncillaryCostsOfInvoice(Invoice $invoice): void
    {
        if (! $invoice->ancillaryCosts()->exists()) {
            return;
        }

        $AncillaryCostService = new AncillaryCostService;
        foreach ($invoice->ancillaryCosts as $ancillaryCost) {
            if (! $ancillaryCost->status->isApproved()) {
                continue;
            }
            $AncillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
        }
    }

    private static function createDocumentFromInvoiceItems(User $user, Invoice $invoice)
    {
        $invoiceData = [
            'date' => $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'number' => $invoice->number,
            'subtraction' => $invoice->subtraction ?? 0,
            'invoice_id' => $invoice->id ?? null,
            'returned_invoice_id' => $invoice->returned_invoice_id ?? null,
            'description' => $invoice->description ?? null,
            'title' => $invoice->title,
        ];

        if ($invoice->invoice_type->isVoid()) {
            $voidedInvoice = $invoice->relationLoaded('voidedInvoice') ?
                $invoice->voidedInvoice :
                $invoice->voidedInvoice()->with('document.transactions')->first();

            $buildResult['transactions'] = $voidedInvoice->document->transactions->map(fn ($transaction) => [
                'subject_id' => $transaction->subject_id,
                'desc' => __('For Void').' '.($transaction->desc ?? ''),
                'value' => -1 * $transaction->value,
            ])->all();
            $documentTitle = __('For Void').' '.($invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')));
        } else {
            $transactionBuilder = new InvoiceTransactionBuilder(self::itemsFormatterForSyncingInvoiceItems($invoice), $invoiceData);
            $buildResult = $transactionBuilder->build();
            $documentTitle = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));
        }

        $documentData = [
            'date' => $invoiceData['date'] ?? now()->toDateString(),
            'title' => $documentTitle,
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        $document = DocumentService::createDocument($user, $documentData, $buildResult['transactions']);
        DocumentService::changeDocumentStatus($document, $user, 'approved');
        DocumentService::syncDocumentable($document, $invoice);

        return $document;
    }

    private static function itemsFormatterForSyncingInvoiceItems(Invoice $invoice): array
    {
        return $invoice->items
            ->map(fn ($t, $i) => [
                'transaction_index' => $i,
                'itemable_id' => $t->itemable_id,
                'itemable_type' => $t->itemable_type === Product::class ? 'product' : 'service',
                'quantity' => $t->quantity,
                'description' => $t->description,
                'unit_discount' => $t->unit_discount,
                'vat' => $t->vat,
                'vat_is_value' => true,
                'unit' => $t->unit_price,
                'total' => $t->amount,
                'warehouse_id' => $invoice->warehouse_id,
            ])
            ->values()
            ->all();
    }

    public static function getChangeStatusValidation(Invoice $invoice): InvoiceStatusDecision
    {

        $productIds = self::getProductIdsFromInvoice($invoice);

        $nextStatus = $invoice->status->isApprovedOrSettled()
            ? InvoiceStatus::UNAPPROVED
            : InvoiceStatus::APPROVED;

        return self::decideInvoiceStatusChange($invoice, $productIds, $nextStatus);

    }

    private static function getProductIdsFromInvoice(Invoice $invoice): array
    {
        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        return $productIds;
    }

    /**
     * Validate that related invoices have the correct status before changing an invoice's status.
     */
    private static function enforceInvoiceStatusRules(Invoice $invoice, array $productIds, InvoiceStatus $nextStatus): Collection
    {
        $conflicts = collect();

        // Check for prior unapproved (BUY or SELL) invoices (soft rule - warning)
        if ($nextStatus->isApproved()) {
            $prior = self::findConflictingUnapprovedInvoices($invoice, $productIds);

            if ($prior->isNotEmpty()) {
                $conflicts->push([
                    'rule' => 'prior_unapproved_buy',
                    'invoices' => $prior,
                ]);
            }
        }

        // Check for subsequent approved invoices (BUY or SELL) (hard rule - error)
        $subsequent = self::findConflictingInvoices($invoice, $productIds, $nextStatus);

        if ($subsequent->isNotEmpty()) {
            $conflicts->push([
                'rule' => 'subsequent_approved',
                'invoices' => $subsequent,
            ]);
        }

        return $conflicts;
    }

    /**
     * Validate there are no related invoices that block changing the given invoice's status.
     *
     * @param  Invoice  $invoice  The invoice being checked (excluded from search)
     * @param  array<int>  $productIds  Product IDs to search for in related invoices
     * @param  array  $invoiceTypes  Invoice types to include in the search (e.g. BUY, SELL)
     * @param  InvoiceStatus  $nextStatus  Status value to change to
     *
     * @throws Exception If one or more related invoices are found that would prevent the status change
     */
    private static function findConflictingInvoices(Invoice $invoice, array $productIds, InvoiceStatus $nextStatus, ?array $invoiceTypes = []): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where(function ($q) use ($invoice) {
                $q->where('date', '>', $invoice->date)
                    ->orWhere(function ($sub) use ($invoice) {
                        $sub->where('date', $invoice->date)
                            ->where('number', '>', $invoice->number);
                    });
            });
        if (! empty($invoiceTypes)) {
            $query->whereIn('invoice_type', $invoiceTypes);
        }

        if (in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true) && $invoice->returned_invoice_id) {
            $query->where('id', '!=', $invoice->returned_invoice_id);
        }

        $query->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
            ->whereIn('itemable_id', $productIds)
        )
            ->whereIn('status', InvoiceStatus::approvedOrSettled());

        // Return collection of invoices (no exception here)
        return $query->get(['id', 'invoice_type', 'number', 'date']);
    }

    private static function findConflictingUnapprovedInvoices(Invoice $invoice, array $productIds, ?array $invoiceTypes = []): Collection
    {

        if (empty($productIds)) {
            return collect();
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where(function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)
                    ->orWhere(function ($sub) use ($invoice) {
                        $sub->where('date', $invoice->date)
                            ->where('number', '<', $invoice->number);
                    });
            });
        if (! empty($invoiceTypes)) {
            $query->whereIn('invoice_type', $invoiceTypes);
        }
        $query->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
            ->whereIn('itemable_id', $productIds)
        )
            ->whereNotIn('status', InvoiceStatus::approvedOrSettled());

        // Return collection of invoices (no exception here)
        return $query->get(['id', 'invoice_type', 'number', 'date']);
    }

    /**
     * Decide upon the invoice status change by aggregating rules and messages
     * Returns an InvoiceStatusDecision DTO with messages and conflicts
     */
    public static function decideInvoiceStatusChange(Invoice $invoice, array $productIds, $nextStatus): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        self::checkReturnInvoiceRelationForStatusChange($invoice, $nextStatus, $decision);

        if (in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY], true) && $nextStatus->isApproved()) {
            self::checkProductsQuantityForStatusChange($invoice, $productIds, $decision);
        }

        $conflictGroups = self::enforceInvoiceStatusRules($invoice, $productIds, $nextStatus);

        foreach ($conflictGroups as $group) {
            $rule = $group['rule'];
            $invoices = $group['invoices'];

            $invoiceList = $invoices->map(fn ($inv) => $inv->invoice_type->valueName().': '.$inv->number)->implode(', ');

            if ($rule === 'subsequent_approved') {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_subsequent', ['invoices' => $invoiceList]));
                $invoices->each(fn ($i) => $decision->addConflict($i));
            }

            if ($rule === 'prior_unapproved_buy') {
                $decision->addMessage('warning', __('invoices.status_change.prior_unapproved', ['invoices' => $invoiceList]));
                $invoices->each(fn ($i) => $decision->addConflict($i));
            }
        }

        if ($nextStatus->isApproved()) {
            self::checkAncillaryCostsForStatusChange($invoice, $decision);
        }

        return $decision;
    }

    private static function checkReturnInvoiceRelationForStatusChange(Invoice $invoice, InvoiceStatus $nextStatus, InvoiceStatusDecision $decision): void
    {
        if (in_array($invoice->invoice_type, [InvoiceType::BUY, InvoiceType::SELL], true) && $nextStatus->isUnapproved()) {
            $returnInvoices = $invoice->getReturnInvoice();
            foreach ($returnInvoices as $returnInvoice) {
                if ($returnInvoice) {
                    $decision->addMessage('error', __('invoices.status_change.blocked_by_return_invoice', [
                        'invoice' => $returnInvoice->number,
                    ]));
                    $decision->addConflict($returnInvoice);
                }
            }

            $voidInvoice = $invoice->voidInvoice;
            if ($voidInvoice) {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_return_invoice', [
                    'invoice' => $voidInvoice->number,
                ]));
                $decision->addConflict($voidInvoice);
            }

            return;
        }

        if (! in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
            return;
        }

        $originalInvoice = $invoice->getReturnedInvoice();
        if (! $originalInvoice) {
            $decision->addMessage('warning', __('Returned invoice is not linked to any original invoice (:invoice)', [
                'invoice' => $invoice->number,
            ]));

            return;
        }

        $returnedInvoices = Invoice::where('returned_invoice_id', $invoice->id)->whereNot('id', $invoice->id)->with('items')->get();
        $sumLastReturnedInvoiceItems = 0;
        foreach ($originalInvoice->first()->items as $item) {
            $returnedInvoiceItem = null;
            foreach ($returnedInvoices as $returnedInvoice) {
                $returnedInvoiceItem = $returnedInvoice->items()
                    ->where('itemable_type', $item->itemable_type)
                    ->where('itemable_id', $item->itemable_id)
                    ->first();

                $sumReturnedInvoiceItem += $returnedInvoiceItem?->quantity ?? 0;
            }
            if ($item->quantity < $returnedInvoiceItem?->quantity + $sumLastReturnedInvoiceItems) {
                $decision->addMessage('error', __('The addition quantity for this item and its last returned invoice items cannot exceed the original invoice quantity of :quantity.', ['quantity' => $item->quantity]));
                $decision->addConflict($originalInvoice);
            }
        }

        $originalDateIsAfterReturnDate = $originalInvoice->date !== null && $invoice->date !== null
            ? strtotime((string) $originalInvoice->date) > strtotime((string) $invoice->date) : false;

        if ($originalDateIsAfterReturnDate) {
            $decision->addMessage('error', __('Original invoice (:invoice) date is after return invoice date. Return invoice cannot be before its original invoice.', [
                'invoice' => $originalInvoice->number,
            ]));
            $decision->addConflict($originalInvoice);

            return;
        }

        if ($invoice->invoice_type->isVoid()) {
            $decision->addMessage('warning', __('Void invoice is linked to original invoice (:invoice)', [
                'invoice' => $originalInvoice->number,
            ]));
            $decision->addConflict($originalInvoice);

            if (! $originalInvoice->status->isApprovedOrSettled()) {
                $decision->addMessage('error', __('Voided invoice is not approved', [
                    'invoice' => $originalInvoice->number,
                ]));
            }

            return;
        }

        $decision->addMessage('warning', __('Return invoice is linked to original invoice (:invoice)', [
            'invoice' => $originalInvoice->number,
        ]));
        $decision->addConflict($originalInvoice);
    }

    public static function getChangeStatusDecision(Invoice $invoice, $nextStatus): InvoiceStatusDecision
    {
        $productIds = self::getProductIdsFromInvoice($invoice);

        $nextStatus = $nextStatus instanceof InvoiceStatus ? $nextStatus : InvoiceStatus::fromName($nextStatus);

        return self::decideInvoiceStatusChange($invoice, $productIds, $nextStatus);
    }

    public static function notAllowedInvoiceForAncillaryCosts(Invoice $invoice, array $productIds): array
    {
        if (! $invoice->status->isApprovedOrSettled()) {
            return [];
        }

        $invoices = Invoice::where('date', '>=', $invoice->date)
            ->whereHas('items', function ($query) use ($productIds) {
                $query->where('itemable_type', Product::class)
                    ->whereIn('itemable_id', $productIds);
            })->get();

        return $invoices->filter(fn ($inv) => ! self::getChangeStatusValidation($inv)->canProceed)->values()->all();
    }

    public static function getUnapprovedSellPriorInvoices(array $productIds, string $date, int $invoiceNumber, ?Invoice $excludeInvoice = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = Invoice::whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_BUY])
            ->whereNotIn('status', InvoiceStatus::approvedOrSettled())
            ->where(function ($q) use ($date, $invoiceNumber) {
                $q->where('date', '<', $date)
                    ->orWhere(function ($sub) use ($date, $invoiceNumber) {
                        $sub->where('date', $date)
                            ->where('number', '<', $invoiceNumber);
                    });
            })
            ->whereHas('items', function ($query) use ($productIds) {
                $query->whereIn('itemable_id', $productIds)
                    ->where('itemable_type', Product::class);
            });

        if ($excludeInvoice) {
            $query->where('id', '!=', $excludeInvoice->id);
        }

        return $query->get(['id', 'invoice_type', 'number'])->toArray();
    }

    private static function checkProductsQuantityForStatusChange(Invoice $invoice, array $productIds, InvoiceStatusDecision $decision): void
    {
        $conflictGroups = self::validateProductsQuantityForStatusChange($invoice, $productIds);
        $conflictsGroupedByRule = $conflictGroups->groupBy('rule');

        if ($conflictsGroupedByRule->has('oversell_allowed')) {
            $productsList = $conflictsGroupedByRule->get('oversell_allowed');

            $decision->addMessage('warning', __('product_oversell_allowed'));
            foreach ($productsList as $product) {
                $decision->addConflict($product['product']);
            }
        }

        if ($conflictsGroupedByRule->has('insufficient_quantity')) {
            $productsList = $conflictsGroupedByRule->get('insufficient_quantity');

            $decision->addMessage('error', __('insufficient_quantity_for_product'));
            foreach ($productsList as $product) {
                $productsList->each(fn ($product) => $decision->addConflict($product['product']));
            }
        }
    }

    private static function validateProductsQuantityForStatusChange(Invoice $invoice, array $productIds): Collection
    {
        $errors = collect();

        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            if ($invoice->status->isApprovedOrSettled()) {
                continue;
            }

            $invoiceItem = $invoice->items->firstWhere('itemable_id', $product->id);

            if (! $invoiceItem) {
                continue;
            }

            $requiredQuantity = $invoiceItem->quantity;
            $availableQuantity = (float) WarehouseProductStock::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $invoiceItem->invoice->warehouse_id)
                ->value('quantity');

            if ($availableQuantity < $requiredQuantity) {
                if ($product->oversell) {
                    $errors->push([
                        'rule' => 'oversell_allowed',
                        'product' => $product,
                    ]);
                } else {
                    $errors->push([
                        'rule' => 'insufficient_quantity',
                        'product' => $product,
                        'required' => $requiredQuantity,
                    ]);
                }
            }
        }

        return $errors;
    }

    private static function checkAncillaryCostsForStatusChange(Invoice $invoice, InvoiceStatusDecision $decision): void
    {
        $conflictsAncillaryCosts = self::enforceAncillaryCostsStatusRules($invoice);
        foreach ($conflictsAncillaryCosts as $group) {
            $rule = $group['rule'];
            $ancillaryCosts = $group['ancillary_costs'];

            $ancillaryCostList = $ancillaryCosts->map(fn ($ac) => 'ID '.$ac->id.' (Invoice ID: '.$ac->invoice_id.', Status: '.$ac->status->valueName().')')->implode(', ');

            if ($rule === 'subsequent_ancillary_cost_approved') {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_subsequent_ancillary_cost_approved', ['ancillary_costs' => $ancillaryCostList]));
                $ancillaryCosts->each(fn ($ac) => $decision->addConflict($ac));
            }

            if ($rule === 'prior_ancillary_cost_unapproved') {
                $decision->addMessage('warning', __('invoices.status_change.prior_ancillary_cost_unapproved', ['ancillary_costs' => $ancillaryCostList]));
                $ancillaryCosts->each(fn ($ac) => $decision->addConflict($ac));
            }
        }
    }

    private static function enforceAncillaryCostsStatusRules(Invoice $invoice): Collection
    {
        $errors = collect();
        $productIds = self::getProductIdsFromInvoice($invoice);

        if ($invoice->ancillaryCosts->isEmpty()) {
            $ancillaryCosts = self::findConflictingUnapprovedAncillaryCostsForInvoiceWithNoAncillaryCosts($invoice, $productIds);
            if ($ancillaryCosts->isNotEmpty()) {
                $errors->push([
                    'rule' => 'prior_ancillary_cost_unapproved',
                    'ancillary_costs' => $ancillaryCosts,
                ]);

                return $errors;
            }
        }

        $prior = self::findConflictingUnapprovedAncillaryCosts($invoice, $productIds);
        if ($prior->isNotEmpty()) {
            $errors->push([
                'rule' => 'prior_ancillary_cost_unapproved',
                'ancillary_costs' => $prior,
            ]);
        }

        $subsequent = self::findConflictingApprovedAncillaryCosts($invoice, $productIds);
        if ($subsequent->isNotEmpty()) {
            $errors->push([
                'rule' => 'subsequent_ancillary_cost_approved',
                'ancillary_costs' => $subsequent,
            ]);
        }

        return $errors;
    }

    private static function findConflictingUnapprovedAncillaryCostsForInvoiceWithNoAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return AncillaryCost::where('status', '!=', InvoiceStatus::APPROVED)
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '<', $invoice->number);
                });
            })->get();
    }

    private static function findConflictingUnapprovedAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return AncillaryCost::where('status', '!=', InvoiceStatus::APPROVED)
            ->whereNotIn('id', $invoice->ancillaryCosts->pluck('id')->toArray())
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '<', $invoice->number);
                });
            })->get(['id', 'invoice_id', 'status', 'type']);
    }

    private static function findConflictingApprovedAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return AncillaryCost::where('status', InvoiceStatus::APPROVED)
            ->whereNotIn('id', $invoice->ancillaryCosts->pluck('id')->toArray())
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '>', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '>', $invoice->number);
                });
            })->get(['id', 'invoice_id', 'status', 'type']);
    }

    // ================================
    // Form Data Transformation Methods
    // ================================

    /**
     * Extract invoice data from validated form input
     */
    public static function extractInvoiceData(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'date' => $validated['date'],
            'invoice_type' => InvoiceType::fromName($validated['invoice_type']),
            'customer_id' => $validated['customer_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'returned_invoice_id' => $validated['returned_invoice_id'] ?? null,
            'document_number' => $validated['document_number'],
            'number' => $validated['invoice_number'],
            'subtraction' => $validated['subtractions'] ?? 0,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * Map form transactions to invoice items array
     */
    public static function mapTransactionsToItems(array $transactions, bool $vatIsValue = false): array
    {
        return collect($transactions)->map(fn ($t, $i) => [
            'transaction_index' => $i,
            'itemable_id' => $t['item_id'],
            'itemable_type' => $t['item_type'],
            'quantity' => $t['quantity'] ?? 1,
            'description' => $t['desc'] ?? null,
            'unit_discount' => $t['unit_discount'] ?? 0,
            'vat' => $t['vat'] ?? 0,
            'vat_is_value' => $vatIsValue,
            'unit' => $t['unit'] ?? 0,
            'total' => $t['total'] ?? 0,
            'warehouse_id' => null,
        ])->toArray();
    }

    /**
     * Prepare transactions for view (create/edit form)
     */
    public static function prepareTransactions(?Invoice $source = null, string $mode = 'create'): Collection
    {
        if (old('transactions')) {
            return self::prepareFromOldInput();
        }

        if ($mode === 'edit' && $source instanceof Invoice) {
            return self::prepareFromInvoice($source);
        }

        return self::getEmptyTransaction();
    }

    /**
     * Restore transactions from old form input
     */
    private static function prepareFromOldInput(): Collection
    {
        return collect(old('transactions'))->map(function ($transaction, $index) {
            $transaction['id'] = $index + 1;

            if (empty($transaction['item_type']) || empty($transaction['item_id'])) {
                return $transaction;
            }

            $isProduct = $transaction['item_type'] === Product::class;
            $model = $isProduct
                ? Product::find($transaction['item_id'])
                : Service::find($transaction['item_id']);

            $transaction['subject'] = $model?->name;
            $transaction[$isProduct ? 'product_id' : 'service_id'] = $model?->id;
            $transaction['quantity'] ??= 1;

            return $transaction;
        });
    }

    /**
     * Prepare transactions from existing invoice for edit form
     */
    private static function prepareFromInvoice(Invoice $invoice): Collection
    {
        return $invoice->items->map(function ($item, $index) {
            $isProduct = isset($item->itemable->inventory_subject_id);

            return [
                'id' => $index + 1,
                'transaction_id' => $item->transaction_id,
                'desc' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'off' => $item->unit_discount,
                'vat' => $item->vat,
                'total' => $item->amount,
                'inventory_subject_id' => $item->itemable->inventory_subject_id ?? $item->itemable->subject_id ?? null,
                'subject' => $item->itemable->name ?? null,
                'product_id' => $isProduct ? $item->itemable->id : null,
                'service_id' => $isProduct ? null : $item->itemable->id,
            ];
        });
    }

    /**
     * Get empty transaction structure for new invoice form
     */
    public static function getEmptyTransaction(): Collection
    {
        return collect([[
            'id' => 1,
            'transaction_id' => null,
            'inventory_subject_id' => null,
            'subject' => null,
            'desc' => null,
            'quantity' => 1,
            'unit' => null,
            'off' => null,
            'vat' => null,
            'total' => null,
            'product_id' => null,
            'service_id' => null,
        ]]);
    }

    public function validateVoidingInvoice(Invoice $invoice, ?string $date = null): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        if ($invoice->voidInvoice()->exists()) {
            $decision->addMessage('error', __('Invoice has voided already.'));
        }

        if (! $invoice->status->isApprovedOrSettled()) {
            $decision->addMessage('error', __('Invoice must be approved before voiding.'));
        }

        if ($invoice->invoice_type !== InvoiceType::SELL) {
            $decision->addMessage('error', __('Only sell invoices are eligible for voiding.'));
        }

        if ($invoice->getReturnInvoice()->isNotEmpty()) {
            $decision->addMessage('error', __('Only sales invoices that have not been returned are eligible for voiding.'));
        }

        if ($date !== null) {
            if (strtotime($date) < strtotime($invoice->date)) {
                $decision->addMessage('error', __('Void invoice date cannot be earlier than the invoice date.'));
            }
        }

        return $decision;
    }

    public function voidInvoice(Invoice $invoice, User $user, string $date, float $number): array
    {
        $voidInvoice = null;

        DB::transaction(function () use ($invoice, $user, $date, $number, &$voidInvoice) {
            $voidInvoice = new Invoice;
            $voidInvoice->fill(collect($invoice)->except(['id', 'date', 'creator_id', 'document_id', 'number', 'status'])->toArray());
            $voidInvoice->status = InvoiceStatus::PENDING;
            $voidInvoice->creator_id = $user->id;
            $voidInvoice->date = $date;
            $voidInvoice->number = $number;
            $voidInvoice->invoice_type = InvoiceType::VOID;
            $voidInvoice->returned_invoice_id = $invoice->id; // Like a return sell invoice
            $voidInvoice->save();

            $voidInvoice->items()->createMany(
                $invoice->items->map(fn ($item) => [
                    'itemable_type' => $item->itemable_type,
                    'itemable_id' => $item->itemable_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_discount' => $item->unit_discount,
                    'cog_after' => $item->itemable_type === Product::class ? $item->cog_after : 0.0,
                    'quantity_at' => $item->itemable_type === Product::class ? $item->itemable->quantity : 0,
                    'price' => $item->price,
                    'vat' => $item->vat,
                    'amount' => $item->amount,
                    'description' => $item->description,
                ])->toArray()
            );

            $this->approveInvoice($voidInvoice);
        });

        return [
            'invoice' => $voidInvoice,
            'document' => $voidInvoice?->document,
        ];
    }
}
