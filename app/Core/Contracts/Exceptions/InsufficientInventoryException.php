<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when an outbound dispatch quantity exceeds available inventory stock.
 */
class InsufficientInventoryException extends DomainException {}
