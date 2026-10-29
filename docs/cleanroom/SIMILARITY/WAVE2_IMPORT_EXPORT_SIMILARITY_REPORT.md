# Clean-Room Similarity Audit Report

**Target Directory:** `app\Services\DocumentImportExport`  
**Upstream Mirror:** `C:\Reference\upstream-mirror\app\Services\DocumentImportExport`  
**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap

| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |
|---|---|---|---|---|---|---|
| `AccountingFileSchema.php` | `FreeAmirImportFormat.php` | 4.64% | 3.35% | 31.06% | **9.54%** | 🟢 CLEAN (<20%) |
| `CsvExportStreamer.php` | `DocumentImportExportService.php` | 13.38% | 9.86% | 33.43% | **16.33%** | 🟢 CLEAN (<20%) |
| `DakhlStandardImportFormat.php` | `DocumentImportFormat.php` | 8.28% | 8.77% | 38.89% | **14.55%** | 🟢 CLEAN (<20%) |
| `DocumentExportRowTransformer.php` | `DocumentImportFormatRegistry.php` | 2.76% | 9.8% | 26.5% | **9.62%** | 🟢 CLEAN (<20%) |
| `DocumentFilterQueryBuilder.php` | `DocumentImportExportService.php` | 10.39% | 9.86% | 22.32% | **12.62%** | 🟢 CLEAN (<20%) |
| `DocumentImportExportService.php` | `DocumentImportFormat.php` | 4.08% | 10.0% | 32.38% | **11.52%** | 🟢 CLEAN (<20%) |
| `DocumentImportExportValidator.php` | `DocumentImportExportService.php` | 12.66% | 13.4% | 30.41% | **16.43%** | 🟢 CLEAN (<20%) |
| `DocumentImportFormat.php` | `DocumentImportFormat.php` | 10.36% | 10.31% | 40.0% | **16.27%** | 🟢 CLEAN (<20%) |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 11.7% | 10.99% | 33.58% | **15.86%** | 🟢 CLEAN (<20%) |
| `DocumentImportPipeline.php` | `DocumentImportExportService.php` | 9.63% | 5.58% | 26.43% | **11.78%** | 🟢 CLEAN (<20%) |
| `FreeAmirImportFormat.php` | `DocumentImportFormatRegistry.php` | 3.07% | 13.79% | 38.46% | **13.37%** | 🟢 CLEAN (<20%) |
| `ImportFeedbackFormatter.php` | `DocumentImportExportService.php` | 8.87% | 4.8% | 22.8% | **10.44%** | 🟢 CLEAN (<20%) |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 13.21% | 3.88% | 36.07% | **14.98%** | 🟢 CLEAN (<20%) |
| `ParsianImportFormat.php` | `ParsianImportFormat.php` | 16.32% | 9.64% | 37.16% | **18.48%** | 🟢 CLEAN (<20%) |
| `ParsianRootCatalogue.php` | `ParsianImportFormat.php` | 9.33% | 3.42% | 28.35% | **11.36%** | 🟢 CLEAN (<20%) |
| `ParsianTransactionIngestor.php` | `ParsianImportFormat.php` | 12.52% | 5.24% | 37.15% | **15.26%** | 🟢 CLEAN (<20%) |
| `StandardJournalIngestor.php` | `FreeAmirImportFormat.php` | 17.18% | 4.83% | 37.82% | **17.6%** | 🟢 CLEAN (<20%) |

**Tainted Files (>=20% similarity):** 0 / 17
