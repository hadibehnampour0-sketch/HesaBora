# Trade Engine Architecture Proposal & Gold/Silver Pilot Specification (r1, 2026-09-25)

> **Status: SPECIFICATION ONLY — no code, no migration, no schema, no route.**
> Trade phase is opened **for design/specification only** (per owner direction: "Trade Engine Architecture + Gold/Silver Pilot Specification first, then Decisions, then Implementation").
> **This is not a Decision.** The D-099 contract text (Trade Engine Architecture) is to be authored by the owner. Everything below is a *proposal with options* to support that authoring.
> Builds on: D-074 (shape locked: Trade/Deal, OUT[]/IN[], Valuations, Position, Settlement, Status), D-070 (Settlement engine, polymorphic source), Vision doc `Dakhl_PRODUCT_VISION_AND_STRATEGY_2026-09-25.md`.

## 1. Direction and one tension to resolve

Owner direction: build a **general** Trade engine; use gold/silver as the **pilot/lab domain**, not a "gold module" (no `GoldTrade/GoldWeight/GoldPurity` tables). Roadmap order: Identity doc → Engine Architecture decision → Gold pilot research → Data model proposal → impact review on current architecture → only then code.

**Tension with D-074:** D-074 forbids `GenericTradeEntity/GenericPosition/GenericValuation/UniversalSettlement` built "for later" and demands design "against the real requirements of the Trade build phase." Resolution proposed: the *specification* is general in vocabulary (Item / Quantity+Unit / Valuation / Ownership / Settlement) but is **validated against concrete scenarios first** (gold S1–S3 below, then mobile/iron as a paper check, §6). No generic abstraction is coded before that validation passes and the owner locks D-099. Owner should confirm this reading of D-074.

## 2. Conceptual model (proposal)

```
TradeExchange (aggregate, company-scoped, FiscalYearScope)
├── parties: side_a, side_b            (existing Customer/Subject; see Q5)
├── legs[]  direction OUT|IN
│     item (Product)  quantity  unit  [attributes: purity, wage, ...]
│     ownership_after: TRANSFERRED | RETAINED_BY_GIVER (consignment)
├── valuations[]  per leg or per group, each with basis + timestamp + source
│     basis: AGREED | MARKET_SNAPSHOT | DEFERRED
├── position (derived, never stored as truth): net per item-kind
│     e.g. Gold18: -20g, Coin: +2, Rial: pending
└── settlements[]  CASH | CHEQUE | CREDIT | IN_KIND_OFFSET | LEAVE_OPEN  (D-070 polymorphic source)
status: OPEN → PARTIALLY_SETTLED → SETTLED (+CANCELLED)
```

Design rules derived from research + D-074:
1. Exchange is its **own aggregate with legs**; the financial Document/Transaction is a **derived artifact**, voidable/regenerable with a back-reference (avoids ERPNext's orphan set-off JE on cancel).
2. **Ownership is a separate dimension from warehouse/location** (Odoo `owner_id` pattern); consignment liability arises at **sale**, not receipt. CONSIGNMENT ≠ OPEN_EXCHANGE (D-074).
3. **Never collapse to Rial up front.** Store native quantity/unit; rial value only via explicit Valuation; ledger posted at valuation/settlement events.
4. **Three distinct numbers:** quantity, value-at-trade (agreed), current market value (informational, from Industry Intelligence — never rewrites posted data).
5. In-kind offset of same-kind legs is a **quantity operation on the exchange**, not a money JE.
6. Additive: `Customer`/`Invoice`/`SettlementService`/sync engine untouched until the owner-approved impact review.

## 3. The five open questions — options and recommendation (owner decides)

| # | Question | Options | Recommendation (with reason) |
|---|---|---|---|
| Q1 | Trade = Document or Event? | (a) new Document subtype; (b) own aggregate producing Documents | **(b)** — D-074 says Trade is an aggregate with children; a flat Document cannot carry multi-unit legs or a pending valuation. Documents remain the accounting output. |
| Q2 | Ownership separate from stock? | (a) separate owner dimension; (b) special "consignment warehouses" | **(a)** — location≠ownership; (b) is a workaround that breaks valuation reports. Note current stock is a single quantity per Product/warehouse with lots/serials and reservations (`ProductLot`, `ProductSerialNumber`, `StockReservation`); owner dimension is an impact-review item. |
| Q3 | Valuation basis | agreed price / market snapshot / deferred | **Support all three per leg**, recorded with `basis`, `source`, `unit (rial/toman)`, `timestamp`; default AGREED; DEFERRED for iron/آبشده "price fixed later". Market snapshot is advisory. |
| Q4 | Multi-unit balances vs Rial only | Rial only; multi-unit position | **Multi-unit position, derived**, with Rial as one axis; ledger stays Rial. Needs a purity/weight normalization rule for gold (see §5). |
| Q5 | Party = Company or new layer? | Company; new Party entity; metadata on Customer | Not Company (Company is tenant boundary, D-092). **Recommend metadata over existing Customer** (it already nets buy+sell on one `subject_id`); new entity only if the impact review finds a blocker. Note: parties may sit in *different* Dakhl companies/installs — cross-tenant exchange is out of pilot scope (single-side recording only). |

## 4. Gold/Silver pilot scenarios (from the owner)

**S1 — Gold-for-gold with wage/difference.** A gives 100 g raw 18k; B gives 80 g finished gold + wage + cash difference. Tests: purity normalization, wage as separate component, mixed kinds in one exchange, cash remainder.
**S2 — Open account between two dealers.** During the day A gives 200 g, B gives 100 g; night: net 100 g or rial equivalent. Tests: derived Position per kind, settle-in-kind vs settle-in-rial choice, valuation at settlement time (price moved since morning).
**S3 — Consignment.** Factory gives 50 coins to a seller; ownership stays with the factory; seller only holds. Tests: owner≠holder, no revenue/inventory recognition for the holder until sale, liability created at sale, return-of-consignment.

Each scenario must produce, on paper: legs, valuations, Position, settlement(s), and the resulting Documents/Transactions — and show the rial ledger is touched only at valuation/settlement.

## 5. Domain unknowns to confirm with real dealers (unverified — research found only vendor marketing pages)

- Fine-weight normalization (e.g. 750/1000 = 18k) vs storing purity per lot; rounding rules.
- How wage (اجرت) is charged (percent of weight vs per-gram) and whether it is settled in rial or weight.
- Whether dealers settle open accounts in weight or rial by custom, and when the rate is fixed.
- Coin handling (بهار آزادی types, half/quarter): unit=each with per-type reference weight, or weight-based?
- Any religious/legal constraint on same-kind exchange (one Persian source mentions it as ريا rules) — **business rule for the owner, not assumed here**.
Action: 3–5 interviews with gold/coin shop owners **before** data-model proposal.

## 6. Generality check (Step 2 of the owner's roadmap, paper only)

| Domain | Item | Unit | Special |
|---|---|---|---|
| Mobile | model/variant | each (serial optional) | same-model offset in kind (e.g. cases) |
| Iron | rebar/beam | ton | price fixed later (DEFERRED valuation) |
| Gold | grams + purity | g | wage, price-of-day |
If the same aggregate expresses all three without domain tables, the model passes. If it needs domain-specific columns, they belong in item *attributes*, not new tables.

## 7. Impact review checklist (required before any code)

`Product` (single unit + `average_cost`, lots/serials), stock ledger & `StockReservation`, `DeliveryNote`/`GoodsReceipt`, `Invoice`/`InvoiceItem`, `SettlementObligation/Installment` (D-070 polymorphic source), `Cheque`, Sync engine D-076–D-090 (Trade needs aggregate-level conflict rules, per D-074), Moadian/tax (an exchange is taxable supply — how each leg is invoiced), permissions (route-name permissions, seeder groups), FiscalYearScope/company_id, Guardrails #5, #54–#57, and Industry Intelligence `MarketQuote` (advisory input only).

## 8. What this spec does NOT authorize

Any migration/model/service/route; any change to Customer/Invoice/Settlement; any sync work; market-feed integration; a "gold module". Next step for the owner: author D-099 (Trade Engine Architecture) and, if desired, follow-on decisions; then request the data-model proposal.

## 9. Owner review notes (2026-09-25, relayed review of this spec) — INPUT for D-099, not a locked decision

Positions expressed, recorded faithfully so D-099 can be authored from them:
- **D-074 reading confirmed:** no `GenericTradeEntity/GenericPosition/GenericValuation/UniversalSettlement`; a shared *design vocabulary* (Trade, Party, Leg, Item, Quantity, Unit, Valuation, Settlement, Position) is fine if each element originates from a real need.
- **Q1:** Trade = independent aggregate; accounting Documents are its output (not the reverse). Rationale: open dealer accounts are an ongoing relationship (day 1 A→B 100 g; day 2 B→A 40 g; day 3 B→A 20 g + cash), not an invoice.
- **Q2 (amends my recommendation):** ownership principle `Owner ≠ Holder ≠ Location` accepted, but **do not add an owner dimension to the current Stock now** (Product/Warehouse/Lot/Serial/Reservation is sensitive; risk of drifting into heavy ERP). Instead a **Trade Ownership Layer** kept separate from Stock (Trade → Ownership State → Stock only if needed).
- **Q3:** AGREED / MARKET_SNAPSHOT (price+timestamp frozen at trade) / DEFERRED (quantity exists, value pending — e.g. iron).
- **Q4:** Trade holds **native quantities**; Accounting holds **money**; the two stay separate.
- **Q5 (amends my recommendation):** now = `Customer(subject_id)` + Trade metadata; future = a Party abstraction (Person/Company/Supplier/Customer/Dealer/Partner/Agent) — deferred as premature.
- **Addition — Trade Lifecycle:** Intent → Agreement → Exchange → Open Position → Settlement → Closure.
- **Addition — statuses:** OPEN, PARTIALLY_SETTLED, SETTLED, CANCELLED, **DISPUTED**. *Note: D-074 locked OPEN→PARTIALLY_SETTLED→SETTLED(+CANCELLED); DISPUTED (and Intent/Agreement stages) would extend that locked shape and must be explicitly stated in D-099.*
- **Gold pilot = Domain Discovery, not development:** (1) interviews — ≥3 gold dealers, 1 آبشده seller, 1 wholesale coin dealer (open-account practice, ledger books, in-kind offset recording, wage calculation, when ownership transfers); (2) paper simulation without code (e.g. Ali 100 g raw vs Hasan 80 g finished + wage + cash); (3) impact review; only then schema/model/service/migration.
- **Proposed decision sequence:** D-099 Trade Engine is a new business capability, not an accounting document type → D-100 Gold Pilot Domain Findings → D-101 Trade Data Model Proposal → D-102 Architecture Impact Review. (Numbers are the owner's suggestion; text authored by owner.)

Spec changes implied: §3 Q2 and Q5 rows superseded by the above; §4 pilot is research/paper-simulation only; status set gains DISPUTED pending D-099 wording.
