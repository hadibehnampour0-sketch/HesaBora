<?php

namespace App\Enums;

/**
 * D-085: distinguishes a Document that represents this Company's own,
 * locally-originated financial activity from one that was copied/imported
 * as baseline history from another fiscal year (Company). NATIVE is the
 * column default -- every existing/legacy row and every ordinarily-created
 * Document is NATIVE unless a copy/import path explicitly overrides it to
 * BASELINE. This is the deliberate safe default (D-085 decision 4: unknown/
 * legacy provenance is treated as LOCAL/UNKNOWN and blocks Company
 * deletion) -- no heuristic ever infers BASELINE from timestamps, numbers,
 * or company age.
 */
enum FinancialProvenance: int
{
    case NATIVE = 1;
    case BASELINE = 2;
}
