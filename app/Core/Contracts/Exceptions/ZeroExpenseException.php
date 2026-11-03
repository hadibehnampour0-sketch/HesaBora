<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to allocate an ancillary expense of zero value.
 */
class ZeroExpenseException extends ValidationException
{
}
