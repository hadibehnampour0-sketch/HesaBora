<?php

namespace App\Support\Moadian;

/**
 * Factory and connection manager for Moadian gateway instances.
 */
class MoadianManager
{
    /**
     * Create a new Moadian client instance configured with taxpayer credentials.
     */
    public function for(?string $privateKey = null, ?string $certificate = null, ?string $username = null): object
    {
        return new MoadianClient(
            privateKey: (string) $privateKey,
            certificate: (string) $certificate,
            username: (string) $username,
            baseUrl: config('services.moadian.base_uri')
        );
    }
}
