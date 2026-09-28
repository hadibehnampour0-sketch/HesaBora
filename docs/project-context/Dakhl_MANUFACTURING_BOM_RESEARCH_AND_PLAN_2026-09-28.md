# Dakhl — Manufacturing / BOM (فرمول ساخت): research, fact-check and phased plan (2026-09-28)

Owner supplied two rounds of external research on adding a Manufacturing/BOM module to Dakhl and asked for an independent verdict before executing anything. This document is that verdict: what in the supplied research holds up, what needs correcting against Dakhl's actual code, and what to build now vs. later. **No D-0XX decision text is authored here** — a draft is proposed at the end for the owner to review/author, per house rule.

## 1. Verdict

**Worth doing. The market thesis is sound and the technical model is the industry-standard one**, not something invented for this chat. Verified independently (not just trusting the pasted text):

- **Sepidar** (سپیدار سیستم): production package explicitly does BOM ("فرمول ساخت"), automatic cost calculation, waste/scrap handling (either as "consumption deviation" or moved to a waste warehouse), min/max stock, demand forecasting from sales/production history. [sepidarsystem.com](https://www.sepidarsystem.com/products/sepidar/production-accounting-software/)
- **Holoo** (هلو تولیدی): "فرمول تولید" per product; **materials are deducted automatically at the time the finished product is sold**, not at a separate production event — a materially simpler (and different) model than Sepidar/ERPNext/Odoo. Also offers "تخمین تولید" (how many units can be made from current raw-material stock). [holoo.co.ir](https://holoo.co.ir/product/%D9%86%D8%B1%D9%85%D8%A7%D9%81%D8%B2%D8%A7%D8%B1-%D8%AD%D8%B3%D8%A7%D8%A8%D8%AF%D8%A7%D8%B1%DB%8C-%D8%AA%D9%88%D9%84%DB%8C%D8%AF%DB%8C-%D9%87%D9%84%D9%88-apex/), [persianhesab.com](https://persianhesab.com/%D8%A2%D9%85%D9%88%D8%B2%D8%B4-%D8%A7%DB%8C%D8%AC%D8%A7%D8%AF-%D9%81%D8%B1%D9%85%D9%88%D9%84-%D8%AA%D9%88%D9%84%DB%8C%D8%AF-%D8%AF%D8%B1-%D9%87%D9%84%D9%88/)
- **ERPNext**: BOM holds components, quantities, **scrap %** per material and an optional **scrap/by-product item** with its own rate and warehouse; a Work Order pulls the BOM's materials and (optionally) its operations, and can name a source/WIP/target/scrap warehouse. [docs.frappe.io](https://docs.frappe.io/erpnext/v13/user/manual/en/manufacturing/bill-of-materials), [erpnext.com](https://erpnext.com/manufacturing/work-orders)
- Odoo MRP's fuller model (Routing/Work Center/capacity) is real but is correctly identified in the pasted research as overkill for a 5-person workshop — agreed, out of scope.

**Correction to the pasted research's own conclusion, found by checking Dakhl's actual code, not by re-deriving the general ERP model:** the proposal to rename/replace `Product` with a generic `Item` entity is the wrong move for this codebase specifically, and the plan below does not do that. See §3.

## 2. What already exists in Dakhl (code-grounded, not assumed)

- `Product` already IS the stock-tracked-item concept (physical goods). `Service` is a separate, deliberately non-stock model. There is no unified "Item" table today, and every invoice line is `itemable_type` polymorphic (`Product` or `Service`) — this polymorphism already gives Dakhl exactly the flexibility ERPNext gets from a single "Item" doctype, without a rename.
- `WarehouseProductStock` (per warehouse, per product) already carries a **weighted-average cost layer** (`average_cost`), updated on purchase/consumption and already exercised by `WarehouseInvoiceStockTest` and friends. This is a real cost engine already, just not yet fed by a production event.
- `WarehouseService::transfer()` already does atomic, row-locked, negative-stock-safe movement between two warehouses (guardrail #53's own precedent: this exact mechanism was reused, not duplicated, for the recent capillary-distribution van-warehouse work).
- `StockReservation` (P4.1-P4.3) already proves the pattern of a side table that never mutates `WarehouseProductStock.quantity` directly except through a governed service call.
- There is **no generic stock-movement ledger** today. Every subsystem (Invoice, WarehouseTransfer, StockReservation) tracks its own history in its own table; nothing reads a shared "all movements" log. This matters for §4.

## 3. What to correct in the pasted research

1. **Do not rename `Product` → `Item`.** `Product` is referenced across dozens of services/tests (1800+ tests total in this repo). The polymorphic `itemable_type` pattern already gives the same generality ERPNext's "Item" doctype gives, at zero migration risk. Add a **classification field on the existing `Product` model** instead (§4).
2. **Do not build a new universal `stock_movements` ledger as a prerequisite.** Every existing stock-affecting subsystem (Invoice approval, WarehouseTransfer, StockReservation) already has its own tested, audited history table. Retrofitting all of them onto one shared ledger just to prepare for Manufacturing would be exactly the "premature generalization" the owner's own D-074 entry already rejected once for Trade. A `ProductionOrder` can be its own source of truth (its own materials/outputs rows), the same way `WarehouseTransfer` is today.
3. **Holoo's "consume at time of sale" model should not be Dakhl's default.** It quietly couples a sale event to a production event, which makes it harder to (a) produce ahead of demand into stock, (b) cost a batch correctly when yield/waste varies, and (c) reverse/audit a bad production run independently of any sale. An explicit **Production Order** (Sepidar/ERPNext's model) is the correct default; Holoo's simpler flow can be offered later as an optional "produce-on-sell" toggle for the smallest shops, not the initial design.
4. **Cost model**: no new "cost engine" needs to be designed. A Production Order's cost = Σ(consumed quantity × that stock row's current `average_cost`) + optional manual labor/overhead entered on the order, divided by output quantity, blended into the output product's `average_cost` in the output warehouse with the exact same weighted-average math `WarehouseService` already uses for purchases. This is an extension, not a new subsystem.

## 4. Phased plan

### Phase 0 — Readiness (additive, zero behavior change, safe to do immediately)
- Add a **nullable** `type` enum column to `products` (`ProductType`: `general` (default/current behavior, unclassified), `raw_material`, `semi_finished`, `finished_good`, `waste`). Nothing reads it yet; every existing Product stays `general` and behaves exactly as today.
- No new tables, no UI, no permission changes. This is the one piece of "don't foreclose the future" that costs nothing to do now, and is the only part of Phase 0/readiness this document executes today (see Entry in the execution log).

### Phase 1 — Production Lite (its own module; **not started now** — needs an explicit go-ahead, same as Trade/Integration/Tax)
- `production_recipes` (id, company_id, product_id [= output], output_quantity, is_active) + `production_recipe_items` (recipe_id, product_id [material], quantity, unit, waste_percent).
- `production_orders` (id, company_id, recipe_id, quantity_ordered, quantity_produced, status: DRAFT → PRODUCED (+ CANCELLED), labor_cost, overhead_cost, produced_at, created_by) — a two-state lifecycle, deliberately simpler than Invoice's Draft/Approved/Void chain, matching "سطح ۱" from the owner's own research.
- `ManufacturingService::produce()`: one locked transaction, mirroring `WarehouseService::transfer()`'s concurrency discipline — decrements each material's `WarehouseProductStock` (rejecting on insufficient stock, waste % applied to the required quantity), increments the output product's stock, recomputes its weighted-average cost, writes the order as PRODUCED. No new ledger table (§3.2).
- UI: "Production Lite" screens (recipe builder, "Produce now" quick-entry, a simple shortage/estimate view — Holoo's "تخمین تولید" idea is worth keeping as a read-only helper). Named **"تولید و بهای تمام‌شده"** in the UI per the owner's own naming instinct, never "Manufacturing"/"MRP" to the user.
- Explicitly deferred (سطح ۲/۳): multi-step routings, work centers/machine capacity, MRP/auto-purchasing, quality control, batch/serial genealogy (Dakhl already has a separate `ProductTracking` feature for lots/serials that a later phase could link to, not duplicate).

### Phase 2/3 (later, only if real workshop customers need them)
Multi-stage production, capacity planning, MRP — matches the owner's own "سطح ۳" and is not designed here.

## 5. Proposed decision text (DRAFT — for the owner to review/author, not locked)

> **D-103 — Manufacturing/BOM domain: readiness now, module later.** The Product/Warehouse/cost model must stay able to support a future Bill-of-Materials/Production module without being redesigned when that phase starts. Concretely: Product gains a nullable classification (`general` default; `raw_material`/`semi_finished`/`finished_good`/`waste` available), with no behavior change today. The Production module itself (recipes, production orders, a "produce" action against existing warehouse stock and cost-layer mechanics) is a separate future phase, scoped as "Production Lite" (single-step, no routings/work-centers/MRP), branded to users as "تولید و بهای تمام‌شده", never "Manufacturing"/"ERP". No generic cross-module stock-movement ledger is introduced; Production Order keeps its own history, matching how Invoice and Warehouse Transfer already do.

## 6. What this document executes today

Only Phase 0's schema readiness (§4): the nullable `Product.type` column and its enum. Everything else in this document is a plan for the owner's go-ahead, not a build.
