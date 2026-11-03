<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to create an opening voucher in a fiscal year that already has one.
 */
class TargetYearAlreadyOpenedException extends DomainException {}
