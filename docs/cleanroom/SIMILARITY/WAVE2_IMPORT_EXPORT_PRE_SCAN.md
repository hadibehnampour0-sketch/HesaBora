# Clean-Room Similarity Audit Report

**Target Directory:** `app\Services\DocumentImportExport`  
**Upstream Mirror:** `C:\Reference\upstream-mirror\app\Services\DocumentImportExport`  
**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap

| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |
|---|---|---|---|---|---|---|
| `DocumentImportExportService.php` | `DocumentImportExportService.php` | 100.0% | 100.0% | 100.0% | **100.0%** | 🔴 TAINTED / MUST REWRITE (>=20%) |
| `DocumentImportFormat.php` | `DocumentImportFormat.php` | 83.53% | 81.48% | 95.08% | **85.23%** | 🔴 TAINTED / MUST REWRITE (>=20%) |
| `DocumentImportFormatRegistry.php` | `DocumentImportFormatRegistry.php` | 100.0% | 100.0% | 100.0% | **100.0%** | 🔴 TAINTED / MUST REWRITE (>=20%) |
| `FreeAmirImportFormat.php` | `FreeAmirImportFormat.php` | 100.0% | 100.0% | 100.0% | **100.0%** | 🔴 TAINTED / MUST REWRITE (>=20%) |
| `ImportSubjectResolver.php` | `ImportSubjectResolver.php` | 100.0% | 100.0% | 100.0% | **100.0%** | 🔴 TAINTED / MUST REWRITE (>=20%) |
| `ParsianImportFormat.php` | `ParsianImportFormat.php` | 100.0% | 100.0% | 100.0% | **100.0%** | 🔴 TAINTED / MUST REWRITE (>=20%) |

**Tainted Files (>=20% similarity):** 6 / 6
