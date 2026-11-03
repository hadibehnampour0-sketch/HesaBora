<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when a cheque status transition violates legal commercial banking workflows.
 */
class InvalidChequeStateTransitionException extends DomainException
{
}
