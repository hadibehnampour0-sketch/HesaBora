<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to post an invoice that contains no item lines.
 */
class EmptyInvoiceException extends ValidationException {}
