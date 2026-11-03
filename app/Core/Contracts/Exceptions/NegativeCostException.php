<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when inventory acquisition cost is less than zero.
 */
class NegativeCostException extends ValidationException
{
}
