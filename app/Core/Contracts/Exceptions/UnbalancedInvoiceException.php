<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when invoice item lines do not reconcile to header financial totals.
 */
class UnbalancedInvoiceException extends DomainException
{
}
