# ابزار آفلاین صدور لایسنس دخل (Dakhl Offline License Issuer)

این ابزار به صورت کاملاً مستقل و آفلاین طراحی شده و منحصراً در اختیار مالک پروژه/تیم صدور مجوز قرار دارد. کلید خصوصی (Private Key) هرگز نباید وارد مخزن سورس، سرورهای وب یا بیلد نصبی برنامه (NativePHP/Electron) شود.

## دستورات CLI

### ۱. تولید جفت‌کلید جدید Ed25519 (Key Generation)
```bash
php dakhl-license.php keygen --name=dakhl-root-2026
```
این دستور دو فایل در پوشه `keys/` تولید می‌کند:
- `keys/dakhl-root-2026.pub` (کلید عمومی ۳۲ بایتی هگز - جهت قرار گرفتن در `config/license.php`)
- `keys/dakhl-root-2026.key` (کلید خصوصی ۶۴ بایتی هگز - **محرمانه**)

### ۲. صدور لایسنس جدید (Issue License)
```bash
php dakhl-license.php issue \
  --key=keys/dakhl-root-2026.key \
  --kid=dakhl-root-2026 \
  --tier=professional \
  --org="شرکت بازرگانی آذرخش" \
  --days=365 \
  --fingerprint="DAKHL-A1B2-C3D4-E5F6"
```
خروجی یک رشته توکن امضا شده با استاندارد `DAKHL-LIC-v2.{payload}.{signature}` خواهد بود.

### ۳. بررسی و تأیید توکن لایسنس (Verify License)
```bash
php dakhl-license.php verify \
  --token="DAKHL-LIC-v2...." \
  --pubkey=keys/dakhl-root-2026.pub
```
