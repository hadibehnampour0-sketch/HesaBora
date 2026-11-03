<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when an update or delete operation is attempted on a posted financial record.
 */
class PostedFinancialMutationException extends DomainException {}
