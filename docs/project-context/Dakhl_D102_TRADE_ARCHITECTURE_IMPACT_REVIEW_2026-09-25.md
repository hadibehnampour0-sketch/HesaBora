# D-102 Trade Architecture Impact Review (r1, 2026-09-25)

> **Status: DOCUMENTATION-ONLY review of current code vs. the provisional D-101 proposal. Not a decision, no implementation authorized (D-099 §12, Guardrail #5).**
> Method: read-only code investigation of the repo at HEAD (facts with file refs; no tests run, no runtime checks). Line numbers are as read on 2026-09-25 and can drift.
> Companion: `Dakhl_D101_TRADE_DATA_MODEL_PROPOSAL_PROVISIONAL_2026-09-25.md`. D-099 stays PROPOSED.

## 1. Verdict in one paragraph
The existing financial core can stay untouched **if** Trade is built as a separate aggregate that *requests* accounting/stock effects through the existing invoice-approval path (or a deliberately new, reviewed writer). The current code has **hard mismatches for native multi-unit quantities**: no unit-of-measure anywhere, 2-dp quantity and money columns, a float `products.quantity` alongside decimal warehouse stock, and no standalone stock-movement writer. Any Trade implementation must first decide *how* physical quantity changes flow (see §4 decision points), because the code offers only the invoice route.

## 2. Findings by area (facts) and impact
| Area | Fact in code | Impact on D-101 |
|---|---|---|
| **Stock storage** | `products.quantity` is `float`; per-warehouse `warehouse_product_stocks.quantity` decimal(18,2) and `average_cost` decimal(18,2); `warehouse_transfers` 18,2. No stock ledger/movement table (a migration named "movement_logs" creates only reservations/receipts/notes). | **Blocker-class mismatch** for grams at 3 dp and per-gram cost; dual truth (float vs decimal). |
| **Stock writers** | Only `ProductService::addProductsQuantities/subProductsQuantities` (via `adjustForInvoice`), `recalculateQuantity` (rebuilds from approved invoices), `WarehouseService::transfer`; row locks via `lockForUpdate`. Stock moves **only** through invoice approval (`InvoiceService::changeInvoiceStatus`). | Trade cannot move stock without either producing invoices or adding a new writer. D-099 forbids "Inventory Accounting Rewrite" — a new writer is a scoped, reviewed exception, not a rewrite. |
| **Average cost** | `CostOfGoodsService::updateProductsAverageCost`; `ProductService::updateWarehouseAverageCosts` (weighted average). 2 dp. | Rial-per-gram avg cost at 2 dp loses precision; gold cost model (weighted purity-normalized?) is a new question. |
| **Negative stock** | Guarded unless `product->oversell` (ProductService, StoreInvoiceRequest). | Open accounts where the dealer temporarily holds negative position must not be blocked by stock guard — Trade position lives outside stock. |
| **Units** | No unit/UOM field on Product/InvoiceItem; only `unit_price`, `unit_discount`. | Units must be introduced (as Product attribute/registry) or grams/coins modelled as separate products. **Design decision needed.** |
| **Lots / serials / reservations** | `ProductLot` (decimal 18,4, unique company+product+lot_number, no invoice link), `ProductSerialNumber` (status in_stock/sold, nullable `invoice_id`, unique per company), `StockReservation` (18,4, morph source). All additive/informational — nothing in invoice/stock flow reads or gates on them. | Good news: 4-dp scale exists; lots/serials are candidate carriers of assay/ingot identity without touching core flow. But they carry no purity/attribute data and no link to invoice items. |
| **DeliveryNote / GoodsReceipt** | One-per-invoice rows (`invoice_id` unique); `InvoiceFulfillmentService` creates rows only; no stock or accounting effect. | Not usable as Trade movement records; Trade needs its own movement/handover records. |
| **Customer** | `customers.subject_id` nullable; `CustomerService::syncSubject`; `InvoiceTransactionBuilder::buildCustomerTransaction` posts BUY (+) and SELL (−) to the same subject → one netted balance. No role field (`type` is a tax classification enum; old buyer/seller booleans dropped; no `is_supplier`); `group_id` exists; stored `customers.balance` plus computed balance from Transactions. | Confirms D-099 §9: Customer + metadata works for dealer accounts. Role/relationship metadata is genuinely absent → an additive table/columns is needed. Careful: Customer→Subject redesign is locked out of sync stages (AGENTS.md). |
| **Settlement** | `settlement_obligations` (`source_type/id`, unique on source), `payment_allocations` (`allocatable_type/id`), `settlement_installments`. Sources today: Cheque, Invoice. Allocatables: Invoice, SettlementInstallment. No morph map (full class names stored). `SettlementService::createObligation` typed `Cheque|Invoice`; `resolveCustomer` throws for other sources; `PaymentService::allocate` is generic. Only a real `Payment` allocation may create an accounting effect (D-070). | Trade can become a new source **but** requires adding a `createObligationForTrade` method and a `resolveCustomer` branch — a small change *inside* the settlement service (D-070 shape). Amounts are Rial 18,2 — in-kind settlements cannot be expressed as obligations today. |
| **Invoice / InvoiceItem** | `itemable` morph Product|Service only; `quantity` decimal(10,2); `unit_price/unit_discount/amount` decimal(18,2) **NOT NULL**; `vat` amount decimal(16,2); unique (invoice, itemable_id, itemable_type); no JSON/attributes/unit/wage columns. Transaction rounding 2 dp. Types BUY, SELL, RETURN_BUY, RETURN_SELL, VOID. Void via `InvoiceService::voidInvoice` (VOID invoice linked by `returned_invoice_id`); hard delete blocked when payments/allocations exist. | Retail gold invoices (raw gold/wage/profit/VAT lines) do not fit InvoiceItem today; a DEFERRED-value line cannot be stored (NOT NULL price). Trade-In as purchase+sale in one invoice is not possible (one type per invoice). Same product cannot repeat in an invoice. |
| **Tax / Moadian** | `tax_rules` (sstid, rate 5,2, exempt, effective dates) and `TaxRuleService` exist but appear consumed only by `TaxReadinessService`; invoice VAT comes from per-item `vat` percent passed into the builder / `products.vat` default. Moadian mapping `MoadianService::moadianInvoiceItems` maps sstid/name/quantity/price/discount/vat; `validateSendMoadian` requires sstid per item. **No gold/jewelry/purity/wage handling anywhere.** | Dynamic VAT-by-rule is not wired into invoicing → D-101's "rate from TaxRule" needs a wiring decision. Gold Moadian pattern is entirely new work and depends on the official guide (unverified fields). |
| **Sync** | `SyncPilotService::SYNCABLE_ENTITIES` (Customer, Product, Document, Invoice, Payment, PaymentAllocation); new entity needs `uuid` + `HasSyncEnvelope` + outbox trait + registry entry; apply-time exclusion consts exist for Invoice/Document. AGENTS.md: Trade out of scope for sync stage 2.8E; D-071 requires UUIDs for scoped entities. | Trade must be UUID-ready from day 1 (cheap) but need not sync; aggregate-level conflict semantics stay a separate later design (D-074). |
| **Reports / permissions** | `ReportExportService::EXPORTS` (permission = route name) + `match` arm; `CheckPermission` uses route name (`prefix.*` wildcard); `RolesAndPermissionsSeeder` groups; permission-adding migrations exist. | Standard, low-risk: new permission group `trade-*` in seeder + migration for upgrades (Guardrail #55: switch team context before granting). |
| **Test env** | SQLite in phpunit + `SqliteSchemaHelper`; migrations must work on both drivers. | Any decimal-scale change migration needs SQLite-safe path (see D-096 upgrade-path lesson). |

## 3. Incidental finding (not Trade)
`MoadianService::moadianInvoiceItems` reads `$item->discount`, which is not an InvoiceItem attribute (only `unit_discount`), so `adis`/`vra` may ignore discounts, and `dis` mixes per-unit vs total semantics. Spun off as a separate follow-up task; **not** part of this review.

## 4. Decision points the review surfaces (owner decides; none assumed)
1. **Physical movement path.** (a) Trade generates ordinary invoices/receipts and rides the existing approval path; (b) new narrowly-scoped stock-movement writer in `ProductService` for Trade legs; (c) Trade does not move stock in phase 1 (position only), reconciled later. Trade-off: (a) forces invoice shape onto in-kind deals; (b) touches the most sensitive service; (c) leaves two truths but is safest to start.
2. **Units and precision.** Introduce a unit registry + wider quantity scale (e.g. 4 dp) *only for Trade quantities* vs changing stock/invoice columns globally. Recommendation for consideration: keep Trade's own quantity columns at ≥4 dp and defer global stock changes; avoid touching `products.quantity`/invoice decimals now [D].
3. **Item identity carrier.** Reuse `ProductLot`/`ProductSerialNumber` (needs attribute support they lack) vs Trade-owned item-instance table. Both additive; lot/serial reuse risks coupling to features that other flows may later gate on.
4. **In-kind settlement.** `settlement_obligations` are Rial-only; in-kind offsets belong inside Trade (events), and only the *residual Rial* becomes a `SettlementObligation`. Confirms D-099 "Trade is not Ledger".
5. **Retail gold invoicing.** Whether to extend InvoiceItem (nullable price / attributes / wage lines) or introduce a gold-invoice presentation layer outside InvoiceItem; depends on Moadian gold pattern (unverified).
6. **Tax wiring.** Connect `TaxRuleService` to invoicing, or keep per-item VAT percent input for now.
7. **Party metadata.** Additive table keyed by customer, without touching Customer→Subject.
8. **UUID/sync-readiness.** Trade tables get `uuid` from the start (D-071) but no sync registration.

## 5. Risk register (Trade-specific)
| Risk | Severity | Mitigation |
|---|---|---|
| Two quantity truths (Trade position vs stock) diverge | High | Explicit reconciliation view; single owner per quantity; decision point 1 |
| Precision loss (2 dp) corrupts gram balances | High | Trade-owned ≥4 dp columns; no reuse of 2-dp columns for native quantity |
| Float `products.quantity` drift | Medium | Never read it for Trade; existing issue, note only |
| Invoice shape forced onto Trade | Medium | Keep Trade separate; produce invoices only for the Rial/tax outputs |
| Settlement service changes ripple (D-070) | Medium | Add source type only; characterization tests around `createObligation`/`resolveCustomer` first |
| Moadian gold pattern unknown | Medium | Do not promise gold e-invoicing until official guide verified |
| Sync scope creep | Low | UUID only; no registration |
| SQLite/MySQL migration divergence | Medium | SqliteSchemaHelper + upgrade-path tests (D-096) |

## 6. Pre-implementation gates (proposed, for the owner's Implementation Decision)
1. Real-dealer verification of D-101 [?] items; D-099 → ACCEPTED only if validated.
2. Owner picks options for decision points 1–3 minimum.
3. Guardrail #5 amended in AGENTS.md at that time (and only then).
4. A characterization-test plan for every existing service that would be touched (`SettlementService`, `ProductService` if option b).
5. Phased slice: first vertical slice = one scenario (e.g. S2 dealer account, position-only, no stock movement, no Moadian) to test the aggregate before anything else.
