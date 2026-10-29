# Clean-Room Dual-Measure Similarity Audit Report

**Target Directory:** `app\Support\Moadian`  
**Upstream Mirror:** `C:\Reference\upstream-mirror\vendor\jooyeshgar\moadian\src`  

### Evaluation Methodology
1. **Measure 1 — Lexical / Literal Token Cloning:** Evaluates literal identifier reuse, exact token sequences, and raw line overlap:
   $$\text{M1} = (0.50 \times \text{Shingle}_{\text{4-gram}}) + (0.30 \times \text{Line Overlap}) + (0.20 \times \text{Token Jaccard})$$
2. **Measure 2 — Syntactic / Structural AST Cloning:** Evaluates underlying control-flow and grammar patterns by normalizing all variable identifiers (`$VAR`), strings (`_STR_`), and numbers (`_NUM_`):
   $$\text{M2} = (0.60 \times \text{AST Shingle}_{\text{6-gram}}) + (0.40 \times \text{Structural Line Pattern})$$

| Target File | Closest Upstream File | Measure 1 (Literal) | Measure 2 (AST Struct) | Max Score | Status |
|---|---|:---:|:---:|:---:|:---:|
| `InvoiceHeader.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `InvoiceItem.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `MoadianClient.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `MoadianInvoice.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `MoadianManager.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `MoadianResponse.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `Payment.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `TaxIdGenerator.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `Verhoeff.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |
| `Facades\Moadian.php` | `None` | 0.0% | 0.0% | **0.0%** | 🟢 CLEAN (<15%) |

- **Files with >= 20% in either measure:** 0 / 10
- **Files with >= 15% in either measure:** 0 / 10

