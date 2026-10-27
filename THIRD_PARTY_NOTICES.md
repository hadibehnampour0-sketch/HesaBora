# Third-Party Notices & Attribution Ledger

This document records third-party notices, intellectual property provenance, and open-source licenses for software components, public-domain algorithms, and fonts integrated into or utilized by Dakhl (دخل).

---

## 1. Astronomical Calendar Algorithms (`App\Support\JalaliDate`)

The solar calendar conversion engine in `App\Support\JalaliDate` implements public-domain astronomical formulas for exact conversion between Gregorian and Solar Hijri (Jalali) dates:

- **Academic Citation:**
  - Kazimierz M. Borkowski (1996), *"The Persian calendar for any year"*, Toruń Centre for Astronomy, Nicolaus Copernicus University. Published in *Earth, Moon, and Planets* 74: 111–118.
  - Ahmad Birashk (1993), *"A Comparative Calendar of the Iranian, Muslim Lunar, and Christian Eras"*, Mazda Publishers.
- **Legal Characterization:**
  - In accordance with the Berne Convention for the Protection of Literary and Artistic Works (Article 2, Paragraph 8) and United States Copyright Act (17 U.S.C. § 102(b)), mathematical formulas, calendar cycle arithmetic (the 33-year Iranian leap cycle with 8 leap years: $33 \times 365 + 8 = 12053$ days), and Julian Day Number (JDN) algorithms constitute uncopyrightable scientific facts and mathematical principles.
  - The PHP 8.3 implementation in `App\Support\JalaliDate` was authored independently from the mathematical specifications without incorporating expressive code or comments from LGPL/GPL implementations.

---

## 2. Iranian Typography & Open-Source Fonts

Dakhl bundles Persian open-source typefaces for user interface display and PDF document generation:

### 2.1 Vazirmatn Font
- **Author:** Saber Rastikerdar (صابر راستی‌کردار)
- **License:** SIL Open Font License 1.1 (OFL-1.1)
- **Copyright:** Copyright (c) 2015-2022 Saber Rastikerdar
- **Notice:** This Font Software is licensed under the SIL Open Font License, Version 1.1. The font and derivatives may be bundled, embedded, and redistributed with any software, provided that individual fonts are not sold by themselves.

### 2.2 Sahel Font
- **Author:** Saber Rastikerdar (صابر راستی‌کردار)
- **License:** SIL Open Font License 1.1 (OFL-1.1)
- **Copyright:** Copyright (c) 2015-2022 Saber Rastikerdar
- **Notice:** This Font Software is licensed under the SIL Open Font License, Version 1.1.

---

## 3. Core Permissive Dependencies

Runtime dependencies utilized by Dakhl are verified permissive open-source libraries:

| Package | Version | License | Copyright / Notice |
|---|---|---|---|
| `paragonie/sodium_compat` | `^2.5` | **MIT** | Copyright (c) 2017-2024 Paragon Initiative Enterprises |
| `firebase/php-jwt` | `^7.1` | **BSD-3-Clause** | Copyright (c) 2011, Neuman Vong, Google LLC |
| `barryvdh/laravel-dompdf` | `^3.1` | **MIT** | Copyright (c) 2013-2024 Barry vd. Heuvel |
| `spatie/laravel-permission` | `^6.4` | **MIT** | Copyright (c) Spatie bvba |
| `laravel/sanctum` | `^4.2` | **MIT** | Copyright (c) Taylor Otwell |
