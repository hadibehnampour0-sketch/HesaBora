# Dakhl — Technical Decisions & Architecture Recommendations
## Research baseline: 2026-09-12

## Executive conclusion
Dakhl should use a hybrid architecture: local-first user experience, server-authoritative cross-device/account state, shared Domain/Service Layer, operation-aware synchronization, company/tenant isolation, modular entitlements, versioned tax rules, provider adapters, and policy-controlled AI.

The existing accounting spine `Subject -> Document -> Transaction` should remain protected. The main modernization effort should harden and extend the layers around that spine rather than replacing it.

## Decision 1 — Offline/Online authority
### Recommended baseline
Use **local-first + server-authoritative reconciliation**.

- Offline device may create valid local business operations and post them to its local ledger when local invariants pass.
- Every local operation receives a globally unique operation ID and an outbox record.
- When online, the server re-validates the operation, enforces idempotency, commits it to the canonical online ledger, and acknowledges it.
- The device keeps two distinct concepts: financial/business status and sync status (`synced`, `pending`, `conflict`, `rejected`).
- Electronic tax-system status must never be claimed as official while the external authority has not acknowledged it.
- Cross-device truth and legally/externally authoritative states are server-authoritative after reconciliation.
- Posted financial history is immutable; conflict resolution never silently edits a posted financial event.

This combines the local-first philosophy seen in Actual Budget with the server-authoritative/custom conflict patterns documented by PowerSync. Actual stores data locally and syncs changes in the background; PowerSync documents business-rule validation, idempotency and server-side conflict handling as application responsibilities.

Invoice numbering: use UUID as canonical identity. Online official numbering can be allocated centrally. Offline devices should receive reserved number ranges while connected; if no official range is available, use a clearly marked provisional offline reference and do not represent it as an officially accepted electronic tax invoice until synchronization/submission succeeds.

## Decision 2 — Commercial model
### Recommended baseline
The commercial owner of a subscription should be the **Organization/Tenant**, not an individual user and not a device.

Recommended hierarchy:
`Organization -> Companies -> Users/Seats -> Subscriptions -> Entitlements -> Licenses/Activations`

- Core Accounting is the base product.
- Sales, Inventory, Tax, Moadian, CRM, HR/Payroll, Market Intelligence and AI may be sold as add-ons.
- Bundles may package multiple modules for an industry.
- Monthly and yearly subscriptions are supported.
- Yearly should be the preferred commercial offering, with monthly available.
- Adding a module mid-cycle should normally be prorated immediately or at the next billing point according to a configurable policy.
- Downgrades/removals should normally take effect at the next renewal to avoid destructive mid-cycle behavior.
- Seats are an optional commercial dimension; devices are primarily an activation/license limit, not a default billing unit.
- Billing provider is an interchangeable integration; do not couple the product architecture to Stripe/Paddle or any foreign provider.

This follows the separation used by Stripe between products/prices and entitlements and by Lago between usage/subscriptions, entitlements and billing. Paddle's subscription model also demonstrates explicit proration and scheduled changes.

## Decision 3 — Serial / License role
### Recommended baseline
A serial/license key is **not** the authority. The entitlement is the authority.

Target:
`Subscription -> Entitlement -> Signed License -> Activation -> Device`

- License payloads should be cryptographically signed, preferably with Ed25519.
- Clients should contain only the public verification key.
- Online activation is used when connectivity exists.
- Offline clients verify a cached signed entitlement locally.
- Default desktop offline grace period: 14 days after the last successful license refresh; make policy configurable per plan.
- Module expiry must never delete data.
- After expiry, access may become read-only/limited until renewal.
- Activation/deactivation and device transfer are supported.
- Server-side revoke/renewal takes effect when the device reconnects.
- Licensing security is a practical protection, not a promise against determined reverse engineering.

Keysmith and Susi are strong architectural references for signed entitlements, device activation, offline verification and grace/lease concepts.

## Decision 4 — Cloud / tenant architecture
### Recommended baseline
Do **not** move Dakhl to a database-per-customer model initially and do **not** switch away from MariaDB/MySQL merely for this purpose.

Use a shared application/database cluster with strict tenant scoping:
`Organization/Tenant -> Company -> records`

- Introduce Organization/Tenant as the commercial/security boundary above the current Company concept.
- Keep the current Company/fiscal-year behavior intact during the first migration; normalize fiscal-year modeling only after a dedicated data-model audit.
- Every tenant-owned table should have a resolvable tenant boundary, directly or through a mandatory Company/Organization relation.
- Enforce isolation in middleware, service/query scopes, authorization and tests.
- Treat tenant isolation as a security invariant, not a UI convention.
- Store evidence/files under tenant-scoped paths/objects.
- Offer dedicated database deployment later for large enterprise/self-hosted customers if economically justified.

Odoo demonstrates multi-company data isolation inside one database through company fields and security rules. Frappe demonstrates the opposite extreme with database-per-site multitenancy. For Dakhl's SMB SaaS start, shared DB + strict tenant scoping is the better cost/complexity tradeoff; enterprise isolation can be an optional deployment tier later.

## Decision 5 — Tax governance
### Recommended baseline
Build a **Tax Rule Pack / Tax Governance system**, not a hard-coded tax calculator.

Each rule should carry at least:
- jurisdiction
- tax type
- taxpayer/activity scope
- effective-from / effective-to
- rule priority
- calculation method
- exemptions/thresholds
- source law/circular reference
- source publication date
- implementation version
- approval status
- regression test cases

Tax rules should be released as versioned/signed rule packs that can be updated without rewriting the accounting core. Offline clients can receive signed rule-pack updates when connected.

Do not reuse `TaxSlab` as the general tax engine; keep payroll withholding separate and adapt it later if needed.

The current Iranian legal framework makes this direction particularly important: the amended Article 19 of the Taxpayer/Store Terminals law links acceptance without ordinary examination to compliance and an electronically grounded return, while retaining a random-review mechanism; Article 20 provides a legal route for exemption from presenting books/documents for eligible taxpayers using accounting software approved by the Taxpayer System steering group and complying with the law. Therefore Dakhl should pursue technical readiness for these conditions but must not claim legal approval/certification until formally obtained.

## Decision 6 — Industry extension architecture
### Recommended baseline
Use **internal modular extensions first, separate packages later**.

Target:
`Core Domain -> Industry Profile -> Industry Capabilities/Rules/Widgets/Providers`

Industry Profile can control:
- dashboard widgets
- calculator availability
- vocabulary/defaults
- product attributes
- tax classification defaults
- provider mappings
- pricing intelligence
- recommended bundles

Industry Profile must not fork the accounting engine.

Odoo is a useful reference because modules can add new business logic, views, data and country-specific accounting configuration without replacing the whole core. ERPNext's own modularization discussions also reinforce the UX benefit of exposing only the modules a customer needs.

## Decision 7 — Market-data providers
### Recommended baseline
Use a **Provider/Adapter + normalized MarketQuote** architecture.

Two supported modes:
1. Managed Provider — turnkey Dakhl connection.
2. Bring Your Own API — customer-defined endpoint/provider/credentials.

For web clients, server-side adapters should be the default because arbitrary third-party APIs may not support browser CORS/auth safely. Desktop may connect directly only where the provider explicitly supports safe direct access.

Credentials:
- encrypted at rest
- tenant scoped
- never logged
- masked in UI
- connection-testable

Data:
- normalized instrument/price/unit/currency/timestamp/provider/freshness
- primary/fallback provider
- cache
- freshness indicator
- provenance

Market data can suggest prices and drive intelligence, but never silently rewrite historical financial records. When a market quote materially influences a transaction, store a timestamped snapshot/provenance.

OpenBB is a useful reference because it separates providers from common core models and supports custom backends for user-defined data sources.

## Decision 8 — AI privacy and execution
### Recommended baseline
Introduce an **AI Gateway / AI Policy Layer** with three modes:

1. Local AI where the device has adequate model/runtime capability.
2. Dakhl-managed AI provider.
3. Bring Your Own AI API.

Default privacy rule:
**Do not send the full company database to an AI provider.**

Instead:
- fetch only the records needed for the task
- minimize/redact sensitive fields where possible
- prefer structured tool calls over giant free-form prompts
- require user confirmation for consequential financial actions
- enforce the same permissions as the human user
- record reasoning/evidence/confidence metadata for recommendations
- maintain audit records for executed actions
- make retention/provider choices visible to the organization administrator

OpenAI's current API policy says API inputs/outputs are not used for training by default, but API requests may still generate abuse-monitoring logs or application state depending on endpoint/settings; zero-data-retention controls have eligibility and endpoint-specific caveats. Therefore Dakhl must not advertise blanket "no data leaves the system" claims for cloud AI. The architecture should make provider/retention policy explicit and swappable.

Local AI can be an optional capability rather than a hard requirement for the first release.

## Decision 9 — Data portability
### Recommended baseline
Create a first-class **Dakhl Data Package** for export/import.

Export should have two forms:
- Human export: PDF/Excel/CSV reports.
- Full machine export: versioned package containing structured data, attachments/evidence, metadata, audit history references, schema version and checksums.

Import must be staged:
`Source -> Mapping -> Validation -> Preview -> Commit`

Never import directly into posted financial tables.

Imported records should preserve original source identifiers and source-system metadata so migrations remain auditable.

ERPNext demonstrates the practical value of spreadsheet-based import/export for migration and bulk setup; Dakhl should go further with a complete structured package because financial/tax evidence and audit history need preservation too.

## Decision 10 — Product architecture / release scope
### Recommended baseline
Do not wait for every future feature before declaring a stable product. Build in layers.

Immediate production-hardening layer:
- P0 data-loss protection
- posted-document immutability
- decimal-safe money arithmetic
- login 500 root-cause/fix
- cheque/payment correctness

Then foundation:
- Payment Allocation
- Bank Reconciliation
- AR/AP
- offline/online architecture validation
- tenant boundary

Then strategic layers:
- Tax Compliance
- Evidence/Audit Ready
- Zero-Knowledge UX
- Market/Industry Intelligence
- Licensing/Entitlement
- AI/Copilot

Every later layer must use the same Domain/Service Layer.

## Why these decisions fit Dakhl
They preserve the strengths already verified in the repository: a real double-entry core, derived balances from transactions, Persian/RTL/Jalali localization, Moadian integration, broad SMB ERP coverage and an embedded desktop runtime. The main weakness is not the accounting engine itself; it is the missing control, synchronization, compliance and product layers around it.

## Sources
1. Actual Budget — local-first sync and architecture: https://actualbudget.org/docs/vision/ and https://actualbudget.org/docs/getting-started/sync/
2. PowerSync — custom conflict resolution, idempotency and server-side processing: https://docs.powersync.com/handling-writes/custom-conflict-resolution
3. Odoo — multi-company security and modular architecture: https://www.odoo.com/documentation/18.0/developer/howtos/company.html and https://www.odoo.com/documentation/18.0/developer/tutorials/server_framework_101/01_architecture.html
4. Frappe/ERPNext — payment reconciliation, dimensions, data import/export: https://docs.frappe.io/erpnext/payment-reconciliation, https://docs.frappe.io/erpnext/accounting-dimensions, https://docs.frappe.io/erpnext/data-import
5. Keysmith — signed licenses, entitlements, device activation, offline verification: https://github.com/ik-labs/keysmith
6. Susi — desktop/offline licensing, leases and grace periods: https://github.com/Xikaku-Inc/susi
7. Lago — subscriptions, entitlements and billing: https://github.com/mirrored-projects/Lago
8. Stripe — products/prices and entitlements: https://docs.stripe.com/products-prices/how-products-and-prices-work and https://docs.stripe.com/billing/entitlements
9. Paddle — subscription lifecycle and proration: https://developer.paddle.com/concepts/subscriptions/proration/
10. OpenBB — provider/custom backend architecture: https://docs.openbb.co/workspace/developers/data-integration and https://github.com/OpenBB-finance/backends-for-openbb
11. OpenAI — API data controls and retention: https://platform.openai.com/docs/models/default-usage-policies-by-endpoint
12. Iran Taxpayer/Store Terminals law current consolidated text (including 1404 amendments) and Article 19/20: https://www.irancodify.com/b/%D9%82%D8%A7%D9%86%D9%88%D9%86_%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%D9%87_%D9%87%D8%A7%DB%8C_%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87%DB%8C_%D9%88_%D8%B3%D8%A7%D9%85%D8%A7%D9%86%D9%87_%D9%85%D9%88%D8%AF%DB%8C%D8%A7%D9%86_%D9%85%D8%B5%D9%88%D8%A8_1398-07-21
13. Article 19 amended 1404 text: https://zamani.tax/articles/%D9%82%D8%A7%D9%86%D9%88%D9%86-%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%D9%87-%D9%87%D8%A7%DB%8C-%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87%DB%8C-%D9%88-%D8%B3%D8%A7%D9%85%D8%A7%D9%86%D9%87-%D9%85%D9%88%D8%AF%DB%8C%D8%A7%D9%86/%D9%85%D8%A7%D8%AF%D9%87-19-%D9%82%D8%A7%D9%86%D9%88%D9%86-%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%D9%87-%D9%87%D8%A7%DB%8C-%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87%DB%8C-%D9%88-%D8%B3%D8%A7%D9%85%D8%A7%D9%86%D9%87-%D9%85%D9%88%D8%AF%DB%8C%D8%A7%D9%86
14. Article 20 approval condition: https://www.irancodify.com/b/%D9%82%D8%A7%D9%86%D9%88%D9%86_%D9%BE%D8%A7%DB%8C%D8%A7%D9%86%D9%87_%D9%87%D8%A7%DB%8C_%D9%81%D8%B1%D9%88%D8%B4%DA%AF%D8%A7%D9%87%DB%8C_%D9%88_%D8%B3%D8%A7%D9%85%D8%A7%D9%86%D9%87_%D9%85%D9%88%D8%AF%DB%8C%D8%A7%D9%86_%D9%85%D8%B5%D9%88%D8%A8_1398-07-21
