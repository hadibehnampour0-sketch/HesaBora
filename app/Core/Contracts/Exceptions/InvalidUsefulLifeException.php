<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when fixed asset useful life is zero or negative.
 */
class InvalidUsefulLifeException extends ValidationException
{
}
