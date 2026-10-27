<?php

namespace App\Support\Moadian\Facades;

use App\Support\Moadian\MoadianClient;
use App\Support\Moadian\MoadianManager;
use Illuminate\Support\Facades\Facade;

/**
 * Clean-room Laravel Gateway Facade for Iranian National Tax Administration (INTA) e-invoicing.
 *
 * Provides a mockable static access interface to the underlying MoadianManager service,
 * supporting digital certificate signing, electronic invoice transmission, and status inquiries.
 *
 * @method static MoadianClient for(?string $privateKey = null, ?string $certificate = null, ?string $username = null)
 *
 * @see MoadianManager
 */
class Moadian extends Facade
{
    /**
     * Identification handle for the Moadian service container registration.
     */
    public const IOC_BINDING_KEY = 'moadian';

    /**
     * Resolve the registered IoC accessor string for the tax gateway manager.
     */
    protected static function getFacadeAccessor(): string
    {
        return self::IOC_BINDING_KEY;
    }
}
