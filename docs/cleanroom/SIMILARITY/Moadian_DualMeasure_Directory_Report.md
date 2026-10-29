# Clean-Room Dual-Measure Similarity & Directory-Level Audit Report

**Target Directory:** `app/Support/Moadian` (10 files)  
**Upstream Mirror:** `C:\Reference\upstream-mirror\jooyeshgar-moadian\Jooyeshgar-moadian-a58547b\src` (27 files)  

### Evaluation Methodology
1. **Measure 1 — Lexical / Literal Token Cloning:** Evaluates literal identifier reuse, exact token sequences, and raw line overlap:
   $$\text{M1} = (0.50 \times \text{Shingle}_{\text{4-gram}}) + (0.30 \times \text{Line Overlap}) + (0.20 \times \text{Token Jaccard})$$
2. **Measure 2 — Syntactic / Structural AST Cloning:** Evaluates underlying control-flow and grammar patterns by normalizing all variable identifiers (`$VAR`), strings (`_STR_`), and numbers (`_NUM_`):
   $$\text{M2} = (0.60 \times \text{AST Shingle}_{\text{6-gram}}) + (0.40 \times \text{Structural Line Pattern})$$
3. **Directory-Level Aggregate Scan:** Evaluates the entire module by concatenating all target files into a unified virtual document and measuring global overlap against the upstream mirror.
4. **Moved-Not-Rewritten Block Detection:** Flags any target file whose code shingles show $\ge 20\%$ asymmetric containment inside an upstream file or contains $\ge 6$ contiguous matching lines, detecting code chunks shifted across split files.

## 1. Directory-Level Module Aggregate Scores

| Module Target Directory | Upstream Mirror Directory | Directory M1 (Literal) | Directory M2 (AST Struct) | Directory Max Composite | Containment M1 | Containment M2 | Status |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `app/Support/Moadian` | `C:\Reference\upstream-mirror\jooyeshgar-moadian\Jooyeshgar-moadian-a58547b\src` | 8.24% | 3.22% | **8.24%** | 13.99% | 10.95% | 🟢 CLEAN (<15%) |

## 2. Moved-Not-Rewritten Block Analysis

The following split files contain block-level overlap or containment exceeding threshold (>=20% containment or >=6 contiguous lines):

| Split Target File | Source Upstream File | Containment % | Contiguous Lines | Finding Classification |
|---|---|:---:|:---:|---|
| `MoadianResponse.php` | `Http\Response.php` | 38.27% | 3 | ⚠️ Moved Block / Shared Standard Logic |

## 3. File-by-File Dual-Measure Breakdown

| Target File | Closest Upstream File | Measure 1 (Literal) | Measure 2 (AST Struct) | Max Score | Status |
|---|---|:---:|:---:|:---:|:---:|
| `InvoiceHeader.php` | `Payment.php` | 10.32% | 6.41% | **10.32%** | 🟢 CLEAN (<15%) |
| `InvoiceItem.php` | `Payment.php` | 14.1% | 9.84% | **14.1%** | 🟢 CLEAN (<15%) |
| `MoadianClient.php` | `Moadian.php` | 8.94% | 3.54% | **8.94%** | 🟢 CLEAN (<15%) |
| `MoadianInvoice.php` | `Invoice.php` | 16.94% | 6.96% | **16.94%** | 🟡 ELEVATED (>=15%) |
| `MoadianManager.php` | `Exceptions\MoadianException.php` | 13.73% | 7.06% | **13.73%** | 🟢 CLEAN (<15%) |
| `MoadianResponse.php` | `Http\Response.php` | 16.11% | 9.46% | **16.11%** | 🟡 ELEVATED (>=15%) |
| `Payment.php` | `Payment.php` | 11.52% | 5.41% | **11.52%** | 🟢 CLEAN (<15%) |
| `TaxIdGenerator.php` | `Facades\Moadian.php` | 9.57% | 5.1% | **9.57%** | 🟢 CLEAN (<15%) |
| `Verhoeff.php` | `Services\VerhoeffService.php` | 12.13% | 6.65% | **12.13%** | 🟢 CLEAN (<15%) |
| `Facades\Moadian.php` | `Facades\Moadian.php` | 19.17% | 12.23% | **19.17%** | 🟡 ELEVATED (>=15%) |

- **Files with >= 20% in either measure:** 0 / 10
- **Files with >= 15% in either measure:** 3 / 10

### Comprehensive Listing of Files >= 15% (Audit Action List)
| File | Closest Upstream File | M1 (Literal) | M2 (AST Struct) | Max Score | Clean-Room Status Classification |
|---|---|:---:|:---:|:---:|---|
| `Facades\Moadian.php` | `Facades\Moadian.php` | 19.17% | 12.23% | **19.17%** | Committed as low-similarity rewritten (contaminated author) |
| `MoadianInvoice.php` | `Invoice.php` | 16.94% | 6.96% | **16.94%** | Committed as low-similarity rewritten (contaminated author) |
| `MoadianResponse.php` | `Http\Response.php` | 16.11% | 9.46% | **16.11%** | Committed as low-similarity rewritten (contaminated author) |
