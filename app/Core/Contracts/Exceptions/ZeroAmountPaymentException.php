<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when a treasury payment or receipt has an amount of zero or less.
 */
class ZeroAmountPaymentException extends ValidationException
{
}
