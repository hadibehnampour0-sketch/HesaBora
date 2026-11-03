<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when a journal line has zero amount or both debit and credit amounts.
 */
class InvalidLineAmountException extends ValidationException
{
}
