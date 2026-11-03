<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when an allocation amount exceeds remaining invoice balance.
 */
class InvoiceOverpaymentException extends ValidationException
{
}
