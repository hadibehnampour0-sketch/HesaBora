# Dakhl Clean-Room Core Test Coverage Matrix
**Document Version:** 1.0.0 (Clean-Room Fail-First Verification Baseline)  
**Total Specifications:** 16  
**Total PHPUnit Characterization Tests:** 62  
**Initial Baseline Status:** 100% RED (62 Failing / 0 Passing Vacuously)  

---

## Specification to Test Method Mapping

### SPEC_01: Chart of Accounts Hierarchy & Balance (7 Tests)
- **Rule 1 (3-Tier Hierarchy):** `Spec01ChartOfAccountsTest::test_create_valid_three_tier_account_hierarchy_happy_path`
- **Rule 1 (No Tier Skipping):** `Spec01ChartOfAccountsTest::test_reject_invalid_tier_skipping_hierarchy`
- **Rule 2 (Cycle Prevention):** `Spec01ChartOfAccountsTest::test_reject_circular_parent_dependency_loop`
- **Rule 3 (Account Nature Inheritance):** `Spec01ChartOfAccountsTest::test_child_account_inherits_parent_account_nature`
- **Invariant (Company Scoping):** `Spec01ChartOfAccountsTest::test_multi_tenant_company_isolation_guard`
- **Invariant (Code Uniqueness):** `Spec01ChartOfAccountsTest::test_duplicate_code_in_same_company_rejected`
- **Rule 4 (Balance Aggregation):** `Spec01ChartOfAccountsTest::test_parent_balance_aggregates_all_descendants`

### SPEC_02: Sequential Document Numbering Engine (5 Tests)
- **Rule 1 (Single Sequential Progression):** `Spec02DocumentNumberingTest::test_allocate_next_number_sequential_progression`
- **Rule 3 (Offline Range Lease D-075):** `Spec02DocumentNumberingTest::test_offline_range_lease_advances_counter_atomically`
- **Rule 3 (Block Size Validation):** `Spec02DocumentNumberingTest::test_reject_invalid_lease_block_size`
- **Rule 4 (No Recycling Policy D-075):** `Spec02DocumentNumberingTest::test_no_recycling_policy_voided_number_not_reused`
- **Invariant (Fiscal Year Isolation):** `Spec02DocumentNumberingTest::test_fiscal_year_scoping_separate_number_sequences`

### SPEC_03: Universal Journal Voucher Posting Core (5 Tests)
- **Rule 1 & Invariant (Double-Entry Balance ADR-A):** `Spec03JournalVoucherPostingTest::test_post_balanced_journal_voucher_happy_path`
- **Rule 1 (Imbalance Rejection):** `Spec03JournalVoucherPostingTest::test_reject_unbalanced_journal_voucher`
- **Rule 2 (Zero-Amount Line Prohibition ADR-A):** `Spec03JournalVoucherPostingTest::test_reject_zero_amount_line_prohibition`
- **Rule 2 (Simultaneous Debit & Credit Prohibition):** `Spec03JournalVoucherPostingTest::test_reject_simultaneous_debit_and_credit_line`
- **Invariant (DB Immutability ADR-E / D-135):** `Spec03JournalVoucherPostingTest::test_posted_voucher_is_strictly_immutable`

### SPEC_04: Mirror Audit Reversals Engine (3 Tests)
- **Rule 2 & Invariant (Exact Debit/Credit Swap D-127):** `Spec04AuditReversalsTest::test_mirror_reversal_inverts_debits_and_credits_accurately`
- **Rule 1 (Anti-Chaining / Single Reversal Only):** `Spec04AuditReversalsTest::test_reject_reversing_already_reversed_voucher`
- **Rule 1 (Draft Exclusion):** `Spec04AuditReversalsTest::test_reject_reversing_draft_voucher`

### SPEC_05: Fiscal Period Freeze State Machine (4 Tests)
- **State 1 (OPEN Normal Posting):** `Spec05PeriodFreezeTest::test_open_period_permits_posting_normal_operation`
- **State 2 (SOFT_LOCKED Override Check D-128):** `Spec05PeriodFreezeTest::test_soft_locked_period_requires_override_permission`
- **State 3 (FROZEN Absolute Write Lock):** `Spec05PeriodFreezeTest::test_frozen_period_blocks_all_modifications_unconditionally`
- **Invariant (Forward-Only Lifecycle Progression):** `Spec05PeriodFreezeTest::test_reject_backward_period_transition_without_audit_log`

### SPEC_06: Commercial Invoice Posting & Transaction Builder (3 Tests)
- **Rule 4 (Sales Invoice Accounting Voucher Generation D-076):** `Spec06CommercialInvoicePostingTest::test_post_sales_invoice_creates_balanced_accounting_voucher`
- **Rule 6 (Zero Line Items Rejection):** `Spec06CommercialInvoicePostingTest::test_reject_posting_empty_invoice_with_zero_lines`
- **Rule 5 (Idempotency / Already Posted Guard):** `Spec06CommercialInvoicePostingTest::test_reject_posting_already_posted_invoice`

### SPEC_07: Perpetual Inventory Valuation & COGS (3 Tests)
- **Rule 3 (Moving Weighted Average & Dispatch COGS):** `Spec07InventoryValuationCogsTest::test_moving_weighted_average_cost_computation_and_cogs`
- **Invariant (Stock Depletion Guard):** `Spec07InventoryValuationCogsTest::test_reject_outbound_dispatch_exceeding_stock_when_negative_stock_forbidden`
- **Rule 3 & ADR-B (Half-Up Rounding Precision):** `Spec07InventoryValuationCogsTest::test_half_up_rounding_precision_on_fractional_cogs`

### SPEC_08: Ancillary Landed Costs Allocation (3 Tests)
- **Rule 1 (Distribution by Value):** `Spec08AncillaryLandedCostsTest::test_distribute_freight_expense_by_value_reconciles_exactly`
- **Rule 1 (Rounding Fraction Absorption on Highest-Value Line):** `Spec08AncillaryLandedCostsTest::test_rounding_fractions_absorbed_by_highest_value_line`
- **Rule 6 (Zero Expense Guard):** `Spec08AncillaryLandedCostsTest::test_reject_zero_expense_allocation`

### SPEC_09: Treasury Payments & Receipts (5 Tests)
- **Rule 1 (Atomic Double-Entry Posting D-078/D-079):** `Spec09TreasuryPaymentsTest::test_create_atomic_treasury_receipt_with_journal_voucher`
- **Rule 2 (Canonical UUIDv7 Idempotent Retry D-080):** `Spec09TreasuryPaymentsTest::test_canonical_uuid_idempotent_retry_handling`
- **Rule 2 (Payload Tamper Rejection D-080):** `Spec09TreasuryPaymentsTest::test_reject_uuid_payload_tamper_conflict`
- **Rule 3 (Hard Delete Immutability D-084):** `Spec09TreasuryPaymentsTest::test_posted_payment_hard_delete_strictly_forbidden`
- **Rule 6 (Zero/Negative Amount Guard):** `Spec09TreasuryPaymentsTest::test_reject_zero_or_negative_payment_amount`

### SPEC_10: Payment to Invoice Settlement Allocations (4 Tests)
- **Rule 1 & 3 (Capacity Allocation & Balance Decrement D-081):** `Spec10PaymentAllocationsTest::test_allocate_payment_to_invoice_within_capacity`
- **Rule 1 (Unallocated Capacity Overdraft Rejection):** `Spec10PaymentAllocationsTest::test_reject_allocation_exceeding_unallocated_payment_capacity`
- **Rule 1 (Invoice Overpayment Guard):** `Spec10PaymentAllocationsTest::test_reject_allocation_exceeding_invoice_remaining_balance`
- **Rule 5 (Posted Allocation Immutability D-084):** `Spec10PaymentAllocationsTest::test_posted_allocation_immutability_guard`

### SPEC_11: Cheque Instrument 8-State Lifecycle (4 Tests)
- **Rule 3 & 4 (Register -> Deposit -> Clear Workflow D-070):** `Spec11ChequeLifecycleTest::test_cheque_registration_deposit_and_clearance_lifecycle`
- **Rule 3 & 4 (Deposit -> Bounced Workflow):** `Spec11ChequeLifecycleTest::test_deposited_cheque_can_transition_to_bounced`
- **Rule 6 (Illegal State Machine Transition Rejection):** `Spec11ChequeLifecycleTest::test_reject_illegal_state_transition_from_cancelled_to_cleared`
- **Invariant (Unique Sayad Serial Per Company):** `Spec11ChequeLifecycleTest::test_reject_duplicate_cheque_sayad_number_in_same_company`

### SPEC_12: Fixed Assets & Depreciation Engine (2 Tests)
- **Rule 1 & 3 (Straight-Line Depreciation & Monthly Posting):** `Spec12FixedAssetsDepreciationTest::test_straight_line_depreciation_monthly_calculation_and_posting`
- **Rule 2 (Salvage Value Ceiling Enforcement):** `Spec12FixedAssetsDepreciationTest::test_depreciation_stops_when_book_value_reaches_salvage_value`

### SPEC_13: Chronological Cost Dependency Recalculation (3 Tests)
- **Rule 2 & Invariant (Preserve Historical Posted Vouchers D-087/D-127):** `Spec13DependencyRecalculationTest::test_recalculate_cost_chain_preserves_historical_posted_vouchers`
- **Rule 3 (Consolidated Variance Adjustment Voucher):** `Spec13DependencyRecalculationTest::test_variance_adjustment_voucher_generated_for_cumulative_difference`
- **Rule 6 (Closed Period Recalculation Lock):** `Spec13DependencyRecalculationTest::test_reject_recalculation_inside_closed_fiscal_period`

### SPEC_14: Commercial Invoice Mutation Boundaries (5 Tests)
- **Rule 4.1 (Edit/Rebuild Blocked D-086):** `Spec14InvoiceMutationBoundariesTest::test_reject_editing_invoice_with_active_payment_activity`
- **Rule 4.2 (Unapprove Blocked D-087):** `Spec14InvoiceMutationBoundariesTest::test_reject_unapproving_invoice_with_active_allocations`
- **Rule 4.4 (Reject Blocked D-089):** `Spec14InvoiceMutationBoundariesTest::test_reject_rejecting_invoice_with_active_financial_activity`
- **Rule 4.5 (Hard Delete Blocked):** `Spec14InvoiceMutationBoundariesTest::test_reject_hard_deleting_invoice_with_financial_activity`
- **Rule 4 (Mutation Allowed on Non-Financial Draft):** `Spec14InvoiceMutationBoundariesTest::test_allow_mutation_on_invoice_without_any_financial_activity`

### SPEC_15: Financial Provenance Tracking (3 Tests)
- **Rule 3 (Company Deletion Guard on Native Document D-085):** `Spec15ProvenanceTrackingTest::test_reject_deleting_company_with_native_financial_activity`
- **Rule 3 (Company Deletion Guard on Payment Allocation D-085):** `Spec15ProvenanceTrackingTest::test_reject_deleting_company_with_payment_allocations`
- **Rule 3 (Allow Pruning Empty/Draft Companies):** `Spec15ProvenanceTrackingTest::test_allow_deleting_empty_or_draft_only_company`

### SPEC_16: Fiscal Year Closing & Roll-Over (3 Tests)
- **Rule 3 (Two-Stage Closing & Opening Voucher Generation D-085/D-128/D-134):** `Spec16FiscalClosingWorkflowTest::test_two_stage_annual_fiscal_closing_and_opening_voucher_generation`
- **Rule 6 (Unbalanced Ledger Closure Rejection):** `Spec16FiscalClosingWorkflowTest::test_reject_closing_when_unbalanced_ledger_exists`
- **Rule 6 (Duplicate Opening Voucher Guard):** `Spec16FiscalClosingWorkflowTest::test_reject_duplicate_opening_voucher_creation_in_same_year`

---

## Summary Verification
| Category | Metric |
| :--- | :--- |
| Specifications Evaluated | 16 / 16 (100%) |
| Behavioral Test Classes | 16 |
| Total Characterization Tests | 62 |
| Total Expected Assertions / Exceptions | 62 |
| Baseline Red Run | 62 Failed (54 Class Not Found, 8 Exceptions/Domain Errors) |
| Vacuous Passes | 0 |
