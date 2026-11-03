<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to delete a company with native accounting records or allocations.
 */
class CompanyDeletionForbiddenException extends DomainException
{
}
