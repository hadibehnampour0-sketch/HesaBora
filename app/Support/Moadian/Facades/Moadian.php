<?php

namespace App\Support\Moadian\Facades;

use App\Support\Moadian\MoadianClient;
use App\Support\Moadian\MoadianInvoice;
use App\Support\Moadian\MoadianManager;
use App\Support\Moadian\MoadianResponse;
use Illuminate\Support\Facades\Facade;

/**
 * Clean-Room Gateway Access Facade for Iranian National Tax Administration (INTA / سامانه مودیان).
 *
 * Provides a mockable static interface to manage taxpayer credentials, digitally sign
 * e-invoice packets using asymmetric keys, and submit electronic documents to government endpoints.
 *
 * @method static MoadianClient for(?string $privateKey = null, ?string $certificate = null, ?string $username = null)
 * @see MoadianManager
 */
class Moadian extends Facade
{
    /**
     * Unique service registration alias within the Laravel service container.
     */
    public const IOC_IDENTIFIER = 'moadian';

    /**
     * Retrieve the root gateway connection manager from the application container.
     */
    public static function manager(): MoadianManager
    {
        return static::resolveFacadeInstance(self::IOC_IDENTIFIER);
    }

    /**
     * Establish a stateful tax gateway client initialized with company cryptographic credentials.
     */
    public static function createClient(?string $key = null, ?string $cert = null, ?string $user = null): MoadianClient
    {
        return self::manager()->for($key, $cert, $user);
    }

    /**
     * Proxy caller for establishing a client gateway session with taxpayer keys.
     */
    public static function for(?string $privateKey = null, ?string $certificate = null, ?string $username = null): mixed
    {
        return static::resolveFacadeInstance(self::IOC_IDENTIFIER)->for($privateKey, $certificate, $username);
    }

    /**
     * Determine if statutory tax authority credentials are fully populated in environment.
     */
    public static function isConfigured(): bool
    {
        return ! empty(config('services.moadian.base_uri'));
    }

    /**
     * Get the registered service name of the gateway manager from the container.
     */
    protected static function getFacadeAccessor(): string
    {
        return self::IOC_IDENTIFIER;
    }
}
