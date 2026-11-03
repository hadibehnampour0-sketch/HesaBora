<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when registering a cheque number or Sayad ID that already exists for the company.
 */
class DuplicateChequeException extends ValidationException {}
