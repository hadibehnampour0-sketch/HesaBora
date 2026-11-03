<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when a sequential numbering lease block is exhausted or unavailable.
 */
class NumberLeaseExhaustedException extends DomainException {}
