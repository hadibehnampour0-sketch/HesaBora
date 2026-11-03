<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when creating an account with a code that already exists within the company.
 */
class DuplicateAccountCodeException extends ValidationException
{
}
