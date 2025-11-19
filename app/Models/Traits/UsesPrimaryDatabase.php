<?php

namespace App\Models\Traits;

use App\Services\PrimaryDatabaseResolver;

trait UsesPrimaryDatabase
{
    /**
     * Sobrescreve a conexão usada pelo Model.
     */
    public function getConnectionName()
    {
        $connection = PrimaryDatabaseResolver::getConnectionName();

        // Se quiser debugar:
        // \Log::debug('[MODEL] ' . static::class . ' usando conexão ' . $connection);

        return $connection;
    }
}
