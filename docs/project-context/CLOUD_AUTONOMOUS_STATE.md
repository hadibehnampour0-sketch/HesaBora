# Dakhl — Cloud Autonomous State
## Resume checkpoint for future sessions/resets

**Roadmap reference:** `docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-20-r14.md` is now the authoritative roadmap (published from the owner's external Library snapshot, reconciled against actual repo state — see Execution Log Entry 60). `...-r7.md` is retained as historical baseline, not deleted, per the file's own versioning rule.

**Last updated:** 2026-09-21 (Epic Pack 1: J/Q/U/Y closure) — Financial Foundation phase is CLOSED (1426/1426, D-090 resolved). Current phase: **Organization Authorization & Workflow Governance**. Workstreams A-I complete. Owner-issued "Epic Work Package 1" batch closed Workstreams J, Q, U, Y in one continuous pass: **J** produced the written permission-matrix artifact (`docs/SECURITY_PERMISSION_MATRIX.md`) and surfaced CRITICAL finding #12 (unauthenticated `barryvdh/laravel-translation-manager` admin UI, fixed via `config/translation-manager.php` middleware). **Q** closed both remaining audit-trail gaps (direct permission grants, company-membership changes). **U** ran a dedicated adversarial cross-company pass and surfaced **CRITICAL finding #13 — the single most severe finding of the entire mission**: `getActiveCompany()` fell back to trusting the raw, unvalidated incoming request cookie whenever `config('active-company-id')` was null, completely bypassing `DefaultCompany` middleware's membership check (`Cookie::forget()` cannot clear the current request's already-parsed cookie) — any authenticated user could set `active-company-id` to an arbitrary company id and gain full read/write access to that company's entire 31-model `FiscalYearScope`-scoped dataset. Fixed by removing the cookie fallback entirely. **Y** built 7 realistic end-to-end domain business scenarios (`tests/Feature/DomainBusinessScenariosTest.php`). Full suite: 1465 → 1488 (+23 tests), 2 CRITICAL vulnerabilities found and fixed, zero regressions. Combined with earlier corrections, **J, K, L, M, O, P, Q, R, S, T(original), U, Y are now CLOSED/VERIFIED**; only N (owner policy decision), V, W, Z, AA remain open. Second continuation closed R and hardened T/Q. Third continuation audited Workstream J (permission matrix), CRITICAL finding #4, full suite 1444/1444. Fourth continuation audited Workstream L/M, CRITICAL findings #5/#6, full suite 1451/1451. Fifth continuation did Workstream S Phase 1 on a new real-MariaDB two-connection test harness (`tests/Helpers/MariaDbRaceTestCase.php`), CRITICAL findings #7-#10 (Invoice overpay, Document numbering collision, Settlement double-scheduling, Bank reconciliation double-match), full suite 1455/1455. Sixth continuation completed Workstream S Phase 2 (full state-transition inventory), CRITICAL finding #11 (`PaymentService::saveChequePayment()`, same invoice-overpay gap reached via cheque registration), full suite 1456/1456. **Seventh continuation (this update) completed Workstream S Phase 3 (Inventory, Expense & Operational Hardening) across all 5 owner-specified areas. `WarehouseService::transfer()` confirmed already fully compliant. Found and hardened a structural inconsistency in `ProductService::addProductsQuantities()`/`subProductsQuantities()` and `CostOfGoodsService::updateProductsAverageCost()` (naive read-then-write on denormalized `products.quantity`/`average_cost`, next to an already-correctly-locked warehouse-stock update) and in `AncillaryCostService::deleteAncillaryCost()` (same stale-instance shape as guardrail #23, on the deletion path) — all fixed with `lockForUpdate()`. Critically, extensive real-MariaDB testing could NOT prove the quantity/cost inconsistency was silently exploitable: MariaDB's own REPEATABLE READ consistency checking (error 1020) already prevents silent corruption in every interleaving tried, for both the fixed and unfixed code, and a "does it block?" test cannot distinguish the two here (unlike findings #7-#11) since any UPDATE-vs-UPDATE conflict blocks regardless of the read style. This is reported HONESTLY as defensive hardening, not inflated to a 12th CRITICAL finding — recorded as guardrail #25 so this exact methodological trap and its limit aren't rediscovered from scratch. Areas 3-5 (Expense workflow, Refund/reverse operations, Pattern compliance) were confirmed already covered by existing work (AncillaryCost IS the expense concept; D-084 already structurally forbids refunds/reversals, re-verified via the existing 9-test immutability suite, not re-tested from scratch). Full suite: 1457/1457 (1456 + 1 new).** **Workstream S is now COMPLETE across all three of the owner's planned phases** (Financial Atomicity → State Transition Inventory → Inventory & Expense Hardening): 15 real concurrency findings fixed, full suite 1426 → 1457, zero regressions. **Workstream T (Security Hardening) followed immediately, same session, same five-role framing.** Swept clean (no finding): SQL injection, mass assignment (Company's `$guarded=[]` has no exploitable call site), file-download path traversal. Two real findings fixed: (1) stored XSS -- `ProductImportService`/`CustomerImportService`/`ServiceImportService` (CSV import) bypassed the web form's own name-validation regex entirely, and the bypassed value later rendered unescaped via `{!! !!}` in `InvoiceStatusDecision::toDetailText()`'s product-conflict message -- fixed with `e()` escaping at the output point plus a `<>"'` denylist at import (deliberately narrower than the web form's own allowlist regex, which rejects a legitimate Persian typographic character); (2) `POST /login` and `POST /reset-password` had zero rate limiting despite the app's own `throttle:6,1` convention already covering every other sensitive auth route -- fixed by adding it. A related test-infrastructure gap (no per-test cache isolation, causing the new throttle test to intermittently 429 unrelated tests depending on run order) was fixed globally via `Cache::store()->flush()` in the base `TestCase::setUp()`. Full suite: 1457 → 1462. **Workstream T's broader sweep continued the same session**: CSRF, CORS, session cookies, user impersonation, device/sync API auth, and SVG-upload rendering were all checked and confirmed already safe (impersonation looked concerning at first glance -- the underlying package's `canImpersonate()` trait default is insecure -- but `User` correctly overrides it with a real permission check; verified by reading the override, not assumed). One more real finding: `composer audit` (not previously run this session) found 16 real CVEs across `guzzlehttp/guzzle` (direct dependency, 7.12.3) and `league/commonmark` (transitive, 2.8.2, including a real XSS filter bypass, though no direct app-code usage found). Fixed with `composer update guzzlehttp/guzzle league/commonmark --with-dependencies`, entirely within existing constraints (guzzle → 7.15.5, commonmark → 2.10.1); `composer audit` now clean; full suite re-verified green (1462/1462, dependency-only change, no new tests needed). **Ninth continuation pass closed out Workstream T.** Finding #4: no defense-in-depth response headers at all — added `app/Http/Middleware/SecurityHeaders.php` (CSP, X-Frame-Options, nosniff, Referrer-Policy, conditional HSTS), registered globally in `bootstrap/app.php`, verified against every Blade view for CDN/script/font dependencies the CSP's `default-src 'self'` would otherwise break. `npm audit` (not previously run) found and fixed 7 dev-only build-tooling vulnerabilities via `npm audit fix`, `npm run build` re-verified. Investigated `POST /api/devices/pair`'s lack of rate limiting: the 8-char activation code (`strtoupper(Str::random(8))`, effectively 36 characters after case-collapse) against a 15-minute TTL gives a 36^8 ≈ 2.8×10^12 keyspace — reasoned through explicitly that even 100,000 req/s sustained for the full window (~9×10^7 attempts) falls more than four orders of magnitude short, so this is NOT practically brute-forceable; added `throttle:10,1` anyway purely for convention-consistency with `/login`/`/reset-password`, not as a fix to a proven vulnerability. Full suite: 1462 → 1465 (2 SecurityHeaders tests + 1 DevicePairing throttle test). **Workstream T is now COMPLETE**: 4 real findings fixed (stored XSS + import bypass, missing login/reset-password throttling, 16 dependency CVEs, missing security headers), 1 preventive convention hardening (device-pairing throttle), 1 test-infrastructure fix (global per-test cache isolation), 6 areas swept and confirmed safe with documented reasoning (SQL injection, mass assignment, path traversal, CSRF, CORS, session cookies, impersonation, device/sync auth, SVG upload). Full suite: 1457 → 1465 across the whole workstream, zero regressions, no schema changes.

Remaining open items: the longer-standing J written-matrix artifact, N's policy question, Q's two remaining gaps, `GroupActionService::approveInactiveInvoices()` not independently fail-first tested, standalone Y/Z passes, and an ongoing `composer audit`/`npm audit` cadence going forward (no fixed schedule established — should be re-run periodically, not a one-time pass) — all narrow and explicitly classified in Decision Log D-091 and Execution Log Entries 59-69 -- the 30-item Phase Exit Gate is NOT green yet.

---

**Prior summary (session 2 of the autonomous mission, continued — see §10 for the rebrand/roadmap work, Entries 8–10 for Phase 1 (complete), and Entries 11–24 for Phase 2 stages 2.1–2.8E, ALL OF PHASE 2 NOW FULLY COMPLETE (Sync Contract, Device Pairing, UUID/Change Tracking, Outbox/Inbox, Customer/Product Sync Pilot, Conflict Resolution Workflow, Document Draft Sync, Invoice Draft Sync, Offline Numbering & Reservation, Approved Sales Invoice Sync, Integration & Hardening Gate, Payment Financial Event Identity, Offline Payment Creation + Sync, Server Financial Authority & Reconciliation Gate, Payment Allocation Sync, Distributed Payment & Allocation Integration Hardening — D-071 through D-082, twelve Decisions) plus **D-074 (Trade/Consignment Architecture, future, documentation-only — no code)** — 2.8E (the final sub-stage) needed ZERO production code changes, only tests, proving 2.8A-2.8D already composed correctly; a Read-Only status audit was also performed mid-session confirming no drift and no missed decisions; an unnumbered future item (Customer→Subject accounting materialization) and a recurring "bare row breaks a new model-event hook" Open Engineering Item have both been flagged, not started; the owner wants one large Phase 2 checkpoint/evaluation before deciding on Phase 3)
**Full test suite status as of this update: 1426 passed / 0 failed.** All 10 Phases of the post-checkpoint chain are CLOSED (Execution Log Entries 25–46), the Financial Integrity Closure Batch is CLOSED (Entry 48), the 7-Batch Sequential Roadmap Execution Queue is CLOSED (Entries 49-55), the Final Closure Pass is CLOSED (Entry 56), and the **Final Marathon Closure Pass is CLOSED (Entry 57) -- D-090 is now GENUINELY RESOLVED** (`unsettleInstallment()` delegates to `PaymentService::removeAllocation()`, a mechanical application of D-084's already-decided rule, not a new policy), plus **two critical, previously-invisible double-posting defects found and fixed**: re-approving an already-approved invoice (and, mirror-image, re-unapproving an already-unapproved one) silently duplicated Documents / manufactured phantom inventory with no concurrency needed -- a plain resubmit was enough. A third identical defect was found and fixed in `AncillaryCostService` by re-auditing for the same pattern. **The Financial Foundation phase's full 22-item Exit Gate is now all GREEN** -- see Decision Log for the complete evidence. Checkpoint Reconciliation performed and confirmed (Entry 47) -- no drift found inside this repository; a separate `...r10...` roadmap file the owner holds externally does not exist in this git working tree and cannot be reached from this session. **Financial Integrity Closure Batch complete (Entry 48)**: two real Cheque lifecycle bugs fixed (`returnToCustomer()`/`cancel()` missing `reverseInvoicePaymentCredit()`, mirroring `bounce()`), one more authorization bug fixed (`AncillaryCostController`, identical to Phase 8's Invoice fix), zero new Decisions, and `SettlementService::unsettleInstallment()` formally isolated as an unresolved architecture question (owner's planned D-090) -- not fixed, not guarded, not removed.

**Sequential Roadmap Execution Queue complete (Entries 49-55, Batches 1-7)**: Batch 1 fixed a real Cheque-deletion/Settlement-orphaning defect (mirrors the Invoice-deletion guard). Batch 2 found and fixed a second real Money/Decimal defect (`InvoiceTransactionBuilder`'s COG/Inventory lines, `average_cost * fractional quantity`) via a centralized `pushTransaction()` choke point replacing ~15 scattered call sites, plus self-caught and fixed a regression (a too-strict `int` type hint) during its own Gate 2. Batch 3 re-verified the desktop login-500 issue is already fixed (no code change). Batch 4 fixed a third real bug, the same uuid-NULL class as D-085's Invoice fix, in `_importCustomers()`. Batch 5 found Bank Reconciliation is already fully implemented (no code change, corrected a stale roadmap claim). Batch 6 re-confirmed Sync/Offline-Online integrity, recorded one theoretical-only (cryptographically infeasible) observation, no code change. Batch 7 rebuilt the P0/P1 matrix from actual verified state. **Final queue full suite: 1421/1421. Zero new Decisions across the entire 7-batch queue** -- every fix was a mechanical extension of an already-locked invariant. Phase 2 (D-071 through D-084) is COMPLETE WITH OPEN DEBT, not unqualified "fully complete." None of it is committed yet — still blocked on git identity (§0).
**D-085 — Company deletion provenance — LOCKED, IMPLEMENTED, VERIFIED, FINAL.** Two owner-required boundary tests passed (copied Payment stays baseline-deletable; editing a baseline invoice correctly produces a NATIVE document and blocks deletion -- no bypass found). Fixed a real, unrelated bare-row bug found along the way: `_importInvoices()`'s `saveQuietly()` silently left copied invoices with `uuid = NULL`. Full detail: Decision Log's D-085 entry, Execution Log Entry 35. Full suite after D-085 hardening: 1384/1384.

**D-086 — Approved Invoice Financial Immutability — LOCKED, IMPLEMENTED, VERIFIED, fully closed.** `InvoiceService::updateInvoice()` rejects financial-content changes (items/totals/customer/warehouse, or the approved-rebuild branch outright) once an Invoice has a real Payment or PaymentAllocation. Its one open item is resolved by D-087.

**D-087 — Explicit Separation of User-Facing Unapprove From Internal COG Status Cycling — LOCKED, IMPLEMENTED, VERIFIED.** Owner rejected a boolean bypass flag; locked two structurally separate entry points instead: `unapproveInvoice()` (guarded, the only path the controller can reach) vs. a new public `recycleInvoiceStatusForDependencyRecalculation()` reserved for `GroupActionService`'s existing, trusted internal mechanism (provably non-financial: never touches amount/vat/customer/items/Payment/Allocation, verified even against an already-Paid invoice). Full detail: Decision Log's D-087 entry, Execution Log Entry 36. Full suite: 1397/1397.

**D-088 — Approved Invoice Re-Approval Is Service-Layer Guarded — LOCKED, IMPLEMENTED, VERIFIED.** Closed the one remaining gap found during Phase 1's final audit: `changeInvoiceStatus('approved')` now guards itself instead of relying on caller-side checks. **Approved Invoice Mutation Boundary (D-086+D-087+D-088) is CLOSED** -- edit/rebuild, unapprove, re-approve, and delete are all service-layer guarded, verified by exhaustive caller enumeration. Full detail: Decision Log, Execution Log Entry 37. Full suite: 1398/1398.

**Post-D-088 Phase 2 — Money/Decimal Safety (bounded slice, not a Decision).** Found and fixed a real, reproducible bug (not just a SQLite-test artifact): `InvoiceTransactionBuilder::extractItemValues()` computed VAT-by-percentage and item-amount with no rounding, which `DocumentService::createDocument()`'s own existing validation actively rejected for ordinary VAT/price combinations (confirmed by temporarily reverting the fix and observing the exact rejection). Fixed with two `round(..., 2)` calls at the single point of calculation. Full detail: Execution Log Entry 38. Full suite: 1400/1400.

**Phase 3 — `SettlementService::unsettleInstallment()` audit-integrity correction.** Corrected a claim repeated 5 times across this session's own docs ("dead code, zero callers") -- a real test (`CheckSettlementTest`) exercises it directly. Not removed, not changed; the "dead code" characterization was wrong (searches only ever covered `app/`, never `tests/`). Full detail: Execution Log Entry 39.

**Phase 4 — Cheque lifecycle audit.** `ChequeService::bounce()`'s direct `Payment::delete()` confirmed to be the same, already-sanctioned D-084 cheque-lifecycle exception (structurally cannot orphan a PaymentAllocation). No defect found, no code changed. Full detail: Execution Log Entry 40.

**D-089 — Approved Invoice Rejection Is Service-Layer Guarded — LOCKED, IMPLEMENTED, VERIFIED.** Found during Phase 5 (Cross-Module Financial Integrity): `changeInvoiceStatus('rejected')` was the one status-transition arm D-086/D-087/D-088 never guarded — a bare status update that would silently detach a financially-active invoice's status from its untouched ledger. Same self-authored, mechanical D-086-extension pattern as D-088. **Approved Invoice Mutation Boundary (D-086+D-087+D-088+D-089) is CLOSED** across all four mutation directions plus delete. Full detail: Decision Log, Execution Log Entry 41. Full suite: 1403/1403.

**Phase 5 — Cross-Module Financial Integrity.** Traced `Invoice -> Document -> Transaction -> Payment -> PaymentAllocation/Settlement -> Installment -> Subject Balance` against the owner's 10 questions. One real defect found and fixed (D-089, above). One test-coverage gap closed (multi-line-item rounding regression test — no code change needed, generalized correctly on first run). All other invariants (duplicate financial effects, reversal idempotency, state/ledger sync, live balance reconstructability, Settlement double-settle/un-settle completeness, delete/cascade orphaning) confirmed holding by inspection and existing/new tests — no other defect found. Full detail: Execution Log Entry 41.

**Phase 6 — Sync/Replay/Idempotency.** No defect found. Confirmed Cheque/Settlement have zero Sync surface; the generic Invoice `applyUpdate()`/`applyCreate()`/`applyDelete()` dispatch is structurally unreachable once an invoice is approved/settled/document-linked (blocked earlier by `invoiceDraftSyncViolation()`), so D-086–D-089's service-layer guards are correct defense-in-depth, not the only barrier; `applyApprove()`'s own version-check + idempotent-replay short-circuit means it never actually exercises D-088's rejection path; no Sync operation reaches `'unapproved'`/`'rejected'` at all. No code changed. Full detail: Execution Log Entry 42.

**Phase 7 — Baseline/Provenance Integrity.** D-085 re-verified end-to-end (fresh Payment against a copied invoice correctly registers as native; the only other "import" feature found, `DocumentImportExportService`, is an unrelated external-file bulk import into the current company, not a D-085 bypass). No gap found, no code changed. Full detail: Execution Log Entry 43.

**Phase 8 — Authorization + Destructive Actions.** Multi-tenancy company-scoping checked and confirmed solid (encrypted cookie, membership re-validated every request). One real, security-relevant bug found and fixed: `InvoiceController::store()`/`update()` called `auth()->user()->can('invoices.approve')` and **discarded the result** -- any Seller (a role explicitly denied this permission) could approve an invoice outright just by adding `approve=1` to a create/update request. `changeStatus()` had no permission check of its own at all. Fixed with `abort_unless(...)` at all three call sites (the codebase's existing convention) -- not a new Decision, a discarded-return-value bug fix restoring already-intended, already-designed behavior. 6 new tests, verified to actually catch the bug via revert-and-rerun. Full detail: Execution Log Entry 44.

**Phase 9 — Regression/Invariant Test Quality.** `CheckSettlementTest`'s unsettle test only proved a derived number, not the mechanism. Added a test proving the real invariant: only the `PaymentAllocation` is deleted, the underlying `Payment` survives untouched and becomes reusable. All other nine focus invariants already have deep, non-happy-path coverage from earlier in this chain -- none added, per the "do not chase test count" rule. Full detail: Execution Log Entry 45.

**Phase 10 — Final Architecture + Roadmap Audit.** Chain complete. All 10 Phases closed GREEN. Two real production bugs found and fixed across the chain (D-089's status-transition gap; the `invoices.approve` authorization bypass), both with verified-teeth regression tests. **Final full suite: 1410/1410.** Full detail (all 14 required report items): Execution Log Entry 46.
**Current git branch:** `dakhl-foundation`
**Latest commit on branch (HEAD):** `12e3874a` — "Merge branch 'chore-ignore-playwright-cli' into 'main'"
**Working tree status:** NOT clean — see "Working-tree inventory" below. This is expected and safe; nothing is lost. **Git identity is still unset; 0 real commits exist beyond HEAD.**

---

## 0. BLOCKING ISSUE — READ THIS FIRST

**RESOLVED 2026-09-22, READ THIS BEFORE ANYTHING BELOW IN THIS SECTION.** Git identity was set that day (`Dakhl Developer <dev@dakhl.local>` -- a generic placeholder identity, not the owner's own name/email as this section originally anticipated below). The entire P0-P12 backlog described throughout this document as "ready to commit" or "NOT yet staged" **has since been committed** -- 22 atomic commits reconstructing the full history, followed by a Release Readiness Audit (3 more commits: git hygiene, a missing `7zip-bin` Electron dependency, desktop production error-display handling), for 25 total and 1728 passing tests. A local Git mirror backup exists at `D:\Backups\Dakhl.git` (verified via a real recovery clone). **No remote push has occurred and none is planned** -- this stays local/private by explicit owner instruction. See the Decision Log's "Product Strategy Execution Sequencing — 2026-09-22" entry and roadmap §39 for the current priority order (Phase R -> User Simplicity -> AI architecture -> Integration architecture -> Monetization -> Android, in that order; Android/Field Operations Mobile is explicitly postponed, not cancelled).

The rest of this section (and the "Ready-to-commit work" staging plan in Section 2 below) is preserved as historical record of how the git-identity blocker was diagnosed and what the ORIGINAL staging plan looked like -- it does not reflect what was actually committed (the actual 25 commits used a different, more accurate grouping derived from the real repository content; see the Decision Log for that). Do not re-execute anything in Section 2 below.

---

**Original entry, preserved for history:**

**No git commit can be made in this environment.** `git config --list --show-origin` confirms there is no `user.name`/`user.email` set at system, global (`~/.gitconfig` does not exist), or repo (`.git/config`) level. The acting agent (Claude) operates under an absolute rule that forbids ever modifying git config (`NEVER update the git config`), and this rule takes precedence over the autonomous-mission brief's request for continuous commits. This is not a judgment call — it is a hard operating constraint.

**Required owner action (one time, ~10 seconds):**
```bash
git config user.name "Hadi Behnampour"
git config user.email "hadi.behnampour0@gmail.com"
```
(Repo-local is sufficient — no need for `--global`.) Once this is set, every commit described in "Section 2 — Ready-to-commit work" below can be created immediately with the exact commands given.

**Practical note on `lang/en.json` / `lang/fa.json`:** these two files accumulate translation-string additions from Commits 2, 7, and 9 (login message, company-deletion message, document-immutability message respectively). Since `git add` is file-level, you cannot cleanly split one file across three commits without `git add -p`. Simplest correct approach: commit `lang/en.json`/`lang/fa.json` together with whichever of those three commits you make **last**, and drop the `lang/en.json`/`lang/fa.json` lines from the earlier two commits' `git add` commands (the code/logic changes in those earlier commits will still work correctly even before their translation string lands, since Laravel falls back to the key itself when a translation is missing — it just won't be Persian yet). Do not invent a 4th "translations" commit unless you want to; either grouping is fine, just don't lose track of which files landed where.

Until this is resolved, a resumed session should re-check `git config user.name` before assuming this blocker is still open, then proceed straight to Section 2.

---

## 1. What happened this session

The prior conversation turn was a **read-only architecture audit** (see `Dakhl_MASTER_CONTEXT_AND_ROADMAP...` context and the audit findings already delivered in-chat). This turn, the owner issued a new mission brief granting full autonomous implementation/commit authority (see the mission prompt text — not duplicated here; its rules are binding: protected-core care, small coherent commits, no pushes without explicit authorization, financial-safety-first, etc.).

On starting work, `git status` revealed the working tree was **already not clean** — substantial uncommitted work already existed, dated 2026-09-10/11 (i.e., predating this conversation), sitting alongside the newly-added `docs/project-context/` files (r4/r7 revisions + `archive/`, added just before the mission brief was sent). This pre-existing work turned out to be a **coherent, mostly-complete, well-tested fix for exactly the P0 login-500 bug** the roadmap flags — not random drift. Rather than discard or duplicate it, this session investigated it, verified it with the real test suite, fixed one real bug found in it, flagged one real security concern found in it, and organized it into reviewable commit groups.

### Key discovery: baseline test suite is badly broken on SQLite

Before touching anything, this session ran the full suite on the **original committed code** (by temporarily stashing the WIP) as a control:

- **Baseline (committed HEAD): 645 failed / 472 passed** — the vast majority `QueryException`, spread across almost every domain (ActivityLog, API, AttendanceLog, Warehouse, WorkShift, etc.).
- **With the WIP applied: 20 failed / 1109 passed.**
- Spot-checked: the 20 remaining failures exist identically in both runs (e.g. `WarehouseTest > index filters warehouses by name code and inventory`) — confirmed pre-existing and unrelated to this work, not a regression.

Root cause of the 645 baseline failures: `database/migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php` previously **no-op'd entirely on SQLite** (`if ($this->isSqlite()) { return; }`), so enum columns never got converted from string to tinyInteger on the SQLite schema (used for tests AND the NativePHP desktop runtime), while application code elsewhere assumes integer values. This is almost certainly a major contributor to the P0 desktop login-500 bug and to general desktop-build instability — it is not narrowly a "login" bug, it is a **systemic SQLite/MySQL schema-parity bug**.

---

## 2. Ready-to-commit work (staged/prepared, blocked only on git identity)

### Commit 1 — STAGED AND READY (already `git add`-ed, just needs `git commit`)
**Files:** `database/migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php`, `tests/Feature/ConvertEnumColumnsToIntegerMigrationTest.php`
**Verified:** `php artisan test --filter=ConvertEnumColumnsToIntegerMigrationTest` → 3/3 pass. Full suite with this + everything below applied → 20 failed / 1109 passed (pre-existing failures only).
**Commit message (use verbatim):**
```
fix: make enum-to-integer migration work on SQLite

The migration previously no-op'd entirely on SQLite (`if ($this->isSqlite())
{ return; }`), so the desktop/test SQLite schema never got the tinyInteger
conversion that MySQL/MariaDB deployments received. This left enum columns
as raw strings on SQLite while application code elsewhere assumed integer
values, causing widespread QueryException failures across the test suite
(645/1117 tests failing on a clean SQLite run before this fix).

Adds SQLite-specific up/down paths that rebuild the column via
Schema::table()->tinyInteger()->change() (working around SQLite's inability
to alter a CHECK-constrained enum column in place), remap existing string
values to their integer equivalents, and normalize any unexpected values to
the column default. Down migration restores the string representation
symmetrically.

Verified: full suite now at 20 failed / 1109 passed, and the 20 remaining
failures are confirmed pre-existing/unrelated (same tests fail identically
on HEAD without this change).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:** `git commit -F -` with the message above already staged (or re-run `git add database/migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php tests/Feature/ConvertEnumColumnsToIntegerMigrationTest.php` first if the index was touched by anything else).

---

### Commit 2 — NOT yet staged. Files to add:
```
app/Http/Controllers/Auth/LoginController.php
app/Models/User.php
app/Providers/AppServiceProvider.php
config/nativephp.php
database/factories/UserFactory.php
database/migrations/2026_09_11_120000_add_username_to_users_table.php
database/seeders/RolesAndPermissionsSeeder.php
resources/views/auth/login.blade.php
lang/en.json
lang/fa.json
tests/Feature/AuthLifecycleTest.php
tests/Feature/UsernameLoginTest.php
```
**UPDATED 2026-09-14:** the seeded default credential is now `admin` / `1234` (owner's explicit instruction, replacing the earlier `Dakhl@2026!`) — see §7 below. The `lang/fa.json` change now also includes an unrelated-but-bundled fix: the Persian translation of "The provided credentials do not match our records." was `"کاربری با این مشخصات یافت نشد."` ("no such user found"), which is misleading for a simple wrong-password case; it now reads `"نام کاربری یا رمز عبور اشتباه است."` ("username or password is incorrect"). Commit message should mention both.
**What it does:** Switches login from email-primary to username-primary (with email fallback for pre-existing accounts), adds `users.username` (nullable unique), wires the NativePHP desktop SQLite connection override in `AppServiceProvider::boot()` (`NATIVEPHP_RUNNING` → force `database.default=sqlite` + `NATIVEPHP_DATABASE_PATH`), fixes `config/nativephp.php` `cleanup_env_keys` to strip `DB_*`/`DEPLOY_*`/`MAIL_PASSWORD` from the bundled production `.env` (previously these leaked into the shipped desktop package — a real security fix), simplifies the demo-user seeder to a single canonical admin account, and rewrites the login view to a minimal username/password form (removes the old demo-credentials table and email-based UI).
**IMPORTANT — security note already applied:** `database/seeders/RolesAndPermissionsSeeder.php` seeds a fixed admin credential (`admin@dakhl.local` / `Dakhl@2026!`) unconditionally on every install (`DatabaseSeeder` → `RolesAndPermissionsSeeder` → runs on NativePHP first-boot via `NativeAppServiceProvider`'s forced `db:seed`). This is baked into public GPL source, so it is not a secret — but it is a real default admin login that ships with every install. This session added an explanatory code comment above the credential (not a functional change) flagging it and referencing the open follow-up below (P0-02). **This was NOT redesigned** — only commented — because forcing a password change or randomizing the credential is a product/security-policy decision, not an "implementation detail," and is recorded as an open decision in Section 4.
**Verified:** `php artisan test --filter="UsernameLoginTest|AuthLifecycleTest"` → 71/71 pass (62 in the initial targeted run + re-confirmed as part of the 1109-pass full run).
**Commit message (use verbatim):**
```
fix: switch login to username-based auth and fix NativePHP desktop DB wiring

Root-cause fix for the P0 "packaged Windows build returns 500 on invalid
login credentials" issue. Two things were previously missing that made the
desktop runtime unstable:

1. AppServiceProvider::boot() had no logic to point Laravel's default DB
   connection at the NativePHP-managed SQLite file at runtime. Combined
   with config/nativephp.php not stripping DB_* from the bundled production
   .env, a packaged install could end up attempting to use build-time
   MySQL connection settings that don't exist on the end-user machine.
   AppServiceProvider now forces database.default=sqlite and points it at
   NATIVEPHP_DATABASE_PATH when NATIVEPHP_RUNNING is set; cleanup_env_keys
   now also strips DB_*, DEPLOY_*, and MAIL_PASSWORD from the shipped .env.

2. Login now authenticates primarily by username (new nullable-unique
   users.username column) with an email fallback for accounts created
   before this change, matching the simplified single-field login form.
   The demo-credentials table and email-based UI were removed from the
   login page.

The demo/seed admin account (RolesAndPermissionsSeeder) is simplified to a
single canonical admin@dakhl.local / admin account; see the code comment
there for a flagged follow-up (P0-02, forced password change on first
login) rather than a silent redesign of the credential scheme.

Tests: AuthLifecycleTest + UsernameLoginTest, 71/71 passing.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add app/Http/Controllers/Auth/LoginController.php app/Models/User.php app/Providers/AppServiceProvider.php config/nativephp.php database/factories/UserFactory.php database/migrations/2026_09_11_120000_add_username_to_users_table.php database/seeders/RolesAndPermissionsSeeder.php resources/views/auth/login.blade.php lang/en.json lang/fa.json tests/Feature/AuthLifecycleTest.php tests/Feature/UsernameLoginTest.php
git commit -F <message above>
```

---

### Commit 3 — NOT yet staged. Files to add:
```
.env.example
CONTRIBUTING.md
README.md
README.en.md
config/pdf.php
lang/en/about.php
lang/fa/about.php
resources/views/about.blade.php
resources/views/auth/partials/header.blade.php
resources/views/components/footer.blade.php
package-lock.json
```
**What it does:** Completes the FreeAmir → Dakhl product-brand rename (locked decision D-001) across docs, the about page, footer/header, `.env.example` (`APP_NAME`, `APP_VERSION=0.1.0`), the PDF export title, and `package-lock.json`'s `name` field. Also sets `NATIVEPHP_UPDATER_ENABLED=false` in `.env.example` as a release-safety default (auto-updater should stay off until it's actually configured/verified).
**Verified:** No PHP logic changes; covered incidentally by the full-suite run (no test asserts old "FreeAmir" branding broke).
**Commit message (use verbatim):**
```
chore: complete FreeAmir to Dakhl brand rename (D-001)

Updates README/CONTRIBUTING, the about page, footer/header labels,
.env.example (APP_NAME, APP_VERSION), the PDF export document title, and
package-lock.json's package name to Dakhl, per the locked decision that
Dakhl is the product brand and FreeAmir is upstream/base heritage only.

Also disables the NativePHP auto-updater by default in .env.example
(NATIVEPHP_UPDATER_ENABLED=false) as a release-safety default until the
update channel is actually configured and verified.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add .env.example CONTRIBUTING.md README.md README.en.md config/pdf.php lang/en/about.php lang/fa/about.php resources/views/about.blade.php resources/views/auth/partials/header.blade.php resources/views/components/footer.blade.php package-lock.json
git commit -F <message above>
```

---

### Commit 4 — NOT yet staged. `docs/project-context/` (the canonical context set itself)
**Files:** everything under `docs/project-context/` (currently untracked) — this should be version-controlled since it's the durable, authoritative source the mission brief requires every future session to read first.
**Commit message:**
```
docs: track canonical project-context set (r4/r7) and archive prior revisions

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:** `git add docs/project-context/ && git commit -F <message above>`

---

### Commit 5 — NOT yet staged. Files to add (new this session, 2026-09-14):
```
app/Http/Requests/StoreProductRequest.php
app/Http/Requests/StoreServiceRequest.php
tests/Feature/ProductServiceCodeGenerationTest.php
```
**What it does:** Fixes a real crash discovered while reproducing an owner-reported bug: `StoreProductRequest::getValidatedData()` and `StoreServiceRequest::getValidatedData()` used MySQL-only SQL (`whereRaw('code REGEXP "^[0-9]+$"')` + `CAST(code AS UNSIGNED)`) to auto-generate the next product/service code when the code field is left blank. This throws `SQLSTATE[HY000]: no such function: REGEXP` on SQLite — i.e. it crashes on every desktop-app product/service creation where the user leaves the code field empty (a very common case; the UI presents code as optional/auto-numbered). Replaced with portable PHP (`pluck` + `preg_match` + `max`) that works identically on MySQL and SQLite.
**Verified:** New test `ProductServiceCodeGenerationTest` (2 tests) confirms products/services can be created without a code and get sequential numbers (1, 2, ...) on SQLite. Full suite re-run after this fix: still 20 pre-existing/unrelated failures, 1112 passed (up from 1109 — the 3 new tests from this session).
**Commit message (use verbatim):**
```
fix: make product/service auto-code generation work on SQLite

StoreProductRequest::getValidatedData() and StoreServiceRequest::getValidatedData()
used MySQL-only SQL (REGEXP, CAST(... AS UNSIGNED)) to compute the next
numeric code when the code field is left blank. This threw
"SQLSTATE[HY000]: no such function: REGEXP" on SQLite (the desktop-app and
default test database), crashing every product/service creation where the
user leaves the code field empty.

Replaced with portable PHP (pluck + preg_match + max) that produces the
same result on both MySQL and SQLite.

Found while reproducing an owner-reported bug about reselling a product in
a second invoice; that specific scenario was verified NOT to be a bug (see
CLOUD_AUTONOMOUS_EXECUTION_LOG.md session 2), but this crash was a real,
separate defect discovered along the way.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add app/Http/Requests/StoreProductRequest.php app/Http/Requests/StoreServiceRequest.php tests/Feature/ProductServiceCodeGenerationTest.php
git commit -F <message above>
```

### Commit 6 — NOT yet staged. Files to add (new this session, 2026-09-14):
```
tests/Feature/WarehouseInvoiceStockTest.php
```
**What it does:** Adds `test_the_same_product_can_be_sold_again_in_a_separate_later_invoice` — a permanent regression test for the exact scenario the owner reported as buggy (sell a product, then sell it again from a second invoice). Extensively verified NOT to be a bug (two independent methods, see §7) — this test just locks in that correct behavior so a future change can't silently break it.
**Commit message:**
```
test: lock in that a product can be sold again in a separate invoice

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:** `git add tests/Feature/WarehouseInvoiceStockTest.php && git commit -F <message above>`

---

### Commit 7 — NOT yet staged. Files to add (new this session, 2026-09-14):
```
app/Models/Company.php
lang/en.json
lang/fa.json
tests/Feature/CompanyDeletionFinancialGuardTest.php
```
**What it does:** P0 fix from the read-only audit: `Company::booted()` now registers a `static::deleting()` guard that throws a `ValidationException` if the company has any Document with `approved_at` set (checked with `withoutGlobalScopes()` so it's correct regardless of which company is currently "active"). A company with only draft/unapproved documents (e.g. leftovers from a failed import, PR #381) remains deletable exactly as before — this was verified against the *existing* `CompanyAccessTest` suite, not just new tests.
**Verified:** 14 tests (4 new + 10 existing `CompanyAccessTest`) pass together. Full suite: still the same 17 pre-existing/unrelated failures, 1119 passed (was 1112 in Commit 5/6's count +... see running totals below — the exact number doesn't matter, what matters is the failure *set* never changed).
**Commit message:**
```
fix: block company deletion when approved financial documents exist

Adds a Company::deleting() guard that throws a ValidationException if the
company has any Document with approved_at set, checked via
withoutGlobalScopes() so it is correct regardless of which company is
currently active. Companies with only draft/unapproved documents (e.g. from
a failed import, see PR #381 "allow company deletion after failed
imports") remain deletable exactly as before.

This closes a real data-loss risk: transactions.subject_id/document_id and
documents.company_id were previously widened to cascadeOnDelete()
(migrations 2026_04_13_050229 and 2026_05_10_055743) with no application-
level guard, so a single company delete could wipe an entire posted ledger.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add app/Models/Company.php lang/en.json lang/fa.json tests/Feature/CompanyDeletionFinancialGuardTest.php
git commit -F <message above>
```

### Commit 8 — NOT yet staged. Files to add (new this session, 2026-09-14):
```
app/Services/ChequeService.php
tests/Feature/ChequeManagementTest.php
```
**What it does:** P0 fix from the read-only audit: `ChequeService::bounce()` now calls a new private `reverseInvoicePaymentCredit()` after posting the bounce document — it finds the registration-time Payment (the only one that ever carries `invoice_id`; `clear()`'s payment intentionally doesn't), deletes it, and calls `PaymentService::syncInvoiceStatus()` so the invoice correctly reverts from PAID/PARTIALLY_PAID back to APPROVED (unpaid). Previously a bounced cheque left the invoice falsely marked as paid indefinitely.
**Verified:** 3 new tests (received cheque bounce after deposit, issued cheque bounce, and a cheque with no linked invoice bouncing without error) + full existing `ChequeManagementTest` suite (39/40 — the 1 failure is the pre-existing, unrelated Jalali-date-precision issue). Full suite: still the same 17 pre-existing failures only.
**Commit message:**
```
fix: restore invoice status when a settlement cheque bounces

ChequeService::bounce() previously only posted the bounce accounting
document and updated the cheque's own status, leaving the registration-time
Payment (and therefore the invoice's PAID/PARTIALLY_PAID status) untouched.
An invoice settled by a cheque that later bounced stayed incorrectly marked
as paid indefinitely.

bounce() now deletes that registration payment (the only one that ever
carries invoice_id — clear()'s payment intentionally doesn't, to avoid
double-counting) and re-syncs the invoice status via
PaymentService::syncInvoiceStatus(), for both receivable (customer cheque)
and payable (issued cheque) directions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add app/Services/ChequeService.php tests/Feature/ChequeManagementTest.php
git commit -F <message above>
```

### Commit 9 — NOT yet staged. Files to add (new this session, 2026-09-14):
```
app/Http/Controllers/DocumentController.php
lang/en.json
lang/fa.json
tests/Feature/DocumentImmutabilityTest.php
```
**What it does:** P0 fix from the read-only audit, deliberately scoped narrowly: `DocumentController::update()` and `::destroy()` now call a new `ensureManualDocumentIsEditable()` guard that blocks (a) any document linked to another domain (`documentable` set — invoice/payment/cheque; previously only the `edit()` GUI checked this, not the actual `update`/`destroy` actions, so a direct POST/DELETE could bypass it) and (b) any document that is already approved (`approved_at` set) — the operator must unapprove it first via the existing `changeStatus` action. **This intentionally does NOT touch `DocumentService::updateDocument/updateTransaction/deleteDocument`** — those remain unguarded because `PaymentService`, `ChequeService`, and `InvoiceService` all legitimately call them internally as their own controlled delete-and-recreate correction mechanism; guarding them there would have broken every payment/cheque/invoice correction flow. Full immutability of that internal layer is a larger, separate redesign (likely tied to the future reversal/adjustment model), not attempted this session.
**Verified:** 5 new tests (approved manual doc blocked from update/delete, unapproved manual doc still editable, unapprove-then-edit works, and an approved invoice-linked document is blocked even via direct HTTP bypassing the edit-form redirect). Full suite: still the same 17 pre-existing failures only, 1127 passed.
**Commit message:**
```
fix: block editing/deleting approved documents via the manual Documents screen

DocumentController::update() and ::destroy() had no approved_at guard at
all, and unlike edit(), didn't even check whether the document belongs to
another domain (invoice/payment/cheque) — so a direct POST/DELETE could
bypass the edit-form's own redirect-away check. A plain, manually-created,
approved accounting document could be freely edited or hard-deleted through
the standard "Documents" UI.

Adds ensureManualDocumentIsEditable(), used by both update() and destroy():
blocks documents with a documentable owner (must be corrected through that
owner's own flow) and blocks approved documents (must be unapproved first
via the existing changeStatus action).

Deliberately scoped to this controller only. DocumentService's own
update/delete methods remain unguarded because PaymentService, ChequeService,
and InvoiceService rely on calling them internally as their controlled
correction mechanism for invoice/payment/cheque documents; guarding them
there would break those flows. Full posted-document immutability at the
service layer is a larger follow-up, not attempted here.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
```
**To commit:**
```bash
git add app/Http/Controllers/DocumentController.php lang/en.json lang/fa.json tests/Feature/DocumentImmutabilityTest.php
git commit -F <message above>
```

---

## 3. Explicitly NOT committed — needs follow-up, do not re-attempt blindly

**`nativephp/electron/electron-builder.mjs` and `nativephp/electron/php.js`** — left uncommitted on purpose. The WIP rewrites the embedded-PHP-binary extraction step to use `7zip-bin` (replacing a `yauzl` streaming approach that reportedly truncates large binaries), but **`7zip-bin` is not declared in `package.json` and is not present in `node_modules`** — `require('7zip-bin')` would throw at build time. This is not covered by the PHPUnit suite (it's a Node/Electron build script), so it was not caught by the green test run. **Do not commit these two files as-is.**

**Next safe step for a future phase (not started, do not assume done):**
1. `npm install 7zip-bin --save` (or `--save-dev`, matching how other native build tooling is declared in `package.json`).
2. Confirm `require('7zip-bin')` resolves from `nativephp/electron/php.js` (e.g. `node -e "require('7zip-bin')"` from the `nativephp/electron` directory, or wherever its `node_modules` resolves from — check if `nativephp/electron` has its own `package.json`/`node_modules` separate from the repo root before assuming the root `npm install` is sufficient).
3. If verifying the actual packaged build (`npm run native:build` or equivalent) is feasible in the execution environment (needs network access to download the embedded PHP binary, and may be slow), do so before committing. If not feasible in this environment, at minimum verify the module resolves and document that the full packaging pipeline still needs manual verification before release.
4. Then stage `nativephp/electron/electron-builder.mjs`, `nativephp/electron/php.js`, and the resulting `package.json`/`package-lock.json` dependency addition as their own commit.

**`baseline-dakhl.sql`** (untracked, 0 bytes) and **`build/php/php.exe`** (untracked, a downloaded embedded-PHP binary artifact) — left alone. These look like local scratch/build-output, not source. Recommend adding `/build` to `.gitignore` (currently only `/public/build` is ignored) in a small follow-up commit, and leaving `baseline-dakhl.sql` for the owner to confirm before deleting (it may be an in-progress manual export attempt).

---

## 4. Important open decisions for the owner (not resolved autonomously, by design)

1. **Seeded default admin credential policy** (see Commit 2 above). Is `admin@dakhl.local` / `Dakhl@2026!` intended as a documented, public default (common self-hosted-app pattern, acceptable if paired with a forced-change-on-first-login flow)? Recommend a follow-up phase **P0-02 — Force password change for default admin on first login** before any public v0.1.0 release. Not started.
2. **NativePHP packaging fix is incomplete** (see Section 3) — needs the owner's environment (or a future session with appropriate network/build access) to finish and verify it.
3. **Git identity was never configured in this environment** (Section 0) — resolved by the owner running the two `git config` lines; this is not something to leave as a standing "open decision," just a one-time unblock.

---

## 5. Files touched but NOT part of any group above (still sitting modified, harmless)
None remaining — every modified/untracked file from the original `git status` is accounted for in Commits 1–4 or Section 3.

---

## 6. (Superseded — see §8) Original "next exact action" from earlier in the mission

Everything listed in the original version of this section (immutability, company-deletion guard, cheque bounce) has been **completed and verified** — see Execution Log Entry 4. This section is kept only so the history isn't silently erased; do not act on it. Go to §8 for the current state.

---

## 8. SESSION 2 CONTINUED — full test suite reached 0 failures + new Account Settings feature (2026-09-14, same calendar day, later in the session)

After Entry 4's three P0 fixes, the owner asked to fix the remaining 17 pre-existing test failures (tracked since the very start of this mission) and then add a self-service "change my own username/password" feature. Both are done. **Full suite: 1154 passed / 0 failed.**

### What the 17 pre-existing failures actually were (all fixed, all root-caused — not papered over)

Every one of these turned out to be a genuine SQLite-vs-MySQL behavioral difference (the same class of bug as the enum-migration fix from Entry 2), or one clearly-identified test-environment fragility. None were weakened/skipped to make them pass — see Execution Log Entry 5 for full root-cause detail on each. Summary:

1. **6× `MonthlyBudgetTest`** — `Rule::exists()->where('is_permanent', false)`: passing a raw PHP `false` through Laravel's `Rule::exists()->where()` silently stringifies to `''` (empty string) in the compiled rule string, producing `WHERE is_permanent = ''` instead of `= 0`. MySQL's loose typing makes `'' = 0` true (masking it); SQLite's strict typing makes it false. Fixed with a closure-based `->where(fn ($q) => ...)` instead, which never goes through that stringification path. **File: `app/Http/Controllers/MonthlyBudgetController.php`.**
2. **2× `ReturnInvoiceCOGSTest` + 1× `ReturnInvoiceValidationTest`** — the `SUM(transactions.value) = 0` approval invariant used strict `!= 0`, and SQLite's NUMERIC-affinity float storage produced sums like `-1.7763568394002505e-15` (verified via debug logging) for legitimately-balanced multi-step return scenarios. Changed to `abs($sum) > 0.005` (still a strict cent-level check, not a loosened invariant). **File: `app/Services/DocumentService.php`** (both `approveDocument()` and `approveAll()`).
3. **2× `WarehouseTest`** — (a) the inventory filter did `whereRaw('(SELECT SUM(...)) = ?', [(float) $inventory])`; binding a PHP float against an INTEGER-typed `SUM()` result fails equality in SQLite even when numerically equal (verified with a standalone PDO reproduction) — fixed with a tolerance comparison. (b) the lock test asserted SQL contains `'for update'`, but SQLite's grammar (`SQLiteGrammar::compileLock()`) always compiles locks to an empty string — SQLite has no row-level locking syntax at all, this is a permanent platform limitation, not an app bug — fixed the *test* to only require `'for update'` on non-SQLite drivers. **Files: `app/Http/Controllers/WarehouseController.php`, `tests/Feature/WarehouseTest.php`.**
4. **`ChequeManagementTest`** (1) — `Cheque::write_date`/`due_date` used a plain `'date'` cast; Eloquent's `fromDateTime()` uses the *connection's* datetime format for ALL date-castable attributes regardless of 'date' vs 'datetime', so the raw stored string kept a `00:00:00` suffix on SQLite while MySQL's real `DATE` column type silently strips it. Fixed with explicit `'date:Y-m-d'` format casts. **File: `app/Models/Cheque.php`.**
5. **2× `DocumentImportExportTest`** (CSV import idempotency) — same root cause as #4, but for `Document::date`/`approved_at`. The import de-duplication check (`Document::where('number', ...)->where('date', $date)->exists()`) compared a clean `'Y-m-d'` string against a raw-stored `'Y-m-d 00:00:00'` value and never matched, so every re-import created a duplicate. Fixed the same way. **File: `app/Models/Document.php`.**
   - **IMPORTANT CAVEAT, already handled, do not redo:** the same `date:Y-m-d` fix was *attempted* on 8 other models (`AncillaryCost`, `AttendanceLog`, `Employee`, `Invoice`, `MonthlyAttendance`, `Payment`, `PublicHoliday`, `SalaryDecree`, `WarehouseTransfer`) as a "fix it everywhere at once" pass, and it **broke 38 tests** — specifically `Invoice.date`, because `CostOfGoodsService`, `InvoiceService`, `AncillaryCostService`, and `GroupActionService` all do `Invoice::where('date', ..., $invoice->date)` self-referential comparisons. Eloquent does **not** reformat a raw Carbon object passed as a `where()` value according to the column's cast — it always serializes via Carbon's own default `'Y-m-d H:i:s'` format regardless of the cast. So fixing only the *storage* format broke the *comparison* side for any model with this query pattern. **This was reverted for all 8 files** — they are back to plain `'date'` casts, exactly as before this session. **Do not re-apply `date:Y-m-d` to `Invoice.php` or any other model without first grepping for `where('<column>', ..., $model-><column>)` self-comparison patterns and either fixing every call site to use `->format('Y-m-d')` explicitly, or leaving that model alone.** Only `Cheque` and `Document` were verified safe (no such self-comparison pattern exists for their date columns) and are the only two actually changed.
6. **`BackupControllerTest`** (payroll remap) — the test imported a payload with `payroll.id: 1` into a *fresh* SQLite test database and asserted the remapped payroll's new ID is `!= 1` — but since this is the very first payroll ever inserted in that fresh DB, it also got auto-increment ID `1`, coincidentally matching, even though remapping (via an old-id→new-id lookup table, not a numeric offset) was working correctly. This is a test-fragility issue, not an app bug. Fixed by having the test occupy id=1 first (`Payroll::factory()->create()`) so the assertion is deterministic regardless of environment/auto-increment history. **File: `tests/Feature/BackupControllerTest.php`.**
7. **`CostIncomeDashboardTest`** (leap Esfand day 30) — fixed as a side effect of #5 (`Document.date` fix); no separate change needed. Verify this stays green if `Document.php`'s cast is ever touched again.
8. **`InvoiceCsvExportTest`** — the invoice date-range filter did `where('date', '>=', $request->start_date)` where the request value was slash-formatted (`'2026/06/01'`) but the stored column is dash-formatted (`'2026-06-10'`); MySQL's real `DATE` column type parses either separator leniently on comparison (masking the bug), SQLite's dynamic typing does pure lexicographic string comparison (exposing it). Fixed by normalizing with `str_replace('/', '-', ...)` before the comparison. **Found and fixed in *two* places — `InvoiceController::applyInvoiceFilters()` and a near-identical duplicate method of the same name in `app/Services/ReportExportService.php` (the actual one used by the CSV export route) — these two methods have drifted into parallel implementations of the same filtering logic; worth consolidating in a future cleanup, not attempted this session.**

### New feature: self-service Account Settings (owner request: "the user should be able to create a new username and password for themselves")

Previously the *only* way to change a username/password was the super-admin-only `Management\UserController` (requires `access-super-admin-panel`), or the seeded demo default. Added a new, minimal, self-service page any logged-in user can reach from the header dropdown (next to "API Tokens" / "My Information" / "Logout"):

- **`app/Http/Controllers/AccountController.php`** (new) — `edit()` shows the form, `update()` validates `current_password` (must match via `Hash::check` — this is the identity-confirmation gate for changing credentials), `username` (required, unique ignoring self), `password` (optional, `confirmed`, min 8). Re-establishes the auth session via `Auth::setUser($user->fresh())` after saving so the request's `auth()->user()` reflects the change immediately.
- **Routes** (`routes/web.php`): `GET/PUT /account` → `account.edit` / `account.update`, placed in the plain `['auth', 'ensure-feature-enabled:email_verification']` group (same as `/about`) — deliberately **not** behind `check-permission`, since every authenticated user needs this regardless of role/permissions.
- **View:** `resources/views/account/edit.blade.php` — username field, optional new-password + confirmation, required current-password, using the existing `x-platform-layout`/`x-text-input`/`x-show-message-bags` components (matches `about.blade.php`'s style).
- **Header link:** added to `resources/views/components/header.blade.php`'s user dropdown.
- **10 new tests** (`tests/Feature/AccountSettingsTest.php`): shows current username, change username, change password, keep username unchanged without a password, wrong current-password rejected, duplicate-username rejected, same-username-as-self allowed, password-confirmation mismatch rejected, guest redirected to login, and an end-to-end test that changes the username then actually logs in with the new one.

### Files changed this session-continuation (all new commits, none of Commits 1–9 from earlier in this file changed)

- `app/Http/Controllers/MonthlyBudgetController.php`
- `app/Services/DocumentService.php`
- `app/Http/Controllers/WarehouseController.php`
- `tests/Feature/WarehouseTest.php`
- `app/Models/Cheque.php` (additional change beyond Commit 8 — the `date:Y-m-d` cast)
- `app/Models/Document.php` (additional change beyond Commit 9 — the `date:Y-m-d` cast)
- `tests/Feature/BackupControllerTest.php`
- `app/Http/Controllers/InvoiceController.php`
- `app/Services/ReportExportService.php`
- `app/Http/Controllers/AccountController.php` (new)
- `resources/views/account/edit.blade.php` (new)
- `resources/views/components/header.blade.php`
- `routes/web.php`
- `tests/Feature/AccountSettingsTest.php` (new)
- `lang/en.json`, `lang/fa.json` (accumulated additions from every fix above that needed a new user-facing message)

### Ready-to-commit groups 10–15 (NOT yet staged — same git-identity blocker as Commits 1–9)

Given the volume, these are grouped by fix cluster rather than one-commit-per-file. Bind `lang/en.json`/`lang/fa.json` to whichever commit you make **last** among Commits 2, 7, 9, and 10–15 (they all touch those two files) — or simplest: commit everything else first, then do one final `git add lang/en.json lang/fa.json && git commit -m "chore: add translation strings accumulated this session"`.

**Commit 10 — MonthlyBudget exists-rule fix:**
```bash
git add app/Http/Controllers/MonthlyBudgetController.php
git commit -m "$(cat <<'EOF'
fix: use closure form for MonthlyBudget subject exists-rule on SQLite

Rule::exists('subjects','id')->where('is_permanent', false) silently
stringifies the PHP boolean false to an empty string inside Laravel's
compiled rule string, producing WHERE is_permanent = '' instead of = 0.
MySQL's loose typing coerces '' to 0 for comparison (masking the bug);
SQLite's strict typing does not, so every monthly-budget forecast request
failed subject validation on SQLite. Switched to the closure form of
where(), which operates directly on the query builder and never goes
through that stringification path.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 11 — decimal-safe approval tolerance:**
```bash
git add app/Services/DocumentService.php
git commit -m "$(cat <<'EOF'
fix: tolerate float epsilon in the document balance invariant

approveDocument()/approveAll() compared SUM(transactions.value) with
strict != 0. SQLite's NUMERIC-affinity float storage can produce sums
like -1.7763568394002505e-15 for legitimately-balanced multi-step
transactions (verified via debug logging on real return-invoice
scenarios), incorrectly blocking approval. Changed to abs($sum) > 0.005 —
still a strict cent-level check on a 2-decimal-place column, not a
loosened invariant; a genuine imbalance of a full cent or more is still
rejected exactly as before.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 12 — warehouse inventory filter + lock-test driver awareness:**
```bash
git add app/Http/Controllers/WarehouseController.php tests/Feature/WarehouseTest.php
git commit -m "$(cat <<'EOF'
fix: correct SQLite float-bind and lock-syntax quirks in warehouse code

1. The inventory filter used whereRaw('(SELECT SUM(...)) = ?', [(float)
   $inventory]). Binding a PHP float against an integer-typed SUM() result
   fails equality comparison in SQLite even when the values are
   numerically equal (verified with a standalone PDO reproduction).
   Switched to a tolerance comparison (ABS(...) < 0.005).
2. test_transfer_locks_source_and_destination_rows... asserted the
   captured SQL contains "for update", but SQLite's grammar
   (SQLiteGrammar::compileLock()) always compiles locks to an empty
   string — SQLite has no row-level locking syntax at all. This is a
   permanent platform limitation, not an application bug (the app
   correctly calls lockForUpdate(); SQLite just can't honor it). Fixed
   the test to only require "for update" on non-SQLite drivers, while
   still verifying the query targets the right table/columns/ordering.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 13 — Cheque/Document date-cast precision (extends Commits 8/9):**
```bash
git add app/Models/Cheque.php app/Models/Document.php
git commit -m "$(cat <<'EOF'
fix: store Cheque/Document dates as pure Y-m-d on every driver

Both models used a plain 'date' cast. Eloquent's fromDateTime() applies
the *connection's* datetime format to all date-castable attributes
regardless of 'date' vs 'datetime', so the raw stored string kept a
"00:00:00" suffix on SQLite while MySQL's real DATE column type silently
strips it on insert — the same value looks different depending on which
driver wrote it. This broke exact-string date lookups (e.g. CSV import
de-duplication by document number+date) on SQLite. Fixed with explicit
'date:Y-m-d' format casts.

Deliberately NOT applied to Invoice or 8 other date-cast models in this
pass — CostOfGoodsService/InvoiceService/AncillaryCostService/
GroupActionService all do self-referential `Invoice::where('date', ...,
$invoice->date)` comparisons, and Eloquent does not reformat a raw Carbon
object passed to where() according to the column's cast (it always uses
Carbon's default 'Y-m-d H:i:s'). Applying this fix there broke 38 tests
by making the storage side pure-date while the comparison side stayed
full-datetime. Only Cheque and Document were verified to have no such
self-comparison pattern.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 14 — BackupControllerTest determinism fix:**
```bash
git add tests/Feature/BackupControllerTest.php
git commit -m "$(cat <<'EOF'
test: make payroll-remap-id assertion deterministic

The test imported a payload with payroll.id=1 into a fresh test database
and asserted the remapped id is != 1 — but as the first payroll ever
inserted into that fresh database, it also received auto-increment id 1,
coincidentally matching regardless of whether the id-remapping lookup
table logic worked correctly. Fixed by occupying id=1 with a throwaway
Payroll::factory() row first, making the assertion meaningful regardless
of environment/auto-increment history.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 15 — invoice CSV export date-filter separator bug:**
```bash
git add app/Http/Controllers/InvoiceController.php app/Services/ReportExportService.php
git commit -m "$(cat <<'EOF'
fix: normalize date separators in invoice export/list filters

start_date/end_date filters compared a slash-formatted request value
('2026/06/01') directly against the dash-formatted stored column
('2026-06-10'). MySQL's real DATE column type parses either separator
leniently on comparison (masking the bug); SQLite's dynamic typing does
plain lexicographic string comparison (exposing it — no invoices ever
matched a date-range filter on SQLite). Fixed by normalizing '/' to '-'
before the comparison, in both InvoiceController::applyInvoiceFilters()
and the near-identical duplicate method in ReportExportService (the one
actually used by the CSV export route) — these two have drifted into
parallel implementations of the same filter logic, worth consolidating
later but out of scope here.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 16 — Account Settings feature:**
```bash
git add app/Http/Controllers/AccountController.php resources/views/account/edit.blade.php resources/views/components/header.blade.php routes/web.php tests/Feature/AccountSettingsTest.php
git commit -m "$(cat <<'EOF'
feat: let any user set their own username and password

Previously the only way to change a username/password was the
super-admin-only Management\UserController, or the seeded default. Adds
a self-service /account page (GET/PUT, in the plain auth+
email-verification route group, not gated by check-permission since
every authenticated user needs it regardless of role) that lets a user
update their own username and/or password, requiring their current
password to confirm identity first. Linked from the header's user
dropdown alongside API Tokens / My Information / Logout.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 17 — accumulated translation strings:**
```bash
git add lang/en.json lang/fa.json
git commit -m "$(cat <<'EOF'
chore: add translation strings accumulated this session

Covers messages introduced by Commits 2, 7, 9, and 10-16: login error
wording, company-deletion guard, document-immutability guard, and the
new Account Settings page.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## 8b. CORRECTION to §3 (NativePHP packaging) + Commit 18 (critical Persian-digit login fix)

**Correction:** §3 claimed the `electron-builder.mjs`/`php.js` packaging fix was broken because `7zip-bin` wasn't installed. That check was run against the **wrong** `node_modules` (root, not `nativephp/electron/`). It IS present in `nativephp/electron/node_modules/7zip-bin` (transitive dependency of `electron-builder`), confirmed by direct `require()`. `php artisan native:build win x64` has been run successfully twice this session. **Commit 3 from §2 (`nativephp/electron/electron-builder.mjs`, `nativephp/electron/php.js`) is safe to commit** — do not leave it excluded.

**Commit 18 — critical Persian-digit password normalization fix (highest priority of all pending commits):**
```bash
git add app/Http/Controllers/Auth/LoginController.php app/Http/Controllers/AccountController.php app/Http/Controllers/Auth/PasswordResetController.php app/Http/Controllers/Auth/RegisterController.php app/Http/Controllers/Management/UserController.php tests/Feature/PersianDigitPasswordTest.php
git commit -m "$(cat <<'EOF'
fix: normalize Farsi digits in every password input path

On a Persian keyboard layout, numeric keys type Farsi digits (۰-۹) rather
than ASCII digits. The seeded default admin password ("1234") is entirely
digits, so a Persian-keyboard user typing it at login produces "۱۲۳۴",
which silently fails Hash::check() against the stored ASCII hash — this
broke essentially every login attempt for a real user testing on a second
machine.

The codebase already has an established fix for exactly this problem
(RegisterController::verifyOtp() normalizes the OTP field via
toEnglish()), but no password-handling code path applied the same
normalization — a pre-existing gap, not something this session
introduced. Grepped every Hash::check()/Auth::attempt()/password
assignment in app/ and applied toEnglish() consistently at all five real
gaps: LoginController::login() (username+password), AccountController
::update() (current_password check + new password), PasswordResetController
::resetPassword(), RegisterController::registerWithEmail(), and
Management\UserController::store()/update() (admin setting another
user's password).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
**Verified:** 7 new tests, full suite 1161/1161 passing (0 failures) both before and after. This should be the **first** commit landed once git identity is unblocked, ahead of Commits 1–17, since it's a correctness bug affecting every Persian-keyboard user rather than a nice-to-have.

---

## 10. Market/OSS research session (2026-09-14) — new roadmap decisions recorded, none implemented yet

Owner directed a competitive/OSS research pass (Iranian competitors: Holoo, Sepidar/System Group family, Mahak, Hesabfa; open-source prior art on GitHub) to find gaps in the roadmap, then said "ثبت کن" (record these decisions). Full detail and sourcing lives in `Dakhl_DECISION_LOG_2026-09-12-r4.md`'s new "Market / Open-Source Research Baseline — 2026-09-14" section (Decision IDs D-048–D-053) — read that before starting any of the items below, this section is only a pointer/status summary.

**Nothing below has been implemented, designed in detail, or even branched — this was a research + decision-recording pass only, per the owner's explicit instruction. Do not start writing code for any of these without the owner picking a starting point first.**

- **D-048 Mobile** — pilot NativePHP Mobile (cheque tracking + quick invoice draft), Flutter/Invoice-Ninja-pattern as fallback.
- **D-049 E-commerce** — first-party WooCommerce WP-side plugin as primary deliverable (not just API docs), secondary optional webhook receiver; reuse the D-032 Outbox/Inbox idempotency mechanism, dedup on WC order ID not delivery ID.
- **D-050 CRM** — add `crm_leads` (nullable `subject_id`), `crm_pipelines`/`crm_pipeline_stages`, polymorphic `crm_activities`; no new identity model, `Subject` stays canonical.
- **D-051 Onboarding** — Driver.js (MIT) guided tours + progressive disclosure for advanced fields.
- **D-052 FreeAmir upstream diff** — action item, not yet done: needs `git remote add upstream https://github.com/Jooyeshgar/FreeAmir` (or a fetch of its history) and a real diff against our HEAD to find mergeable Moadian/payroll work. Purely local/git work, not web research — do this first if picking up D-052.
- **D-053 Product tiering** — explicitly left OPEN, needs an owner business decision before any design work, not a technical task.

**Suggested next step when resuming:** ask the owner which of D-048–D-052 to turn into an actual implementation phase first (D-053 needs an owner decision, not implementation). Do not assume priority order — none was given.

---

## 9. Next exact action (current, supersedes §6)

1. Unblock git identity (Section 0), then run **Commit 18 first** (§8b — highest priority, real user-facing correctness bug), then Commits 1–17 in order (1-9 from earlier in this file, 10-17 from §8), verifying `git status`/`php artisan test` stays green between groups.
2. **Commit 19 — the full HesaBora→Dakhl rebrand diff** (§10: text purge, file/branch renames, new SVG logo, `config/nativephp.php` app_id/website update) — land this as its own commit after 1–18, not folded into any of them.
3. **Commits 20–24 in order** (§11 below): Payment Allocation, AR/AP Ageing, Bank Reconciliation, CheckSettlement (all of Phase 1), then Sync foundation 2.1–2.3 (Phase 2, in progress — D-071). Read the note above Commit 20 in §11 about the `payment_allocations` schema before landing it (it was widened to polymorphic in-place for Commit 23, so Commit 20's migration file on disk already reflects the final schema).
4. Re-run the full suite once more after all commits land — expect **1216 passed / 0 failed**, unchanged from pre-commit (committing doesn't alter file contents).
5. Append a completion entry to `CLOUD_AUTONOMOUS_EXECUTION_LOG.md` noting the commit hashes for 1–24 (Entries 8–11 already document what 20–24 contain in detail).
6. **Read `Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md` §23/§24/§30 (updated 2026-09-14) and the D-071 contract in the Decision Log for the current phase order — do not rely on the summary below, it may drift out of sync.** As of this writing, **Phase 1 is complete**; **Phase 2 (Offline/Online sync, D-071) is in progress** — stages 2.1 Sync Contract, 2.2 Device Pairing, 2.3 UUID/Change Tracking are done and tested (Execution Log Entry 11); **stages 2.4 Outbox/Inbox, 2.5 Customer/Product pilot, 2.6 Conflict Handling, 2.7 Document/Invoice, 2.8 Payment/Transaction remain, in that exact locked order — do not skip ahead** (e.g. do not wire Document/Invoice sync before the Customer/Product pilot has proven the Outbox/Inbox + conflict-handling machinery). After Phase 2: Phase 3 UX/onboarding/dashboard/POS-checkout UI, then tax depth, then market-facing growth (API v1, WooCommerce plugin, CRM+marketer, mobile). Smaller, lower-risk items still open, cheap to fold in whenever convenient:
   - Consolidate the duplicated `applyInvoiceFilters()` logic between `InvoiceController` and `ReportExportService` (noted in Commit 15) — a "one financial truth" violation in miniature.
   - Finish the NativePHP packaging fix from Entry 2/Section 3 (`7zip-bin` dependency) — still untouched, still uncommitted, still excluded.
   - The Payment-Allocation/Settlement credit-payment UI forms are functional but minimal (no design/polish pass) — fine for now, worth revisiting during Phase 3's UI work.
7. Continue the same discipline: read real code before trusting docs/roadmap wording, write focused tests, run the full suite before *and* after every change, keep commits small, update this file + the execution log before ending any session.

---

## 11. Commits 20–50 (NOT yet staged — same git-identity blocker) — Payment Allocation + AR/AP Ageing + Bank Reconciliation + CheckSettlement (Phase 1 complete) + Sync foundation 2.1–2.8E, PHASE 2 COMPLETE WITH OPEN DEBT (stage 2.7 Document/Invoice sync + stage 2.8 Payment/Transaction, all sub-stages 2.1 through 2.8E green — nineteen Decisions D-071 through D-089; Trade/Consignment architecture locked as future-only; Commit 37 closes the last two Sync-completion/immutability gaps a post-Phase-2 Final Audit found; Commit 38 closes the highest-priority remaining bypass, Invoice→Payment/Allocation deletion, as a post-Phase-2 hardening run Batch A; Commit 39 closes a D-081 test-coverage gap found in hardening Batch C and records Batches B/D/E's audit findings; Commit 40 implements D-085 (provenance-aware Company deletion, LOCKED and VERIFIED); Commit 41 finalizes D-085 hardening (two boundary tests + a real Invoice-copy uuid bug fix) and locks D-086 (Approved Invoice Financial Immutability, partial — one item DECISION PENDING), see Execution Log Entry 35 — full suite 1391/1391; Commit 45 implements D-089 and closes Phase 5's Cross-Module Financial Integrity audit — full suite 1403/1403)

**Commit 20 — Payment Allocation:**
```bash
git add database/migrations/2026_09_14_223200_create_payment_allocations_table.php app/Models/PaymentAllocation.php app/Models/Payment.php app/Models/Invoice.php app/Services/PaymentService.php app/Http/Controllers/PaymentAllocationController.php routes/web.php database/seeders/RolesAndPermissionsSeeder.php tests/Feature/PaymentAllocationTest.php
git commit -m "$(cat <<'EOF'
feat: add multi-invoice payment allocation (roadmap P1 6.1)

Adds a payment_allocations table as an additive layer alongside the
existing single-invoice payments.invoice_id column, without changing
that existing flow's behavior (verified: full suite unchanged before
and after, since every prior test scenario has zero rows in the new
table).

New: PaymentAllocation model, Payment::allocations()/Invoice::
paymentAllocations() relations, and PaymentService methods
createCreditPayment() (records an advance/credit payment from a
customer not tied to one invoice), unallocatedAmount(),
allocatePayment() (one payment -> multiple invoices, multiple
payments -> one invoice, partial allocation, all validated against
both the payment's unallocated balance and the invoice's remaining
balance), and removeAllocation() (correction without a second bank
movement -- only the allocation row is removed, the payment's own
accounting document is untouched).

PaymentService::paidAmount() now also sums paymentAllocations for an
invoice, and deletePayment() now re-syncs every invoice a deleted
credit payment had allocated (previously only handled the single-
invoice_id case).

New PaymentAllocationController + 3 routes + Spatie permissions
(customers.credit-payments.store, payments.allocations.store/destroy).
No dedicated Blade form yet for creating a credit payment or
allocating it -- backend/route layer only in this pass, UI is a
fast-follow.

Tests: PaymentAllocationTest, 10 tests. Full suite: 1171/1171 passing
after this commit (up from 1161).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**IMPORTANT — read before running Commit 20:** the `payment_allocations` migration file on disk was edited in place (same file, same filename, no second migration) after D-070 to use polymorphic `allocatable_type`/`allocatable_id` instead of a hard `invoice_id` FK, so CheckSettlement (Commit 23) could reuse this exact ledger instead of building a second one. The commit message above still describes the original invoice-only version because it was written before that change — this is fine (nothing was ever committed, so there is no stale history to reconcile), but when writing the *actual* commit message at commit time, mention the polymorphic schema instead of `invoice_id`, or just also read Commit 23's message for the full picture.

**Commit 21 — AR/AP Ageing:**
```bash
git add app/Services/AgeingService.php app/Http/Controllers/AgeingController.php resources/views/ageing/ routes/web.php database/seeders/RolesAndPermissionsSeeder.php resources/views/components/menu.blade.php tests/Feature/AgeingReportTest.php
git commit -m "$(cat <<'EOF'
feat: add AR/AP ageing reports with drill-down (roadmap P1 6.3)

Generalizes the aging-bucket convention already used by
CrmDashboardService (0-30/31-60/61-90/Over 90 days) into a new
AgeingService usable for both receivables (SELL invoices) and
payables (BUY invoices -- suppliers are Customer records too, there
is no separate Supplier model). Uses the precise per-invoice
PaymentService::remainingAmount() (now allocation-aware) as the open-
balance source of truth, an accuracy improvement over the CRM
dashboard's original approximate ledger-credit heuristic.

AgeingService: report() (bucketed totals + per-party breakdown +
per-invoice drill-down), overdueWorklist() (flat list, age > 30 days,
oldest first), statement() (chronological running-balance drill-down
-- covers both "customer statement drill-down" and "supplier
statement" from the roadmap with one method).

New AgeingController + 3 routes under /ageing, permissions, and a
sidebar menu entry under Reports.

Tests: AgeingReportTest, 8 tests, including 2 HTTP-level smoke tests
against the real routes (which required adding the missing Spatie
permission entries for both this feature and Commit 20's routes --
CheckPermission derives the required permission from the route name,
so new named routes need a matching Permission row or every request
403s).

Full suite: 1179/1179 passing after this commit (up from 1171).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 22 — Bank Reconciliation:**
```bash
git add database/migrations/2026_09_14_224810_create_bank_reconciliation_tables.php app/Models/BankStatement.php app/Models/BankStatementLine.php app/Services/BankReconciliationService.php app/Http/Controllers/BankReconciliationController.php resources/views/bank-reconciliation/ resources/views/bankAccounts/show.blade.php routes/web.php database/seeders/RolesAndPermissionsSeeder.php tests/Feature/BankReconciliationTest.php
git commit -m "$(cat <<'EOF'
feat: add bank reconciliation v1 (roadmap P1 6.2)

First version: CSV import, manual matching against existing
accounting Transactions, duplicate-import detection, and match
history via the line's own reconciled_* fields. This service never
creates or posts a new accounting Document/Transaction -- it only
links an already-posted Transaction to an imported statement line, so
"no duplicate financial posting" holds by construction, not by a
runtime check.

New bank_statements/bank_statement_lines tables. A unique
(bank_account_id, dedupe_hash) index is the duplicate-detection
mechanism -- re-importing overlapping rows skips the duplicates
instead of erroring the whole import.

BankReconciliationService: importCsv() (reuses the same
fgetcsv/header-mapping convention as CustomerImportService),
unmatchedLines(), candidateTransactions() (unreconciled Transactions
on the bank account's own settlement subject, ranked by amount
closeness -- a suggestion, the human still picks), matchLine()
(rejects an already-reconciled line or a transaction already claimed
by another line), unmatchLine().

New BankReconciliationController + 4 routes, permissions, a minimal
Blade view, and a "Reconcile" button on the existing bank-account show
page.

Tests: BankReconciliationTest, 8 tests. Full suite: 1187/1187 passing
after this commit (up from 1179).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 23 — CheckSettlement (D-070):**
```bash
git add database/migrations/2026_09_14_223200_create_payment_allocations_table.php database/migrations/2026_09_15_081135_create_settlement_tables.php app/Models/PaymentAllocation.php app/Models/Invoice.php app/Models/SettlementObligation.php app/Models/SettlementInstallment.php app/Services/PaymentService.php app/Services/SettlementService.php app/Http/Controllers/SettlementController.php app/Http/Controllers/ChequeController.php resources/views/settlements/ resources/views/cheques/show.blade.php routes/web.php database/seeders/RolesAndPermissionsSeeder.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/CheckSettlementTest.php
git commit -m "$(cat <<'EOF'
feat: add CheckSettlement per the owner-authored D-070 design contract

Locked as Decision D-070 before any code was written (see
Dakhl_DECISION_LOG_2026-09-12-r4.md), because this touches
accounting-posting-adjacent logic: the acting agent stopped and asked
for direction on the settlement design rather than redesigning
ChequeService's already-working, tested cheque lifecycle. The owner
authored the full contract.

Core rule: Instrument (Cheque lifecycle -- register/deposit/clear/
bounce/endorse/return/cancel/executeGuarantee, completely untouched),
Obligation (SettlementObligation, polymorphic source), and Settlement
(SettlementInstallment schedule + real Payment allocations) are three
independent lifecycles. A bounced cheque can stay Bounced forever
while its obligation moves Open -> Partially Settled -> Settled.

PaymentAllocation is generalized from a hard invoice_id FK to
polymorphic allocatable_type/allocatable_id (safe now -- built this
session, no external consumers yet) so settlement installments and
invoices share the exact same allocation ledger and the exact same
over-allocation guard, instead of a second parallel payment/allocation
system. PaymentService::allocatePayment()'s public behavior is
unchanged; it now delegates to a new low-level allocate() primitive.

SettlementObligation/SettlementInstallment never store a paid/settled
amount or status -- always computed live from payment_allocations, so
nothing can drift out of sync. Creating an obligation or an
installment plan posts zero accounting entries; only
SettlementService::recordInstallmentPayment() (which calls the same
PaymentService::createCreditPayment() used by invoice advance
payments) can create a financial effect.

New SettlementController + 4 routes + permissions + a "Settlement"
section on the cheque show page, deliberately independent of the
existing lifecycle-Actions card.

Tests: CheckSettlementTest, 11 tests, covering every scenario the
owner specified verbatim (partial/full settlement, over-settlement
rejection, no-double-allocation, zero-posting installment plans,
exactly-one-document real payments, Cheque.status independence, and
the owner's own golden/integration test combining Payment Allocation +
CheckSettlement + Bank Reconciliation with no duplicate financial
effect) plus an HTTP-level end-to-end smoke test.

Full suite: 1197/1197 (+11 vs the prior commit) passing, zero
regressions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 24 — Phase 2 stages 2.1–2.3 (Offline/Online Sync foundation, D-071):**
```bash
git add app/Enums/SyncStatus.php app/Enums/SyncOperation.php app/Enums/DeviceInstallationStatus.php app/Models/DeviceInstallation.php app/Models/DeviceActivationCode.php app/Services/DevicePairingService.php app/Http/Middleware/AuthenticateDevice.php app/Http/Controllers/DeviceController.php app/Http/Controllers/Api/DevicePairingController.php app/Models/Concerns/HasSyncEnvelope.php app/Models/Customer.php app/Models/Product.php app/Models/Document.php app/Models/Invoice.php app/Models/Payment.php app/Models/Transaction.php app/Models/Subject.php app/Services/FiscalYearService.php database/migrations/2026_09_15_084520_create_device_pairing_tables.php database/migrations/2026_09_15_085600_add_sync_envelope_to_distributed_entities.php resources/views/devices/ routes/web.php routes/api.php bootstrap/app.php database/seeders/RolesAndPermissionsSeeder.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/DevicePairingTest.php tests/Feature/SyncEnvelopeTest.php
git commit -m "$(cat <<'EOF'
feat: Offline/Online sync foundation stages 2.1-2.3 (D-071)

Locked as Decision D-071 before any migration or sync code was
written, per the same discipline as D-070: the acting agent laid out
six open architectural questions (server/tenant scope, UUID scope,
offline numbering, sync rollout order, server-rejection UX, device
identity) and the owner authored the full seven-point contract plus
the binding 2.1-2.8 implementation order.

2.1 Sync Contract: SyncStatus (PENDING/SYNCED/CONFLICT/REJECTED) and
SyncOperation (CREATE/UPDATE/DELETE) enums -- the shared vocabulary
every later stage builds on.

2.2 Device Pairing/Identity: device_installations + device_activation_
codes tables. DevicePairingService issues short-lived activation
codes (web, session-authenticated) and redeems them in one step,
creating an installation with a fresh installation_uuid + random
device_key (returned in plaintext exactly once, only its hash is
stored). New auth.device middleware validates X-Device-Uuid/X-Device-
Key headers, mirroring the existing SetApiCompany config-based
pattern. New POST /api/devices/pair (no prior credential -- the code
IS the credential) and GET /api/devices/ping (auth.device-protected).

2.3 UUID + Change Tracking: new HasSyncEnvelope trait auto-generates a
canonical uuid on creating (only if not already set), explicitly
defaults sync_version/sync_status in the creating hook (DB column
defaults are not reflected onto the in-memory model immediately after
create()), and adds originDevice()/markSynced()/markConflict()/
markRejected()/needsSyncReview(). Attached to the 7 D-071-scoped
entities (Customer, Product, Document, Invoice, Payment, Transaction,
Subject) via one migration adding uuid/sync_version/sync_status/
origin_device_id to all 7 tables. The local auto-increment id is
never replaced -- uuid is a layered identity column.

Real bug found and fixed during this stage: FiscalYearService's
fiscal-year transfer exports each row's full attribute array and
re-inserts it into a new Company, which was copying uuid verbatim and
colliding with the source row's (globally unique) uuid. Fixed with a
stripSyncEnvelopeColumns() helper at both of FiscalYearService's
entry points into fetchSourceData(), so every transferred row gets a
genuinely fresh uuid -- correct, since a fiscal-year transfer creates
new rows in a new company, not a sync of the same distributed entity.

Tests: DevicePairingTest (11) + SyncEnvelopeTest (7) = 18 new tests.
Full suite: 1216/1216 passing (up from 1198), zero regressions once
the fiscal-year fix landed.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Note on stage 2.4 (Outbox/Inbox):** folded into Commit 24 above per Entry 12 -- `sync_outbox`/`sync_inbox` tables, `SyncOutboxEntry`/`SyncInboxEntry` models, `SyncOutboxService`/`SyncInboxService` were all added as part of the same uncommitted diff, plus the `operation_id`/`base_sync_version` columns were added to that same migration file in place for stage 2.5 below (rather than a second migration for two columns on tables that have never been committed).

**Commit 25 — Phase 2 stage 2.5 (Customer/Product Sync Pilot, gated exit criteria passed):**
```bash
git add app/Models/Concerns/SyncsToOutbox.php app/Services/Sync/SyncContext.php app/Services/Sync/SyncPilotService.php app/Services/Sync/SyncOutboxService.php app/Services/Sync/SyncInboxService.php app/Models/Concerns/HasSyncEnvelope.php app/Models/Customer.php app/Models/Product.php app/Models/SyncOutboxEntry.php app/Models/SyncInboxEntry.php app/Http/Controllers/Api/SyncController.php routes/api.php database/migrations/2026_09_15_091410_create_sync_outbox_and_inbox_tables.php tests/Feature/SyncPilotTest.php tests/Feature/SyncOutboxInboxTest.php
git commit -m "$(cat <<'EOF'
feat: Customer/Product Sync Pilot -- D-071 stage 2.5, gated and passed

The owner explicitly gated this stage before any code was written:
"treat it as a gated Customer/Product Sync Pilot. Do not start 2.6 or
introduce Document/Invoice/Payment/Transaction sync until 2.5 passes
explicit idempotency, conflict, retry, and convergence gates and the
full suite remains green." Four non-negotiables were set: no
automatic sync beyond Customer/Product, enqueue really wired to
mutation events (not just an unused Outbox table), idempotency from
day one, and a genuine two-device conflict resolved per contract
(never silent last-write-wins).

New SyncsToOutbox trait (deliberately separate from HasSyncEnvelope,
so Document/Invoice/Payment/Transaction keep their stage-2.3 identity
columns without silently starting to sync) hooks created/updating+
updated/deleted on Customer/Product to call SyncOutboxService::
enqueue(). New SyncContext::applyingRemote() suppresses re-enqueueing
while the sync system itself applies an incoming change, preventing
an infinite device-to-device echo. HasSyncEnvelope gained an
updating() hook that auto-bumps sync_version on any real business-
field change, skipped when the caller (the sync-apply code) already
set sync_version explicitly.

sync_outbox/sync_inbox gained operation_id (client-generated, unique,
stable across retries -- the idempotency key) and base_sync_version
(the version an UPDATE/DELETE was based on).

New SyncPilotService: pushEntry() checks operation_id first (a resent
batch returns the ORIGINAL cached outcome, never reapplies), then
applies CREATE/UPDATE/DELETE with version-checked conflict detection
-- a base_sync_version mismatch against the entity's current version
is a CONFLICT, never an overwrite. pull() returns rows after a given
local id cursor. New POST /api/sync/push and GET /api/sync/pull,
auth.device-protected.

Two regressions found by the full suite (not the targeted pilot
tests) and fixed: SyncInboxService::receive() (written before
operation_id existed) needed a default-generated operation_id/
base_sync_version; SyncOutboxInboxTest's own pending() test needed to
acknowledge the now-automatic CREATE entry before asserting on its
own manually-enqueued ones.

Tests: SyncPilotTest, 13 tests covering every item on the owner's
exit-gate checklist (create/update/delete enqueue, push, pull, retry/
duplicate idempotency, real conflict -- proven not last-write-wins,
rejected, offline-accumulate-then-reconnect convergence, revoked-
device rejection). Full suite: 1238/1238 passing (up from 1225), zero
regressions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 26 — Phase 2 stage 2.6 (Conflict Resolution Workflow, D-072):**
```bash
git add database/migrations/2026_09_15_132715_create_sync_conflicts_table.php app/Enums/SyncConflictStatus.php app/Enums/SyncConflictResolution.php app/Models/SyncConflict.php app/Services/Sync/SyncPilotService.php app/Services/Sync/SyncConflictService.php app/Http/Controllers/SyncConflictController.php resources/views/sync-conflicts/ routes/web.php database/seeders/RolesAndPermissionsSeeder.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md tests/Feature/SyncConflictResolutionTest.php
git commit -m "$(cat <<'EOF'
feat: Conflict Resolution Workflow -- D-072, stage 2.6 redefined by the owner

The owner corrected the framing before implementation: conflict
DETECTION already exists and was proven in stage 2.5
(SyncPilotService's version check, SyncInboxService::needsReview()).
Stage 2.6 needed the missing back half -- review + resolve -- not a
second detection engine. Locked as D-072 before any code was written.

Locked flow: Conflict detected (2.5) -> persisted conflict record ->
user-visible review queue -> resolution decision (KEEP_SERVER /
KEEP_LOCAL / MANUAL_EDIT only, no generic merge) -> a NEW operation_id
through the SAME 2.5 push pipeline (never a direct mutation) ->
converged state.

New sync_conflicts table + SyncConflict model: a dedicated,
reviewable/resolvable record per conflict (local_snapshot/
server_snapshot, base_version/server_version, origin_device_id,
status, resolution, resolved_by/resolved_at, resulting_operation_id
-- persisted BEFORE the resolution's push attempt so a retry reuses
it instead of generating a fresh one). SyncPilotService's existing
applyUpdate()/applyDelete() conflict branches now also call a new
recordConflict() alongside the existing markConflict() call --
detection logic itself is completely untouched.

New SyncConflictService: openConflicts() (the review queue) and
resolve(). KEEP_SERVER needs no new operation (server data already
wins) -- just settles the conflict as an explicit outcome. KEEP_LOCAL/
MANUAL_EDIT both persist resulting_operation_id first, fetch the
entity's CURRENT server version fresh, then call
SyncPilotService::pushEntry() with that operation_id/payload/version
-- resolution is literally just another push through the unmodified
2.5 pipeline, making it idempotent/retry-safe by construction. A
repeat resolve() on an already-resolved conflict returns the cached
outcome without touching anything.

New SyncConflictController + routes + permissions + a review-queue
view.

Tests: SyncConflictResolutionTest, 8 tests -- every owner-specified
scenario verbatim: conflict visible in review queue with both
snapshots intact; KEEP_SERVER settles without a new operation;
KEEP_LOCAL converges via a new operation (sync_version 1->2->3);
MANUAL_EDIT applies a human payload via a new operation_id; MANUAL_EDIT
without a payload is rejected; resolving the SAME conflict twice
(simulating a network-failure retry) reuses the identical operation_id
and produces exactly one mutation, not two; plus 2 HTTP smoke tests.

Full suite: 1246/1246 passing (up from 1238), zero regressions -- no
bug surfaced this stage, unlike 2.3 and 2.5 which each found one.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 27 — Phase 2 stage 2.7A (Document Draft Sync, D-073):**
```bash
git add app/Models/Concerns/SyncsDocumentDraftsToOutbox.php app/Models/Document.php app/Services/DocumentService.php app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/DocumentDraftSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Document Draft Sync -- D-073, stage 2.7A of Phase 2 sync

2.7 is the first sync stage to touch a financial entity, so -- same
discipline as D-070/D-071/D-072 -- the owner personally authored the
full seven-point contract before any code was written, locked as
D-073. Key rules exercised by this first sub-stage (2.7A of the
locked 2.7A-2.7E staging): Document is the sync aggregate root
(Invoice stays out of scope until 2.7B); Sync state != Accounting
state, so only Draft (approved_at null) documents sync, Approved is
2.7D; official numbering commits at finalization, not now (2.7C), so
`number` is never applied from a remote payload; hard DELETE is
permanently removed from sync for financial documents -- every
DELETE against a Document is rejected outright, never silently
ignored; documentable-linked (automatic) documents stay out of scope
since Invoice/Cheque/Settlement/Payment all attach through that link.

New SyncsDocumentDraftsToOutbox trait (separate from the unconditional
SyncsToOutbox used by Customer/Product): only enqueues a CREATE/UPDATE
while the document isDraftSyncEligible() -- has a company_id,
approved_at is null, documentable_type is empty. No delete hook at
all, since a local delete has no sync-side equivalent to translate
into under decision 5.

Fixed a real ordering bug in DocumentService::createDocument() before
it could reach the sync trait: the original code created the bare
Document row first and only associated its `documentable` in a
second save afterward, so the document's first `created` event would
see a false "manual draft" for what was about to become an automatic,
documentable-linked document. Associating documentable before the
first save fixes this (and removes a redundant UPDATE for every
automatic document as a side benefit).

SyncPilotService extended: Document added to SYNCABLE_ENTITIES; a new
documentDraftSyncViolation() check rejects (as an auditable REJECTED
inbox row, never a silent drop) any DELETE, any payload setting
approved_at, any payload with a documentable link, or any UPDATE
against an already-approved document; a new DOCUMENT_EXCLUDED_ON_APPLY
list (number/creator_id/approver_id/approved_at/documentable_id/
documentable_type) keeps those fields local-only regardless of what a
client sends; pull() now filters Document rows to Draft + non-
documentable-linked only.

Full-suite run (not the targeted tests) caught a second real
pre-existing bug: ActivityLogTest creates a bare Document with no
company_id at all (a genuine legacy/import code path), which crashed
the new enqueue hook with a TypeError. Fixed by folding a
`company_id !== null` check into isDraftSyncEligible() itself -- a
document with no company has no device/company to sync to.

Tests: DocumentDraftSyncTest, 15 tests -- auto-enqueue wiring (create/
update enqueue, approving/deleting/automatic-linking do not), real
push-over-HTTP create/update, every locked-boundary rejection (DELETE,
approved_at in payload, update against an approved document,
documentable link in payload), `number` proven immune to a remote
overwrite attempt, a genuine two-device version conflict still
produces a reviewable SyncConflict row, pull() excludes approved/
automatic documents, retry/duplicate idempotency.

Full suite: 1261/1261 passing (up from 1246), after fixing the one
real regression this stage surfaced (the company_id TypeError above)
-- the fourth stage in Phase 2, after 2.3 and 2.5, where the full
suite (not the feature-specific tests) caught a genuine pre-existing
bug.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 28 — Phase 2 stage 2.7B (Invoice Draft Sync, D-073) + D-074 (Trade/Consignment Architecture, future, documentation-only):**
```bash
git add app/Enums/SyncStatus.php app/Services/Sync/SyncInboxService.php app/Services/Sync/SyncOutboxService.php app/Services/Sync/SyncPilotService.php app/Models/Concerns/SyncsInvoiceDraftsToOutbox.php app/Models/Invoice.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/InvoiceDraftSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Invoice Draft Sync -- D-073, stage 2.7B; lock D-074 (Trade/Consignment, future-only)

Before 2.7B, the owner raised a real domain case a normal Invoice
cannot model: a jeweler swapping 10 coins for 6g of gold with the net
cash difference settled later that day -- multiple items moving both
directions in different units, no up-front Rial total, a Position
that stays open until valuation/settlement. The owner proposed a
future Trade/Deal/Consignment/Open Exchange architecture and, after
being asked how to sequence it against 2.7B, explicitly chose to lock
it as D-074 (documentation-only, zero implementation, zero impact on
Document/Invoice/SettlementService/SyncPilotService today) and run
2.7B independently in parallel rather than pausing Phase 2 sync for
an unscoped subsystem. D-073 gained a scope-clarification addendum
confirming Trade/Consignment is fully outside its boundary.

D-074's locked shape (future only, explicitly no code): a Trade/Deal
aggregate (SALE/PURCHASE/CONSIGNMENT/OPEN_EXCHANGE, the latter two
kept independent since real consignment and open-exchange ownership
semantics differ), multi-unit Entries (native units, no forced Rial
conversion), a Position (running net-by-kind pre-settlement),
separate Valuations, and a Settlement generalizing D-070's
Document-vs-Settlement split to a second future source. Explicit
non-goals: no GenericTradeEntity/GenericPosition/GenericValuation/
UniversalSettlement built now "for later."

2.7B (Invoice Draft Sync) is the first real exercise of D-073
decision 6 (dependency-aware sync): an Invoice's customer_id is a
required NOT NULL foreign key, so a CREATE cannot even be attempted
without resolving the parent Customer first. New
SyncStatus::PENDING_DEPENDENCY -- distinct from CONFLICT/REJECTED,
since it is not a human decision, just a retry once the dependency
lands. pushEntry()'s idempotency check now treats this status as
non-terminal: a resent operation_id re-attempts resolution against
the same stored inbox entry instead of replaying a cached "still
waiting" result forever.

New SyncsInvoiceDraftsToOutbox trait (mirrors
SyncsDocumentDraftsToOutbox): enqueues only while company-scoped,
document_id is null (a Draft never has one in this domain -- a
Document is only created together with approval), status is in an
explicit draft-like allow-list (not a negation of
isApprovedOrSettled(), which misses APPROVED_INACTIVE), and
invoice_type is not a return/void type. Found and fixed the real
cross-entity-reference problem before it reached the wire:
customer_id is a local auto-increment id, meaningless on another
device. SyncOutboxService::enqueue() gained a backward-compatible
optional payload-overrides parameter; the new trait substitutes the
Customer's canonical uuid for customer_id in the outbox payload.

SyncPilotService extended: invoiceDraftSyncViolation() mirrors
documentDraftSyncViolation()'s rejections (DELETE, missing
customer_uuid/invoice_type on CREATE, an approved/settled status, a
Document link, a return/void type, touching an already-linked or
already-approved invoice). resolveInvoiceDependency() resolves
customer_uuid to a local customer_id (or returns a blocked outcome ->
PENDING_DEPENDENCY); normalizes invoice_type/status from the client's
name-strings to real enum instances (a real bug caught while writing
this stage's own tests: Eloquent's native enum cast calls
Enum::from(), which requires the raw int, not "sell"/"pending"); and
-- since invoices.number/vat/subtraction/amount are legacy NOT NULL
columns with no database default, unlike Document's nullable number
-- assigns this device's own next local invoice number (the same
scheme every other local Invoice creation already uses) and
zero-defaults the totals, since 2.7B syncs the header only, no items.

Tests: InvoiceDraftSyncTest, 17 tests -- the core dependency scenario
(push an Invoice referencing a not-yet-existing Customer ->
pending_dependency, invoice NOT created; the Customer arrives locally;
retry with the SAME operation_id -> accepted, invoice created with
the resolved customer_id) plus every locked-boundary rejection, real
push-over-HTTP create/update, version conflict, pull() filtering, and
retry/duplicate idempotency.

Full suite: 1278/1278 passing (up from 1261) -- the first Phase 2
stage since 2.6 to land with zero full-suite-only regressions; both
real bugs this stage were caught by the feature test itself during
development.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 29 — Phase 2 stage 2.7C (Offline Numbering & Reservation Contract, D-075):**
```bash
git add database/migrations/2026_09_15_140500_create_numbering_leases_table.php database/migrations/2026_09_15_140600_add_finalization_to_invoices_table.php app/Enums/NumberingLeaseStatus.php app/Enums/SyncOperation.php app/Models/NumberingLease.php app/Models/Invoice.php app/Services/Sync/NumberingLeaseService.php app/Services/Sync/SyncPilotService.php app/Http/Controllers/Api/NumberingController.php app/Http/Controllers/Api/SyncController.php routes/api.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/InvoiceNumberingSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Offline Numbering & Reservation Contract -- D-075, stage 2.7C

2.7B's interim workaround (each device takes its own next local
Invoice::max(number)+1) was explicitly transitional and unsafe for
real distributed use -- a naive, racy, non-server-authoritative
scheme. Before replacing it, the owner personally authored the full
seven-point Offline Numbering & Reservation Contract, locked as
D-075, with an explicit locked 2.7C-A..E sub-staging and an
instruction not to start 2.7D (Approved Document Sync) until this
stage's reservation/exhaustion/retry/collision tests are green.

The seven locked decisions: a Reserved Range is for official/
finalized numbers only, never Drafts (Draft -> Finalize -> consume ->
immutable); a range is scoped to (company_id, invoice_type,
origin_device_id) -- Organization+Fiscal Year already collapse into
company_id in this codebase's existing convention, so no redundant
fiscal-year column was added; exhaustion blocks offline finalization,
never a provisional number; ranges are server-authoritative via a
locked atomic reservation (SELECT ... FOR UPDATE against the highest
range_end ever issued for that series), never a naive MAX(number)+1;
reserved-but-unused numbers are never recycled in v1; reservation and
consumption are different events -- only Finalize consumes, through
the same idempotent operation_id pipeline as every other sync
operation; the DB unique constraint remains the final collision
guard (the existing invoices_number_invoice_type_company_id_unique
needed no change; the new numbering_leases table gets an analogous
(company_id, invoice_type, range_start) unique index).

New numbering_leases table + NumberingLease model + NumberingLeaseStatus
enum (ACTIVE/EXHAUSTED). invoices gains is_number_finalized/
number_finalized_at, deliberately kept OUT of $fillable so no future
form or payload can ever mass-assign them -- only forceFill() inside
the one controlled path sets them.

New NumberingLeaseService: reserveRange() (the locked, atomic
allocation -- verified with a real concurrent-devices test proving
disjoint ranges) and consumeNext() (locks the device's own active
lease, returns the next number or null -- never invents one).

New SyncOperation::FINALIZE -- a fourth operation, not a flavor of
UPDATE, routed through the exact same pushEntry() idempotency/
version-conflict machinery already proven for CREATE/UPDATE/DELETE.
SyncPilotService::applyFinalize(): rejects if the target doesn't
exist; is a safe idempotent no-op if already finalized; respects the
normal base_sync_version conflict check; calls
NumberingLeaseService::consumeNext() and rejects with an explicit
"Numbering unavailable" message (not PENDING_DEPENDENCY -- this needs
an explicit client action, reserving a new range, not passive
convergence) if the lease is exhausted or missing.

New Reserve Range API (POST /api/sync/numbering/reserve-range,
auth.device-gated).

Found and fixed a real validation bug while writing this stage's own
tests: SyncController::push()'s entries.*.payload rule was
required|array, and Laravel's required rule treats an empty array as
absent -- so FINALIZE's necessarily-empty payload failed validation
with a 422 before ever reaching SyncPilotService. Fixed by changing
the rule to present|array (also widened the operation rule's in:1,2,3
to include the new 4).

Tests: InvoiceNumberingSyncTest, 12 tests -- every owner-specified
gate: disjoint reservation (concurrent devices, separate invoice-type
series); Finalize consuming and locking a number; numbering-unavailable
when no lease exists; exhaustion blocking further offline
finalization without a provisional number, converging once a new
range is reserved; unused numbers from an old lease never recycled by
a later reservation; retry-safety (resent Finalize operation_id
consumes exactly one number); finalizing twice as a safe no-op; both
DB unique constraints as collision guards; and the normal
version-conflict check applying to Finalize too.

Full suite: 1290/1290 passing (up from 1278) -- the second Phase 2
stage in a row (after 2.7B) to land with zero full-suite-only
regressions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 30 — Phase 2 stage 2.7D (Approved Sales Invoice Sync, D-076):**
```bash
git add database/migrations/2026_09_15_150000_add_paired_by_user_to_device_installations_table.php app/Models/DeviceInstallation.php app/Services/DevicePairingService.php app/Enums/SyncOperation.php app/Http/Controllers/Api/SyncController.php app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/InvoiceApprovalSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Approved Sales Invoice Sync -- D-076, stage 2.7D

2.7D is the first stage where a sync operation authorizes a real,
immutable accounting effect rather than transporting a record. The
owner personally authored the full contract before any code was
written, locking it as D-076, with a nine-scenario test gate and an
explicit instruction that sync must reuse the EXISTING accounting
engine (InvoiceService), never invent a second one.

Locked contract: FINALIZED (2.7C) and APPROVED/POSTED are two
separate operations, never one -- a new SyncOperation::APPROVE.
Approval is server-authoritative only, never offline (Finalize
itself already requires contacting the server-authoritative lease
per D-075 -- "offline" here describes the gap between a completed
Finalize and a later Approve, not that Finalize works offline). The
server re-runs the EXISTING InvoiceService::getChangeStatusValidation()
before posting via the EXISTING InvoiceService::changeInvoiceStatus()
-- no parallel accounting logic anywhere in the sync layer. Posted
invoices remain immutable through sync. Three distinct, never-
conflated outcomes: Technical Retry (existing operation_id
idempotency), Business Rejection (REJECTED), Data/Accounting Conflict
(CONFLICT via the existing SyncConflict flow). Posting is idempotent
via the existing operation_id; a repeat Approve on an already-approved
invoice with a MATCHING version is a safe no-op. Initial scope: Sales
Invoice only.

A real gap this stage exposed and closed: InvoiceService::
approveInvoice() (reused unchanged) attributes the resulting Document
via auth()->user() -- but sync has no logged-in web session, and
DeviceInstallation carried no durable "which user does this device
act as" reference. Added device_installations.paired_by_user_id, set
once at pairing from the activation code's issuer (previously
discarded after pairing) -- not a new auth concept, just keeping a
fact that already existed for one request.

SyncPilotService::applyApprove() is routed around the generic Invoice
Draft Sync violation/dependency checks entirely (those protect
Drafts; Approve has its own rules): rejects if the invoice doesn't
exist, isn't a Sales Invoice, or isn't finalized; checks the normal
base_sync_version conflict FIRST; only THEN treats an already-approved
invoice with a MATCHING version as a safe no-op -- this ordering is
what makes the two-devices gate scenario correct (a stale-version
caller gets a deterministic conflict, never a silent "someone already
did it" -- a real ordering bug this stage's own tests caught and
fixed, before the full suite ever ran). Calls
InvoiceService::getChangeStatusValidation() for real revalidation;
temporarily sets the device's paired user via Auth::setUser() (fully
restored in a finally, including a clean logout when there was none)
around the single call to InvoiceService::changeInvoiceStatus();
catches a genuine collision's QueryException and converts it to a
Business Rejection, never an unhandled 500.

Retrofitted the same collision safety into applyFinalize() (2.7C): a
number occupied outside the lease system entirely is now a graceful
REJECTED -- this is where a "number collision" can actually still
occur in this architecture, since Approve itself never rewrites
number.

Tests: InvoiceApprovalSyncTest, 10 tests -- all nine owner-specified
gate scenarios (finalize->approve exactly one posting; no double-post
on repeat approve; finalize/approve as independent round trips;
number collision rejected with zero postings; never-finalized invoice
rejected with zero postings; resent operation_id produces exactly one
posting; UPDATE and DELETE both rejected on a posted invoice; two
devices racing to approve -- one accepted, one conflict) plus the
Sales-Invoice-only scope boundary.

Full suite: 1300/1300 passing (up from 1290) -- the third Phase 2
stage in a row (after 2.7B, 2.7C) to land with zero full-suite-only
regressions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 31 — Phase 2 stage 2.7E (Integration & Hardening Gate, D-077) — stage 2.7 now fully complete:**
```bash
git add app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/Phase2IntegrationHardeningTest.php
git commit -m "$(cat <<'EOF'
test: Document/Invoice Sync Integration & Hardening Gate -- D-077, stage 2.7E

Unlike every prior stage this session, 2.7E is explicitly not a new
feature. The owner personally authored the full contract, locking it
as D-077: an Integration Hardening Gate proving that 2.7A-2.7D
(Customer/Product pilot, Document Draft, Invoice Draft, Offline
Numbering, Approved Sales Invoice) compose correctly as ONE chain --
Customer -> Invoice Draft -> Dependency -> Finalize+Number ->
Approve/Post -> Retry/Conflict/Recovery -> Converged state -- with an
explicit rule: a real sync-infrastructure defect found is fixed in
place; an accounting-semantics question is split into its own future
Decision, never resolved ad hoc here.

A residual bug the owner's own review caught before any 2.7E code
was written: re-reading D-076's fix to applyApprove() (reordering the
"already approved" check to run AFTER the version check, so a
stale-version caller gets CONFLICT instead of a silent accept), the
owner independently flagged that applyFinalize() (2.7C) had the exact
same bug. Fixed identically here.

New tests/Feature/Phase2IntegrationHardeningTest.php, 13 tests
covering every locked gate item: full dependency chain end to end
(worst-case ordering -- Invoice listed before its Customer in the
same offline batch -- pending_dependency, then same-batch Customer
accepted, then a same-operation_id retry succeeds, then finalize,
then approve, one Document, fully converged); partial batch failure
(independent per-entry outcomes -- one batch with an accepted
Customer, an accepted dependent Invoice, a conflicting stale UPDATE,
and a pending_dependency Invoice, with the failures never rolling
back the successes); retry/crash-after-success for CREATE, FINALIZE,
and APPROVE; stale-version conflict vs. idempotent no-op tested
explicitly for both FINALIZE and APPROVE; full-lifecycle enforcement
(Draft edits succeed; Finalized-but-not-Approved may still have
non-number fields edited, confirmed as intentional D-076 behavior;
Approved/Posted rejects both UPDATE and DELETE; hard DELETE rejected
at every stage, not only Posted); multi-device convergence (two
devices push/pull/push, server truth matches what both observed);
exactly-once accounting posting surviving a barrage of retries, a
pull, and a same-version different-operation_id approve afterward.

A real, pre-existing, out-of-scope gap was surfaced while building
the dependency-chain test and deliberately NOT fixed here per
decision 1: a Customer created via the bare Customer Sync Pilot
(stage 2.5) CREATE payload has no accounting Subject
(CustomerGroup -> Subject -> Customer.subject_id), which
InvoiceService::getChangeStatusValidation()/approveInvoice() require.
This is a Customer/accounting-domain gap the Customer Sync Pilot was
never designed to close (it was only ever tested for CRUD sync in
isolation) -- not a Document/Invoice sync defect. The test attaches a
Subject directly as documented, unrelated test setup; the finding is
flagged for a future Decision, not resolved in this stage's code.

Full suite: 1313/1313 passing (up from 1300) -- the fourth Phase 2
stage in a row (after 2.7B, 2.7C, 2.7D) to land with zero
full-suite-only regressions. Stage 2.7 (Document/Invoice sync, D-073)
is now fully complete, 2.7A through 2.7E all green.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 32 — Phase 2 stage 2.8A (Payment Financial Event Identity, D-078):**
```bash
git add app/Models/Payment.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/PaymentFinancialEventIdentityTest.php
git commit -m "$(cat <<'EOF'
feat: Payment Financial Event Identity -- D-078, stage 2.8A

Before any 2.8 code, the owner requested a Read-Only status audit of
PaymentService.php, which surfaced three load-bearing facts about the
existing domain: Payment creation is already atomic (Create ->
Document -> immediate approval, no Draft/Approve split like Invoice);
PaymentService::allocate() is already a generic, allocation-target-
agnostic ledger primitive (D-070); and PaymentService::deletePayment()
performs a real, unconditional hard delete of a posted Payment and
its Document, in tension with the Roadmap's own D-027/D-028. The
owner authored the full D-078 contract grounded in these facts rather
than copying Invoice's Draft/Finalize/Approve shape onto Payment by
default, and explicitly scoped this commit to 2.8A only.

D-078's nine locked decisions (full text in the Decision Log): Payment
stays a one-step financial event, never an Invoice-like two-phase
Draft; offline Payment creation is local-first with an explicit
LOCAL_PENDING_SYNC concept (not built yet -- that's 2.8B); a new
financial_event_uuid identifies the economic event, distinct from a
sync operation_id (a retry preserves both); Payment creation and
Allocation remain fully separate; posted Payments are never
hard-deleted through any runtime (the immutability rule is locked
now, the reversal/correction workflow itself is deferred to its own
future Decision); Cheque lifecycle stays fully independent of Payment
Sync; accounting posting is exactly-once per financial_event_uuid;
Payment/Sync/Allocation are three lifecycles that never share one
status field; Customer->Subject materialization is out of scope
(candidate D-079).

Payment::getFinancialEventUuidAttribute() -- a semantic accessor, not
a new column. Payment already carries a stable uuid via
HasSyncEnvelope (wired since stage 2.3, unused until now); 2.8A's only
job was establishing that this existing identity IS what the Decision
Log calls financial_event_uuid.

Tests: PaymentFinancialEventIdentityTest, 5 tests -- a credit payment
and an invoice-linked payment both get a valid financial_event_uuid
at creation; two distinct Payments never share one; the identity
survives allocation and plain field updates unchanged; and
financial_event_uuid is demonstrably distinct from a sync
operation_id (two SyncOutboxService::enqueue() calls against the same
Payment produce two different operation_ids but the same entity_uuid).

Full suite: 1318/1318 passing (up from 1313) -- the fifth stage in a
row (after 2.7B, 2.7C, 2.7D, 2.7E) with zero full-suite-only
regressions, and the first stage where the targeted tests themselves
found no defect at all.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 33 — Phase 2 stage 2.8B (Offline Payment Creation + Sync, D-079):**
```bash
git add app/Models/Concerns/SyncsPaymentsToOutbox.php app/Models/Payment.php app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/PaymentOfflineSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Offline Payment Creation + Sync -- D-079, stage 2.8B

The first stage where Payment actually enters the Outbox/Inbox/
SyncPilotService pipeline. The owner authored the full D-079 contract,
numbering it after D-078 (superseding the informally-floated
"candidate D-079" for Customer->Subject materialization, which
remains a separate, still-unnumbered future item), and required a
pre-implementation audit rather than a guess on one specific point:
how the server materializes a replayed Payment under the client's own
financial_event_uuid without double-posting.

Audited answer, locked into D-079: PaymentService::createCreditPayment()/
createPayment() always build their own Payment row internally and
cannot accept a pre-built model or an external uuid -- unlike
Document/Invoice's generic applyCreate(), Payment cannot be synced by
filling attributes onto new Payment($attributes), since that would
skip the existing service's Document/Transaction creation entirely
and break D-078 decision 1 (creation IS posting). Locked approach:
call the existing PaymentService method exactly once, then -- still
inside the same transaction -- forceFill the resulting row's uuid to
the client's financial_event_uuid with sync_version reset to 1 (an
identity fix-up, not a second creation or posting).

New SyncsPaymentsToOutbox trait: enqueues a CREATE the moment
PaymentService already created and posted a Payment locally. No
UPDATE/DELETE enqueue at all. Eligibility excludes cheque-linked
payments and -- the one real bug this stage's full-suite run caught
-- excludes any Payment whose company cannot be resolved through its
Document or payer Customer, mirroring the exact fix shape already
used for Document in an earlier stage (FiscalYearService's bulk
company-copy path creates a bare Payment before either is
resolvable). paymentSyncPayloadOverrides() translates local
payer_id/invoice_id/settlement_subject_id into their canonical uuids
the same way Invoice's customer_uuid already does.

SyncPilotService extended: Payment added to SYNCABLE_ENTITIES but
routed around the generic create/update/delete dispatch entirely --
paymentSyncViolation() rejects DELETE, UPDATE, invalid payment_type,
non-positive amount, missing required uuids, and any cheque
reference; resolvePaymentDependencies() resolves subject_uuid
(always) plus customer_uuid or invoice_uuid (by payment_type) the
same dependency-aware way Invoice already does -- a missing reference
becomes PENDING_DEPENDENCY, never a rejection; applyPaymentCreate()
implements the audited materialization answer, using the device's
pairedByUser (D-076's mechanism, reused) as the acting user, and
catches ValidationException/QueryException as graceful Business
Rejections, never an unhandled 500. pull() gained a Payment-specific
branch (no reliable top-level company_id of its own -- scoped via its
Document, cheque-linked rows excluded).

Tests: PaymentOfflineSyncTest, 9 tests -- offline credit payment and
offline invoice-linked payment both accepted with exactly one
Document each; a Payment referencing a not-yet-existing Customer
becomes pending_dependency then converges on retry; resending the
same operation_id never creates a duplicate Payment or posting; pull
returns the synced Payment; DELETE is rejected; a zero-amount payload
is rejected with zero postings; a cheque-referencing payload is
rejected as out of scope; and an end-to-end push->retry->pull
scenario converges to exactly one Payment, one financial event, one
Document.

Full suite: 1327/1327 passing (up from 1318), after fixing the one
real regression this stage surfaced -- the sixth stage in a row
(after 2.7B-2.7E, 2.8A) landing green, and the fourth time this
session the "bare row from a legacy/import path breaks the new
enqueue hook" bug class has surfaced, always caught by the full-suite
discipline, never the targeted tests alone.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 34 — Phase 2 stage 2.8C (Server Financial Authority & Reconciliation Gate, D-080):**
```bash
git add app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/PaymentServerAuthorityGateTest.php
git commit -m "$(cat <<'EOF'
test: Server Financial Authority & Reconciliation Gate -- D-080, stage 2.8C (redefined)

After reviewing 2.8B's own test gate (already proving create/retry/
duplicate-push/crash-recovery/pull-convergence/exactly-one-posting),
the owner determined that 2.8C's originally-planned name ("Server
Posting + Exactly-Once") would mean rebuilding an already-closed
proof. The owner redefined and locked D-080: 2.8C becomes Server
Financial Authority & Reconciliation Gate, narrowed to exactly the
scenarios 2.8B left open -- a DIFFERENT operation_id claiming the SAME
financial_event_uuid, and a server-side business-validation failure
on a payment the device already treats as posted locally.

Five locked scenarios, all now tested: same financial_event_uuid +
matching payload + different operation_id -> idempotent convergence,
not a conflict; same uuid + different payload -> a tamper/data
conflict; different uuid + superficially similar payload -> a
genuinely separate event (proof of absence of payload-similarity
deduplication); a real server-side business validation failure (via
the EXISTING PaymentService, not a sync-invented check) -> a
deterministic REJECTED, never a silent local rollback; Received !=
Validated != Posted stay conceptually distinct but collapse safely to
one clean REJECTED on any failure, with a retried already-rejected
operation_id returning the same cached rejection rather than
re-attempting.

SyncPilotService::applyPaymentCreate() refined: the existing-uuid
branch no longer unconditionally treats a re-declared CREATE as a
conflict. New paymentMatchesExisting() compares only the fields that
define what the economic event actually IS (amount, settlement
subject, resolved target) against the already-existing Payment -- a
match converges idempotently, a mismatch remains a conflict with a
clarified message. Never compares across different
financial_event_uuids -- two different uuids are always two different
events by construction.

Tests: PaymentServerAuthorityGateTest, 5 tests -- exactly the five
scenarios above, none re-testing 2.8B's own mechanics. The business-
validation-failure test doubles as a natural exercise of the still-
open Customer->Subject materialization gap (a customer with no
accounting Subject correctly fails PaymentService's own internal
check, not this Sync layer's pre-checks).

An Open Engineering Item recorded but explicitly not fixed: the
company_id-resolution guard 2.8B needed is the fourth time this
session a bare row from a legacy/system-generated/transfer code path
has broken a new model-event hook (Document in 2.3, Document again in
2.7A, Payment in 2.8B) -- flagged for a future holistic fix, not
refactored here.

Full suite: 1332/1332 passing (up from 1327) -- the seventh stage in
a row (after 2.7B-2.7E, 2.8A-2.8B) with zero full-suite-only
regressions, and the second stage this session where the targeted
tests found no defect at all.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 35 — Phase 2 stage 2.8D (Payment Allocation Sync, D-081, full batch 2.8D-1..5):**
```bash
git add database/migrations/2026_09_15_160000_add_sync_envelope_to_payment_allocations_table.php app/Models/PaymentAllocation.php app/Services/Sync/SyncPilotService.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/PaymentAllocationSyncTest.php
git commit -m "$(cat <<'EOF'
feat: Payment Allocation Sync -- D-081, stage 2.8D (full batch 2.8D-1..5)

The first stage where sync must reconcile a relationship BETWEEN two
already-independent synced entities (Payment and Invoice) rather than
an entity in isolation. The owner authored the full D-081 contract
grounded in a pre-implementation code review confirming
PaymentAllocation had no sync envelope at all, and that
PaymentService::allocate()'s lockForUpdate() only ever serializes
concurrent requests against the same server database -- meaning the
distributed problem reduces cleanly to the same single-database
concurrency problem already solved, provided every allocation is
applied through the existing method, never bypassed. The owner
explicitly authorized running all five micro-stages autonomously
without stopping between them, provided each stage's focused tests
stayed green.

Locked contract: PaymentAllocation becomes its own independent Sync
Entity with a canonical uuid -- the first extension of the UUID-scoped
distributed entity list since stage 2.3. No new SyncOperation; reuses
CREATE with dedicated apply logic, mirroring Payment's own D-079
pattern. Sync never inserts into payment_allocations directly -- the
only path is Sync -> dedicated apply -> existing
PaymentService::allocatePayment() -> existing lockForUpdate() ->
existing business validation. A capacity failure is a Business
Rejection through that existing validation, never a sync-invented
remaining-amount check. Four independent lifecycles (Payment/Sync/
Allocation/Accounting Posting) -- an Allocation rejection never
touches the Payment's own sync_status. DELETE/reversal explicitly out
of scope.

2.8D-1 (Identity): migration adding uuid/sync envelope columns to
payment_allocations; PaymentAllocation adopts HasSyncEnvelope.

2.8D-2 (CREATE sync): PaymentAllocation added to SYNCABLE_ENTITIES,
routed around the generic dispatch entirely. paymentAllocationSyncViolation()
rejects anything but CREATE, non-invoice targets, and missing/invalid
fields. resolvePaymentAllocationDependencies() resolves payment_uuid
and the target invoice_uuid the same dependency-aware way already
proven -- a missing reference becomes PENDING_DEPENDENCY.
applyPaymentAllocationCreate() calls PaymentService::allocatePayment()
exactly once (no Auth::setUser() dance needed -- allocatePayment()
takes its acting User as an explicit parameter), then fixes up the
resulting row's identity to the client's entity_uuid.

2.8D-3 (Partial + multi-invoice): proven entirely by the mechanism
above, no new code.

2.8D-4 (Idempotency + dependency + business rejection):
paymentAllocationMatchesExisting() added, mirroring D-080's
idempotent-convergence-vs-tamper-conflict distinction for Allocation
identity.

2.8D-5 (Integration/convergence): offline-created allocation survives
retry-push and pull, converging to exactly one allocation; DELETE and
UPDATE against an allocation are both rejected as out of scope.

Tests: PaymentAllocationSyncTest, 12 tests across all five
micro-stages -- all passed on the first run, zero defects found in
the entire batch, the first time this session a multi-stage batch
landed clean end to end without a single fix needed mid-flight.

Full suite: 1344/1344 passing (up from 1332) -- the eighth stage in a
row (after 2.7B-2.7E, 2.8A-2.8C) with zero full-suite-only
regressions.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 36 — Phase 2 stage 2.8E (Distributed Payment & Allocation Integration Hardening, D-082, full batch 2.8E-1..5) — PHASE 2 COMPLETE:**
```bash
git add docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md tests/Feature/Phase28IntegrationHardeningTest.php
git commit -m "$(cat <<'EOF'
test: Distributed Payment & Allocation Integration Hardening -- D-082, stage 2.8E (full batch) -- Phase 2 complete

The last Integration Gate for the entire Payment/Allocation chain
(2.8A-2.8D), in the exact same spirit as D-077 (2.7E): explicitly not
a new feature. The owner authored the full D-082 contract and
pre-authorized running all five micro-stages autonomously, stopping
only for a genuine architectural ambiguity or if any of seven
explicit non-goals (reversal, Cheque integration, Trade,
Customer->Subject redesign, a new posting engine, a new
SyncOperation, a generic conflict framework) surfaced as apparently
necessary.

Seven locked scenarios plus one capstone test, all verified: Payment
succeeds/Allocation fails leaves Payment exactly SYNCED with no blind
rollback; chained retries after a simulated crash between an accepted
Payment and its pending Allocation resume only the Allocation, never
recreating the Payment; two devices on the same Payment identity
re-verify D-080's same-payload-idempotent / different-payload-conflict
distinction at the chain level; two devices with conflicting
Allocations against the same Invoice are decided entirely by the
existing PaymentService::allocatePayment() validation; an Allocation
listed before its Payment in the same offline batch becomes
pending_dependency and converges once retried; hard delete on a
posted Payment stays rejected (woven into the first scenario's own
test); and the capstone End-to-End Financial Invariant Test (Payment
100, Allocation A 60, Allocation B 40, under retries/duplicates/a
concurrent device/pulls) converges to exactly one Payment, both
allocations at their exact amounts, the Invoice fully paid, and
exactly one accounting posting.

Zero production code changes were needed -- tests only. This is the
first time an entire hardening stage needed no code change at all,
meaning 2.8A-2.8D's individual designs already composed correctly
without any seam needing patching.

Tests: Phase28IntegrationHardeningTest, 6 tests, all passing on the
first run.

Full suite: 1350/1350 passing (up from 1344) -- the ninth stage in a
row (after 2.7B-2.7E, 2.8A-2.8D) with zero full-suite-only
regressions.

PHASE 2 STATUS: FULLY COMPLETE. Every stage from 2.1 (Sync Contract)
through 2.8E is built, tested, and green -- D-071 through D-082
(twelve Decisions), 1350/1350 passing, zero known regressions.
Deliberately deferred future work: Customer->Subject accounting
materialization, a recurring "bare row breaks a new model-event hook"
Open Engineering Item, Trade/Consignment (D-074, future architecture
only), Payment/Allocation Reversal, Cheque Sync, Transaction Sync,
and Bank Reconciliation Sync.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 37 — D-083 (Payment/PaymentAllocation local Outbox enqueue) + D-084 (Posted Financial Record Immutability at the Service Layer), one batch:**
```bash
git add app/Models/Concerns/SyncsPaymentAllocationsToOutbox.php app/Models/PaymentAllocation.php app/Services/PaymentService.php tests/Feature/PaymentOfflineSyncTest.php tests/Feature/PaymentAllocationSyncTest.php tests/Feature/PostedFinancialRecordImmutabilityTest.php tests/Feature/InvoicePaymentTest.php tests/Feature/PaymentAllocationTest.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: close Phase 2 Sync completion gaps + posted financial record immutability -- D-083 + D-084

A Phase 2 Final Read-Only Audit found two of the sixteen sub-stages
(2.8B, 2.8D) were reported complete but only proven in the
receive/apply direction, and found the most significant gap of the
whole phase: PaymentService::deletePayment()/removeAllocation() could
still hard-delete a posted Payment/Allocation through the ordinary Web
path, even though Sync already rejected this and D-078 already
declared posted Payments are never hard-deleted through any runtime.

D-083 (owner-authored, locked before code): new
SyncsPaymentAllocationsToOutbox trait gives PaymentAllocation its own
local Outbox auto-enqueue, symmetric with Payment's own
SyncsPaymentsToOutbox trait -- CREATE-only, Invoice-targets-only, no
new SyncOperation, no bypass of PaymentService::allocate()/
allocatePayment(). Direct tests proved Payment's own trait -- which
had zero prior direct test coverage despite already working -- and
the new Allocation trait both enqueue exactly once from a real local
domain-service call, never re-enqueue during sync-apply, and never
enqueue on a rejected attempt.

D-084 (owner-authored, locked before code): PaymentService::
removeAllocation() and the non-cheque branch of deletePayment() now
unconditionally reject via a new rejectPostedFinancialMutation()
guard. The cheque-linked branch of deletePayment() is explicitly
preserved unchanged -- discovered empirically when an initial
unconditional guard broke two passing ChequeManagementTest cases
(deleting a cheque-linked payment cascades to its Cheque/history/
documents and is real, tested, load-bearing UI behavior for undoing a
mistakenly-registered cheque). The fix scopes the new guard to exactly
the "financial event" boundary D-078/D-079 already locked (cheque-
linked payments already excluded from Payment Sync), restoring the
cheque branch verbatim rather than inventing a new exception.
Reversal/Correction is deliberately not implemented; the guard raises
a clear ValidationException instead.

Two further bypasses of the new guard were found and explicitly NOT
fixed, per the batch's own scope: Invoice::deleting()'s bulk
paymentAllocations()->delete() plus the DB-level payments.invoice_id
cascadeOnDelete() foreign key (an Invoice hard-delete still silently
destroys its Payments/Allocations without going through
PaymentService), and SettlementService::unsettleInstallment() (deletes
a PaymentAllocation directly but has zero callers anywhere in the app
today).

Tests: PaymentOfflineSyncTest +2, PaymentAllocationSyncTest +3, new
PostedFinancialRecordImmutabilityTest (9 tests dedicated to D-084).
Three pre-existing tests in InvoicePaymentTest/PaymentAllocationTest
that asserted the now-forbidden pre-D-084 delete/removal behavior were
rewritten to assert rejection instead, documented inline as
superseding.

Gate 1 (targeted): 80/80 passed on the first run. Gate 2 (full suite):
first run surfaced the cheque-branch question above (2 failures),
fixed in place per the reasoning above; second run: 1365/1365 passing
(1350 baseline + 15 net new/split tests), zero regressions elsewhere.

Phase 2 status revised: COMPLETE WITH OPEN DEBT, not "fully complete"
without qualification -- remaining open items are the two identified
bypasses above, the systemic Bare-Row pattern (Customer/Product's
SyncsToOutbox trait has no company_id guard), the two-generation
Sync-ingestion architecture inconsistency, and the already-deferred
Reversal/Correction, Cheque Sync, Transaction Sync, and Bank
Reconciliation Sync.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 38 — Post-Phase-2 hardening Batch A: close the Invoice→Payment/Allocation deletion bypass (D-084 hardening, no new Decision):**
```bash
git add app/Models/Invoice.php tests/Feature/InvoiceFinancialDeletionGuardTest.php tests/Feature/InvoicePaymentTest.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md AGENTS.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: close Invoice deletion bypass around Payment/Allocation immutability -- D-084 hardening

D-083/D-084 (Commit 37) closed PaymentService::deletePayment()/
removeAllocation(), but left one identified, unfixed bypass: deleting
an Invoice could still silently destroy its Payments (via the
payments.invoice_id cascadeOnDelete() foreign key) and its
PaymentAllocations (via Invoice::deleting()'s own bulk
paymentAllocations()->delete() query) without ever going through
PaymentService -- defeating D-084's own invariant one level up the
object graph.

No new Decision was needed: D-084 decision 3 already requires this
enforcement not depend only on UI/controller/Sync restriction, which
applies identically to Invoice as it does to Payment. Recorded as an
addendum under D-084 in the Decision Log rather than a new D-08X.

Invoice::booted()'s deleting hook now rejects deletion of any invoice
with a non-cheque Payment or any PaymentAllocation, mirroring D-084's
own cheque exclusion exactly -- InvoiceService::deleteInvoice()
already removes a cheque-linked Payment via ChequeService::delete()
(D-070's independent lifecycle) before this hook runs, so a cheque-only
invoice is unaffected.

Tests: new InvoiceFinancialDeletionGuardTest (7 tests) covering
legitimate deletion of financially-clean invoices, rejection for both
direct-Payment and Allocation cases through the service, rejection of
a direct model-level delete() call (not just the service wrapper), and
that a rejected deletion leaves the Payment/Allocation/items
untouched. One pre-existing InvoicePaymentTest case asserted the
now-forbidden cascade-delete behavior and was rewritten to assert
rejection instead.

Full suite: 1372/1372 passing (up from 1365), zero regressions --
every ChequeManagementTest cheque-invoice-deletion scenario confirmed
unaffected.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 39 — Post-Phase-2 hardening Batches B–E: financial-bypass audit, D-081 test-coverage gap closed, Bare-Row re-audit, next-roadmap reconnaissance (no production behavior changed):**
```bash
git add tests/Feature/PaymentAllocationSyncTest.php docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md docs/project-context/CLOUD_AUTONOMOUS_STATE.md
git commit -m "$(cat <<'EOF'
test: close a D-081 idempotency test-coverage gap; record hardening audit findings (Batches B-E)

Batch B (financial bypass audit across Payment/PaymentAllocation/
Invoice/Document/Transaction): no new code needed. One item stopped
for a future Decision -- InvoiceService::unapproveInvoice()/
updateInvoice()'s approved-edit branch delete/rebuild the Invoice's
own Document with no internal check for existing Payments, mirroring
the exact shape of bypass Batch A just closed for deletion, but
InvoiceController::changeStatus() already blocks it at the controller
level and fixing the service layer requires a policy call the owner
should make, not an inferred one.

Batch C (D-083/D-084 regression audit): found that
SyncPilotService::paymentAllocationMatchesExisting() -- the D-080-style
same-identity/different-operation_id idempotent-vs-conflict distinction
-- has been correctly implemented since D-081 but was never directly
tested (only Payment's own equivalent had a dedicated test file). Added
two tests to PaymentAllocationSyncTest; both passed on the first run,
confirming the code was already correct.

Batch D (Bare-Row / Sync boundary re-audit): re-confirms the pattern is
systemic (FiscalYearService predates Sync, mixes save()/saveQuietly()
inconsistently) and that Customer/Product's SyncsToOutbox trait still
has no company_id guard -- but no live bypass was found (Customer is
protected by a NOT NULL DB constraint, Product by its own model hook),
so nothing was changed, per this batch's own "only fix what's
production-reachable" rule.

Batch E (next locked roadmap work): re-read the full Roadmap and
Decision Log. Every owner-authored, LOCKED Decision (D-070-D-084) is
now implemented. The roadmap's older pre-Sync P0-P16 checklist is not
in Decision form and none of it is implementation-ready without an
owner-authored contract, per this run's own rule. Read-only
reconnaissance on the most concrete, owner-flagged item (roadmap
5.0, "ACTIVE / BLOCKING MAJOR EXPANSION") found a significant new
issue: CompanyController::destroy() has no guard at all, and a 2026-04
migration deliberately cascades company_id deletes across
invoices/documents/payments at the DB engine level -- silently
bypassing every financial-immutability guard built this entire
session, because a DB-level CASCADE never fires an Eloquent model
event. Not implemented (requires a new owner decision, especially
given a recent, deliberate commit -- #381 -- that intentionally kept
company deletion possible after failed imports). Proposed as a future
Decision question in Execution Log Entry 30. Run concludes here per
its own "do not invent work" rule.

Full suite: 1374/1374 passing.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 40 — D-085 LOCKED and implemented: provenance-aware Company deletion:**
```bash
git add app/Enums/FinancialProvenance.php app/Models/Company.php app/Models/Document.php app/Services/FiscalYearService.php database/migrations/2026_09_17_120000_add_provenance_to_documents_table.php tests/Feature/CompanyDeletionFinancialGuardTest.php lang/en.json lang/fa.json docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: provenance-aware Company deletion -- D-085

D-085's forensics (Entry 31) found Company::deleting()'s pre-existing
guard too broad: approved_at alone cannot tell a Company's own local
financial activity apart from approved history it legitimately copied
in from another fiscal year, so a freshly-copied-by-mistake Company
became permanently undeletable the moment its copy included any
approved Document.

The owner locked Option A: provenance-aware deletion, explicitly
rejecting a naive companies.is_copied flag (proven insufficient --  a
copied Company can later receive genuine new native activity while
such a flag stays permanently true).

Architecture inspection (before any schema choice) found
FiscalYearService::_importDocuments() is the single choke point every
copy/import path routes through -- cheques/invoices/payments/
ancillary-costs never create their own Document rows during import,
they only reference already-imported document ids. It also found no
import path copies PaymentAllocation at all today.

Implementation, the smallest design the architecture actually
supports: one new column, documents.provenance (FinancialProvenance
NATIVE|BASELINE, NATIVE the NOT NULL default so every existing/legacy
row and every ordinarily-created Document is safely native with zero
other code changes needed), set to BASELINE in exactly one place
(_importDocuments()). PaymentAllocation needs no column at all since
it is never copied -- its mere existence is already a reliable
native-activity signal. Company::deleting() now blocks on any approved
NATIVE Document or any PaymentAllocation for the Company.
FiscalYearTransferService (a different, per-document transfer feature)
is deliberately left untouched -- a transferred document safely
defaults to NATIVE.

Tests: CompanyDeletionFinancialGuardTest rewritten 4 -> 12 (+8 net),
covering the full matrix including a production-path fiscal-year copy
that stays deletable until it receives new native Payment/Allocation/
Document activity, and a legacy row with no explicit provenance value
proving the column default itself protects pre-D-085 data.

Full suite: 1382/1382 passing, zero regressions -- Cheque, D-084,
fiscal-year transfer/copy atomicity all confirmed unaffected.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 41 — D-085 final hardening (two owner-required boundary tests + a real Invoice-copy uuid bug fix) + D-086 Approved Invoice Financial Immutability (locked, implemented, one item DECISION PENDING):**
```bash
git add app/Services/FiscalYearService.php app/Services/InvoiceService.php tests/Feature/CompanyDeletionFinancialGuardTest.php tests/Feature/ApprovedInvoiceFinancialImmutabilityTest.php lang/en.json lang/fa.json docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: D-085 final hardening + D-086 Approved Invoice Financial Immutability

D-085 final hardening: two owner-required boundary tests. Test A proves
a Company whose only financial history is a copied Document AND a
copied Payment (via the real production
FiscalYearService::createWithCopiedData() path, which pulls Payments
in automatically alongside Documents/Invoices) is still correctly
deletable -- the "baseline-only is deletable" claim holds for Payment,
not just a bare Document. Test B proves editing a copied, BASELINE,
approved Invoice through the real InvoiceService::updateInvoice() path
deletes the old Document outright and creates a brand-new one that
lands as NATIVE (the column default), never leaving a financially
mutated record as BASELINE -- the exact scenario that would expose a
real D-085 bypass. Neither test needed an architecture change to pass.

Found and fixed a real, unrelated bare-row bug while building Test B:
FiscalYearService::_importInvoices()'s saveQuietly() (correctly used
to suppress SyncsInvoiceDraftsToOutbox's enqueue during copy) also
silently suppressed HasSyncEnvelope's uuid-assignment hook -- unlike
sync_version/sync_status, uuid has no DB-level default, so every
copied Invoice got uuid = NULL, invisibly, until a later update() (a
real save) crashed trying to enqueue a sync operation with a null
entity_uuid against sync_outbox's NOT NULL constraint. Fixed with the
narrowest possible change: explicitly assign a fresh uuid before
saveQuietly() in both _importInvoices() loops, not a save() swap
(which would have wrongly started enqueueing copied Draft invoices as
newly-created local drafts).

D-086: InvoiceService::updateInvoice() now rejects any financial
content change (items/totals/customer/warehouse, or the approved-edit
rebuild branch outright) once an Invoice has a real Payment or
PaymentAllocation -- this was the one Invoice-mutation path confirmed
to have zero guard at any layer. Non-financial metadata and
Approved-invoices-without-payment-yet are completely unaffected.

Guarding unapproveInvoice() itself (also requested) was attempted and
reverted: it broke GroupActionService::inactivateDependentInvoices()/
approveInactiveInvoices(), an existing, trusted mechanism that reuses
changeInvoiceStatus('unapproved') as a low-level primitive to cycle a
downstream invoice's status for COG-chain consistency when an earlier
Buy invoice changes, never touching its own amount/customer. Since
that trusted caller and a hypothetical untrusted one share the same
public method with no way to distinguish them, this is recorded as an
open Decision question in the Decision Log's D-086 entry rather than
resolved by guessing.

Tests: CompanyDeletionFinancialGuardTest +2 (14 total),
ApprovedInvoiceFinancialImmutabilityTest new (7 tests).

Full suite: 1391/1391 passing (up from 1382), zero regressions --
including the exact InvoiceGroupActionTest case that first caught the
over-broad version of the D-086 guard.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 42 — D-087: explicit separation of user-facing unapprove from internal COG status cycling -- D-086 fully closed:**
```bash
git add app/Services/InvoiceService.php app/Services/GroupActionService.php tests/Feature/InvoiceUnapproveBoundaryTest.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: separate user-facing invoice unapprove from internal COG status cycling -- D-087

D-086 left one open item: InvoiceService::unapproveInvoice() is a
shared low-level primitive also used internally by
GroupActionService::inactivateDependentInvoices()/
approveInactiveInvoices() to cycle a downstream invoice's status
(Approved -> unapprove -> APPROVED_INACTIVE -> reapprove) for COG-chain
consistency when an earlier Buy invoice changes -- guarding it broke
that real, tested mechanism.

The owner explicitly rejected a boolean bypass/force/internal flag on
unapproveInvoice() as the fix, since a future caller could trivially
defeat the financial guard through it. Locked design instead: two
structurally separate, explicitly named entry points.

unapproveInvoice() (private, the only path changeInvoiceStatus
('unapproved') and therefore InvoiceController::changeStatus() can
ever reach) now enforces the D-086 financial-immutability guard
unconditionally. A new public
recycleInvoiceStatusForDependencyRecalculation() is reserved for
GroupActionService's internal mechanism -- unguarded not via a
parameter, but because it is a different method entirely.
GroupActionService no longer calls the general-purpose
changeInvoiceStatus() at all, for either direction of its cycle.

Verified the internal primitive is provably non-financial in the
sense these Decisions protect: it never touches the invoice's own
amount/vat/customer_id/items, and never creates/deletes/modifies a
Payment or PaymentAllocation row -- confirmed directly against an
already-Paid invoice, the exact case that broke the earlier
over-broad guard. It does delete/recreate the Document row and
recompute COG, an intrinsic, pre-existing part of this mechanism, not
new behavior.

Tests: InvoiceUnapproveBoundaryTest (6 tests) -- user unapprove with
Payment/Allocation rejected, unaffected without settlement, the
internal primitive proven non-financial against a Paid invoice, the
real GroupActionService flow green end to end with a Payment attached,
and a reflection-based test proving no bypass flag exists anywhere.

Full suite: 1397/1397 passing (up from 1391). D-086 is now fully
closed -- no open items remain.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 43 — D-088: Approved Invoice re-approval is service-layer guarded -- Approved Invoice Mutation Boundary (D-086+D-087+D-088) fully closed:**
```bash
git add app/Services/InvoiceService.php tests/Feature/ApprovedInvoiceFinancialImmutabilityTest.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: guard Approved Invoice re-approval at the service layer -- D-088

Phase 1's final boundary audit (post-D-087) exhaustively enumerated
every external caller of changeInvoiceStatus(), updateInvoice(),
approveInvoice(), unapproveInvoice(), and
recycleInvoiceStatusForDependencyRecalculation() -- exactly 4 exist.
Found: changeInvoiceStatus('approved') had no service-layer guard of
its own, unlike 'unapproved' (D-087). Both its real callers
(InvoiceController, SyncPilotService::applyApprove()) already prevent
reaching it on a settled invoice via their own upstream checks, but
the service layer should not depend on that remaining true forever.

New private userFacingApproveInvoice() wrapper applies the identical,
already-locked D-086 invariant symmetrically. approveInvoice() itself
stays unguarded for its two legitimate direct callers
(recycleInvoiceStatusForDependencyRecalculation() from D-087,
voidInvoice()'s brand-new-row case) -- same structural pattern as
D-087, a separate guarded wrapper, never a flag.

Test: direct-service re-approval of a Paid invoice is rejected with
zero side effects (Document, amount, warehouse quantity unchanged).

Full suite: 1398/1398. Approved Invoice Mutation Boundary
(D-086+D-087+D-088) is now closed on every confirmed front: edit/
rebuild, unapprove, re-approve, delete.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 44 — Money/Decimal Safety bounded fix + Settlement dead-code audit correction + Cheque lifecycle audit (no defects found):**
```bash
git add app/Services/InvoiceTransactionBuilder.php tests/Feature/InvoiceMoneyRoundingTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: round VAT/item-amount at the point of calculation -- Money/Decimal Safety bounded slice

Audited real (not theoretical) money/decimal risk points across
PaymentService, InvoiceService, SettlementService,
InvoiceTransactionBuilder. DB columns are correctly decimal(x,2)
throughout; PaymentService/SettlementService already apply a
consistent 0.001 epsilon at every real overpayment-validation
boundary; no dangerous exact-equality float comparison exists
anywhere. One real, empirically-confirmed defect found:
InvoiceTransactionBuilder::extractItemValues() computes VAT-by-
percentage and item-amount via multiplication with zero rounding
anywhere downstream.

Verified empirically (a deleted-after-use probe script) that SQLite
(every automated test's database) does not enforce decimal(x,2)
precision at all, while MySQL/MariaDB (production) rounds each column
independently at insert -- and confirmed, by temporarily reverting the
fix and rerunning, that this is not merely a test-vs-production
discrepancy: DocumentService::createDocument()'s own existing
validation ("value must have 0-2 decimal places") actively rejects
ordinary VAT/price combinations without this rounding, a real,
reproducible, production-affecting invoice-creation failure.

Fixed with round($itemVat, 2) and round($itemAmount, 2) at the single
point of multiplication, before any aggregation -- the correct fix for
the SUM(transactions.value) = 0 balanced-document invariant too,
since rounding each component once before aggregation guarantees the
balance holds by construction rather than by N independent DB-side
roundings happening to cancel out.

Also corrected an audit-integrity issue found while investigating
SettlementService::unsettleInstallment() for dead-code removal: this
session's own prior documentation claimed "dead code, zero callers"
in 5 places, but a real test (CheckSettlementTest) calls it directly
and depends on its behavior -- the method was never removed or
changed, only the incorrect characterization was corrected.

Also audited the Cheque bounce lifecycle's direct Payment::delete()
call and confirmed it is the same, already-sanctioned D-084
cheque-lifecycle exception (structurally cannot orphan a
PaymentAllocation) -- no defect, no code changed.

Tests: InvoiceMoneyRoundingTest (2 tests), verified to actually fail
without the fix before being confirmed green with it restored.

Full suite: 1400/1400 passing (up from 1398).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 45 — D-089: Approved Invoice rejection is service-layer guarded -- Approved Invoice Mutation Boundary (D-086+D-087+D-088+D-089) fully closed + Phase 5 Cross-Module Financial Integrity audit:**
```bash
git add app/Services/InvoiceService.php tests/Feature/ApprovedInvoiceFinancialImmutabilityTest.php tests/Feature/InvoiceMoneyRoundingTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: guard Approved Invoice rejection at the service layer -- D-089

Phase 5's Cross-Module Financial Integrity audit (tracing Invoice ->
Document -> Transaction -> Payment -> PaymentAllocation/Settlement ->
Installment -> Subject Balance against the owner's 10 questions)
found: changeInvoiceStatus('rejected') was a bare status update with
no service-layer guard -- the one status-transition direction
D-086/D-087/D-088 never covered. Marking a financially-active invoice
REJECTED is a reversal via a different status label: Document/
Transactions/Payment/PaymentAllocation all stay untouched, only the
label changes. Today unreachable in practice (the only caller,
InvoiceController::changeStatus(), already blocks it via its own
isPartiallyPaid()/isPaid() check), but the service layer should not
depend on that remaining true forever -- identical reasoning to D-088,
a mechanical, symmetrical extension of the already-locked D-086
invariant, not a new business policy.

New private userFacingRejectInvoice() wrapper applies the same
hasEffectiveFinancialActivity() guard. Verified the regression test
actually catches the bug: temporarily reverted the guard, reran,
confirmed failure (REJECTED does not match expected PAID), restored,
reconfirmed green.

Also closed a test-coverage gap (no code change): the Phase 2 Money/
Decimal rounding fix only had single-line-item coverage. Added a
multi-line-item regression test confirming per-item rounding-before-
aggregation still balances to zero across several differently-shaped
fractional lines -- passed on the first run.

All other Phase 5 questions (duplicate financial effects, reversal
idempotency, state/ledger sync, live balance reconstructability,
Settlement double-settle/un-settle completeness, delete/cascade
orphaning) checked and confirmed holding by inspection and existing
tests -- no other defect found.

Full suite: 1403/1403 passing (up from 1400). Approved Invoice
Mutation Boundary (D-086+D-087+D-088+D-089) is now closed on every
confirmed front: edit/rebuild, unapprove, re-approve, reject, delete.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 46 — fix: enforce invoices.approve permission on store/update/change-status (Phase 8 authorization audit, no code change from Phases 6-7):**
```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/InvoiceApprovalAuthorizationTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: enforce invoices.approve permission on store/update/change-status

Phase 8's Authorization + Destructive Actions audit (multi-tenancy
company-scoping checked and confirmed solid; every Invoice destructive
action's permission enforcement then audited) found:
InvoiceController::store() and update() both called
auth()->user()->can('invoices.approve') and discarded the boolean
result -- no if, no abort_unless, no assignment. invoices.approve is a
real, separately-seeded permission deliberately excluded from the
Seller role (RolesAndPermissionsSeeder) alongside invoices.change-status,
i.e. the system's own role design already intends Sellers to create/
edit invoices but never approve them. Because the check's result was
thrown away, any Seller could approve an invoice outright just by
adding approve=1 to an ordinary create/update request, bypassing the
intended segregation-of-duties control entirely. changeStatus() had no
permission check of its own at all.

Not a new Decision: the permission already existed and was already
seeded/excluded correctly -- this restores already-intended behavior
broken by a coding mistake, the same class of fix as the Phase 2
Money/Decimal rounding bug.

Fixed with abort_unless(auth()->user()->can('invoices.approve'), 403)
at all three call sites, the codebase's own existing convention
(CompanyController, HomeController, Management\UserController).
Verified no currently-seeded role has invoices.change-status without
also having invoices.approve, so changeStatus()'s new gate introduces
no regression for any existing role. approveInactiveInvoices()/
groupAction() deliberately left ungated, consistent with D-087's
already-established finding that they are non-financial, internal
COG-chain mechanics, not real user-facing approval.

Test: 6 new tests proving both directions (403 without the permission,
success with it) for all three call sites. Verified to actually catch
the bug: temporarily reverted all three fixes, reran, confirmed the
three "rejected" tests failed (302 instead of 403 -- silently
approved), restored, reconfirmed green.

Full suite: 1409/1409 passing (up from 1403). Also includes Phase 6
(Sync/Replay/Idempotency) and Phase 7 (Baseline/Provenance Integrity)
audit findings -- both concluded no code change needed.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 47 — fix: Cheque return/cancel reverse invoice payment credit + AncillaryCost authorization guard (Financial Integrity Closure Batch; unsettleInstallment() left unresolved):**
```bash
git add app/Services/ChequeService.php app/Http/Controllers/AncillaryCostController.php tests/Feature/ChequeManagementTest.php tests/Feature/AncillaryCostApprovalAuthorizationTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: Cheque return/cancel reverse invoice payment credit; guard AncillaryCost approval

Financial Integrity Closure Batch, Section 5: ChequeService::bounce()
already calls reverseInvoicePaymentCredit() so a bounced cheque's
invoice doesn't stay incorrectly marked paid. returnToCustomer()
('return', reachable directly from REGISTERED) and cancel()
('cancel', reachable directly from ISSUED for every non-guarantee
cheque, since purpose is always SETTLEMENT) mean exactly the same
underlying fact -- the cheque was never actually collected/paid --
but were both missing this call. Zero test coverage existed for
either action, which is how the gap went unnoticed. endorse() checked
and correctly excluded: the invoice genuinely was settled by that
cheque. Verified each fix fail-first: a dedicated regression test
confirmed to fail against the pre-fix code, then the one-line fix
applied (mirroring bounce() exactly), then reconfirmed green.

Section 6: the identical discarded-can()-return-value defect Phase 8
found and fixed in InvoiceController also existed, unchanged, in
AncillaryCostController::store()/update()/changeStatus() --
ancillary-costs.approve is a real, separately-seeded permission
excluded from Seller, exactly mirroring invoices.approve. Fixed with
the same abort_unless(...) convention. A repository-wide sweep
confirmed these were the only two controllers with this defect.

Section 4: SettlementService::unsettleInstallment() formally isolated
as an unresolved architecture question, not fixed, not guarded, not
removed -- re-confirmed exactly one caller anywhere (tests only), but
none of guard/leave/wire-up-with-Reversal-semantics is a neutral
mechanical fix; each is itself a policy choice D-084 already deferred
to a future Decision. Full reasoning in the Decision Log. Owner has
indicated this becomes D-090, investigated read-only first.

Zero new Decisions locked -- both fixes are mechanical extensions of
already-locked invariants (D-070's bounce() mechanism; the existing
authorization convention).

Tests: ChequeManagementTest (+3), AncillaryCostApprovalAuthorizationTest
(new file, +4). Full suite: 1417/1417 passing (up from 1410).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 48 — fix: Cheque-deletion Settlement-orphan guard + centralized COG/Inventory rounding + copied-Customer uuid gap (Sequential Roadmap Execution Queue, Batches 1/2/4; Batches 3/5/6/7 verification-only, no code):**
```bash
git add app/Services/ChequeService.php app/Services/InvoiceTransactionBuilder.php app/Services/FiscalYearService.php tests/Feature/CheckSettlementTest.php tests/Feature/InvoiceMoneyRoundingTest.php tests/Feature/CompanyDeletionFinancialGuardTest.php AGENTS.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: Cheque-deletion Settlement guard, centralized COG rounding, Customer copy uuid gap

Sequential Roadmap Execution Queue, Batch 1: ChequeService::delete()
had no guard against orphaning a SettlementObligation with real
settled activity -- settlement_obligations.source_id is a plain
polymorphic column with no FK at all (by design). Deleting a Cheque
mid-way through a bounced-cheque recovery destroyed a real, already-
collected PaymentAllocation with no trace of why it existed, the same
class of violation the Invoice-deletion guard already closed. Fixed
with the identical guard shape: rejects only when real settled
activity exists, an Obligation with no payment yet still allows
deletion. Verified fail-first.

Batch 2: extractItemValues()'s docblock claimed unit_price*quantity
and VAT were the only two multiplications that could exceed 2
decimals -- disproven empirically (average_cost * a fractional
quantity, a real decimal(10,2) column, produces 4 decimals that
DocumentService::createDocument()'s own validation rejects). Fixed
architecturally: every one of InvoiceTransactionBuilder's ~15 raw
transaction-value pushes now routes through a new centralized
pushTransaction() helper that rounds at the single true choke point,
covering SELL/BUY/RETURN_SELL/RETURN_BUY and the four shared builders.
Self-caught and fixed a regression during this same batch's own Gate
2: the helper's `int $subjectId` type hint was stricter than the
original code, which tolerated a null subject id for a Service item
with no subject configured.

Batch 4: _importCustomers() creates each copied Customer via
saveQuietly(), which suppresses HasSyncEnvelope's own creating hook --
the identical bug class D-085 already found and fixed for
_importInvoices() (uuid has no DB default, unlike sync_version/
sync_status). A copied Customer with uuid = NULL crashed on its first
later update() via SyncsToOutbox's NOT NULL sync_outbox.entity_uuid
constraint. Fixed identically: explicit uuid assignment before
saveQuietly().

Batches 3/5/6/7 verification-only: desktop login-500 already fixed
(re-confirmed against current code, roadmap checklist corrected);
Bank Reconciliation already fully implemented (roadmap corrected);
Sync/Offline-Online integrity re-confirmed, one theoretical-only
(cryptographically infeasible) observation recorded, not acted on;
P0/P1 matrix rebuilt from verified current state.

Zero new Decisions across the entire 7-batch queue -- every fix is a
mechanical extension of an already-locked invariant.

Full suite: 1421/1421 passing (up from 1417).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 49 — fix: Cheque edit rejected once a SettlementObligation exists + D-090 final three-part decision statement (Final Closure Pass):**
```bash
git add app/Services/ChequeService.php tests/Feature/CheckSettlementTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: reject Cheque amount edit once a SettlementObligation exists

Final Closure Pass, Domain/Product Analyst finding:
SettlementService::createObligationForCheque() has no status
precondition, so an obligation can be created for a still-REGISTERED
(never-transitioned) cheque. ChequeService::update()'s own existing
guard only blocks editing after a real lifecycle transition (deposit/
clear/bounce/etc.), not after an obligation exists. Between those two
facts, a cheque's amount could be edited after its
SettlementObligation.original_amount snapshot was already taken,
silently desyncing the two -- verified fail-first (cheque edited to
250, obligation stuck at 100) before writing the fix.

Fixed by extending update()'s existing "something meaningful already
happened, register a replacement instead" guard to also cover an
existing SettlementObligation, the identical convention already used
for lifecycle-transition history. Not a new business policy.

Also: SettlementService::unsettleInstallment()/D-090 given a final,
precise three-part decision statement after the widest reachability
trace yet (still zero production callers) plus two new architectural
facts (no audit/history trail if wired up; PaymentAllocation only
syncs on CREATE so a reversal would never propagate to another
device). Not resolved -- correctly requires an owner decision across
three separable sub-questions. Not deleted, not guarded, not wired up.

Test: CheckSettlementTest (+1).

Full suite: 1422/1422 passing (up from 1421).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 50 — fix: D-090 resolved (Settlement unsettle now rejects via removeAllocation) + two critical double-posting defects on invoice re-approve/re-unapprove + identical AncillaryCost defect (Final Marathon Closure Pass):**
```bash
git add app/Services/SettlementService.php app/Services/InvoiceService.php app/Services/AncillaryCostService.php tests/Feature/CheckSettlementTest.php tests/Feature/ApprovedInvoiceFinancialImmutabilityTest.php tests/Feature/InvoiceUnapproveBoundaryTest.php tests/Feature/AncillaryCostApprovalAuthorizationTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-12-r7.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: resolve D-090 + close two critical invoice double-posting defects

D-090 final resolution: SettlementService::unsettleInstallment() used
to hard-delete a PaymentAllocation directly, bypassing
PaymentService::removeAllocation()'s D-084 immutability guard. D-084's
own text never actually sanctioned a Settlement-specific exception the
way it explicitly reasoned through Cheque's registration-payment
deletion (D-070) -- it listed this as an unclosed bypass, not a
blessed one. unsettleInstallment() now delegates to removeAllocation()
directly, a mechanical application of the already-decided D-084.2/
D-084.3 rule, not a new policy. This resolves all three previously-
open sub-questions (audit trail, Sync propagation, immutability
exception) by making them moot -- no reversal happens, so nothing
needs to be tracked or synced. A real, governed Settlement reversal
capability remains a future, separate architecture decision (D-084.5)
if ever wanted. Tests rewritten to prove the new invariant, verified
fail-first against the old delete-based code.

CRITICAL defect found by adversarial re-audit (Workstream 15): re-
approving an already-approved, financially-clean invoice silently
created a second, orphaned Document and double-added warehouse
quantity -- no concurrency required, a plain resubmit of the same
"approve" request was enough. Reproduced via both a raw service call
and a real HTTP request to the production changeStatus endpoint. The
original D-086/D-088 docblock's claim that the controller "already
prevents reaching it in that state" was empirically false.
userFacingApproveInvoice() now treats already-approved-with-no-
activity as a safe idempotent no-op (matching
SyncPilotService::applyApprove()'s existing D-076 idempotent-replay
principle) instead of falling through to re-run approveInvoice().

Mirror-image defect: unapproving an already-unapproved invoice
manufactured phantom inventory (subProductsQuantities() ran
unconditionally with no check that a Document existed to reverse).
unapproveInvoice() now no-ops when the invoice isn't currently
approved-or-settled.

Third occurrence found by re-auditing for the same pattern:
AncillaryCostService::changeAncillaryCostStatus() had the identical
defect (getChangeStatusValidation() picks rules from current status
alone, never checking against the requested status). Fixed with the
same idempotent-no-op guard. Verified the legitimate unapprove-then-
reapprove correction workflow remains unaffected.

Checked and confirmed NOT vulnerable to this pattern: Cheque's
status-machine (structurally prevents replay), Settlement's
amount-based validation (a repeat call fails the remaining-amount
check), GroupActionService::approveInactiveInvoices() (self-limiting
by its own status-filtered query).

Tests: CheckSettlementTest (3 rewritten/added), 
ApprovedInvoiceFinancialImmutabilityTest (+1), InvoiceUnapproveBoundaryTest
(+1), AncillaryCostApprovalAuthorizationTest (+1). Every fix verified
fail-first.

Gate 1: 509 tests across every Invoice/COGS/GroupAction/Warehouse/
Payment/Return/Cheque/AncillaryCost/Settlement/Sync-related file, all
passed. Full suite: 1426/1426 passing (up from 1422).

The Financial Foundation phase's full 22-item Exit Gate is now all
GREEN, including D-090 (genuinely CLOSED, not merely re-classified).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 51 — feat: company-scoped role authorization via Spatie teams (D-091, Organization Authorization phase, Workstreams A-I) + a critical SQLite data-loss bug found and fixed:**
```bash
git add app/Models/User.php app/Providers/AppServiceProvider.php app/Http/Controllers/Management/UserController.php app/Http/Controllers/Management/RoleController.php app/Http/Controllers/CompanyController.php app/Http/Middleware/DefaultCompany.php app/Http/Middleware/SetApiCompany.php app/Http/Middleware/AuthenticateDevice.php app/Support/CompanyPermissionsTeamResolver.php config/permission.php database/migrations/2026_09_20_100000_scope_spatie_roles_to_companies.php resources/views/users/form.blade.php tests/Feature/CompanyScopedRoleMigrationTest.php tests/Feature/AuthLifecycleTest.php tests/Feature/InvoiceDashboardPermissionMigrationTest.php tests/Feature/UserImpersonationTest.php AGENTS.md docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
feat: company-scoped role authorization via Spatie teams (D-091)

Enables Spatie Permission's teams feature with company_id as the team
foreign key, implementing both owner-locked decisions: Company remains
the sole tenant/security boundary (no Organization model introduced);
every existing user's global role is materialized into company-scoped
assignments for each company they currently belong to, with zero
permission loss.

Role DEFINITIONS stay global/shared (roles.company_id IS NULL for the
6 seeded roles), matching Spatie's own OR-logic role lookup. Only
ASSIGNMENTS (model_has_roles/model_has_permissions) are company-scoped,
matching Spatie's stock composite-PK shape. Super-Admin -- a pre-
existing, system-wide oversight role per AppServiceProvider's
Gate::before -- is assigned via the reserved sentinel company id 0 and
checked via new User::isSuperAdmin()/hasGlobalPermission() helpers that
bypass Spatie's team-scoped relations entirely. Team context resolves
via a new App\Support\CompanyPermissionsTeamResolver, deferring to the
same getActiveCompany() helper every other company-scoping mechanism
already uses, instead of a new, stricter "no company" posture that
would have broken dozens of pre-existing tests and been inconsistent
with the rest of the app's established fallback convention.

CRITICAL finding: the migration originally added a
roles.company_id -> companies.id foreign key for defense-in-depth.
SQLite cannot ALTER a table to add a foreign key, so Laravel's SQLite
grammar transparently rebuilds the whole roles table -- and because
model_has_roles.role_id/role_has_permissions.role_id both cascade-
delete on roles.id (Spatie's own original migration), that rebuild
silently wiped every row in both dependent tables. SQLite is the real
database engine for the NativePHP desktop build, not just a test
convenience -- this would have silently deleted every user's role and
permission assignment on any desktop install applying this migration.
Caught only by a dedicated migration test that replays the actual
legacy-upgrade path, since RefreshDatabase alone never exercises it
(a fresh test database already has teams enabled from the very first
migration). Fixed by removing the FK entirely, matching Spatie's own
FK-less stock shape for this column.

Also fixed: RoleController had zero company scoping -- any company
could view, edit, or delete any other company's custom roles. Now
scoped to the active company, with the 6 system roles correctly
excluded.

Tests: tests/Feature/CompanyScopedRoleMigrationTest.php (new, 3 tests)
proves multi-company expansion, single-company preservation, the
no-company case, the Super-Admin/direct-permission sentinel, rerun-
safety, cross-company role-change isolation, and no-implicit-
inheritance on new membership. Exactly one pre-existing test file
adjusted, AuthLifecycleTest.php (explicit company_id => null on raw
Role::create() calls for system roles; a few assertions scoped to the
correct company context; two hasRole('Super-Admin') calls switched to
isSuperAdmin(), verified via git diff) -- no other
pre-existing test required any change.

Full suite: 1429 passed / 0 failed (1426 baseline + 3 new).

This closes Workstreams A-I of the 23-workstream Organization
Authorization & Workflow Governance phase (the migration itself, role-
definition-vs-assignment design, team-context wiring, backfill,
migration safety/tests, legacy compatibility, new-membership
behavior). Workstreams J (permission matrix), L/M (workflow/approval
audit), N (SoD), Q (audit-trail extension), R (further DB constraints),
S/T (concurrency/cache tests), U/Z (dedicated adversarial isolation
pass), V (UX audit), and Y (domain-analyst pass) remain explicitly
open for a subsequent pass -- see Decision Log D-091's "What this pass
did NOT complete" section. The 28-item Phase Exit Gate is NOT being
declared green.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 52 — fix: Spatie wildcard permission cache is not team-aware (D-091 continuation) + role-change audit trail + real MariaDB verification:**
```bash
git add app/Support/CompanyPermissionsTeamResolver.php app/Http/Controllers/Management/UserController.php app/Http/Controllers/CompanyController.php app/Providers/AppServiceProvider.php config/permission.php database/migrations/2026_09_15_140500_create_numbering_leases_table.php tests/Feature/CompanyAuthorizationIsolationTest.php tests/Feature/RoleAssignmentAuditTrailTest.php docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md AGENTS.md
git commit -m "$(cat <<'EOF'
fix: Spatie wildcard permission cache is not team-aware (D-091 pt.2)

Direct continuation of the Organization Authorization phase from
Workstreams A-I (Commit 51) into Workstreams J-AA. No restart.

CRITICAL: this app's pre-existing enable_wildcard_permission=true
config routes can()/hasPermissionTo() through Spatie's wildcard
checker, which caches a per-user permission index on the
PermissionRegistrar singleton and never invalidates it when team
context changes. Checking a user's permissions in Company A, then
switching team context to Company B and checking again, silently
returned Company A's permission set. hasRole() alone was unaffected
(queries fresh every call); only the wildcard-backed can() path was.
Fixed in App\Support\CompanyPermissionsTeamResolver:
getPermissionsTeamId() now compares every resolution against the last
one and forgets the wildcard index on any change, not only on an
explicit setPermissionsTeamId() call (the ambient getActiveCompany()
fallback can also change without anyone calling it). Also fixed a
related mistake: UserController/CompanyController used to "restore a
captured team-id value" after a temporary override; restoring a
captured value can itself go stale and re-pin the wrong company, so
they now clear with setPermissionsTeamId(null) instead.

Audit trail gap closed: role assignment/removal (model_has_roles
attach/detach/sync) never fires an Eloquent model event, so it was
invisible to the existing generic activity-log listener.
config('permission.events_enabled') is now true (was Spatie's
disabled default) and AppServiceProvider now listens for
RoleAttached/RoleDetached, logging causer/target/roles/company to the
existing activity-log infrastructure.

Verified the full migration set, including D-091's own migration and
its legacy-upgrade path, against a real local MariaDB 12.3 instance
(not just SQLite) -- both the fresh-install and upgrade paths produce
identical, correct results. Found and fixed one small, unrelated,
genuinely pre-existing MySQL-only bug blocking this verification:
numbering_leases' composite index name exceeded MySQL/MariaDB's
64-character identifier limit.

Attempted and reverted: a company_user uniqueness constraint (no
current production code path can create a duplicate, but nothing
enforced it at the schema level). Running it against the full suite
surfaced 95 pre-existing tests that create incidental duplicate
company_user rows via a test-construction pattern (CompanyFactory's
afterCreating hook plus separate explicit test-level attachment),
unrelated to production safety. Reverted rather than risk a large
regression under time pressure; left as documented future work.

Tests: tests/Feature/CompanyAuthorizationIsolationTest.php (+5,
same-object multi-switch, real HTTP cookie switch, immediate
revocation effect, RoleController cross-company isolation, Super-Admin
bypass from any company), tests/Feature/RoleAssignmentAuditTrailTest.php
(+1).

Full suite: 1435 passed / 0 failed (1429 + 6 new).

Workstreams K (service-layer sweep), N (SoD analysis, no new code --
existing controls already sufficient; "last Admin" question classified
THEORETICAL given Super-Admin's existing bypass), O/P (API/Sync parity,
verified structural via the shared resolver, no change needed) also
covered this pass. Remaining open items (permission matrix artifact J,
two further audit-trail gaps, the company_user constraint, standalone
Y/Z passes) are narrow and explicitly classified in the Decision Log --
the 30-item Phase Exit Gate is still not being declared green.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

**Commit 53 — fix: enforce company_user uniqueness at the schema level, root-causing the test-side duplicate pattern instead of leaving Commit 52's revert in place:**
```bash
git add database/migrations/2026_09_20_100001_add_unique_constraint_to_company_user_table.php tests/ docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md
git commit -m "$(cat <<'EOF'
fix: enforce company_user uniqueness, root-causing test duplicates

Commit 52 attempted a unique(['company_id', 'user_id']) constraint on
company_user and reverted it after it broke 95 tests. Revisited from
first principles instead of leaving that as an accepted gap.

Root cause: CompanyFactory's afterCreating hook silently attaches
User::first() to every company it creates (via the safe
syncWithoutDetaching). A large number of test files separately called
the raw, non-deduplicating attach() on the same Company::users()/
User::companies() relation for a user that can coincide with
User::first() in that test's data, producing a genuine second pivot
row for the same (company, user) pair. This was a test-construction
pattern, not a production one -- every production code path
(UserController, CompanyController) already used sync()/
syncWithoutDetaching() exclusively, confirmed by grepping the whole
app/ tree for any raw attach() call on this relation and finding none.

Fixed at the source: all 94 occurrences of ->companies()->attach(...)/
->users()->attach(...) across 59 test files (verified single-argument,
no pivot data, all on Company/User receivers) were changed to
syncWithoutDetaching(...), the same safe pattern already used
everywhere in production code and by the factory hook itself -- not a
workaround or a loosening of the constraint.

The migration (still deduping any pre-existing rows before enforcing,
so it remains safe against real non-fresh data) is back in place.

Full suite: 1435 passed / 0 failed, with the constraint enforced.
Independently verified clean on a real local MariaDB 12.3 instance
(fresh install, D-091's legacy-upgrade path, and this constraint
migration all together).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## 12. Session 2026-09-22 — git-identity blocker resolved, full backlog committed, Release Readiness Audit, Product Strategy Sequencing (supersedes all "ready to commit"/"NOT yet staged" sections above)

Summary (full detail in the Decision Log and roadmap §39, not duplicated here):

1. **Git identity set** by the owner (`Dakhl Developer <dev@dakhl.local>`). **22 atomic commits** created reconstructing the entire P0-P12 backlog this document spent Sections 1-11 tracking as uncommitted -- grouped by real phase/workstream (verified against the Decision Log's own narrative, not the original staging plan in Section 2 above, which is preserved only as historical record).
2. **Release Readiness Audit** performed (git hygiene, security, product completeness, deployment readiness, documentation consistency). Found and fixed: a missing `7zip-bin` Electron packaging dependency (previously known, explicitly left unfixed), unconditional PHP error display in the packaged desktop build (`NativeAppServiceProvider::phpIni()`), and general git hygiene (`.gitignore` gaps, scratch files). 3 more commits, 1728 tests total.
3. **Local Git mirror backup created**: `D:\Backups\Dakhl.git` (bare mirror), verified branch/tag/commit-hash-identical to the working repo via `git fetch`, and via a real recovery clone (checked out, files present, history intact, then deleted). Re-synced after every subsequent commit batch. **No GitHub remote created, no push performed, none planned** -- explicit owner instruction to stay fully local/private.
4. **Product Strategy Execution Sequencing** decided and recorded (Decision Log + roadmap §39): NOT a new direction -- a research-validated sequencing of the Product Identity/AI-Copilot/Modular-Licensing sections already present in this Decision Log since the start of the mission. New priority order: R-01 (forced password change on first login) -> Phase U (User Simplicity Layer, extending P8's wizard pattern) -> Phase AI architecture design -> Phase Integration architecture design (extending the existing D-076-080 sync infrastructure, not a new mechanism) -> Phase Monetization detailed scoping -> Android/Field Operations Mobile (explicitly postponed pending open mobile-specific decisions, not cancelled).

**No code was written for the Product Strategy Sequencing itself** -- documentation/architecture-decision output only, per explicit owner scope. The only code changes this session were the Release Readiness Audit's 3 fixes (item 2 above).

**Current git state**: 25 commits on `dakhl-foundation`, working tree clean, 1728/1728 tests green, backup mirror current. Next exact action per roadmap §39.8: R-01 (first-login forced password change), then Phase U.

---

## §13 — 2026-09-25 Product Vision pass
Docs only: vision doc + roadmap §40 + Decision Log narrative + Entry 71. D-074 remains future-only; Android last; next decision number D-099 (owner authors text). Open questions: vision doc ch. 12.

## §14 — 2026-09-25 Industry Profiles
Built per owner request; see roadmap §40.12. Suite 1758/1758. Open: browser visual check by owner, wiring of profile settings to invoicing is intentionally NOT done.
