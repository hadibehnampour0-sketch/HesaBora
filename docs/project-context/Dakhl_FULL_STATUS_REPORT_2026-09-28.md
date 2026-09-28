# Dakhl (دخل) — Full Status Report (2026-09-28)

One consolidated picture of the whole product as it stands today: what exists and works, what is tested, and what is explicitly not done — down to the small pieces. Written to answer "what do we have, what's finished, what's left" in one place, instead of scattered across the Decision Log, roadmap and execution log.

**Repo**: `C:\Projects\HesaBora`, branch `dakhl-foundation`. **432 files** in `app/` (110 controllers, 100 services). **165 test files, 1890 tests, 7312 assertions — all green** as of the last commit. Everything is **local only**: no GitHub remote, nothing ever pushed; a bare mirror at `D:\Backups\Dakhl.git` is kept in sync after every commit. Base stack: Laravel 12, Blade + Alpine.js + daisyUI/Tailwind v4, MySQL/MariaDB in production, SQLite in tests. Originates from FreeAmir (an open-source Iranian Laravel accounting app) rebuilt and extended into Dakhl.

---

## 1. Core accounting (foundation — done, mature)

- **Chart of accounts / Subjects**: hierarchical, coded, searchable, transferable.
- **Documents & Transactions**: double-entry ledger, draft/posted lifecycle, print/export, bulk approve, document-number sorting tool, import/export.
- **Reports**: Ledger, Journal, Sub-ledger, Trial Balance (+CSV/print), Documents report, Cost/Income, Company overview, plain-language report (Simple mode).
- **Budgets**: monthly income/expense workbench with rollover.
- **Bank accounts, banks, cheques, chequebooks**: full lifecycle incl. cheque status transitions and a cheque report.
- **Bank reconciliation**: import, match, unmatch.
- **Fixed assets**: register, post monthly depreciation, dispose — now has its own screen (previously JSON-only).
- **Currencies & exchange rates**: dated FX rates, now has its own screen.
- **Payment terms (instalment schedules)**: define, and apply directly from an invoice's page — settlement obligations + instalment plans.
- **Recurring invoices**: create a template, auto-generate on schedule or "issue now" — now has its own screen.
- **Analytic dimensions** (branch/project/cost-center/sales-channel): tag document transaction lines, per-dimension profitability report — both now have a UI (previously the tagging endpoint had none at all).
- **Stock reservations**: hold stock without moving it — now has an index screen (previously the model/service/API existed with no page).
- **Dashboards**: Cash flow (driver-aware SQL, fixed a real MySQL-only bug this pass), Cheque risk, Profitability by dimension, Inventory valuation, Slow-moving stock.

All of the above got **screens + menu entries + seeded permissions** this pass where they previously had none (an audit found ~10 backend-complete, UI-absent features — Entries 98–101 in the execution log).

## 2. Products, services, warehouse

- Products & Services with groups, VAT, barcode, oversell handling, discount formulas.
- Multi-warehouse stock (`WarehouseProductStock`), atomic locked transfers, transfer history.
- Product tracking (lots/serial numbers), goods-receipt/delivery-note fulfillment flow.
- Warehouse dashboard, inventory reports (valuation, slow-moving).
- **Manufacturing/BOM: Phase 0 only.** A nullable `products.type` classification (general/raw_material/semi_finished/finished_good/waste) was added — purely additive, nothing reads it yet. Full research + phased plan written (`Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md`). **No recipe/BOM, no production order, no "produce" action exists** — deliberately held pending your go-ahead.

## 3. Customers, CRM

- Customers/groups, import/export.
- CRM dashboard (basic).

## 4. Invoices & payments

- Sell/Buy/Return-Sell/Return-Buy/Void invoice types, full approval chain, transfer between fiscal years, group actions, conflict detection on cross-linked invoices.
- Ancillary costs (shipping/insurance/customs/etc.) on buy invoices, tax-deductible flag.
- Payments: cash/subject, cheque, accounting-document creation/removal, credit/advance payments, payment allocation across invoices.
- **Quick payment ("Collect money"/"Pay suppliers")**: guided page, can now settle several invoices oldest-first from one amount (added this session).
- **Payment term application** from the invoice page (new this session).
- **Tax evidence attachment** directly from the invoice page (new this session — see §7).

## 5. HR / Payroll / Attendance

- Employees, org charts, organization units, personnel requests (with approve/reject workflow).
- Attendance logs (incl. bulk import), monthly attendances, work shifts.
- Payroll: tax slabs, work sites, work-site contracts, public holidays, payroll elements, salary decrees, payroll runs.
- Employee self-service portal.

## 6. Security, roles, sync, integration platform (foundation)

- Spatie roles/permissions, **team-scoped by company** (a real, previously-fixed wildcard-cache cross-company leak — D-091).
- `CheckPermission` middleware: permission name == route name convention, enforced everywhere new work has been added this whole mission.
- Activity log incl. role-assignment audit trail.
- **R-01: forced password change** for the default admin on first login (done).
- **Offline/online sync foundation** (Phase 2, D-071–D-083, locked/implemented): outbox/inbox, conflict resolution (keep-local/keep-server/manual-edit), device pairing, server-authoritative numbering leases. Syncable: Customer, Product, Document/Invoice (draft + sell finalize/approve), Payment (create), PaymentAllocation (create). **Not syncable, by design so far**: Cheque, Transaction, Bank Reconciliation, buy-side approval, return/void invoices, Reversal/Correction.
- **Integration Hub skeleton** (this mission): idempotent inbound event ledger (`integration_events`, `external_references`), adapter/handler interfaces, tested with a fake adapter only. **No real platform adapter, no route/UI, nothing wired to a real e-commerce/POS system.**
- API tokens (Sanctum), webhooks (outbound registrations).

## 7. Tax module — done this session, all 9 planned items except one

Went from "no independent Tax area" to a complete, own top-level menu:
1. **Tax Center** — hub linking every tax screen, shows the latest readiness score.
2. **Tax Readiness score** (0–100, 4 weighted metrics: profile completeness, Moadian compliance, evidence coverage, VAT-rule alignment) — pre-existing, now Persian-translated.
3. **Seasonal (quarterly) VAT report** — invoice-derived, explicitly labeled as an approximation, not a verified byte-level TTMS format.
4. **T-5 Ledger reconciliation** (new): independently re-derives VAT from the general ledger and flags any mismatch against the invoice-derived report.
5. **T-1 Audit pack** (new): one downloadable ZIP — manifest, VAT report + reconciliation, period invoices, Moadian status summary, every evidence file on record.
6. **T-7 Moadian work queue** (new): every invoice that has never successfully reached Moadian, with reason and a one-click resend.
7. **T-8/T-9 "Prepare my tax filing"** (new, your stated top priority): a plain-language checklist per quarter — profile OK? all invoices sent? ledger balanced? evidence attached? — each unmet item links straight to its fix, then a "Download audit pack" button. Explicitly states Dakhl prepares the pack but does not submit the filing for you.
8. **T-2 Evidence Vault** (new UI; the feature itself pre-existed with zero screens anywhere): attach/list/download/delete supporting documents on both ancillary costs and now invoices too, via one reusable widget.
9. **T-3 Filing calendar** (new): per-quarter status (pending/ready/overdue/filed), "mark as filed," and a new Tax Alert rule for an overdue-unfiled quarter. The 15-day due-date offset is explicitly labeled a convention, not confirmed law.
10. **Tax alerts** (pre-existing): low readiness score, rejected Moadian invoice >24h unresent, missing evidence on a high-value cost — now 4 rules with the overdue-filing addition.

**Not done, on purpose — blocked on your decisions (§7.2 of the completeness plan):** a versioned, dated "rule pack" system so VAT/tax rules can be updated as law changes, with a named human reviewer before anything goes live to customers. This needs you to decide: who reviews/publishes daily, what the source of truth for legal changes is, how packs are delivered to installations, and the liability wording. Nothing was built here beyond the design proposal — guessing at any of this would risk the "error-free" promise.

## 8. Capillary distribution (پخش مویرگی) — done this session

Was previously backend-only (P11) with just one analytics screen reachable. Now a full top-level menu:
- **Routes**: define, assign customers with visit priority/days.
- **Visitors**: bind a company user to an optional dedicated **van warehouse** (one-tick creation; the van warehouse is an ordinary `Warehouse` row, no new stock mechanism invented).
- **Visit plans**: one per visitor per day.
- **Daily settlements**: cash/POS/cheque reconciliation, draft → submit → approve, compared against that day's invoiced total.
- **Promotions**: tiered-discount and bonus-goods rules via plain fields (no hand-written JSON); a pure calculator, never touches an invoice directly.
- **"Load the van"** shortcut into the existing warehouse-transfer screen.
- **Van default on invoices**: a visitor's own invoice creation pre-selects their van warehouse.
- **Not done**: per-visitor commission percentage/tiering (still a class constant, not configurable), a route-day calendar view, applying a promotion automatically inside the invoice form (the engine exists, nothing calls it from the UI yet).

## 9. Industry Profiles (per-trade settings)

Data-driven settings + calculators per trade, no code change needed to add a rule. Full for gold, iron, general; starters for building materials, mobile, auto parts, apparel, food. Evidence badges (verified/assumed/needs confirmation) on every default. **Not wired into invoicing** — a profile's calculators exist standalone; nothing in the invoice/product flow calls them automatically yet.

## 10. Today dashboard & AI (tier 1 only)

- **Today dashboard**: KPIs, 7-day sales/purchase chart, receivables donut, cheques, overdue customers, "getting started" checklist, quick actions — every block permission-gated, amounts hidden until revealed.
- **Deterministic insights** ("Needs attention"): duplicate invoice, buy-price spike, receivable concentration, bounced cheque — thresholds tunable per company via the Industry Profiles' `business_insights` profile. **No LLM/AI tier 2 or 3** (no natural-language explanations, no assisted actions) — that needs the data-egress/provider decision from the AI/Integration architecture doc (§A7), not made yet.

## 11. Simple Mode (for non-accountants)

- A `ui_mode` toggle (now an always-visible header checkbox, not just an account-settings page) switches the same screens into a guided flow, same fields/routes/validation as advanced mode.
- Wizards: Sell invoice, Buy invoice, New customer, New product, Ancillary cost.
- Simple sidebar with fewer, plain-language menu items.
- Quick collect/pay page, plain-language business report ("How is my business doing?").
- **Not done**: a Simple-mode entry point into the Tax module's "Prepare my tax filing" (it exists in advanced mode/Tax menu only), Simple-mode versions of anything built in §7–§9 above.

## 12. Design system

- Unified deep-blue light/dark daisyUI theme, RTL-first, right-side sidebar, restyled auth/admin/home pages. Verified via static renders (light/dark, desktop/mobile) and stubbed interactions — **not click-tested by me while logged in** (I don't type passwords); needs your own click-through.

## 13. What is fully out of scope so far (by explicit instruction, not oversight)

- **Trade / Consignment / Open Exchange (D-074/D-099)**: locked as design-only. No `Trade`, `Deal`, `Position`, `Settlement`-generalization code exists anywhere. Blocked on real dealer interviews (simulated/AI-role-played interviews were explicitly rejected as evidence).
- **Real Integration adapters** (an actual e-commerce/POS/marketplace connector) — only the idempotent hub skeleton exists, fed by a fake adapter in tests.
- **AI tier 2/3** (LLM-backed explanations/actions) — architecture designed, not built; needs the data-egress decision.
- **Monetization/licensing scoping** — not started.
- **Android / Field Mobile app** — explicitly last; a hardware/mobile architecture spec exists (ESC/POS printing, barcode scanner, offline sync design) but no Flutter/Kotlin project exists anywhere in this repo.
- **Manufacturing UI** (recipes, production orders) — only the Phase-0 schema readiness exists (§2).
- **Tax rule-pack versioning** — design only (§7).

## 14. Smaller, granular gaps worth naming explicitly

- No multi-invoice allocation UI for *advance/credit* payments (only quick-payment's oldest-first split exists; a customer's pre-paid credit balance is still allocated one invoice at a time).
- Product has no attribute model (weight/purity/etc.) — needed for Trade-specific product templates, deliberately not built (Trade is out of scope).
- `analytic-dimensions.transactions.sync` UI exists now, but there's no bulk-tag tool (one transaction row at a time).
- No filing-calendar entry for annual income-tax/performance return (T-6 in the tax plan) — only the seasonal VAT quarter is covered.
- `TaxFilingCalendarService`'s "overdue" detection only looks at the current and previous quarter, not a full history view of older unfiled quarters.
- Promotions engine has no "stacking" rule (what happens if two promotions could both apply) — first-match behavior only, undocumented for multiple simultaneous promotions.
- No commission/payroll link for capillary-distribution visitors (a "P12.4 Field Operations Commission" engine exists from a much earlier phase but isn't connected to the new visitor/settlement screens built this session).
- Backup/export tooling exists (ZIP-based) but has no scheduled/automatic backup — manual only.
- A known **Moadian item-discount field defect** exists in a **separate session/worktree** the owner started — not present in this branch, not fixed here.
- Pint (code style) has pre-existing debt on ~110 older files, deliberately left untouched this whole mission to avoid noise/merge conflicts with your other in-progress session; every file touched by this mission itself is Pint-clean.

## 15. Verification honesty note

Everything above that has a route/controller has an automated test proving the behavior (permission gate, cross-company isolation, the actual business rule) — the full suite is 1890/1890 green after every change in this report. What automated tests **cannot** prove: how it actually looks/feels logged in as you, on your real data, in a real browser. I verify UI changes via static HTML renders and a stubbed browser pane, never by typing a real password. A genuine click-through review by you is still the one thing missing from every item above marked "done."

## 16. Immediate open decisions (yours to make, nothing else is blocked on them)

1. **Manufacturing Phase 1** ("Production Lite"): go or wait?
2. **Trade**: when/how to run the real dealer interviews.
3. **Integration**: which platform first, and the data-egress/liability questions in the AI/Integration design doc.
4. **Tax rule-pack versioning**: who reviews/publishes, source of truth, delivery mechanism, liability wording.
5. Your own click-through review of everything marked "done" above, on real data.
