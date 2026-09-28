# Dakhl — Integration: platform plan + the two open policy decisions (2026-09-28)

Owner directed three things in chat: (1) plan for a few main Iranian/common platforms, (2) imported invoices should auto-approve by default, (3) warehouse stock must sync **both ways** between Dakhl and each platform. This document is that plan. **No code written here** — it extends `Dakhl_AI_AND_INTEGRATION_ARCHITECTURE_DESIGN_2026-09-25.md` Part B with real, fact-checked platform specifics and the two policy designs, ready to transcribe into a Decision Log entry once the owner confirms it (same pattern as every other D-0XX in this project — the owner authors the final text, this is the input).

## 1. Platforms planned (fact-checked against their real developer docs, not assumed)

| Platform | Reality check | Auth | What it exposes | Build order |
|---|---|---|---|---|
| **WooCommerce** | Self-hosted WordPress plugin — the most common way small/medium Iranian shops run their own online store. Full REST API v3 (products, orders, stock) + native webhooks (`order.created`, `order.updated`, `order.deleted`). [woocommerce.github.io](https://woocommerce.github.io/woocommerce-rest-api-docs/) | API key/secret, per-store, self-service (no platform approval needed) | Orders, order status, product stock quantity (read + write) | **1st** — fastest to a working, testable adapter; nothing to wait on from a third party |
| **Basalam (باسلام)** | Real, documented, production developer platform ("Salam API") with official **PHP and Python SDKs**. [developers.basalam.com](https://developers.basalam.com/) | OAuth2, acting as an agent on the seller's behalf | Core service (user/store/**product** — create/edit/delete, i.e. stock is writable), Order + Order Processing (listing/status), Webhook subscriptions (event push) | **2nd** — real API, PHP SDK matches our stack, but needs OAuth app registration with Basalam first |
| **Digikala Seller (دیجی‌کالا)** | A real "DK Marketplace Open API" (OpenAPI 3.0) exists at `seller.digikala.com/open-api/v1/doc/`. Iran's largest marketplace by far, so highest potential value — but I could **not** fully enumerate its endpoints from outside (the doc page is a JS-rendered Swagger UI); seller-API access on marketplaces like this is typically gated behind an approved seller account, which is a real access hurdle, not just a technical one. [seller.digikala.com](https://seller.digikala.com/open-api/v1/doc/) | Unconfirmed from outside — needs a logged-in seller accepted into their open-API program | Unconfirmed exact scope — needs a hands-on technical spike once we have seller access | **3rd** — highest value, highest uncertainty; do not promise this to a customer before a real spike confirms the endpoints |

Everything else (other Iranian marketplaces, POS systems, payment gateways) goes through the **same adapter interface** — no core-code change needed to add a 4th, 5th platform later, only a new adapter class. Not building a "generic" adapter now; there is nothing to generalize until a second real adapter exists (same discipline as guardrail #53: don't build the abstraction before you have two concrete cases to abstract from).

## 2. Decision: imported orders auto-approve by default (owner's direction)

Accepted as directional. The engineering shape that keeps this safe:

- An imported order **auto-creates and auto-approves** a Sell invoice through the unchanged `InvoiceService` approval path — **only when everything about it is unambiguous**:
  1. every line item resolves to a known Dakhl product (via the external-reference mapping, §B5 of the architecture doc),
  2. the customer resolves or can be safely auto-created (matched by phone/national ID, or created fresh — never silently merged into an existing different customer),
  3. price on the platform is within a configurable tolerance of Dakhl's price (catches a stale/mismapped SKU before it posts a wrong number),
  4. there is enough stock in Dakhl to fill it (respects the existing oversell guard — same as any other sell invoice).
- **If any of those fail, the order is held, visibly, in a review queue** — same UI pattern already built for the Moadian work queue (Entry 104): a real invoice engineer/owner can fix the mapping/price/stock and release it with one click. It is never silently dropped, and never force-posted with guessed data.
- This is not "manual-first with an auto option" — it is **automatic by default**, with a safety valve only for the genuinely ambiguous cases, which is exactly what "why have accounting software if it's not automatic" is asking for, while keeping the same non-negotiable rule this whole project has followed since D-086–D-089: **a posted financial record is never wrong on purpose.**
- Cancellations/refunds follow the same rule: an unambiguous cancellation auto-voids/returns through the existing void/return-invoice path; an ambiguous one (e.g. already paid out) goes to the same review queue.

## 3. Decision: stock syncs both ways (owner's direction)

Two-way sync between Dakhl and N platforms needs one rule for what "the number" is at any moment, or two channels can both think they have the last unit. The design:

- **Dakhl's own stock ledger is the single number that gets aggregated and pushed out** — not because Dakhl is declared "boss," but because it is the one place that already sees every channel's sales: a shop-counter sale decrements Dakhl's stock directly; a platform sale decrements it too, through the exact same invoice path as §2. Every channel's sale ends up as one subtraction from one number.
- **Outbound half (new — does not exist yet):** whenever a mapped product's Dakhl stock quantity changes for any reason (sale, return, transfer, manual adjustment), Dakhl pushes the new available quantity to every platform that product is mapped to. `IntegrationAdapter` today only defines `normalize()` (inbound); it needs a second method (e.g. `pushInventory(string $externalId, float $quantity): void`) — a small, additive interface change, not a redesign.
- **Inbound half (already designed):** a platform order decrements Dakhl's stock through the normal invoice flow (§2) — this is already what Part B's `OrderCreated` → Sell-invoice mapping does; nothing new needed here.
- **Reconciliation pass**, not just event-driven pushes: webhooks are at-least-once and can be delayed or dropped, so a periodic full "re-push every mapped product's current quantity" job (same idea as `IntegrationHub::retryWaiting()`) catches any drift instead of trusting events alone.
- **Honest limitation, stated plainly, not hidden**: two genuinely simultaneous sales for the last unit (one in-shop, one online, seconds apart) cannot be made impossible over a network with webhook latency — no integration anywhere solves this perfectly. Two mitigations, both standard practice and both cheap to add: (a) push stock updates immediately, not batched, so the window is small; (b) an optional per-product "safety buffer" the merchant can set (e.g. always show 1 fewer unit available online than Dakhl actually has), which the merchant controls, not something invented silently.
- This design reuses the existing `ExternalReferenceService` (product↔SKU mapping) and `IntegrationHub` idempotency exactly as already built — no parallel mapping table, no second idempotency mechanism.

## 4. What is still genuinely open (cannot be planned around, needs the owner or a real account)

- Basalam OAuth app registration and Digikala seller-API access are **account/business steps**, not engineering ones — need the owner (or whoever holds the merchant account) to actually register.
- Price-tolerance percentage and the stock safety-buffer default are business calls, not technical ones — proposed defaults (price tolerance 2%, no buffer by default) but not asserted as final.
- Whether Basalam/Digikala's `product` write scope actually allows changing *just* stock quantity without resubmitting the whole listing is not confirmed from outside their docs — first real technical spike (WooCommerce, since it needs no approval) should validate the pattern before assuming it holds for the other two.

## 5. Recommended next step

Build the **WooCommerce adapter first** (real, well-documented, self-service, sandbox-testable with a local WooCommerce install) as the proof of both halves of this design — inbound order → auto-approved invoice, and outbound stock push — before spending any effort on Basalam/Digikala's account-approval process. Everything built for WooCommerce (the safety-valve queue, the price/stock checks, the outbound push mechanism, the reconciliation job) is reusable as-is for the next adapter; only `normalize()`/`pushInventory()` per platform differ.
