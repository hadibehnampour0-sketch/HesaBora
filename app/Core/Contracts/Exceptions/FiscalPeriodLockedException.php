<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to post into a frozen, closed, or audited fiscal period.
 */
class FiscalPeriodLockedException extends DomainException {}
