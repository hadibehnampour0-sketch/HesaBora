# D-100 Desk Discovery Report (knowledge + web verification) — r1, 2026-09-25

> **Status: RESEARCH ONLY — provisional. Not a decision, not field evidence.** Produced at the owner's request so work can proceed without waiting for interviews; real interviews (see the protocol doc) later **correct** this report rather than restart it.
> Evidence grades: **VERIFIED** (2+ independent sources) · **PARTIAL** (one/vendor/secondary source; no primary legal text reached) · **UNVERIFIED** (no source, or sources conflict).
> D-099 remains PROPOSED. Nothing here authorizes schema/code (Guardrail #5).

## 1. Verified/partial domain facts
| Fact | Grade | Design meaning |
|---|---|---|
| 750 (18k) is the market reference; equivalent weight = weight × purity / 750 (100 g @747 = 99.6 g @750). | PARTIAL, leaning VERIFIED (several vendor/educational pages) | Purity conversion is arithmetic on quantity; needs a defined normalization + rounding rule. |
| Mesghal = 4.6083 g; مظنه = price of one mesghal of 17k (purity 705) gold; divisor 4.3318 (= 4.6083×705/750, internally consistent) converts مظنه to a gram-750 price. | VERIFIED (market convention, non-official) | مظنه is a price *index*, not a unit; MarketQuote must carry its own basis. |
| "سوت": sources conflict (0.001 g vs 0.0486 g / 1 mesghal = 96 soot). | **UNVERIFIED** | Do not hard-code; ask dealers. Store weights in plain grams, 3 decimals. |
| Retail price = raw gold + wage (اجرت) + seller profit (commonly 7%, base disputed) + VAT. | PARTIAL | Wage/profit/raw gold must be separate lines. 7% legal basis and base (used gold?) unresolved. |
| VAT applies only to wage / حق‌العمل / profit, not raw gold (since 1400/10/13 law). | VERIFIED | Tax split is a derived accounting artifact of legs (fits D-099 boundary: tax calculation outside Trade). |
| VAT rate: 10% for 1404; **1405 rate unverified** (12% only proposed in one source). | PARTIAL / UNVERIFIED | Rate must be configuration (existing TaxRule), never a constant. |
| Moadian has a dedicated gold/jewelry/platinum invoice pattern with wage, profit, حق‌العمل, purity (0<v≤1000, 2 dp), weight, VAT lines. | PARTIAL | Exact field names/mandatory rules unverified → check the official technical guide before any invoice mapping. |
| آبشده = melted gold cast into blocks; ری‌گیری = lab assay; انگ = lab-stamped code (6 digits Tehran, 4 elsewhere) + lab name, look-up-able. | VERIFIED | Item-instance identity (assay/ingot number) is real for melt dealers. |
| Open dealer accounts held in gold/coin/Rial, with rial→weight conversion (vendor descriptions). | PARTIAL | Multi-unit balances are real; settlement custom still unknown. |
| Trade-in (تعویض): old gold valued as second-hand by weight+purity, no wage refunded; some jewelers cut assumed purity (750→740, single source); difference paid in cash. | PARTIAL | Supports H-01 (IN+OUT+cash in one exchange). Purity haircut = valuation rule, unverified. |
| Software market: Zarnegar, Kimia, Zargar, Chaharbaq, Tah Hesab, Zarrin, Holoo etc. exist; scale, assay no., wage %, melt loss, consignment, purity conversion, trade-in appear in some; position report only in Zarrin; **no vendor shows "بن"/fixing.** "Zarghalam / Tahsa / Golsa" (named in the simulated interview) were not found. | PARTIAL | Need is real; "fixing + weight ledger" may be a real differentiator — or dealers do it off-system. |
| Same-kind gold exchange: religious rulings require equal weight/immediate handover; differing weight settled via a separate cash trade; wage stated separately (rulings differ by marja). | PARTIAL (religious sources only) | Possible **business-rule constraint** on exchange legs; owner decides whether Dakhl enforces, warns, or ignores. |
| Workshop: wage per gram, sometimes plus gold % (1% on 100 g = 101 g); scrap recovery; melt-loss features in vendors; "2–5%" loss figure unsourced. | PARTIAL / UNVERIFIED | Manufacturer flows deferred; "کار مزدی" not found in any source. |
| Meaning of "بن کردن"/fixing; purity-dispute tolerance / re-assay; consignment risk, legal and tax treatment. | **UNVERIFIED** | Highest-priority interview topics. |

Method caveat: pages were read through a summarising fetch tool and no primary legal text (majlis, tax authority, Moadian technical guide) was reached; everything is secondary/vendor.

## 2. Hypothesis status update (H-01..H-11, protocol §10.2)
| H | Desk status |
|---|---|
| H-01 Trade-In = one exchange (IN+OUT+cash) | Supported by 2 sources (PARTIAL) |
| H-02 Purity normalization = conversion to 750 | Formula VERIFIED; placement (attribute vs conversion) is a design choice |
| H-03 melt dealers' base unit = gold-750 grams | Plausible (vendors hold gold-denominated accounts); UNVERIFIED as dealer practice |
| H-04 "بن"/fixing converts weight to Rial at a rate | **UNVERIFIED** (no source defines it) |
| H-05 Position/exposure is a core report | Only one vendor states it; UNVERIFIED as universal |
| H-06 Re-assay adjustment + audit trail | UNVERIFIED |
| H-07 Per-piece assay/ingot identity | Supported for آبشده (assay number verified) |
| H-08 End-of-day physical reconciliation | Plausible; UNVERIFIED |
| H-09 Tax split raw/wage/profit/VAT is part of the flow | Supported (tax rule VERIFIED, Moadian PARTIAL) |
| H-10 Consignment ownership semantics | Software-only; UNVERIFIED |
| H-11 Physical-vs-obligation differs by group | Plausible; field question |

## 3. Provisional direction for D-101 (design input only; each item tagged by evidence)
1. **Quantities stay native, in grams (3 dp), purity as an Item-instance attribute**; normalization to 750 is a Valuation/Unit-conversion concern, not a gold table. (Formula VERIFIED; placement = design; consistent with D-099 §8/§10.)
2. **Legs may need a derived "economic quantity"** from physical quantity + purity; both visible; rounding rule TBD (tolerance UNVERIFIED).
3. **Valuation modes AGREED / MARKET_SNAPSHOT / DEFERRED stay.** A fixing event (weight balance → Rial at a stated rate) is the natural way to resolve DEFERRED, but its real-world rules are UNVERIFIED — model it as a generic valuation event, not as "بن".
4. **Trade-In** = one exchange with IN and OUT legs and a cash settlement (PARTIAL support); no special entity until the model demonstrably fails on it.
5. **Position as calculation over exchange events** (multi-unit, derived), consistent with D-099; whether a dedicated exposure report is core depends on interviews.
6. **Tax split is an output artifact** computed outside Trade from legs + configuration (TaxRule); no rate constants.
7. **Item-instance identity** (assay/ingot number) as attributes; scope of per-piece tracking decided per group (melt dealer vs retail).
8. **Ownership/consignment** stays in the Trade Ownership Layer; concrete semantics wait for real answers.
9. **Same-kind swap rule** = configurable business rule (warn / enforce / off); owner decides; not assumed.
10. **Deferred:** manufacturer flows, melt-loss allowances, کار مزدی, coin-melt conversion.

## 4. What still requires humans (ranked)
1. Meaning and booking of "بن"; open-account settlement custom (weight vs Rial).
2. Purity disputes / re-assay tolerance and correction documents.
3. Consignment risk and recognition.
4. Definition of "سوت".
5. 7% profit basis, 1405 VAT rate, Moadian gold field rules (accountant / official guide).
6. Trade-in purity haircut and wage deduction.
7. Melt loss / کار مزدی.
8. Whether dealers need an exposure report in-system.

Short field kit: ask only these 8 topics (about 30 minutes with 2 real dealers) instead of the full 5×90-minute campaign, then update this report.

## 5. Decision Matrix (desk-provisional; field column empty until real interviews)
| Concept | Desk status | Field evidence |
|---|---|---|
| Native quantity + unit | Supported | — |
| Purity normalization | Supported (formula) | — |
| Ownership Layer | Plausible | — |
| Deferred valuation / fixing | **Unverified** | — |
| Party model (Customer + metadata) | Not challenged | — |
| Physical stock vs obligation | Plausible, group-dependent | — |
| Exception handling / DISPUTED | Unverified | — |

D-099 stays PROPOSED.
