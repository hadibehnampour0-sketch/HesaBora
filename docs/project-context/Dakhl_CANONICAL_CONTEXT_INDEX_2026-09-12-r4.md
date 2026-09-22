# Dakhl / دخل — Canonical Context Index
## Effective: 2026-09-12-r4 (r3)-r2

This file identifies the authoritative project-context documents for future sessions and coding-agent resets.

## Source of Truth Order

1. `Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r5.md`
   - Primary product, architecture, roadmap and decision register.
   - Highest-priority project context.

2. `Dakhl_DECISION_LOG_2026-09-12-r3.md`
   - Durable record of locked product and architecture decisions.
   - Use this to recover decisions after session/agent resets.

3. Supporting research documents:
   - `Dakhl_Accounting_Benchmark_Research_2026-09-11.md`
   - `Dakhl_Iran_Tax_Compliance_Research_2026-09-11.md`
   - `Dakhl_MARKET_AND_OPEN_SOURCE_BENCHMARK_2026-09-11.md`

4. Historical roadmap/addenda remain preserved for audit/history and should not override the newer master context unless explicitly referenced.

## Locked Strategic Direction

- Dakhl is the product brand; FreeAmir is upstream/base only.
- Preserve the existing double-entry accounting core.
- One financial truth across Web, Desktop, API, AI, Offline and Online.
- Zero-accounting-knowledge UX: simple for normal users, deep for professionals.
- Tax Compliance is continuous and should produce an audit-ready/defensible file.
- Payment and allocation are separate concepts.
- Bank statement and accounting events are separate concepts reconciled together.
- Cheque Settlement must become a first-class domain.
- Offline + Online are first-class runtimes of one product.
- Financial sync is operation-aware, idempotent and auditable; naive LWW is not sufficient.
- Industry Intelligence must support both Managed Providers and Bring Your Own API.
- Dakhl is a modular product with independent subscription/entitlement/license activation per capability.
- External market data must be isolated, cached with freshness/provenance, and must not rewrite historical posted data.
- API and AI use the same Domain/Service Layer; no parallel accounting logic.
- AI must be explainable, permission-aware and auditable.
- The product direction is Financial + Tax + Business Operating System for Iranian SMBs.
- Licensing must separate subscription, entitlement and activation, and support offline verification.

## Current Blocking Issue

Packaged Windows build can show `500 Internal Server Error` for invalid login credentials. This is a P0 release bug and must be fixed without introducing end-user PHP/MariaDB installation requirements.

## Agent Reset Rule

After a Cloud/OpenCode/session reset:

1. Read the master context/roadmap.
2. Read the decision log.
3. Read supporting research when the task touches those domains.
4. Read the actual repository.
5. Treat repository state as implementation truth and the master/decision files as product/architecture truth.
6. Do read-only audit before making changes when requested.
7. Work in small, checkpointed phases.

## Important Principle

> Complexity is hidden; capability is not.

> One financial truth, multiple experiences: Simple UI, Accountant UI, API, Offline, Online and AI.


## Repository Audit Status

The 2026-09-12 Cloud read-only audit is authoritative for current implementation gaps. Key P0 findings are financial-history data-loss risk, missing posted immutability enforcement, systemic float arithmetic, and the unresolved packaged-desktop Login-500 root cause.

## Licensing / Commercial Context

The product is intended to support independently sellable modules with separate Subscription/Billing, Entitlement, and License/Activation concepts, including offline signed verification and online refresh/revocation.


## Technical Decision Baseline
The latest technical-decision research is recorded in:
- `Dakhl_TECHNICAL_DECISIONS_RESEARCH_2026-09-12.md`

The Master Context + Roadmap r7 converts that research into project-level locked directions.

Additional locked directions now include:
- Local-first + server-authoritative reconciliation for Offline/Online
- Organization/Tenant-owned subscriptions and modular entitlements
- Signed offline licenses and activation limits
- Shared SaaS DB + strict tenant isolation as the initial cloud model
- Versioned Tax Rule Packs separate from payroll TaxSlab
- Industry extensions without core forks
- Managed + BYO market-data providers through adapters
- AI Gateway with data minimization/privacy policy
- Versioned data portability package + staged import
- Explicit period-close semantics
