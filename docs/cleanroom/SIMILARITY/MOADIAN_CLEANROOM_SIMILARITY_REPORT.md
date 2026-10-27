# Clean-Room Similarity Audit Report

**Target Directory:** `app/Support/Moadian`  
**Upstream Mirror:** `C:/Users/Arad/.gemini/antigravity/brain/f24b351f-3520-47c4-b91a-e7eefbd430d4/scratch/upstream-mirror/jooyeshgar-moadian/Jooyeshgar-moadian-a58547b`  
**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap

| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |
|---|---|---|---|---|---|---|
| `InvoiceHeader.php` | `src\Payment.php` | 4.59% | 7.41% | 29.03% | **10.32%** | 🟢 CLEAN (<20%) |
| `InvoiceItem.php` | `src\Payment.php` | 6.84% | 11.43% | 36.23% | **14.1%** | 🟢 CLEAN (<20%) |
| `MoadianClient.php` | `src\Moadian.php` | 4.58% | 3.08% | 29.11% | **9.04%** | 🟢 CLEAN (<20%) |
| `MoadianInvoice.php` | `src\Invoice.php` | 23.97% | 11.63% | 64.41% | **28.35%** | 🟡 MEDIUM |
| `MoadianManager.php` | `src\Exceptions\MoadianException.php` | 1.67% | 17.65% | 38.0% | **13.73%** | 🟢 CLEAN (<20%) |
| `MoadianResponse.php` | `src\Http\Response.php` | 10.37% | 10.64% | 38.67% | **16.11%** | 🟢 CLEAN (<20%) |
| `Payment.php` | `src\Payment.php` | 12.21% | 12.5% | 56.9% | **21.23%** | 🟡 MEDIUM |
| `TaxIdGenerator.php` | `src\Facades\Moadian.php` | 1.67% | 12.0% | 25.68% | **9.57%** | 🟢 CLEAN (<20%) |
| `Verhoeff.php` | `src\Services\VerhoeffService.php` | 3.23% | 4.48% | 45.83% | **12.13%** | 🟢 CLEAN (<20%) |
| `Facades\Moadian.php` | `src\Facades\Moadian.php` | 26.32% | 35.71% | 69.44% | **37.76%** | 🟡 MEDIUM |

**High Risk / Tainted Files (>40% similarity):** 0 / 10
