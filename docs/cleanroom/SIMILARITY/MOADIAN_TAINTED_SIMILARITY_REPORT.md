# Clean-Room Similarity Audit Report

**Target Directory:** `app/Support/Moadian`  
**Upstream Mirror:** `C:/Users/Arad/.gemini/antigravity/brain/f24b351f-3520-47c4-b91a-e7eefbd430d4/scratch/upstream-mirror/jooyeshgar-moadian/Jooyeshgar-moadian-a58547b`  
**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap

| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |
|---|---|---|---|---|---|---|
| `InvoiceHeader.php` | `src\InvoiceHeader.php` | 5.57% | 3.31% | 36.19% | **11.01%** | 🟢 CLEAN (<20%) |
| `InvoiceItem.php` | `src\InvoiceItem.php` | 4.9% | 6.33% | 69.14% | **18.18%** | 🟢 CLEAN (<20%) |
| `MoadianClient.php` | `src\Moadian.php` | 12.28% | 3.63% | 33.21% | **13.87%** | 🟢 CLEAN (<20%) |
| `MoadianInvoice.php` | `src\Invoice.php` | 27.8% | 12.82% | 70.37% | **31.82%** | 🟡 MEDIUM |
| `MoadianManager.php` | `src\Exceptions\MoadianException.php` | 1.83% | 25.0% | 42.22% | **16.86%** | 🟢 CLEAN (<20%) |
| `MoadianResponse.php` | `src\Http\Response.php` | 11.11% | 10.42% | 41.89% | **17.06%** | 🟢 CLEAN (<20%) |
| `Payment.php` | `src\Payment.php` | 11.3% | 15.62% | 59.65% | **22.27%** | 🟡 MEDIUM |
| `TaxIdGenerator.php` | `src\InvoiceHeader.php` | 26.68% | 7.87% | 46.02% | **24.91%** | 🟡 MEDIUM |
| `Verhoeff.php` | `src\Services\VerhoeffService.php` | 74.59% | 57.45% | 75.76% | **69.68%** | 🔴 TAINTED / HIGH |
| `Facades\Moadian.php` | `src\Facades\Moadian.php` | 35.38% | 38.46% | 80.0% | **45.23%** | 🔴 TAINTED / HIGH |

**High Risk / Tainted Files (>40% similarity):** 2 / 10
