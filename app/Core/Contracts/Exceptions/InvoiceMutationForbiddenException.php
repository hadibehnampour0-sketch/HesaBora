<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to edit, unapprove, or reject an invoice with financial activity.
 */
class InvoiceMutationForbiddenException extends DomainException
{
}
