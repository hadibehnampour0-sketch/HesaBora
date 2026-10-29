# Clean-Room Dual-Measure Similarity & Directory-Level Audit Report

**Target Directory:** `app/Services/DocumentImportExport` (21 files)  
**Upstream Mirror:** `C:\Reference\upstream-mirror\app\Services\DocumentImportExport` (6 files)  

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
| `app/Services/DocumentImportExport` | `C:\Reference\upstream-mirror\app\Services\DocumentImportExport` | 25.49% | 18.75% | **25.49%** | 34.23% | 22.32% | 🔴 HIGH OVERLAP (>=20%) |

## 2. Moved-Not-Rewritten Block Analysis

The following split files contain block-level overlap or containment exceeding threshold (>=20% containment or >=6 contiguous lines):

| Split Target File | Source Upstream File | Containment % | Contiguous Lines | Finding Classification |
|---|---|:---:|:---:|---|
| `AccountingFileSchema.php` | `FreeAmirImportFormat.php` | 23.2% | 5 | ⚠️ Moved Block / Shared Standard Logic |
| `CsvExportStreamer.php` | `DocumentImportExportService.php` | 53.5% | 7 | ⚠️ Moved Block / Shared Standard Logic |
| `DakhlStandardImportFormat.php` | `FreeAmirImportFormat.php` | 56.74% | 7 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentExportRowTransformer.php` | `DocumentImportExportService.php` | 50.14% | 3 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentFilterQueryBuilder.php` | `DocumentImportExportService.php` | 53.92% | 11 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentImportExportService.php` | `DocumentImportExportService.php` | 49.03% | 8 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentImportExportValidator.php` | `DocumentImportExportService.php` | 78.78% | 12 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentImportFormat.php` | `FreeAmirImportFormat.php` | 31.61% | 5 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 18.35% | 6 | ⚠️ Moved Block / Shared Standard Logic |
| `DocumentImportPipeline.php` | `DocumentImportExportService.php` | 46.03% | 5 | ⚠️ Moved Block / Shared Standard Logic |
| `FreeAmirImportFormat.php` | `FreeAmirImportFormat.php` | 50.0% | 6 | ⚠️ Moved Block / Shared Standard Logic |
| `ImportFeedbackFormatter.php` | `DocumentImportExportService.php` | 45.04% | 6 | ⚠️ Moved Block / Shared Standard Logic |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 24.55% | 3 | ⚠️ Moved Block / Shared Standard Logic |
| `ParsianAccountBridge.php` | `ParsianImportFormat.php` | 52.62% | 3 | ⚠️ Moved Block / Shared Standard Logic |
| `ParsianImportFormat.php` | `ParsianImportFormat.php` | 43.95% | 7 | ⚠️ Moved Block / Shared Standard Logic |
| `ParsianRootCatalogue.php` | `ParsianImportFormat.php` | 66.86% | 2 | ⚠️ Moved Block / Shared Standard Logic |
| `ParsianTransactionIngestor.php` | `ParsianImportFormat.php` | 43.82% | 4 | ⚠️ Moved Block / Shared Standard Logic |
| `ParsianTrialBalanceIngestor.php` | `ParsianImportFormat.php` | 36.93% | 2 | ⚠️ Moved Block / Shared Standard Logic |
| `StandardJournalDecoder.php` | `FreeAmirImportFormat.php` | 42.3% | 4 | ⚠️ Moved Block / Shared Standard Logic |
| `StandardJournalIngestor.php` | `FreeAmirImportFormat.php` | 42.33% | 5 | ⚠️ Moved Block / Shared Standard Logic |
| `StandardJournalRowTransformer.php` | `FreeAmirImportFormat.php` | 53.33% | 3 | ⚠️ Moved Block / Shared Standard Logic |

## 3. File-by-File Dual-Measure Breakdown

| Target File | Closest Upstream File | Measure 1 (Literal) | Measure 2 (AST Struct) | Max Score | Status |
|---|---|:---:|:---:|:---:|:---:|
| `AccountingFileSchema.php` | `FreeAmirImportFormat.php` | 9.54% | 3.8% | **9.54%** | 🟢 CLEAN (<15%) |
| `CsvExportStreamer.php` | `DocumentImportExportService.php` | 16.33% | 16.37% | **16.37%** | 🟡 ELEVATED (>=15%) |
| `DakhlStandardImportFormat.php` | `FreeAmirImportFormat.php` | 12.08% | 16.17% | **16.17%** | 🟡 ELEVATED (>=15%) |
| `DocumentExportRowTransformer.php` | `DocumentImportFormatRegistry.php` | 9.62% | 6.19% | **9.62%** | 🟢 CLEAN (<15%) |
| `DocumentFilterQueryBuilder.php` | `DocumentImportExportService.php` | 12.62% | 12.53% | **12.62%** | 🟢 CLEAN (<15%) |
| `DocumentImportExportService.php` | `DocumentImportFormat.php` | 11.52% | 5.77% | **11.52%** | 🟢 CLEAN (<15%) |
| `DocumentImportExportValidator.php` | `DocumentImportExportService.php` | 16.43% | 6.1% | **16.43%** | 🟡 ELEVATED (>=15%) |
| `DocumentImportFormat.php` | `DocumentImportFormat.php` | 16.11% | 12.72% | **16.11%** | 🟡 ELEVATED (>=15%) |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 15.86% | 18.34% | **18.34%** | 🟡 ELEVATED (>=15%) |
| `DocumentImportPipeline.php` | `DocumentImportExportService.php` | 11.78% | 16.85% | **16.85%** | 🟡 ELEVATED (>=15%) |
| `FreeAmirImportFormat.php` | `DocumentImportFormatRegistry.php` | 13.37% | 6.81% | **13.37%** | 🟢 CLEAN (<15%) |
| `ImportFeedbackFormatter.php` | `DocumentImportExportService.php` | 10.44% | 4.94% | **10.44%** | 🟢 CLEAN (<15%) |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 14.98% | 17.32% | **17.32%** | 🟡 ELEVATED (>=15%) |
| `ParsianAccountBridge.php` | `ParsianImportFormat.php` | 16.98% | 16.08% | **16.98%** | 🟡 ELEVATED (>=15%) |
| `ParsianImportFormat.php` | `DocumentImportFormat.php` | 19.17% | 9.48% | **19.17%** | 🟡 ELEVATED (>=15%) |
| `ParsianRootCatalogue.php` | `ParsianImportFormat.php` | 11.36% | 5.47% | **11.36%** | 🟢 CLEAN (<15%) |
| `ParsianTransactionIngestor.php` | `ParsianImportFormat.php` | 18.35% | 17.18% | **18.35%** | 🟡 ELEVATED (>=15%) |
| `ParsianTrialBalanceIngestor.php` | `DocumentImportFormat.php` | 12.32% | 5.62% | **12.32%** | 🟢 CLEAN (<15%) |
| `StandardJournalDecoder.php` | `FreeAmirImportFormat.php` | 10.8% | 7.56% | **10.8%** | 🟢 CLEAN (<15%) |
| `StandardJournalIngestor.php` | `FreeAmirImportFormat.php` | 17.93% | 16.44% | **17.93%** | 🟡 ELEVATED (>=15%) |
| `StandardJournalRowTransformer.php` | `DocumentImportFormat.php` | 11.87% | 5.65% | **11.87%** | 🟢 CLEAN (<15%) |

- **Files with >= 20% in either measure:** 0 / 21
- **Files with >= 15% in either measure:** 11 / 21

### Comprehensive Listing of Files >= 15% (Audit Action List)
| File | Closest Upstream File | M1 (Literal) | M2 (AST Struct) | Max Score | Clean-Room Status Classification |
|---|---|:---:|:---:|:---:|---|
| `ParsianImportFormat.php` | `DocumentImportFormat.php` | 19.17% | 9.48% | **19.17%** | Committed as low-similarity rewritten (contaminated author) |
| `ParsianTransactionIngestor.php` | `ParsianImportFormat.php` | 18.35% | 17.18% | **18.35%** | Committed as low-similarity rewritten (contaminated author) |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 15.86% | 18.34% | **18.34%** | Committed as low-similarity rewritten (contaminated author) |
| `StandardJournalIngestor.php` | `FreeAmirImportFormat.php` | 17.93% | 16.44% | **17.93%** | Committed as low-similarity rewritten (contaminated author) |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 14.98% | 17.32% | **17.32%** | Committed as low-similarity rewritten (contaminated author) |
| `ParsianAccountBridge.php` | `ParsianImportFormat.php` | 16.98% | 16.08% | **16.98%** | Committed as low-similarity rewritten (contaminated author) |
| `DocumentImportPipeline.php` | `DocumentImportExportService.php` | 11.78% | 16.85% | **16.85%** | Committed as low-similarity rewritten (contaminated author) |
| `DocumentImportExportValidator.php` | `DocumentImportExportService.php` | 16.43% | 6.1% | **16.43%** | Committed as low-similarity rewritten (contaminated author) |
| `CsvExportStreamer.php` | `DocumentImportExportService.php` | 16.33% | 16.37% | **16.37%** | Committed as low-similarity rewritten (contaminated author) |
| `DakhlStandardImportFormat.php` | `FreeAmirImportFormat.php` | 12.08% | 16.17% | **16.17%** | Committed as low-similarity rewritten (contaminated author) |
| `DocumentImportFormat.php` | `DocumentImportFormat.php` | 16.11% | 12.72% | **16.11%** | Committed as low-similarity rewritten (contaminated author) |
