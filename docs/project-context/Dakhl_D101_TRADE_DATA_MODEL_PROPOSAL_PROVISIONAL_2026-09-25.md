# D-101 Trade Data Model Proposal — PROVISIONAL (r1, 2026-09-25)

> **Status: CONCEPTUAL PROPOSAL, provisional, evidence-tagged. Not a decision. Not a schema.**
> No migration, model, service, route or sync work is authorized (D-099 §12, Guardrail #5). Names below are *conceptual*; column lists are illustrative, not DDL.
> Depends on D-099 (**PROPOSED**, must stay PROPOSED until real-dealer evidence exists). Inputs: desk report (`…D100_DESK_DISCOVERY_REPORT…`), protocol §10–§11 (simulated material = hypotheses only).
> Evidence tags: **[V]** verified/partial-verified fact · **[H]** hypothesis from simulated material · **[D]** design choice (engineering, not market-derived) · **[?]** open, needs real dealers.
> The D-101 decision text, if any, is authored by the owner.

## 1. Goals and non-goals
Goals: express open-account, in-kind, trade-in, consignment and price-later dealings with native quantities, without touching the Rial ledger until valuation/settlement. Non-goals: gold-specific tables [D], stock-core changes (D-099 §6), tax calculation, payment execution, market-data ownership, sync design, UI.

## 2. Conceptual entities

### 2.1 TradeExchange (aggregate root) [D per D-099]
company-scoped (`company_id ??= getActiveCompany()`, FiscalYearScope). Illustrative attributes: counterparty (→ existing Customer/`subject_id`), `lifecycle` (INTENT…CLOSED), `status` (OPEN…DISPUTED) as two independent axes (D-099 §5), occurred_at, agreed_at, reference (external/verbal ref, e.g. message text or slip no. [H]), notes, created_by, audit trail.
Invariants: legs are append-only once EXCHANGE is reached — corrections are new *adjustment* records, never edits [H, matches audit norm]; position is derived, never stored as truth.

### 2.2 TradeLeg
direction OUT|IN; `item` (→ Product); `quantity` + `unit` (native; gold in grams, 3 dp [V/H]; coins "each"); optional `item_instance` reference (§2.3); `ownership_effect` (TRANSFERRED | RETAINED_BY_GIVER) → Trade Ownership Layer (§2.5).
Attribute bag per leg/instance for domain properties (purity, assay/ingot no., wage basis…) — **key/value attributes with a small typed registry per Product group, not per-domain tables** [D per D-099 §10].

### 2.3 ItemInstanceAttributes (optional, per identifiable piece)
Identity attributes for pieces that are tracked individually: assay/ingot number, laboratory, declared purity, physical weight [V: assay no. is real for آبشده]. Scope of per-piece tracking (which groups need it) [?]. Relation to existing `ProductLot` / `ProductSerialNumber` must be assessed in D-102 (reuse vs. reference) — no new stock table proposed here.

### 2.4 ValuationRecord
attached to a leg or a leg group; `basis` AGREED | MARKET_SNAPSHOT | DEFERRED (D-099 §7); value + currency/unit explicit (rial vs toman — always stored) ; `source` and `timestamp` (for snapshots, from Industry Intelligence `MarketQuote`, advisory only); `superseded_by` (a valuation is never overwritten). 
**Resolution of DEFERRED = a new ValuationRecord ("valuation event")** carrying the fixed rate and the quantity it applies to (partial fixing allowed [H]). Not named "بن" in the model [D]; real-world mechanics [?].

### 2.5 Trade Ownership Layer (D-099 §6)
Per leg: owner, holder, and (optionally) location reference; state transitions (e.g. consignment handed → sold → returned) recorded as OwnershipEvent rows [D]. It is a *view/relationship layer over* Trade; it does not add `owner_id` to stock (D-099). Loss-liability and sale-recognition semantics [?] (H-10).

### 2.6 UnitConversion (rule, not data per trade)
Conversion of a leg's physical quantity to a comparison quantity: for gold, `weight × purity / 750` [V]; purity as attribute on the item instance [D]. Rounding rule: 3 dp, rule TBD [?]. Configured centrally per Product group (a rule registry), not hard-coded in "gold" code [D].
Distinguish **physical quantity** (what moved) and **economic quantity** (converted, for comparison/netting) — both may appear in Position [D].

### 2.7 SettlementLink
Trade's relationship to settlement kinds: CASH, CHEQUE, CREDIT, IN_KIND_OFFSET, LEAVE_OPEN (D-070 polymorphic `allocatable`/source pattern — Trade becomes another source, no `SettlementService` refactor here). An in-kind offset is a quantity operation on the exchange (net legs of same item/unit), recorded as its own event [D per D-099/research]. Same-kind swap rule (warn/enforce/off) = configuration [D; religious constraint PARTIAL, owner decides].

### 2.8 AdjustmentRecord
Purity/weight re-assay corrections, price changes, partial returns: separate documents linked to the original leg, with reason, actor, timestamp; effect expressed in quantity and/or valuation [H; desk: audit trail norm]. Dispute state lives on TradeExchange.status (DISPUTED) [D].

### 2.9 Derived, never stored: Position
Computed from legs + adjustments + settlements, per (counterparty, item/unit) and optionally per economic quantity: `physical_in − physical_out ± adjustments`. A cached read model is allowed as an *optimization* only, rebuildable from events [D per D-099 "derived, never stored as truth"]. Exposure report (physical + receivable − payable, in weight) is one *view* over Position [H, only 1 vendor states it → [?] whether core].

## 3. Accounting artifacts (outputs, outside Trade)
Trade emits *accounting inputs* = quantity + valuation context (D-099 §7). Existing services create Documents/Transactions at valuation/settlement events, keyed back to `trade_exchange_id`; cancelling/adjusting a trade voids or regenerates those artifacts via the existing reversal path (avoiding ERPNext's orphan set-off JE). Tax split (raw gold / wage / profit / VAT) is computed by the tax layer from legs + `TaxRule` configuration; VAT rate never a constant [V: VAT on wage+profit only; rate for 1405 [?]]. Moadian gold invoice field mapping [?] needs the official technical guide.

## 4. Existing-model mapping (impact preview for D-102, not a review)
| Existing | Relationship |
|---|---|
| `Customer` (subject_id) | Counterparty; Trade metadata (relationship type: dealer/retail/factory) as an additive layer [D] |
| `Product`, `ProductGroup` | Item; group holds the attribute/unit-conversion registry [D] |
| `ProductLot`, `ProductSerialNumber` | Candidates for item-instance identity — reuse vs. new [D-102] |
| Stock / `StockReservation` / `DeliveryNote` / `GoodsReceipt` | Physical movement — Trade *requests* movements; must not rewrite stock accounting (D-099 boundary) |
| `SettlementObligation/Installment`, `Cheque` | Settlement source polymorphism (D-070) |
| `Invoice`/`InvoiceItem` | Trade-In may yield purchase + sale lines in one flow [H]; invoice rules unchanged |
| `TaxRule`/Moadian | Output artifact; unverified field rules |
| Sync (D-076–D-090) | Trade is an aggregate → needs its own conflict semantics (D-074); out of scope |
| `MarketQuote` (Industry Intelligence) | Advisory input to MARKET_SNAPSHOT only |

## 5. Paper simulations (conceptual, numbers illustrative)
- **S1 gold exchange:** A OUT 100 g raw @750; B OUT 80 g finished @750 + wage (separate valuation line) + cash 50M (settlement CASH). Position: A gives 100 g, receives 80 g + cash; wage handled as valuation, tax split computed outside. Fits; wage basis [?].
- **S2 dealer account:** day1 A→B 200 g; day2 B→A 70 g; Position = 130 g owed by B (derived). Day3: settle 130 g in-kind (new OUT leg / offset event) or fix at rate (valuation event → Rial obligation). Fits if valuation events support partial fixing [H].
- **S3 consignment:** factory OUT 100 coins, ownership RETAINED_BY_GIVER; holder = seller. Sale event flips ownership + creates obligation to factory; return event reverses. Fits via Ownership Layer; loss/recognition semantics [?].
- **S4 deferred price:** 100 g OUT with DEFERRED valuation; Position in grams only; fixing record later creates value + accounting artifact. Fits.
- **S5 purity conversion:** 100 g @750 leg with purity attribute; UnitConversion yields economic quantity; netting between legs of different purity uses economic quantity [D] — rounding [?].
- **Trade-In (H-01):** IN (customer old gold, valued with purity haircut [?]) + OUT (new piece) + CASH settlement in one exchange. Fits without a new entity; revisit if real flows need one.

## 6. Assumptions flagged for real-dealer validation [?] (from desk report §4)
"بن" mechanics; open-account settlement custom (weight vs Rial); purity tolerance and correction documents; consignment loss/recognition; "سوت" (weight vs purity point, or both); 7% base; 1405 VAT rate; Moadian gold fields; trade-in haircut and wage deduction; melt loss / کار مزدی; whether exposure report is core.

## 7. Risks
Over-modelling (mitigation: no entity beyond §2 until a real scenario fails); attribute-bag sprawl (mitigation: typed registry per Product group); double-truth risk between Trade quantities and stock (mitigation: Trade requests movements through existing services, reconciliation view in D-102); invented dealer rules leaking into the model (mitigation: every [?] item stays configuration or unimplemented until evidenced).

## 8. What would change this proposal
Real interviews contradicting: base unit being grams-750 rather than per-item native units; "physical-vs-obligation" answers; existence of dispute flows different from adjustment records; evidence that per-piece identity is needed beyond melt dealers; any need for an entity not in §2. Each is a reason to amend this document, and if material, D-099.

## 9. Next steps (owner decides)
1. Real-dealer verification (field kit / candidate table). 2. D-102 impact review against the table in §4 (still documentation). 3. Only then an Implementation Decision; D-099 → ACCEPTED only if validated.
