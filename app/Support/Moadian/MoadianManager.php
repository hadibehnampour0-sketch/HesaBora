<?php

namespace App\Support\Moadian;

/**
 * Clean-Room Moadian Service Manager.
 *
 * Factory for creating MoadianClient instances for specific credentials.
 *
 * License: MIT
 */
class MoadianManager
{
    public function for(?string $privateKey = null, ?string $certificate = null, ?string $username = null, ?string $baseUri = null): object
    {
        return new MoadianClient($privateKey ?? '', $certificate ?? '', $username ?? '', $baseUri);
    }
}
