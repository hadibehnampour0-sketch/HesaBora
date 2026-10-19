# Dakhl / دخل — Canonical Context Index
## Effective: 2026-09-29-r5 (supersedes r4 of 2026-09-12)

This file identifies the authoritative project-context documents for future sessions and coding-agent resets.

> **Why r5 exists:** r4's "Source of Truth Order" pointed at `Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r5.md` and `Dakhl_DECISION_LOG_2026-09-12-r3.md` — **neither file exists**. Both were renamed/rolled forward after r4 was written (the roadmap is now `..._2026-09-20-r14.md`, the log `..._2026-09-12-r4.md`). An agent following r4 literally would conclude the project had no master context and no decision log. r5 fixes the pointers and adds the freshness note below. **r4 is preserved unchanged as history**, per this project's convention.

## Source of Truth Order

1. `Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-20-r14.md`
   - Primary product, architecture, roadmap and decision register. Highest-priority project context.
   - **It accumulates sections chronologically. Older sections are deliberately left in place as historical record, not edited.** Before trusting any "not implemented" / "greenfield" claim in it, grep for the last `Current Verified Override` / `Current Engineering Checkpoint` section and read that one — several early sections (e.g. §15 on sync) are stale by design and say so.

2. `Dakhl_DECISION_LOG_2026-09-12-r4.md`
   - Durable record of locked product and architecture decisions. Use to recover decisions after a session/agent reset.
   - **Now current through 2026-09-29** (entries D-104…D-107 plus five Open Findings). Note the file's own header still reads "Consolidated through 2026-09-12-r4" — that header line is stale, the body is not.

3. `Dakhl_FULL_STATUS_REPORT_2026-09-29.md`
   - **The single best "what do we have / what's done / what's left" answer.** Supersedes the 2026-09-28 report, which is preserved as history.
   - Read this before planning any new work; it is far more current than the roadmap's per-phase sections.

4. `CLOUD_AUTONOMOUS_STATE.md` — running session state. *Was stale through 2026-09-25; a 2026-09-29 catch-up section has been appended.*
   `CLOUD_AUTONOMOUS_EXECUTION_LOG.md` — numbered entry log, **current through Entry 111 (2026-09-29)**.

5. Supporting research documents:
   - `Dakhl_Accounting_Benchmark_Research_2026-09-11.md`
   - `Dakhl_Iran_Tax_Compliance_Research_2026-09-11.md`
   - `Dakhl_MARKET_AND_OPEN_SOURCE_BENCHMARK_2026-09-11.md`
   - `Dakhl_ACCOUNTING_SOFTWARE_MARKET_BENCHMARK_2026-09-29.md` — global + 35 Iranian brands, evidence-graded, with priority-ordered add/improve recommendations. **Research input only — authors no D-0XX text; any resulting work needs its own owner-written decision.**

6. Historical roadmap/addenda remain preserved for audit/history and should not override the newer master context unless explicitly referenced. `archive/` holds superseded originals.

## ⚠ Decision-ID allocation (get this wrong and you create a collision)

The log has a history of colliding decision numbers — once already, when an owner-supplied file proposed D-092..D-096 that were already taken, so numbering restarted at D-099. **Check before assigning a new ID.**

| ID | Status | Topic |
|---|---|---|
| D-070…D-091 | LOCKED | Cheque settlement, sync stages 2.7–2.8, immutability, company-scoped roles |
| D-092…D-098 | (rolled/merged) | Roles/authorization decisions — see the log's "Product Vision Integration Pass" note |
| D-099 | **PROPOSED** | Trade Engine as business capability. *Stays PROPOSED until real dealer interviews exist.* |
| D-100, D-101, D-102 | **RESERVED** | Gold/Silver domain discovery sequence — named as prerequisites in D-099 §12 |
| D-103 | **RESERVED (draft)** | Manufacturing/BOM readiness — proposed in `Dakhl_MANUFACTURING_BOM_RESEARCH_AND_PLAN_2026-09-28.md` §5, not yet ratified |
| D-104…D-107 | IMPLEMENTED / ratification pending | WooCommerce adapter; two-way stock sync + cancellation; Production Lite; distribution gap closure |
| D-108…D-115 | **PROPOSED** | Retail Multi-POS LAN (D-108), Smart SMS & Payment Portal (D-109), Loyalty/Caller-ID (D-110), Shahkar/Bank (D-111), Financing/Interest (D-112), Template Designer (D-113), Vision AI OCR (D-114), Competitor Migration & Health Check (D-115) |

**Next free ID: D-116.**

Note the trap: there are also *files* named `Dakhl_D100_…`, `Dakhl_D101_…`, `Dakhl_D102_…`. Those are the Trade discovery documents, which is why those numbers are reserved.

## Locked Strategic Direction

- Dakhl is the product brand; FreeAmir is upstream/base only.
- Preserve the existing double-entry accounting core.
- One financial truth across Web, Desktop, API, AI, Offline and Online.
- Zero-accounting-knowledge UX: simple for normal users, deep for professionals.
- Tax Compliance is continuous and should produce an audit-ready/defensible file.
- Payment and allocation are separate concepts.
- Bank statement and accounting events are separate concepts reconciled together.
- Cheque Settlement must remain a first-class domain (D-070).
- Offline + Online are first-class runtimes of one product.
- Financial sync is operation-aware, idempotent and auditable; naive LWW is not sufficient.
- Industry Intelligence must support both Managed Providers and Bring Your Own API.
- Dakhl is a modular product with independent subscription/entitlement/license activation per capability.
- External market data must be isolated, cached with freshness/provenance, and must not rewrite historical posted data.
- API and AI use the same Domain/Service Layer; no parallel accounting logic.
- AI must be explainable, permission-aware and auditable.
- The product direction is Financial + Tax + Business Operating System for Iranian SMBs.
- Licensing must separate subscription, entitlement and activation, and support offline verification.
- **A posted financial record is never wrong on purpose** — extended to third-party sources in D-104 (auto-approval requires four safety conditions; anything ambiguous goes to a visible review queue).
- **One hook point beats N integration points** — D-105 puts the stock push on the model every stock service already mutates, rather than editing each service.

## Current Blocking Issues (2026-09-29)

**r4's "Current Blocking Issue" — the packaged-Windows-500-on-invalid-login P0 — is resolved** and no longer belongs here. It is retained in r4 as history.

Current open items, in severity order (full detail in the Decision Log's "Open Findings"):
1. ~~CRITICAL — `AncillaryCostTransactionBuilder` produces an unbalanced document when VAT > 0~~ **REFUTED — no bug.** A later pass traced the real data path: `StoreAncillaryCostRequest::prepareForValidation()` always computes `amount` as the VAT-*inclusive* total, so the builder's customer line already balances. **Acting on the original finding would have double-counted VAT and broken a correct posting.** Coverage gap it pointed at is real and now closed by `tests/Feature/AncillaryCostVatBalanceTest.php`. Do not re-investigate.

2. ~~CRITICAL — `OrderCancelHandler` reintroduces the naive `MAX(number)+1` pattern~~ **FIXED in commit `18b701f7`** (2026-09-29, same day). The void number is now allocated through a locked, `invoice_type=VOID`-scoped query inside the void's own transaction, closing the race, the missing lock, and the cross-company read at once. Fail-first verified by that pass.
3. **Latent — `config('amir.*')` is built only by `ConfigLoader`, which is registered only in the `web` group** (`bootstrap/app.php:54`). ~95 call sites read it; in api/queue/console/sync contexts every lookup returns `null`. Latent, not live, because current paths delegate.
4. **Latent — the outbound stock push can fire before its transaction commits.** `QUEUE_CONNECTION=sync` means `PushProductStockJob` runs inline inside the caller's open transaction; a later rollback leaves the platform holding a quantity that never became true.
5. **Known limitation — `ManufacturingService::produce()` posts no accounting document**, so inventory valuation and the general ledger can legitimately diverge after production. A Phase 2 decision, not a bug.
6. **Process gap — no pre-commit rule requires a Decision Log entry.** Four commits (Integration ×2, Manufacturing, Distribution) landed with no decision recorded. That is the structural cause of the documentation drift r5 exists to correct, and it will recur unless something changes.

## Agent Reset Rule

After a Cloud/OpenCode/session reset:

1. Read `Dakhl_FULL_STATUS_REPORT_2026-09-29.md` first — it is the fastest route to current truth.
2. Read the master context/roadmap's **latest** override section, not its first.
3. Read the Decision Log, especially the Open Findings section at the end.
4. Read supporting research when the task touches those domains.
5. Read the actual repository.
6. **Treat repository state as implementation truth and the master/decision files as product/architecture truth.** Where a Decision Log entry and the code disagree, verify which is actually right before assuming the entry is merely stale — one retrospective transcription in this cycle found a real code defect the entry would have hidden.
7. Do a read-only audit before making changes when requested.
8. Work in small, checkpointed phases.
9. **Check the decision-ID table above before creating a D-0XX entry.**

## Repository Audit Status

The 2026-09-12 Cloud read-only audit's P0 findings (financial-history data-loss risk, missing posted immutability enforcement, systemic float arithmetic, packaged-desktop login-500) were all closed in subsequent phases — immutability by D-084…D-089, float arithmetic by the decimal-typed money/quantity columns, login-500 by the Release Readiness Audit. Re-audit rather than assuming; a fresh read-only sweep found three further real defects in 2026-09-29 that no prior audit had caught.

## Licensing / Commercial Context

The product is intended to support independently sellable modules with separate Subscription/Billing, Entitlement, and License/Activation concepts, including offline signed verification and online refresh/revocation. **Not started.**

## Technical Decision Baseline

- `Dakhl_TECHNICAL_DECISIONS_RESEARCH_2026-09-12.md` — the research input; the master context converts it into locked directions.
- Locked directions carried forward: local-first + server-authoritative reconciliation; organization/tenant-owned subscriptions and modular entitlements; signed offline licenses with activation limits; shared SaaS DB with strict tenant isolation as the initial cloud model; versioned Tax Rule Packs separate from payroll TaxSlab; industry extensions without core forks; Managed + BYO market-data providers through adapters; AI Gateway with data-minimization/privacy policy; versioned data-portability package with staged import; explicit period-close semantics.

## Engineering Discipline That Has Held Up (carry this forward)

Findings in this project's history share a shape worth preserving: **almost every real defect was found by a fail-first test written for a *different* feature, or by reading code closely during a refactor — not by the tests aimed at the feature itself.** Examples: the Spatie wildcard-cache leak, the SQLite migration that silently wipes role assignments, the cash-flow sign inversion, the identifier-length failure visible only on MariaDB. Two consequences for future work: keep writing tests for the thing you are *building* while staying suspicious that they prove less than they appear to, and treat any shared helper you reuse or extend as a fresh code-review target.
