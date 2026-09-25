# Phase AI & Phase Integration — Architecture Design (r1, 2026-09-25)

> **Status: DESIGN PROPOSAL, documentation only.** No code, schema or route. No D-0XX text drafted (owner authors decisions). Options are listed with a recommendation; the owner chooses.
> Inputs: roadmap §39.4–§39.5, Decision Log (AI/Copilot, API, Modular Licensing sections), existing code precedents (TaxAlertEngineService, sync outbox/inbox D-076–D-090, `ReportExportService`, activity log), earlier market research (Ramp/Brex/Puzzle/Vic.ai, A2X/Synder).
> Standing constraints: additive layers only; same services/permissions/audit as the rest of the app; offline-capable core; never gate a feature a free user already had (§39.6); Android last.

---

# Part A — Phase AI (Financial Copilot)

## A1. Principles (already locked in the Decision Log, restated as design rules)
1. AI is **not a parallel accounting system** and **not a chat widget first**. It explains, detects, recommends; it never posts.
2. Every AI output is a **suggestion with reason, evidence links and a confidence tier**; a human accepts or rejects; both are logged.
3. AI output passes through the **same permission gates** as the report it is derived from (as the Today dashboard already does: a block appears only if the user could open the underlying report).
4. The product must be **fully useful with AI switched off**; the AI layer degrades to nothing, never to an error.

## A2. Three tiers (build order)
| Tier | What | LLM needed? | Risk |
|---|---|---|---|
| T1 Deterministic detectors | Rule/statistics on existing data: duplicate invoice/payment, unusual price vs product history, sales/purchase spike, customer payment slowing (ageing trend), margin drop, stock-out risk, cheque bounce pattern, VAT/tax-readiness regressions | **No** | Low; testable to exact numbers |
| T2 Explanation & classification | Turns a T1 finding into a plain-language reason; suggests category for an expense/ancillary cost; drafts a reminder message | Optional (provider adapter) | Medium; text only, never posts |
| T3 Assisted actions | "Create an invoice for X" style commands | Yes | High; last, behind explicit confirm; research says last-value pattern |

**Recommendation:** ship T1 first (no provider, no privacy question, immediately testable), surfaced in the Today dashboard's attention list and the existing bell. T2 only after T1 proves value and the provider/privacy design below is accepted.

## A3. Where T1 plugs into the code (facts)
- `TaxAlertEngineService` already implements "rules → insight row with `dedup_key` + 24h suppression → bell" for tax conditions. It is tax-specific (`tax_alerts`). Options:
  - (a) Generalize into a neutral `insights` table (`type`, `severity`, `evidence` JSON, `dedup_key`, `status`) and migrate tax alerts onto it — cleaner, but touches shipped P12 code.
  - (b) **Recommended:** leave `tax_alerts` alone; add a sibling detector service and table for business insights reusing the same pattern; unify later only if duplication hurts.
- Detectors read through existing services (AgeingService, ChequeRiskDashboardService, InvoiceDashboardService, ProductService cost data) — no new read paths into the ledger.
- Company scoping: every table `company_id` + FiscalYearScope (guardrails #22/#32/#36); detectors run per company.
- Scheduling: `QUEUE_CONNECTION=sync` today → run detectors from an Artisan command via the scheduler, or lazily on Today load with a cache TTL; avoid long web requests.

## A4. Provider/adapter design (T2+)
- `AiProvider` interface: `complete(prompt, options)`, `classify(text, labels)`, capability flags, timeout, max tokens, cost cap; adapters: BYO OpenAI-compatible endpoint, Anthropic API, local model (offline). Same pattern as the Market Data Provider/Adapter (encrypted credentials, connection test, primary/fallback, circuit breaker).
- **Privacy by default:** prompts built from an allow-listed field set; no national IDs, bank accounts or full names unless the company opts in; per-company switch `ai_enabled` (default OFF); every call logged (provider, model, token count, redaction profile, latency) **without** storing raw customer data in the log beyond an evidence reference.
- **Iran-specific constraint (unverified):** reachability and legality of foreign AI APIs for Iranian users must be verified before promising T2; a local/BYO-endpoint path must exist from day one.

## A5. Audit & governance
- Activity log source `ai`: suggestion id, detector/model, evidence refs, actor decision (accepted/rejected/ignored), timestamp. Rejections feed a suppression list (do not re-raise the same insight for N days).
- Metrics per detector: raised / accepted / rejected → precision estimate; a detector under a threshold is auto-disabled and flagged (kill switch per detector and global).
- No vendor accuracy claims in-product (research: all published figures are self-reported).

## A6. First slice proposal (needs owner go-ahead)
"Attention" insights on the Today dashboard, deterministic only: (1) possible duplicate invoice (same customer, amount, ±1 day), (2) customer's average days-to-pay worsened ≥ X%, (3) purchase price of a product ≥ Y% above its trailing average, (4) cheque bounce concentration. Thresholds are per-company settings (Industry-Profile-style definitions, so no code change to tune).

## A7. Open questions for the owner
Should T1 insights reuse the bell or have their own inbox? Acceptable data-egress policy for T2? BYO-only or a managed provider? Which 3–4 detectors matter most to real shop owners (validate by interview, like D-100)?

---

# Part B — Phase Integration (Integration Hub)

## B1. Goal and boundary
Bring orders/refunds/payouts/stock from external sales channels into Dakhl **through existing accounting paths**, never a second engine. Out of scope: payment-gateway execution, shipping, catalogue authoring, marketplace listing.

## B2. Reuse of the existing sync infrastructure (facts)
- Sync engine already provides: outbox/inbox tables (`operation_id` unique, `entity_uuid`, JSON payload, `base_sync_version`), idempotency, Business-Rejection vs Technical-Retry vs Data-Conflict distinctions (D-076), and an entity registry (`SyncPilotService::SYNCABLE_ENTITIES`).
- Guardrails that bind the hub: invoices are created only via `InvoiceService`; payments only via `PaymentService::createPayment()/allocate()`; posted financial records are immutable (D-084); Payment/Cheque lifecycle untouched. An integration event that would violate these is a **visible rejection**, never a workaround.
- The hub is a **new inbound channel** feeding those services, not a new sync entity type. It should reuse the idempotency *pattern* (unique external event key) without registering in `SYNCABLE_ENTITIES`.

## B3. Canonical events and adapters
`OrderCreated`, `OrderCancelled`, `RefundIssued`, `PayoutSettled`, `InventoryChanged`, `CustomerUpserted`. One **adapter per platform** translates platform payloads to canonical events; the core consumes only canonical events. Webhooks = fast path; scheduled polling = reconciliation fallback (webhooks are at-least-once and give no state guarantee).

## B4. Accounting mapping (from A2X/Synder patterns)
- Order → **draft** Sell invoice via `InvoiceService` (approval per company policy, D-086–D-089 boundaries respected).
- Marketplace fees, shipping, discounts, gift cards → separate lines/expense entries; never netted into revenue.
- **Clearing account**: orders/fees/refunds accumulate in a configured clearing subject; a payout posts one summary movement clearing → bank, matched to the bank statement by the existing bank-reconciliation feature. Residual in clearing at period end = unreconciled fees/refunds (visible report).
- Refund after payout → return-sell invoice through the existing return path.

## B5. Identity, idempotency, mapping
- Shared **external reference** table (proposal): `company_id`, `source_system`, `external_id`, `entity_type`, `entity_id`, unique `(company_id, source_system, external_id, entity_type)`. The same table serves the Migration platform's `external_id/source_system` requirement — one mechanism, two features.
- Event dedup key `(source_system, external_id, event_type)`; same key + same payload = idempotent no-op; same key + different payload = data conflict surfaced (D-080 spirit); never dedupe by payload similarity.
- Out-of-order events (refund before order) → `Pending Dependency`, never dropped or force-applied.

## B6. Security
Per-company encrypted credentials (same store as market/AI providers); HMAC signature verification on webhooks; replay window; rate limiting and payload size caps; secrets never in logs (guardrail #45-style redaction tests); tenant resolution from the registered connection, never from request data.

## B7. Candidate first platform (unverified, to be validated)
WooCommerce is the likeliest fit (self-hosted, REST + webhooks, widely used by small Iranian shops — **market share unverified**). Iranian marketplaces/gateways (e.g. Basalam, Digikala seller tools, payment PSPs) must be verified for real APIs and terms before any promise; no platform commitment is made here.

## B8. Failure modes to design tests for
Duplicate delivery; retry after partial failure; refund before/after payout; product not mapped; customer conflict (same phone, two records); price/tax mismatch vs platform; company/fiscal-year boundary (order dated in a closed year); rate-limit and outage recovery via polling.

## B9. Open questions for the owner
First platform and target segment; invoice auto-approval policy for imported orders; whether stock is authoritative in Dakhl or in the channel (two-way inventory is a separate, later decision); who owns the clearing-account setup (wizard vs manual).

---

# Part C — Sequencing
1. (Now) Owner reviews A6 and B7/B9 answers. 2. Phase AI T1 slice (deterministic insights) — small, testable, no provider. 3. External-reference table + Integration event skeleton with a **fake adapter** and tests (proves idempotency/clearing flow before any real platform). 4. First real adapter. 5. AI T2 only after the provider/privacy decision. Monetization scoping stays last (§39.8); Android last.
