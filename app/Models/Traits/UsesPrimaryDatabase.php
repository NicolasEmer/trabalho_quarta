<?php

namespace App\Models\Traits;

use App\Services\PrimaryDatabaseResolver;

trait UsesPrimaryDatabase
{
    public function getConnectionName()
    {
        // Delega para o resolver
        return PrimaryDatabaseResolver::getConnectionName();
    }
}
