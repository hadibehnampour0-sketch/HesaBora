# Dakhl — Progress & Review Report (2026-09-25)

> Owner asked for a report when the work was complete. Everything below is committed **locally** on branch `dakhl-foundation`; nothing was pushed; the bare mirror `D:\Backups\Dakhl.git` is in sync.
> Test suite at the time of writing: 1825 tests, 7031 assertions, all passing. No production data was touched; the local dev database was migrated.

## 1. What was delivered (chronological)
| # | Deliverable | Kind | Where to look |
|---|---|---|---|
| 1 | Product vision, forgotten-decision audit (D-074 + Industry Intelligence located), strategy research (4 axes) | docs | `Dakhl_PRODUCT_VISION_AND_STRATEGY_2026-09-25.md`, roadmap §40.1–40.6 |
| 2 | Trade: architecture proposal, **D-099 recorded as PROPOSED (owner-authored)**, D-100 protocol + field kit + desk discovery report, provisional D-101 data model, D-102 impact review (code-grounded) | docs only | `Dakhl_TRADE_*`, `Dakhl_D100_*`, `Dakhl_D101_*`, `Dakhl_D102_*`, Decision Log tail |
| 3 | **Industry Profiles** — data-driven per-trade settings dashboards + calculators (gold, iron, general full; building materials, mobile, auto parts, apparel, food starters; business insights) | code | menu → *Industry Profiles*; `resources/industry-profiles/*.json` |
| 4 | **R-01** forced password change for the default admin | code | `EnsurePasswordChanged`, migration backfill |
| 5 | **Unified design system**: deep-blue light/dark themes, right-side sidebar shell, restyled auth pages/home/admin | code | `resources/css/app.css`, `components/sidebar|header|app-layout` |
| 6 | **Today dashboard** (KPIs, 7-day chart, receivables donut, cheques, overdue customers, getting-started, quick actions) | code | Home |
| 7 | **Phase AI tier 1** deterministic insights (duplicate invoices, buy-price spikes, receivable concentration, bounced cheques), tunable per company | code | "Needs attention" on Home; profile *Smart insights* |
| 8 | **Simple mode**: sidebar menu, wizards for Sell/Buy invoice, New Customer, New Product, Ancillary cost | code | Account settings → Display mode |
| 9 | **Quick payments** (collect / pay) over the existing payment route; **plain-language report** | code | `/quick-payment/{receive|pay}`, `reports/plain` |
| 10 | AI & Integration architecture design; **Integration Hub skeleton** (idempotent event ledger, external references, adapter/handler interfaces, fake-adapter tests) | docs + code (no UI/route) | `Dakhl_AI_AND_INTEGRATION_ARCHITECTURE_DESIGN…`, `app/Services/Integration/` |
| 11 | Small fixes: configs resource routes (500), menu links for Tax Readiness/Workflow Governance | code | — |

## 2. Rules kept throughout
Local-only (no push, no remote); Trade/D-074/D-099: **no Trade code**; no D-0XX text authored by the assistant; Android last; Simple-mode features are presentation layers over the same fields/routes/validation; payments use `PaymentService` unchanged; every new read block is permission-gated per underlying report; every new table is company-scoped. AGENTS.md guardrails #58–#59 added.

## 3. Things that are NOT done (by design or pending owner input)
- **Trade implementation** — gated on real dealer interviews (D-100) and owner approval of D-099. Simulated interviews are hypotheses only.
- **Integration real adapter / handlers** (order → invoice, payout → clearing) — needs platform choice and the answers in the design doc §B9. Only the skeleton exists.
- **AI tier 2/3** (LLM explanations, assisted actions) — needs data-egress/provider decision (design doc §A7).
- **Monetization scoping**, **Android** — deliberately last.
- **Trade-specific product templates** (e.g. gold weight/purity) — Product has no attribute model; belongs with the Trade item-attribute decision.
- **Moadian discount-field bug** — a separate local session was started by the owner (chip task); this branch does **not** contain that fix. Candidate defect: `MoadianService::moadianInvoiceItems` reads a non-existent `$item->discount`.
- **Multi-invoice payment allocation** in quick payments (one invoice per submission).

## 4. What the owner should review (suggested order)
1. Log in; change the default password when prompted (R-01).
2. Account settings → switch to **Simple** display mode; walk: Home (Today, quick actions), *New sale* wizard, *New product* wizard, *Collect money*, *How is my business doing?*.
3. Toggle **dark mode** on Home, an invoice list, a report.
4. *Industry Profiles* → Gold: confirm/adjust the "assumed" defaults; try the calculators.
5. Read the AI/Integration design questions (§A7, §B9) and the D-100 field kit.

## 5. Known caveats to check with real data
- Visual verification was done on static renders (light/dark, desktop/mobile) and stubbed interactions; the assistant does not type passwords, so a full logged-in click-through has **not** been performed by it.
- "Value of my stock" and ledger profit come from existing ledger functions; compare with the warehouse report on real data.
- A reported "site error" could not be reproduced: all 168 parameterless GET routes rendered without a 500 against the local database (after running the pending migrations). If it recurs, send the exact message + URL.
- After pulling new code on any install, run `php artisan migrate` (new tables/columns: industry profiles, must_change_password, integration tables).

## 6. Verification snapshot
Full PHPUnit suite green at the final commit (run before writing this report); Pint clean on all new/changed files (pre-existing style debt elsewhere untouched); whole-app GET probe: 0 failures; tracked-file secret scan: clean.

## 7. Recommended next steps
(a) Real-dealer verification → decide D-099/D-100/D-101 (unlocks Trade). (b) Owner answers to §B9 → first real Integration adapter. (c) Owner review feedback on the new UI → polish pass. (d) Per-company thresholds are done; add accept/reject logging only after the AI inbox decision.
