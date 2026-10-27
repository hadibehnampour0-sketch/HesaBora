<?php

namespace App\Support\Moadian\Facades;

use App\Support\Moadian\MoadianClient;
use App\Support\Moadian\MoadianManager;
use Illuminate\Support\Facades\Facade;

/**
 * Clean-Room Moadian Facade.
 *
 * @method static MoadianClient for(string $privateKey, string $certificate, string $username, ?string $baseUri = null)
 *
 * @see MoadianManager
 */
class Moadian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MoadianManager::class;
    }
}
