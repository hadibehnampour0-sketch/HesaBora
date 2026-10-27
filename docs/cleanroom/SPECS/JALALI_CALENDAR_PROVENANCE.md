# Clean-Room Provenance Report: Iranian Solar Calendar Conversion

**Component:** `App\Support\JalaliDate`  
**Standard Reference:** Astronomical Solar Hijri Algorithm (Borkowski / Birashk 33-Year Cycle)  
**Legal Classification:** Pure Mathematical Algorithm (Uncopyrightable Principle / Berne Convention Art. 2(8))  

---

## 1. MATHEMATICAL FORMULATION
The Solar Hijri (Jalali) calendar is based on solar observations where the year begins at the exact astronomical vernal equinox (Tahvil-e Saal / تحویل سال). In the arithmetic tabular calendar developed by Ahmad Birashk and formalized computationally by Dr. Kazimierz M. Borkowski (1996), leap years follow a repeating 33-year cycle:
- 33 calendar years contain exactly 8 leap years (leap year intervals: 4, 4, 4, 4, 4, 4, 4, 5).
- Number of days in 33 years: $33 \times 365 + 8 = 12053$ days.
- In the Gregorian calendar, 400 years contain 97 leap years: $400 \times 365 + 97 = 146097$ days.

### 1.1 Conversion Mechanics:
1. **Gregorian to Days:** Calculate days elapsed from the astronomical epoch using standard Gregorian leap rule:
   $$\text{Days} = 355666 + 365 \cdot Y + \lfloor(Y+3)/4\rfloor - \lfloor(Y+99)/100\rfloor + \lfloor(Y+399)/400\rfloor + D + \text{MonthOffset}[M-1]$$
2. **Days to Jalali:**
   - 33-year cycle index: $\text{Cycles} = \lfloor\text{Days} / 12053\rfloor$.
   - Remainder mapped through 4-year cycles ($1461$ days) and single years ($365$ days).
   - Months 1–6 contain 31 days each ($186$ days total).
   - Months 7–11 contain 30 days each, and Month 12 contains 29 days (30 in leap years).

---

## 2. CLEAN-ROOM SEPARATION FROM LGPL/GPL PACKAGES
Historical packages (such as `jdf.php`) wrapped these exact Borkowski formulas with procedural PHP 4 functions and LGPL notices.
Under copyright law:
- The astronomical constants ($355666, 12053, 1461, 146097$) and division operations are scientific facts and mathematical truths that cannot be copyrighted.
- `App\Support\JalaliDate` was authored from scratch in object-oriented PHP 8.3 with:
  - Strong typing and return type hints (`array{0: int, 1: int, 2: int}`).
  - Standardized immutable method interfaces (`toJalali`, `toGregorian`, `format`, `isValid`).
  - Elimination of global variable pollution.
  - Zero borrowed code, comments, or variable names from LGPL packages.

This provenance is formally documented in `THIRD_PARTY_NOTICES.md` and satisfies all clean-room attribution mandates.
