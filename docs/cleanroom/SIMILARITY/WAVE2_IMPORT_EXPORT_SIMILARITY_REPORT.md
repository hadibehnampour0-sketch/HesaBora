# Clean-Room Dual-Measure Similarity Audit Report

**Target Directory:** `app/Services/DocumentImportExport`  
**Upstream Mirror:** `C:\Reference\upstream-mirror\app\Services\DocumentImportExport`  

### Evaluation Methodology
1. **Measure 1 — Lexical / Literal Token Cloning:** Evaluates literal identifier reuse, exact token sequences, and raw line overlap:
   $$\text{M1} = (0.50 \times \text{Shingle}_{\text{4-gram}}) + (0.30 \times \text{Line Overlap}) + (0.20 \times \text{Token Jaccard})$$
2. **Measure 2 — Syntactic / Structural AST Cloning:** Evaluates underlying control-flow and grammar patterns by normalizing all variable identifiers (`$VAR`), strings (`_STR_`), and numbers (`_NUM_`):
   $$\text{M2} = (0.60 \times \text{AST Shingle}_{\text{6-gram}}) + (0.40 \times \text{Structural Line Pattern})$$

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
In accordance with the clean-room governance protocol, all files with $\ge 15\%$ in either measure are listed below with their specific metric breakdown:

| File | Closest Upstream File | M1 (Literal) | M2 (AST Struct) | Max Score | Remediation Status |
|---|---|:---:|:---:|:---:|---|
| `ParsianImportFormat.php` | `DocumentImportFormat.php` | 19.17% | 9.48% | **19.17%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `ParsianTransactionIngestor.php` | `ParsianImportFormat.php` | 18.35% | 17.18% | **18.35%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 15.86% | 18.34% | **18.34%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `StandardJournalIngestor.php` | `FreeAmirImportFormat.php` | 17.93% | 16.44% | **17.93%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 14.98% | 17.32% | **17.32%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `ParsianAccountBridge.php` | `ParsianImportFormat.php` | 16.98% | 16.08% | **16.98%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `DocumentImportPipeline.php` | `DocumentImportExportService.php` | 11.78% | 16.85% | **16.85%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `DocumentImportExportValidator.php` | `DocumentImportExportService.php` | 16.43% | 6.1% | **16.43%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `CsvExportStreamer.php` | `DocumentImportExportService.php` | 16.33% | 16.37% | **16.37%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `DakhlStandardImportFormat.php` | `FreeAmirImportFormat.php` | 12.08% | 16.17% | **16.17%** | Committed as low-similarity rewritten; flagged for future refactoring |
| `DocumentImportFormat.php` | `DocumentImportFormat.php` | 16.11% | 12.72% | **16.11%** | Committed as low-similarity rewritten; flagged for future refactoring |
