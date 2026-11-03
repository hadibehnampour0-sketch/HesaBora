<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when sum of line debits does not equal sum of line credits.
 */
class UnbalancedJournalVoucherException extends DomainException
{
}
