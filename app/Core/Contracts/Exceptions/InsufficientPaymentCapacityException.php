<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when an allocation amount exceeds unallocated payment capacity.
 */
class InsufficientPaymentCapacityException extends DomainException
{
}
