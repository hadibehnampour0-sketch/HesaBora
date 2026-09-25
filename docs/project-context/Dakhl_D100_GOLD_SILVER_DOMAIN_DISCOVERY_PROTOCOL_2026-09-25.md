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
