<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to post into a soft-locked period without override permission.
 */
class UnauthorizedPeriodOverrideException extends DomainException
{
}
