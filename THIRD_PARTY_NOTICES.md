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

## 3. Runtime PHP Dependencies (`composer licenses`)

The following libraries are included via Composer in the runtime environment:

### 3.1 LGPL Licensed Components (Dynamic Linking)
- **`dompdf/dompdf`** (v3.1.6)
  - **License:** **LGPL-2.1**
  - **Copyright:** Copyright (c) 2004-2024 The Dompdf Team
  - **Notice:** Licensed under the GNU Lesser General Public License version 2.1. Dakhl links to DomPDF as an external, un-modified library through Composer.
- **`dompdf/php-font-lib`** (v1.0.2)
  - **License:** **LGPL-2.1-or-later**
  - **Copyright:** Copyright (c) 2011-2024 Fabien Ménager
- **`dompdf/php-svg-lib`** (v1.0.2)
  - **License:** **LGPL-3.0-or-later**
  - **Copyright:** Copyright (c) 2014-2024 Fabien Ménager

### 3.2 MIT Licensed Components
- **`barryvdh/laravel-dompdf`** (v3.1.2) — MIT, Barry vd. Heuvel
- **`paragonie/sodium_compat`** (v2.5.0) — MIT, Paragon Initiative Enterprises
- **`laravel/framework`** (v12.x) — MIT, Taylor Otwell
- **`laravel/sanctum`** (v4.2.x) — MIT, Taylor Otwell
- **`spatie/laravel-permission`** (v6.4.x) — MIT, Spatie bvba
- **`guzzlehttp/guzzle`** (v7.15.5) — MIT, Michael Dowling
- **`doctrine/dbal`** (v3.10.5) — MIT, Doctrine Project
- **`carbonphp/carbon-doctrine-types`** (v2.1.0) — MIT, CarbonPHP
- **`brick/math`** (v0.14.8) — MIT, Benjamin Morel

### 3.3 BSD-3-Clause Licensed Components
- **`firebase/php-jwt`** (v7.1.0) — BSD-3-Clause, Neuman Vong, Google LLC
- **`google/gax`** (v1.43.2) — BSD-3-Clause, Google LLC
- **`google/protobuf`** (v5.35.1) — BSD-3-Clause, Google LLC
- **`tijsverkoyen/css-to-inline-styles`** (v2.4.0) — BSD-3-Clause, Tijs Verkoyen
- **`vlucas/phpdotenv`** (v5.6.3) — BSD-3-Clause, Vance Lucas

### 3.4 Apache-2.0 Licensed Components
- **`google/auth`** (v1.52.0) — Apache-2.0, Google LLC
- **`google/cloud-core`** (v1.72.3) — Apache-2.0, Google LLC
- **`google/cloud-translate`** (v1.21.0) — Apache-2.0, Google LLC
- **`grpc/grpc`** (v1.81.0) — Apache-2.0, gRPC Authors

---

## 4. Frontend Client Dependencies (`npm license-checker`)

Front-end asset pipeline libraries bundled via Vite:

| Package | Version | License |
|---|---|---|
| `alpinejs` | `^3.14.8` | **MIT** |
| `chart.js` | `^4.5.1` | **MIT** |
| `chartjs-plugin-datalabels` | `^2.2.0` | **MIT** |
| `@majidh1/jalalidatepicker` | `^0.9.4` | **MIT** |
| `tailwindcss` / `@tailwindcss/vite` | `^4.2.4` | **MIT** |
| `daisyui` | `^5.5.19` | **MIT** |
| `axios` | `^1.11.0` | **MIT** |
| `vite` | `^7.0.7` | **MIT** |
| `sass` | `^1.77.0` | **MIT** |
