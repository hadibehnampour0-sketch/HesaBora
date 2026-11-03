<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to post an invoice that is already posted or finalized.
 */
class InvoiceAlreadyPostedException extends DomainException
{
}
