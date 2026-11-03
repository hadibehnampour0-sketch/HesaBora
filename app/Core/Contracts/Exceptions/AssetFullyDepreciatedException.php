<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Exceptions;

/**
 * Thrown when calculating depreciation for an asset that has already reached its salvage value.
 */
class AssetFullyDepreciatedException extends DomainException {}
