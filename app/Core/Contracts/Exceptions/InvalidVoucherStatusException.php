<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting an operation not permitted for the current voucher status.
 */
class InvalidVoucherStatusException extends DomainException
{
}
