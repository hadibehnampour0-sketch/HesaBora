<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when attempting to close a fiscal year whose ledger accounts do not balance.
 */
class FiscalYearNotReconciledException extends DomainException {}
