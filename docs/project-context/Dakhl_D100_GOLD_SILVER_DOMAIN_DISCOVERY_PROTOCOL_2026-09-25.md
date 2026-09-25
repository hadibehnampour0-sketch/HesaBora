# D-100 Gold/Silver Domain Discovery Protocol — working document (r1, 2026-09-25)

> **Status: RESEARCH ONLY. No architecture change authorized. No implementation authorized.**
> This is a *research protocol*, not the D-100 decision text (owner authors that after findings). Structure comes from the owner's review; the assistant only formatted it.
> **D-099 stays PROPOSED until this discovery is complete** (owner instruction).
> Central question: **does the D-099 Trade model cover real business reality?** Not "what do dealers want".

## 1. Purpose
Validate the Trade Engine against a real, high-complexity domain: variable-value goods, non-Rial units, ownership separate from custody, mixed trades, open settlement, time-dependent pricing.

## 2. Sample (minimum)
| Group | n | Learn about |
|---|---|---|
| A — retail gold | 3 | daily sales, buying from customers, wage (اجرت), profit, customer accounts |
| B — آبشده seller | 1 | weight, purity, مظنه, weight-based settlement, open accounts between dealers |
| C — wholesale coin | 1 | unit = each, coin types, price spread, ownership, consignment (امانی) |
Record per interview: date, interviewee role (no personal identifiers in the repo), consent to notes.

## 3. Interview principles
- Never ask "what would you want in software?" (answer: "everything").
- Ask about **actual behavior**: "Yesterday at closing, how did you close the accounts?" Ask to see the real ledger/notebook/app screens (with permission; photograph only if allowed, store outside git).
- Ask "what happens when…" for exceptions (price moved, item returned, dispute, lost item).

## 4. Question axes (discovery, not design)
**A — Transaction flow.** "Today you took gold from a colleague — what do you record?" Who is debtor/creditor? Weight or money recorded? When is the price fixed? If the price moves tomorrow?
**B — Open account.** Morning A→B 100 g; noon B→A 40 g; night: is the balance kept in weight or Rial? When is settlement final? Does the settlement-time rate matter?
**C — Ownership.** Factory gives 50 coins to a seller. Who is the real owner? When is the "sale" deemed to happen? Who bears loss/theft? How is a return recorded?
**D — Valuation.** Agreed price? Price at trade moment? End-of-day price? Price at settlement? Are trades made without a final price?
**E — Gold-specific (discover only).** How is purity (عیار) kept? Net or gross weight? How is wage computed and settled? How is profit separated from wage? Coins by count or weight? Any religious/legal rule on same-kind exchange that dealers actually observe (record as practice, do not adjudicate).

## 5. Paper simulations (before any data model)
Each must produce: **Legs, Position, Valuation, Settlement**, and the accounting artifact(s), showing where the Rial ledger is touched.
- **S1 Gold exchange:** A: 100 g raw 18k. B: 80 g finished + wage + 50M toman.
- **S2 Dealer account:** day1 A→B 200 g; day2 B→A 70 g; day3 settle 130 g **or** in Rial.
- **S3 Consignment:** factory gives 100 coins to a seller; ownership not transferred.
- **S4 Deferred price:** 100 g delivered today; price fixed tomorrow.
- **S5 Purity conversion:** 100 g at 750 → pure-gold weight.
Use the interviews to replace invented numbers/rules with real ones; note where a simulation cannot be expressed in the D-099 model.

## 6. Required outputs (feed the D-100 text and D-101)
1. **Confirmed Concepts** — D-099 elements the domain validated (e.g. Quantity+Unit required).
2. **Missing Concepts** — needed but absent from D-099 (e.g. purity normalization).
3. **Rejected Assumptions** — assumptions proven wrong (e.g. "dealer balances are always Rial-based").
4. **D-101 questions answered:** Item Attribute? Unit Conversion? Ownership Extension? New Entity? Valuation Rule? — each yes/no with evidence.
Also record: which D-099 parts came from the market vs. only engineering design; any evidence that domain-specific tables are genuinely required (default per D-099 §10: no).

## 7. Findings log template
```
Interview #, group, date:
Observed workflow (their words / steps):
Ledger form (weight/Rial/both):
Rules discovered (purity, wage, price-fix timing, ownership transfer):
Exceptions/disputes:
Simulation results (S1–S5: expressible in D-099? gaps):
Confirmed / Missing / Rejected:
```

## 8. Guardrails
No code/schema/migrations; no gold-specific tables (D-099 §10); Guardrail #5 remains; no interviewee-identifying data or photographed ledgers in git.

## 9. Amendments (owner review round 2, 2026-09-25)

**Roles.** Interviews are conducted by the owner or a trusted real-world person; the assistant acts as Domain Analyst + Architecture Reviewer (question design, answer review, conceptual-model extraction, contradiction hunting, final D-100 preparation, D-101 impact).

**Interview header (identical for all; no name, phone, address, ledger photos, or sensitive data):**
```
Interview ID:
Role:
Business Type:
Years Active:
Approximate Scale:
```

**Per-interview output format (replaces free-form notes):**
```
Interview #NN — <Role>
Observations
  <Topic, e.g. Transaction>: "<observation>"   Confidence: High | Medium | Low
Architecture Impact
  Supports: <e.g. Quantity + Unit model>
  Challenges: <e.g. Purity normalization>
```

**New axis F — Exception Handling** (software fails in exceptions, not normal flow): customer says next day the weight was wrong; agreed price changed; part of the trade returned; account dispute; counterparty refuses. Maps to DISPUTED, Cancellation, Adjustment, Audit.

**New key architectural question (ask every gold interviewee):** "When you say 100 g of gold, is it a specific physical stock, or only an accounting obligation?" Case A: 100 g = real physical stock → Stock matters more. Case B: 100 g = an obligation between two people → Trade Position matters more. The answer may affect the whole Ownership Layer; record per interviewee and per scenario (may differ between open accounts, consignment, and cash sales).

**Final D-100 must include a Decision Matrix:**
| Concept | Confirmed | Evidence | Action |
|---|---|---|---|
| Native Quantity | ? (e.g. Yes) | e.g. n/5 interviews | Keep / Review / D-101 decision |
| Purity | ? | | |
| Ownership Layer | ? | | |
| Deferred Valuation | ? | | |
| Party Model | ? | | |
| Physical stock vs obligation | ? | | |
| Exception handling (DISPUTED etc.) | ? | | |
Rows are filled from evidence only; "?" until then.

**Sequence after D-100:** D-100 findings → D-099 ACCEPTED (only if validated; otherwise amended) → D-101 Data Model Proposal → D-102 Impact Review → Implementation Decision.

## 10. Question bank adopted + SIMULATED interview material (2026-09-25) — NOT EVIDENCE

### 10.1 Question bank (owner-supplied, 43 questions in 11 sections)
Adopted as the standing interview questionnaire (sections: 1 business info; 2 real transaction flow incl. "walk me through yesterday's last deal" and "50 g = physical stock or obligation?"; 3 buying/selling incl. purity conversion, net vs gross weight, per-piece identity; 4 wage/profit/costs; 5 open accounts; 6 ownership/consignment; 7 valuation incl. deferred price and stored spot price; 8 coins; 9 disputes/corrections/returns/history; 10 reports/control incl. "5 numbers at night"; 11 final: what can't your ledger record today / what pain do you tolerate / ideal system). Rules: let the interviewee narrate first; no software suggestions mid-answer; anchor to real examples of a working day; 60–90 min per interview. Per answer record Observation / Example / Impact / Confidence. Full question text lives in the owner's file and should be pasted verbatim into the field kit; **Group D (gold manufacturer/workshop)** is added to the sample (see 10.3).

### 10.2 Status of the two "interviews" received
Interview #01 (retail) and #02 (melt dealer/آبشده) were **role-played by an AI, not conducted with real dealers** (the owner's message states the author "takes the role of" the dealer). They are **simulated hypotheses, not domain evidence.** Consequences:
- They **do not count** toward the 5 required interviews, the Decision Matrix "Evidence" column, or validating/rejecting D-099. D-099 stays PROPOSED; the matrix stays "?".
- Specific figures/rules inside them (7% statutory retail profit, VAT applying only to wage+profit, rate formulas, named accounting products, market names, daily deal counts — one figure in #02 is garbled) are **unverified** and must not be copied into specs.
- Their value is as **hypotheses to test** with real dealers:
| ID | Hypothesis | Source | Test with |
|---|---|---|---|
| H-01 | Trade-In pattern: one exchange with IN (customer's old gold) + OUT (new piece) + cash difference; maybe an "exchange mode" beyond OUT/IN legs | #01 | retail dealers |
| H-02 | Purity normalization to a standard (750) is a valuation/conversion step, not a product attribute; physical vs "economic" (fine) quantity | #01, #02 | all groups |
| H-03 | Melt-dealer base unit is gold-750 grams, not Rial; ~most trades are gold-for-gold | #02 | آبشده dealer |
| H-04 | "Fixing / بن" event converts a weight balance into a Rial obligation at a stated rate; price fluctuation doesn't change weight balance | #02 | آبشده, wholesale |
| H-05 | Position/Exposure (physical + receivable − payable, in weight) is a core daily report, not a side report | #02 | آبشده |
| H-06 | Re-assay adjustment document (purity correction after re-test) with audit trail | #02 | all |
| H-07 | Piece/ingot identity (assay number) tracked per item | #02 | آبشده, retail |
| H-08 | End-of-day physical reconciliation (recorded vs actual) is central for retail | #01 | retail |
| H-09 | Compliance artifact (tax invoice with split of raw gold/wage/profit/VAT) is part of the trade flow, not only a downstream output | #01 | retail + accountant |
| H-10 | Consignment: owner=supplier, holder=retailer, sale recognized when customer takes the piece; loss liability on holder | #01 | retail, wholesale |
| H-11 | Retail = physical stock ("A"); melt dealer = obligation ("B"); the answer to "50 g?" differs by group | #01/#02 | key question |
Working reading (analyst, unproven): three settlement kinds may exist — cash, metal (in-kind), fixing (conversion event) — and Position may need a calculation engine over events rather than a table; Item attributes rather than gold tables (consistent with D-099 §10). These are hypotheses for D-101 only.

### 10.3 Next real steps
1. Conduct **real** interviews: 3 retail, 1 آبشده, 1 wholesale coin, **+1 gold manufacturer/workshop** (melt loss, production scrap, wage creation, relation of product to input gold, customer-supplied gold "کار مزدی") — added because manufacturers sit between the retail and melt worlds.
2. Verify the H-table items; separately verify legal/tax claims with an accountant familiar with gold-trade tax rules.
3. Fill the Decision Matrix only from real evidence.
