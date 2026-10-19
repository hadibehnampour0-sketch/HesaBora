# Dakhl — Accounting-Software Market Benchmark: Global + 35 Iranian Brands (2026-09-29)

Owner asked for a deep market study of accounting software — global and Iranian, including newer/cloud-native entrants — and a comparison against Dakhl to identify what to add or improve.

**This document is research input only. It authors NO D-0XX decision text** (same house rule as every other benchmark doc in this folder). Nothing here is approved, scoped, or scheduled. Any resulting work needs its own Decision Log entry written by the owner.

**Method & evidence grading — read this first.** Every claim below is tagged:

- **[V] Verified** — fetched the vendor's own live site on 2026-09-29 and read the feature list there.
- **[K] Knowledge/list** — from general industry knowledge or a third-party list; the vendor's site was unreachable (many Iranian sites block automated access, have dead DNS, or are stale). **Not independently confirmed. Re-check directly before any commercial decision.**

Several major global vendors (Xero, QuickBooks, Wave, FreshBooks, Gartner) actively blocked automated access and returned 403/transport errors; their coverage here is **[K]** only. This is stated plainly rather than dressed up.

---

## 1. Global landscape — three layers

### Layer A — Cloud SaaS for SMBs (Zoho Books [V], QuickBooks/Xero/FreshBooks/Wave [K])
Dominant model: **monthly subscription, cloud, multi-device, maximum automation**. Common feature set:
- Online invoices + **payment links** + online payment collection
- **Live bank feeds** and ML-assisted reconciliation (Odoo claims auto-matching ~95%)
- **OCR invoice digitization** (upload PDF/image → auto-encode)
- Automated dunning (email/SMS reminders)
- Customer/vendor portals
- Real-time reporting
- **AI**: Zoho's Zia and Odoo both ship a plain-language assistant + anomaly detection

### Layer B — Full ERP (Odoo [V], ERPNext [V], NetSuite [K])
Odoo's own features page lists (selected, directly relevant to Dakhl):
- **Fiscal Localization Packages** pre-configure taxes/charts/legal reports per country
- **Period closing** (monthly/quarterly/yearly) with automated closing-entry checks
- **AI-powered invoice digitization** (OCR)
- **Multi-company** with real-time consolidated reporting + automated intercompany entries
- **Multi-currency** with daily automatic exchange-rate updates
- Analytic accounting (hierarchical cost accounts, multi-axis plans, budgets)
- Dynamic financial statements with drill-down to the underlying journal entry
- **Lock dates** and cash-basis tax accounting
- Tax engine supporting price-included/excluded, tax-on-tax, exemptions, custom grids

ERPNext adds:
- Tree Chart of Accounts; every submitted transaction (including Stock & Manufacturing) auto-creates a GL entry
- Valuation methods: standard / **moving average** / **FIFO**
- Cost centers + dimensions + budgeting with monthly splits
- **Approval workflows** (multi-level)
- Asset register with Written-Down-Value / Straight-Line / Double-Declining depreciation

### Layer C — Key trend signals
- AI is now table-stakes in the newer products (assistant, anomaly detection, OCR, auto-classification).
- Payment links / online collection are expected, not premium.
- Portals (customer/vendor) are standard in cloud products.
- The market is splitting between "thin cloud UX" (Layer A) and "deep integrated ERP" (Layer B); Dakhl currently sits closer to B on depth but with an A-shaped surface.

---

## 2. Iranian market — 35 brands

### A) Newer / cloud-native (the most relevant competitors)

| # | Brand | Model | Distinguishing feature | Grade |
|---|---|---|---|---|
| 1 | حسابفا (Hesabfa) | Cloud SaaS | First cloud accounting in Iran; instalment sales with bank/market interest, **Shahkar identity/bank inquiry**, card-reader/POS link, custom form builder, 20+ dashboard widgets, multi-currency with automatic revaluation, price levels, SMS panel, marketer commission, CRM link | **[V]** |
| 2 | املاکیار (Amlakyar) | Cloud SaaS | Vertical (real-estate) + **AI assistant** (property/customer matching) + PWA + servers inside Iran + isolated dedicated DB | **[V]** |
| 3 | رایورز — رایسا (Rayvarz / Raysa) | Cloud | Cloud financial system for SMBs; "Sam Yar" cloud Moadian gateway | **[V]** |
| 4 | سپیدار — آیند (Aiand) | Cloud-native ERP | Hamkaran's AI-native generation: ledger, receive/pay, procurement, warehouse, CRM, payroll, **customer portal** | **[V]** |
| 5 | هلو APEX | Windows + online | **150+ trade-specialised editions**, **free Moadian connection**, SLM hardware binding, WooCommerce/CRM add-ons, scale/POS/RFID/barcode/kiosk hardware integrations | **[V]** |
| 6 | هلو اسپاد (Spad) | Windows | Companion product line (warehouse / treasury / payroll) | **[V]** |
| 7 | دشت (Dasht) | Windows/LAN | Hamkaran's retail/صنفی brand; multiple retail verticals | **[V]** |
| 8 | راهکاران (Rahkaran) | Enterprise ERP | Full modular ERP (finance/sales/distribution/retail/manufacturing/HR/SCM/BI/BPMS) + industry solutions (steel, oil, pharma, food...) | **[V]** |
| 9 | پیامگستر (Payamgostar) | Cloud | CRM-centric suite with **online payment links**, e-commerce sync, BPMS, call-center, ticketing, loyalty club, BI | **[V]** |

### B) Classic / established (sites unreachable or stale — list-level only)

| # | Brand | Focus | Grade |
|---|---|---|---|
| 10 | محک (Mohak) | Retail/صنفی | **[K]** |
| 11 | محسن (Mahsan) | Retail/صنفی | **[K]** |
| 12 | پارس (Paras) | Corporate | **[K]** |
| 13 | پندار (Pendar) | Industrial | **[K]** |
| 14 | چالاک حساب (Chalak Hesab) | Corporate (already referenced in this project's Decision Log) | **[K]** |
| 15 | تدبیر (Tadbir) | Corporate/industrial | **[K]** |
| 16 | همیار سیستم | صنفی | **[K]** |
| 17 | پدیدار (Padidar) | صنفی | **[K]** |
| 18 | دیدبان (Didehban) | Warehouse/accounting | **[K]** |
| 19 | سبزسان (Sabzsun) | صنفی | **[K]** |
| 20 | دیگران (Dikran) | صنفی | **[K]** |
| 21 | مین سیستم | صنفی | **[K]** |
| 22 | نوین حساب | Corporate | **[K]** |
| 23 | کیان سیستم | صنفی | **[K]** |
| 24 | البرز سیستم | صنفی | **[K]** |
| 25 | حسابگر | صنفی | **[K]** |
| 26 | سیمرغ | صنفی | **[K]** |
| 27 | دیاموند (DMS) | Enterprise | **[K]** |
| 28 | حسابداران | Professional / accounting firms | **[K]** |
| 29 | همراه سیستم | صنفی | **[K]** |
| 30 | رایان سیستم | صنفی | **[K]** |
| 31 | نوآوران ویرا سیستم فرابین | (builder of Hesabfa) | **[K]** |
| 32 | کارپرداز | Procurement/warehouse | **[K]** |
| 33 | ایران سیستم | Enterprise | **[K]** |
| 34 | بهرنگ سیستم | صنفی | **[K]** |
| 35 | آسمان (Aseman) | Cloud/صنفی | **[K]** |

**Honest limitation:** brands 10–35 are mostly the "legacy Windows/صنفی" market. They are not direct feature competitors to Dakhl, but they **set the price floor and the baseline expectations** (invoice printing, barcode, hardware links, phone support, offline).

---

## 3. Common patterns of the newer products — and where Dakhl is thin

The newer entrants (rows 1–9) share these, in rough order of how often they appear:

1. **AI assistant** — Hesabfa, Amlakyar, Aiand, Payamgostar (BI). Dakhl has only deterministic insights.
2. **Cloud/mobile (PWA)** — all of them, no install. Dakhl is web + desktop, no PWA/mobile.
3. **Payment links / online collection** — Payamgostar explicitly; Zoho/Odoo. Dakhl has none.
4. **Identity/bank inquiry (Shahkar)** — Hesabfa. Dakhl has none.
5. **POS / card-reader / hardware** — Hesabfa, Holoo (scale/RFID/kiosk). Dakhl has none.
6. **Instalment sales with interest** — Hesabfa. Dakhl has payment terms/obligations but not a sales-financing flow.
7. **Custom form / invoice builder** — Hesabfa, Holoo. Dakhl unknown/[K].
8. **Loyalty club / customer scoring** — Payamgostar. Dakhl has none.
9. **Customer/vendor portal** — Hesabfa, Aiand, Odoo. Dakhl has none.
10. **Call center / ticketing** — Payamgostar. Dakhl has none.
11. **Vertical specialisation + matching AI** — Amlakyar. Dakhl has Industry Profiles but no matching AI.
12. **Graphical BPMS / approval workflow designer** — Payamgostar, Hamkaran, Rayvarz. Dakhl has an approval *chain* setting, not a designer.

---

## 4. Direct comparison — Dakhl vs. the 9 newer brands

| Capability | Dakhl | Hesabfa | Holoo | Sepidar | Payamgostar | Rayvarz | Aiand |
|---|---|---|---|---|---|---|---|
| Strict double-entry core (SUM=0) | ✅✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| Real offline sync | ✅✅ | ❌ | ⚠️ LAN | ⚠️ | ❌ | ❌ | ❌ |
| Moadian | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| AI assistant | ❌ | ⚠️ | ❌ | ⚠️ | ✅ BI | ❌ | ✅ |
| Payment links | ❌ | ⚠️ | ❌ | ❌ | ✅ | ❌ | ⚠️ |
| Customer portal | ❌ | ✅ | ❌ | ✅ | ✅ | ⚠️ | ✅ |
| SMS panel | ❌ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ⚠️ |
| POS / card-reader | ❌ | ✅ | ✅✅ | ⚠️ | ❌ | ❌ | ❌ |
| Shahkar inquiry | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Custom form/invoice builder | ⚠️ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ⚠️ |
| Instalment sales | ⚠️ | ✅ | ⚠️ | ⚠️ | ❌ | ⚠️ | ⚠️ |
| Graphical BPMS | ❌ | ❌ | ⚠️ | ❌ | ✅✅ | ✅✅ | ⚠️ |
| Loyalty club | ❌ | ❌ | ⚠️ | ❌ | ✅ | ❌ | ❌ |
| Multi-currency + auto reval. | ⚠️ | ✅ | ⚠️ | ✅ | ❌ | ✅ | ⚠️ |
| Industry Profiles (data-driven) | ✅✅ | ❌ | ⚠️ hardcoded | ⚠️ hardcoded | ❌ | ❌ | ❌ |
| Tax-defense pack (audit pack) | ✅✅ | ❌ | ❌ | ⚠️ | ❌ | ⚠️ | ❌ |
| Offline desktop (NativePHP) | ✅✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| CI test gate | ❌ | — | — | — | — | — | — |

Legend: ✅✅ strongest, ✅ present, ⚠️ partial/uncertain, ❌ absent.

---

## 5. Where Dakhl is genuinely ahead

1. **Strict accounting core + document-balance discipline** — stricter than SME tools.
2. **Real offline/online sync** — no Iranian competitor has it; most global cloud tools don't either.
3. **Tax-defense bundle** (Readiness + Ledger reconciliation + Audit pack + Filing calendar) — no competitor, local or global, packages "a defensible file" together.
4. **Industry Profiles, data-driven** — Iranian verticals are hardcoded; Dakhl adds a trade by editing JSON, no code.
5. **One financial truth across web + desktop + API** — breadth of deployment from one core.
6. **Segregation-of-duties / approval chain** — present but configurable; most local rivals lack it.

## 6. Where Dakhl is behind

The gap is almost entirely in the **experience / connectivity layer**, not the core:
cloud/mobile UX, online payment, SMS, portals, AI, hardware/POS, BPMS, CRM, loyalty.

**Strategic read:** Dakhl's moat is **financial depth**; the Iranian rivals' battle is on **experience breadth**. Dakhl should invest in a few high-visibility experiences while protecting the depth — not race to feature-parity on every axis.

---

## 7. Recommendations (priority-ordered)

### Priority 1 — table stakes; without these a buyer doesn't convert (price-undercut defense)
1. **SMS panel** — due-date reminders for cheques/instalments/debts. Every rival has it; cheap, high-impact.
2. **POS / card-reader link** — essential for retail buyers.
3. **Configurable invoice template / form builder** — baseline expectation in Iran.
4. **Payment link on invoices** (Iranian gateway) — turns Dakhl from a recorder into a collector.

### Priority 2 — real differentiation (where Dakhl can be *better*, not merely equal)
5. **Customer/vendor portal** — Dakhl's sync advantage makes this stronger than rivals' versions.
6. **AI assistant over accounting data** (plain-language query) — architecture already designed; blocked on the data-egress decision.
7. **Shahkar identity/bank inquiry** — light, distinctive; only Hesabfa has it.
8. **Automatic FX revaluation + deferred revenue/expense** — completes the existing multi-currency layer.

### Priority 3 — long game
9. **Graphical BPMS** (approval-chain infrastructure already exists).
10. **Lightweight CRM** — differentiate as "accounting-first with CRM", not CRM-first like Payamgostar.
11. **Mobile/PWA** — faster to ship than a native app.

### Anti-recommendations (do NOT build)
- **Trade / Consignment / Position** — locked until real dealer interviews (D-074/D-099). Premature = core contamination.
- **FIFO/LIFO** without a real need — moving average suffices for SMB; needless complexity.
- **Multi-country fiscal localization packages** — focus is Iran; sprawl is harmful.
- **A full drag-and-drop form designer** — ready templates + custom fields may be enough.
- **LLM core without a data-egress decision** — compliance risk.
- **Blind feature-parity copying of Zoho/Odoo** — Dakhl's advantages are sync + tax-defense; protect those.

---

## 8. Suggested next steps

1. **Verify [K] brands directly** before any commercial conclusion — especially محک، محسن، پندار، تدبیر، چالاک، سبزسان، دیگران، مینسیستم، همکاران صنفی.
2. **Deep-dive the 9 [V] brands** on pricing + real feature depth (this pass covered features, not pricing).
3. **Owner decides**: which of Priority 1–3 becomes a real phase → then a Decision Log entry (next free ID is **D-108** per the Canonical Index r5).
4. Cross-reference this with the existing gap findings in the Decision Log's "Open Findings" (ancillary-cost coverage, order-cancel numbering — unrelated to market features, but part of the same closure backlog).

---

## 9. Sources

- **[V]** hesabfa.com, amlakyar.com, rayvarz.com, sepidarsystem.com, holoo.co.ir, spad.ir, systemgroup.net (راهکاران/Aiand/دشت), payamgostar.com, zoho.com/books, odoo.com/app/accounting-features, frappe.io/erpnext/open-source-accounting
- **[K]** general industry knowledge + third-party lists
- **Blocked (403/transport error, no data used as [V]):** xero.com, quickbooks.intuit.com, waveapps.com, freshbooks.com, gartner.com, and the ~26 [K] Iranian brand sites