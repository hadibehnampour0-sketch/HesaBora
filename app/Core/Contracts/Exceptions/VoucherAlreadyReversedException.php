<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to reverse a voucher that is already reversed.
 */
class VoucherAlreadyReversedException extends DomainException {}
