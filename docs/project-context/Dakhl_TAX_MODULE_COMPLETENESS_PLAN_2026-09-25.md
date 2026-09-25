# Dakhl — Tax module: completeness plan (2026-09-25)

Owner direction: the Tax area must be **independent (top-level, not a Reports sub-item)** because it is a differentiator against competitors, and must be **comprehensive, complete and error-free**. Documentation + navigation only in this step; no D-0XX text authored here.

## 1. Done in this step
- Top-level **Tax** menu (advanced mode): Tax Center, Tax Readiness, Seasonal Tax Report, Moadian Histories, Tax alerts. Removed from *Reports*.
- **Tax Center** hub page (`tax-center.index`), permission-gated cards, latest readiness score.
- Persian translations for Tax Readiness (score, metrics, recommendations, messages).
- Note: tax routes sit under the `management/` URL prefix but only need their own permission (`CheckPermission`), not the super-admin panel; an accountant role can use them.

## 2. What exists today (code-grounded)
Readiness score (4 metrics), seasonal VAT report (+CSV/PDF), Moadian send/check-status/history, tax alerts engine + bell, evidence vault (only on ancillary costs), tax-deductible flag on ancillary costs, VAT rules, payroll tax slabs.

## 3. Candidate gaps for "complete" (to verify with a practising Iranian accountant before any build — legal/format details are NOT asserted here)
| # | Gap | Why it matters |
|---|---|---|
| T-1 | **Audit pack**: one export (ZIP/PDF) of a period's invoices, evidence, VAT report, trial balance, Moadian statuses | tax-office/auditor request today means gathering from 5 screens |
| T-2 | Evidence vault beyond ancillary costs (invoices, payroll, assets) and a per-period completeness view | audit defensibility |
| T-3 | Filing calendar: deadlines per period with status (prepared / filed / overdue) and alert linkage | avoid penalties |
| T-4 | Seasonal transactions report in the authority's format (if required for this company type) | filing |
| T-5 | Reconciliation checks: ledger VAT accounts vs invoice VAT vs Moadian-accepted totals; flag differences | "error-free" |
| T-6 | Annual performance/income-tax working papers (taxable-income adjustments from non-deductible costs) | year-end |
| T-7 | Moadian: rejected/failed invoice work queue with reason, retry, and fix guidance (known open item: item-discount field defect in the owner's separate session) | compliance |
| T-8 | Tax profile completeness wizard (economic code, national ID, credentials, VAT rules) with test connection | onboarding |
| T-9 | Simple-mode entry: plain-language "Am I ready for the tax office?" checklist | ease of use |

## 4. "Error-free" definition proposed
Every number on a tax screen must be traceable to invoices/ledger rows; each report has a reconciliation test against ledger totals; every export has a golden-file test; no tax figure computed in a view. Rounding follows the existing money-rounding rules (InvoiceMoneyRounding).

## 5. Suggested order
T-5 reconciliation → T-1 audit pack → T-7 Moadian work queue → T-3 calendar → T-2 evidence → T-8/T-9 → T-4/T-6 only after accountant confirmation of formats.

## 6. Open questions for the owner
1. Which company types are in scope first (legal entities vs. sole proprietors/simple VAT payers)?
2. Preferred audit-pack format (ZIP of PDFs+CSVs vs. one PDF)?
3. Who uses this area — owner only, or an external accountant/auditor role with read-only access?

## 7. Owner requirements added (same day)
1. **Non-expert completion**: a person with no audit knowledge must be able to complete the tax package and submit it to the tax office. => guided flow ("Prepare my tax filing": period → automatic checks → plain-language fixes → audit pack → filing checklist). Every step says what is wrong, why it matters, and the button that fixes it (extends T-8/T-9 from a side feature to the core of the area).
2. **Daily law updates**: tax rules must follow new legislation daily. => rules become **versioned data, never code** (same pattern as Industry Profiles): rule packs with effective-from/to dates, source reference, status (verified / pending review), history and per-company activation; the calculators and readiness checks read the active pack for the transaction date. Existing `TaxRule` (VAT by date) is the seed of this.

### 7.1 What can and cannot be automated (stated plainly)
- Software can ship and apply rule packs the day they are published and warn "a rule changed that affects you".
- Software must not silently treat an unverified legal change as fact. Each pack change needs a **named human reviewer** (accountant/legal) before it goes live to customers; a daily fetch may only create *pending* drafts and alerts. Fully automatic legal updates would make the "error-free" promise unkeepable.
- Historic transactions keep the rules valid on their own date; a rule change never rewrites past filings.

### 7.2 Decisions needed from the owner before building
a. Who reviews and publishes rule packs daily (in-house accountant, partner firm, or both)? What is the source of truth for changes?
b. Delivery: pack published from a central Dakhl service that installations pull (needs a connectivity/security decision), or shipped with releases only?
c. Liability wording: what the product promises ("prepared from the rules published on date X") vs. filing responsibility.
d. Which filings are in the first "guided" scope (see section 6).

### 7.3 Proposed technical shape (no code yet)
`tax_rule_packs` (version, effective_from/to, status, source_url, reviewed_by/at) + `tax_rules` rows per pack; `TaxRuleResolver::for(date)`; readiness metrics, seasonal report and Moadian VAT computations all resolve through it; a "rules changed" alert type in the existing Tax Alert engine; an admin screen to review/publish a pack. Golden-file tests per pack version.
