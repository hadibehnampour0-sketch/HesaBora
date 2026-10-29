# Clean-Room Behavioral Specification: Iranian Tax Administration (INTA) E-Invoice Engine

**Specification Reference:** `docs/cleanroom/SPECS/MOADIAN_BEHAVIORAL_SPEC.md`  
**Standard Compliance:** Official INTA (سازمان امور مالیاتی کشور) Technical Protocol v2.x & RFC 7515 / RFC 7516  
**Roles:**
- **Analyst:** Chief Systems Architect (derived purely from public INTA standards and Dakhl test suite)
- **Reviewer:** Compliance Officer (verified spec is derived solely from official tax-authority specifications; labeled REWRITTEN-LOW-SIMILARITY (contaminated author))

---

## 1. DOMAIN PURPOSE & STATUTORY CONTEXT
Under the Iranian Value Added Tax and Electronic Invoicing Law (قانون پایانه‌های فروشگاهی و سامانه مودیان), registered businesses must generate standardized electronic invoices, assign a unique 22-character tax identifier (شناسه یکتا مالیاتی), cryptographically sign the payload using asymmetric keys issued by official CA (مرکز صدور گواهی الکترونیکی عام / نماد مودیان), and transmit the packet over HTTPS to the INTA gateway.

---

## 2. UNIQUE TAX IDENTIFIER (22 CHARACTERS)
The unique Tax ID (`taxid`) consists of exactly 22 alphanumeric characters structured as follows:

$$\text{TaxID} = \text{FiscalMemoryID (6 chars)} + \text{EpochDayCount (5 digits)} + \text{SequentialSerial (10 digits)} + \text{VerhoeffCheckDigit (1 digit)}$$

### 2.1 Component Specifications:
1. **Fiscal Memory ID (`memoryId`):** 6-character hexadecimal/alphanumeric string assigned by INTA to the taxpayer's software/hardware profile (e.g., `A11111`).
2. **Epoch Day Count (`epochDays`):** 5-digit decimal integer representing the number of days elapsed between the invoice date and the base epoch date (2020-01-01 / 1398-10-11), padded with leading zeros (`sprintf('%05d', $days)`).
3. **Sequential Serial (`serial`):** 10-digit numeric invoice serial number, unique per fiscal memory ID, padded with leading zeros (`sprintf('%010d', $serial)`).
4. **Verhoeff Check Digit (`checkDigit`):** A single decimal digit ($0\dots9$) calculated using the dihedral group $D_5$ algorithm over the preceding 21 characters.
   - For non-digit characters in the memory ID, each alphanumeric character is mapped to its numeric representation (ASCII value or standardized base-10 mapping).

---

## 3. VERHOEFF ALGORITHM SPECIFICATION (DIHEDRAL GROUP $D_5$)
The Verhoeff algorithm utilizes the symmetries of a regular pentagon (order 10) to catch all single-digit transcription errors and adjacent transposition errors.
It consists of:
- Operation table $d(i, j)$ defined on elements $0\dots9$.
- Permutation table $p(pos \pmod 8, val)$ applied based on the position from the right.
- Inverse array $inv(val)$ where $d(val, inv(val)) = 0$.

### Mathematical Invariants:
- Checksum validation:
  For a valid 22-character string, applying the permutations and $D_5$ multiplications across all 22 digits must yield $0$.
- Check digit generation:
  The check digit $c$ is the value such that $c = inv(\text{accumulated checksum of first 21 characters})$.

---

## 4. INTA DATA TRANSFER OBJECTS (DTOs)

### 4.1 Invoice Header (`InvoiceHeader`)
Fields mapping to INTA schema:
- `taxid`: 22-char unique identifier
- `ins`: Invoice pattern / status (1 = standard sell, 3 = void/cancellation, 4 = return)
- `inty`: Invoice type (1 = electronic invoice type 1, 2 = type 2)
- `irtaxid`: Reference Tax ID for return or void invoices
- `indatim`: Invoice issuance timestamp (milliseconds since Unix epoch)
- `indati2m`: Invoice creation/registration timestamp (milliseconds since Unix epoch)
- `inp`: Invoice pattern subject (1 = standard commercial sale)
- `tins`: Seller National Tax Identification Number (11 digits)
- `tob`: Buyer type (1 = individual, 2 = legal entity, 3 = civil partnership, 4 = foreign national)
- `bid`: Buyer national ID / company registration ID
- `tinb`: Buyer economic code
- `bpc`: Buyer postal code (10 digits)
- `tprdis`: Total price before discount
- `tdis`: Total discount
- `tadis`: Total after discount
- `tvam`: Total Value Added Tax
- `todam`: Total other charges / duties
- `tbill`: Total payable amount
- `setm`: Payment settlement method (1 = cash, 2 = credit)
- `cap`: Cash payment amount

### 4.2 Invoice Item (`InvoiceItem`)
- `sstid`: System-wide Goods/Services Standard Identification Code (شناسه کالا/خدمت - 13 digits)
- `sstt`: Product/service description
- `am`: Quantity
- `fee`: Unit price before discount
- `prdis`: Total price before discount
- `dis`: Discount amount
- `adis`: Amount after discount
- `vra`: Value Added Tax rate (e.g. 10%)
- `vam`: Value Added Tax amount
- `tsstam`: Total item gross amount ($adis + vam$)

### 4.3 Payment Record (`Payment`)
- `pmt`: Payment type code (1 = cash, 2 = POS card, 3 = bank transfer, etc.)
- `pv`: Paid value
- `pdt`: Payment timestamp in milliseconds
- `trn`: Bank transaction reference number / RRN
- `acn`: Payer account / card number
- `trmn`: POS terminal ID
- `iinn`: Switch / Issuer identification number

### 4.4 Aggregate Invoice (`MoadianInvoice`)
- Encapsulates `InvoiceHeader`, collection of `InvoiceItem`, and collection of `Payment`.
- Provides `toArray()` and `jsonSerialize()` methods rendering compliant JSON payload packets.

---

## 5. GATEWAY CLIENT & CRYPTOGRAPHIC PROTOCOL (`MoadianClient` & `MoadianManager`)

### 5.1 Cryptographic Signing (JWS - RFC 7515)
- Taxpayer signs the canonical JSON packet using their RSA private key with algorithm `RS256` (SHA-256 with PKCS#1 v1.5 or PSS).
- The signature is detached or included in the payload packet as `signature`.

### 5.2 Transmission Protocol:
- **`sendInvoice(MoadianInvoice $invoice)`**:
  - Signs invoice payload with taxpayer RSA private key.
  - POSTs packet to tax gateway endpoint (`/api/v2/async/invoices` or configured base URI).
  - Returns structured `MoadianResponse` containing `status`, `referenceNumber`, and `taxid`.
- **`inquiryByReferenceNumbers(string ...$referenceNumbers)`**:
  - Queries status of previously dispatched reference numbers.
  - Returns status list (`SUCCESS`, `FAILED`, `IN_PROGRESS`, `NOT_FOUND`).

### 5.3 Laravel Facade & Dependency Injection:
- `Moadian::for($privateKey, $certificate, $username)` returns a configured instance ready to dispatch requests or inquiries.
- Must support clean Mockery interception (`Moadian::shouldReceive('for')->andReturn($mock)`).
