# Dakhl (دخل) — Full Status Report (2026-09-29)

Same purpose and same structure as the 2026-09-28 report: one consolidated picture of the whole product — what exists, what is tested, what is explicitly not done. **The 2026-09-28 report is preserved unchanged as history** per this project's own convention; this file supersedes it and the differences are called out explicitly in §0 so nothing has to be diffed by hand.

**Repo**: `C:\Projects\HesaBora`, branch `dakhl-foundation`, HEAD `0c808aa3`. **432 files** in `app/` (110 controllers, 100 services). **169 test files, 1941 tests — all green** as of the last commit. Everything is **local only**: no remote, nothing pushed; a bare mirror at `D:\Backups\Dakhl.git` is re-synced after every commit. Base stack: Laravel 12, Blade + Alpine.js + daisyUI/Tailwind, MariaDB in production, SQLite in tests and in the packaged desktop build. Originates from FreeAmir, rebuilt and extended into Dakhl.

---

## 0b. Post-publication corrections to the five Open Findings

A later pass on the same day independently re-examined the two CRITICAL findings and **changed both of their statuses**. Recorded here so this report does not mislead anyone who reads only it; the Decision Log carries the full detail.

- **Finding C (`AncillaryCostTransactionBuilder`) — REFUTED, no bug exists.** The original finding read the builder in isolation and assumed `amount` means "sum of item amounts". It does not: `StoreAncillaryCostRequest::prepareForValidation()` (`app/Http/Requests/StoreAncillaryCostRequest.php:37-38`) always computes `amount` as the **VAT-inclusive grand total** before the builder ever sees it, so the customer line already balances the items + VAT exactly. **Applying the finding's suggested fix would have double-counted VAT and broken a correct posting.** The part of the finding that *was* real is the coverage gap: this case had zero prior tests, which is exactly why the misdiagnosis looked plausible — now closed by `tests/Feature/AncillaryCostVatBalanceTest.php`.
- **Finding A (`OrderCancelHandler`) — FIXED, verified.** Commit `18b701f7`: the void invoice number is now allocated through `nextVoidInvoiceNumberForUpdate()` — a `lockForUpdate()` query scoped to `invoice_type = VOID` and the model's own `FiscalYearScope` (hence the right company), inside the same transaction that creates the void. Fail-first verified by reverting.

## 0. What changed since the 2026-09-28 report

Four commits landed after that report was written, covering three previously-open items and closing three small named gaps:

| Commit | What it added | Previously reported as |
|---|---|---|
| `7c67414a` | **First real integration adapter (WooCommerce)** — auto-approve-with-review-queue order import, encrypted connections, outbound stock push | "Integration Hub skeleton … fake adapter only" (§6) |
| `b68e1b7d` | **Live two-way stock push**, order-cancellation handling, bulk product mapping | "no real platform adapter" (§13) |
| `257b8bf7` | **Manufacturing Phase 1 "Production Lite"** — recipes, produce-now with waste/cost math, production history | "**Phase 0 only** … no recipe/BOM, no production order, no produce action" (§2) |
| `0c808aa3` | Per-visitor commission override, promotion suggestions on the invoice form, route-day calendar | three named "not done" gaps (§8) |

Also: the **Decision Log had no entry for any of these four commits**. That gap is now closed retrospectively as D-104…D-107, marked RATIFICATION PENDING (they transcribe shipped code; they are not owner-authored forward decisions).

**Five real defects were found while transcribing that code and are recorded in the Decision Log's "Open Findings" section.** Two were originally filed CRITICAL; **their status has since changed — one was fixed and one was refuted** (see §0b). They are new information, not something the 2026-09-28 report could have known.

---

## 1. Core accounting (foundation — done, mature)

- **Chart of accounts / Subjects**: hierarchical, coded, searchable, transferable.
- **Documents & Transactions**: double-entry ledger, draft/posted lifecycle, print/export, bulk approve, document-number sorting tool, import/export.
- **Reports**: Ledger, Journal, Sub-ledger, Trial Balance (+CSV/print), Documents report, Cost/Income, Company overview, plain-language report (Simple mode).
- **Budgets**: monthly income/expense workbench with rollover.
- **Bank accounts, banks, cheques, chequebooks**: full lifecycle incl. status transitions and a cheque report.
- **Bank reconciliation**: import, match, unmatch.
- **Fixed assets**: register, post monthly depreciation, dispose — has its own screen.
- **Currencies & exchange rates**: dated FX rates, has its own screen.
- **Payment terms (instalment schedules)**: define, and apply from an invoice's page — obligations + instalment plans.
- **Recurring invoices**: template, auto-generate on schedule or "issue now" — has its own screen.
- **Analytic dimensions** (branch/project/cost-center/sales-channel): tag document transaction lines, per-dimension profitability report, both with UI.
- **Stock reservations**: hold stock without moving it — has an index screen.
- **Dashboards**: Cash flow (driver-aware SQL), Cheque risk, Profitability by dimension, Inventory valuation, Slow-moving stock.

---

## 2. Products, services, warehouse, manufacturing

- Products & Services with groups, VAT, barcode, oversell handling, discount formulas.
- Multi-warehouse stock (`WarehouseProductStock`), atomic locked transfers, transfer history.
- Product tracking (lots/serial numbers), goods-receipt/delivery-note fulfillment.
- Warehouse dashboard, inventory reports (valuation, slow-moving).
- **Manufacturing: Phase 1 "Production Lite" is now BUILT** (was Phase 0 only). `ProductionRecipe` + `ProductionRecipeItem` (output product, output quantity, per-material quantity/unit/waste %), `ProductionOrder` + `ProductionOrderMaterial` (a two-state DRAFT→PRODUCED lifecycle, labor/overhead, produced_at), a recipe builder, a "Produce now" screen, a read-only shortage/estimate view, and production history. Branded to users as **"تولید و بهای تمام‌شده"**.
  - `produce()` reuses the existing lock discipline (recipe + every material and output stock row locked in one sorted order inside a single transaction), rejects on insufficient stock respecting `oversell`, applies waste % as *additional consumption*, and blends cost into the output product's `average_cost` with the same weighted-average math `WarehouseService` uses for purchases.
  - **Known limitation**: `produce()` posts **no accounting document** — inventory valuation moves, the general ledger does not. See Decision Log "Open Findings" §E. Phase 2 decision, not started.
  - **Still not built**: multi-stage routings, work centers/capacity, MRP/auto-purchasing, quality control, batch/serial genealogy (Dakhl has a separate `ProductTracking` feature a later phase could link to, not duplicate).

---

## 3. Customers, CRM

Customers/groups, import/export; CRM dashboard (basic).

---

## 4. Invoices & payments

- Sell/Buy/Return-Sell/Return-Buy/Void invoice types, full approval chain, transfer between fiscal years, group actions, conflict detection on cross-linked invoices.
- Ancillary costs (shipping/insurance/customs/etc.) on buy invoices, tax-deductible flag.
- Payments: cash/subject, cheque, accounting-document creation/removal, credit/advance payments, allocation across invoices.
- Quick payment ("Collect money"/"Pay suppliers"): guided page, settles several invoices oldest-first from one amount.
- Payment term application, and tax-evidence attachment, both from the invoice page.
- **New: promotion suggestions on the sell form** — a "Check active promotions" button (gated on `promotions.evaluate`) posts the cart + customer and shows each suggested discount/bonus with its own Apply button. Suggestion-only, never auto-applied; the customer group is resolved server-side, not pushed into the form's JS.
- **Open defect**: ancillary costs **with VAT** produce an unbalanced document and fail approval. See Decision Log "Open Findings" §C. Pre-existing, unrelated to this session's work.

---

## 5. HR / Payroll / Attendance

Employees, org charts, organization units, personnel requests (approve/reject workflow); attendance logs (incl. bulk import), monthly attendances, work shifts; payroll tax slabs, work sites, work-site contracts, public holidays, payroll elements, salary decrees, payroll runs; employee self-service portal.

---

## 6. Security, roles, sync, integration platform (foundation)

- Spatie roles/permissions, **team-scoped by company** (a real, previously-fixed wildcard-cache cross-company leak — D-091).
- `CheckPermission` middleware: permission name == route name convention, enforced on all new work.
- Activity log incl. role-assignment audit trail.
- Forced password change for the default admin on first login.
- **Offline/online sync** (D-071–D-090, implemented): outbox/inbox, conflict resolution, device pairing, server-authoritative numbering leases. Syncable: Customer, Product, Document/Invoice (draft + sell finalize/approve), Payment (create), PaymentAllocation (create). **Not syncable by design so far**: Cheque, Transaction, Bank Reconciliation, buy-side approval, return/void invoices, Reversal/Correction.
- **Integration platform — now a real connector, not a skeleton.** `IntegrationConnection` (encrypted credentials), `IntegrationHub` idempotent event ledger, `ExternalReferenceService` product↔SKU mapping, and the **WooCommerce adapter** as the first live platform. Order import auto-creates and auto-approves a Sell invoice through the unchanged `InvoiceService` path **only** when all four safety conditions hold (every line maps to a known product; the customer resolves or is safely created; price is within a configurable tolerance; stock is sufficient). Anything ambiguous goes to a **visible review queue** — never silently dropped, never force-posted with guessed data. Screens for connections, the queue, and product mapping (including bulk mapping).
  - **Two-way stock**: a `WarehouseProductStockObserver` on the one model every stock-mutating service already ends with is the single hook point; outbound pushes go through a queued job, inbound decrements happen through the normal invoice flow.
  - **Order cancellation** voids the imported invoice through the existing guarded `InvoiceService` void path, surfacing that validation's own reason if the invoice is already paid.
  - **Refunds are not interpreted** — no handler is registered, so a partial refund stays a visible unhandled event rather than a guess.
  - **Open defects**: number allocation in the cancel handler, and the push's commit-boundary timing. See "Open Findings" §A and §D.
- API tokens (Sanctum), webhooks (outbound registrations).

---

## 7. Tax module — complete (9 of 9 planned items)

Went from "no independent Tax area" to a complete, own top-level menu:
1. **Tax Center** hub, showing the latest readiness score.
2. **Tax Readiness score** (0–100, 4 weighted metrics: profile completeness, Moadian compliance, evidence coverage, VAT-rule alignment).
3. **Seasonal (quarterly) VAT report** — invoice-derived, explicitly labeled an approximation, not a verified byte-level TTMS format.
4. **T-5 Ledger reconciliation** — independently re-derives VAT from the general ledger and flags any mismatch against the invoice-derived report.
5. **T-1 Audit pack** — one downloadable ZIP: manifest, VAT report + reconciliation, period invoices, Moadian status summary, every evidence file on record.
6. **T-7 Moadian work queue** — every invoice that never successfully reached Moadian, with reason and one-click resend.
7. **T-8/T-9 "Prepare my tax filing"** — a plain-language per-quarter checklist, each unmet item linking straight to its fix, then Download audit pack. States plainly that Dakhl prepares the pack but does not submit the filing.
8. **T-2 Evidence Vault** — attach/list/download/delete supporting documents on ancillary costs and invoices, via one reusable widget.
9. **T-3 Filing calendar** — per-quarter status (pending/ready/overdue/filed), "mark as filed", and a new Tax Alert rule for an overdue-unfiled quarter. The 15-day due-date offset is explicitly labeled a convention, not confirmed law.

Plus **tax alerts** (pre-existing): low readiness score, rejected Moadian invoice >24h unresent, missing evidence on a high-value cost, overdue unfiled quarter.

**Still blocked on an owner decision**: a versioned, dated "rule pack" system so VAT/tax rules can be updated as law changes, with a named human reviewer before anything goes live. Needs decisions on who reviews/publishes, the source of truth for legal changes, how packs are delivered to installations, and the liability wording. Nothing was built beyond the design proposal — guessing at any of it would risk the "error-free" promise.

---

## 8. Capillary distribution (پخش مویرگی) — complete, gaps closed

Previously backend-only (P11) with one analytics screen; now a full top-level menu:
- **Routes**: define, assign customers with visit priority/days.
- **Visitors**: bind a company user to an optional dedicated **van warehouse** (an ordinary `Warehouse` row — no new stock mechanism was invented, this was a deliberate audit finding, not an omission).
- **Visit plans**: one per visitor per day, **now with a 7-day route-day calendar** (Saturday-start, matching the Iranian week regardless of locale), week navigation, and a per-day quick-add form, alongside the still-present flat list for full-history browsing.
- **Daily settlements**: cash/POS/cheque reconciliation, draft → submit → approve, compared against that day's invoiced total.
- **Promotions**: tiered-discount and bonus-goods rules via plain fields (no hand-written JSON); a pure calculator, now reachable from the invoice form as suggestions (§4).
- **"Load the van"** shortcut into the existing warehouse-transfer screen.
- **Van default on invoices**: a visitor's own invoice creation pre-selects their van warehouse.
- **New: per-visitor commission override** — a visitor's own agreed rate overrides the tiered calculation outright (tier label becomes `'Custom'`); unset falls back to the existing tiers completely unchanged.
- **Still not done**: applying a promotion automatically inside the invoice form (the engine exists and now suggests; it is never auto-applied, by decision); no commission/payroll link for visitors (a "P12.4 Field Operations Commission" engine exists from an earlier phase but is not connected to the new visitor/settlement screens); promotions have no stacking rule (first-match only, undocumented for two simultaneous promotions).

---

## 9. Industry Profiles (per-trade settings)

Data-driven settings + calculators per trade; adding a trade or setting means editing a JSON file, no PHP change. Full for gold, iron, general; starters for building materials, mobile, auto parts, apparel, food. Evidence badges (verified/assumed/needs confirmation) on every default. **Not wired into invoicing** — a profile's calculators exist standalone. Nothing here posts to the ledger, moves stock, or implements Trade/Deal/Consignment/Position.

---

## 10. Today dashboard & AI (tier 1 only)

- **Today dashboard**: KPIs, 7-day sales/purchase chart, receivables donut, cheques, overdue customers, "getting started" checklist, quick actions — every block permission-gated, amounts hidden until revealed.
- **Deterministic insights** ("Needs attention"): duplicate invoice, buy-price spike, receivable concentration, bounced cheque — thresholds tunable per company via the Industry Profiles' `business_insights` profile. **No LLM tier 2/3** — needs the data-egress/provider decision from the AI/Integration architecture doc, not made yet.

---

## 11. Simple Mode (for non-accountants)

A `ui_mode` toggle (always-visible header checkbox) switches the same screens into a guided flow, same fields/routes/validation as advanced mode. Wizards: Sell invoice, Buy invoice, New customer, New product, Ancillary cost. Simple sidebar; quick collect/pay page; plain-language business report.
**Not done**: a Simple-mode entry point into Tax's "Prepare my tax filing"; Simple-mode versions of anything in §7–§9.

---

## 12. Design system

Unified deep-blue light/dark daisyUI theme, RTL-first, right-side sidebar, restyled auth/admin/home pages. Verified via static renders (light/dark, desktop/mobile) and stubbed interactions — **not click-tested while logged in** (no real password is typed); needs an owner's click-through.

---

## 13. Fully out of scope so far (by explicit instruction, not oversight)

- **Trade / Consignment / Open Exchange (D-074/D-099)**: locked as design-only. No `Trade`, `Deal`, `Position`, or generalized-`Settlement` code exists anywhere. Blocked on real dealer interviews — simulated/AI-role-played interviews were explicitly rejected as evidence. D-099 stays **PROPOSED**.
- **Basalam / Digikala adapters**: blocked on account/OAuth registration, not on engineering. WooCommerce was chosen first precisely because it needs no third-party approval.
- **AI tier 2/3** (LLM-backed explanations/actions): architecture designed, not built.
- **Monetization/licensing scoping**: not started.
- **Android / Field Mobile app**: explicitly last. A hardware/mobile architecture spec exists (ESC/POS printing, barcode scanner, offline sync) but no Flutter/Kotlin project exists in this repo.
- **Tax rule-pack versioning**: design only (§7).

---

## 14. Smaller, granular gaps worth naming explicitly

- **Ancillary cost with VAT cannot be approved** (unbalanced document) — see "Open Findings" §C. The most user-visible of the new findings.
- No multi-invoice allocation UI for *advance/credit* payments (a customer's pre-paid credit is still allocated one invoice at a time).
- Product has no attribute model (weight/purity/etc.) — needed for Trade product templates, deliberately not built.
- No bulk tool for analytic-dimension tagging (one transaction row at a time).
- No filing-calendar entry for annual income-tax/performance return (T-6) — only the seasonal VAT quarter.
- `TaxFilingCalendarService`'s overdue detection looks only at the current and previous quarter, not a full history of older unfiled quarters.
- No promotion stacking rule (§8).
- No commission/payroll link for capillary-distribution visitors.
- Backup/export tooling is ZIP-based and **manual only** — no scheduled/automatic backup.
- **No CI test gate**: `.github/workflows/` builds the desktop app and Docker images only; the full test suite is not run automatically on any push.
- A known **Moadian item-discount field defect** exists in a **separate session/worktree** the owner started — not present in this branch.
- Pint has pre-existing style debt on ~90–110 older files, deliberately left untouched to avoid merge noise; every file touched by recent work is Pint-clean.

---

## 15. Verification honesty note

Everything above that has a route/controller has an automated test proving the behavior (permission gate, cross-company isolation, the actual business rule) — the full suite was green after every change in this report. Two honest caveats:

1. What automated tests **cannot** prove: how it actually looks/feels logged in, on real data, in a real browser. The four most recent commits report real-browser verification of the promotion suggestions and the route-day calendar specifically; everything else was verified by static renders and stubbed interactions. An owner's click-through is still the missing step on items marked "done."
2. The five **Open Findings** in the Decision Log were found by *reading* the as-built code, not by a failing test. They are stated with file:line so they can be reproduced, but they have not been reproduced at runtime yet.

---

## 16. Immediate open decisions (owner's to make)

1. **Fix the two CRITICAL findings** (ancillary-cost VAT balancing; order-cancel number allocation) — both touch accounting/document-numbering logic, so AGENTS.md guardrail #3 requires review before a fix lands.
2. **The two latent findings**: `config('amir.*')` outside the `web` group; the stock push's commit-boundary timing.
3. **Ratify or correct D-104…D-107** — they transcribe shipped code, and ratification is what makes them real decisions rather than reconstructed notes.
4. **Trade**: when/how to run the real dealer interviews (D-099 stays PROPOSED until then).
5. **Basalam/Digikala**: register the merchant accounts / OAuth apps.
6. **Tax rule-pack versioning**: reviewer, source of truth, delivery, liability wording.
7. **Manufacturing Phase 2**: should production post accounting documents? (Open Finding §E)
8. **A CI test gate**, so the suite is not dependent on a human remembering to run it.
9. **A pre-commit rule requiring a Decision Log entry**, since that is the structural cause of the gap this report had to close.
10. Owner's own click-through review of everything marked "done," on real data.
