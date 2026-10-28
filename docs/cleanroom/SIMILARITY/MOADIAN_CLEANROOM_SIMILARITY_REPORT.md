# Clean-Room Similarity Audit Report

**Target Directory:** `app/Support/Moadian`  
**Upstream Mirror:** `C:/Reference/upstream-mirror/jooyeshgar-moadian/Jooyeshgar-moadian-a58547b`  
**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap

| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |
|---|---|---|---|---|---|---|
| `InvoiceHeader.php` | `src\Payment.php` | 4.59% | 7.41% | 29.03% | **10.32%** | 🟢 CLEAN (<20%) |
| `InvoiceItem.php` | `src\Payment.php` | 6.84% | 11.43% | 36.23% | **14.1%** | 🟢 CLEAN (<20%) |
| `MoadianClient.php` | `src\Moadian.php` | 4.58% | 3.08% | 28.64% | **8.94%** | 🟢 CLEAN (<20%) |
| `MoadianInvoice.php` | `src\Invoice.php` | 9.79% | 5.36% | 52.17% | **16.94%** | 🟢 CLEAN (<20%) |
| `MoadianManager.php` | `src\Exceptions\MoadianException.php` | 1.67% | 17.65% | 38.0% | **13.73%** | 🟢 CLEAN (<20%) |
| `MoadianResponse.php` | `src\Http\Response.php` | 10.37% | 10.64% | 38.67% | **16.11%** | 🟢 CLEAN (<20%) |
| `Payment.php` | `src\Payment.php` | 2.96% | 7.41% | 39.08% | **11.52%** | 🟢 CLEAN (<20%) |
| `TaxIdGenerator.php` | `src\Facades\Moadian.php` | 1.67% | 12.0% | 25.68% | **9.57%** | 🟢 CLEAN (<20%) |
| `Verhoeff.php` | `src\Services\VerhoeffService.php` | 3.23% | 4.48% | 45.83% | **12.13%** | 🟢 CLEAN (<20%) |
| `Facades\Moadian.php` | `src\Facades\Moadian.php` | 9.71% | 20.83% | 40.32% | **19.17%** | 🟢 CLEAN (<20%) |

**Tainted Files (>=20% similarity):** 0 / 10
