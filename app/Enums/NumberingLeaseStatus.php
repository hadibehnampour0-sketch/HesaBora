<?php

namespace App\Enums;

/**
 * D-075 decision 5: a lease is never recycled once issued -- ACTIVE means
 * numbers remain available to consume via Finalize; EXHAUSTED means every
 * number in the range has been consumed (or the lease is otherwise retired)
 * and the owning device must reserve a brand new range, never reuse gaps.
 */
enum NumberingLeaseStatus: int
{
    case ACTIVE = 1;
    case EXHAUSTED = 2;

    public function label(): string
    {
        return __(match ($this) {
            self::ACTIVE => 'Active',
            self::EXHAUSTED => 'Exhausted',
        });
    }
}
