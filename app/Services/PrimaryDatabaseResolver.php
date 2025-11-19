<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrimaryDatabaseResolver
{
    /**
     * Nome da conexão escolhida para esta request.
     * Pode ser "mysql_vm" ou "mysql".
     */
    protected static ?string $connection = null;

    /**
     * Retorna o nome da conexão primária atual.
     */
    public static function getConnectionName(): string
    {
        if (static::$connection !== null) {
            return static::$connection;
        }

        static::detectConnection();

        return static::$connection ?? config('database.default', 'mysql');
    }

    /**
     * Detecta se a VM está acessível:
     *  - se sim -> usa "mysql_vm"
     *  - se não -> usa "mysql" local
     */
    public static function detectConnection(): void
    {
        if (static::$connection !== null) {
            return;
        }

        try {
            // Testa conexão na VM
            DB::connection('mysql_vm')->getPdo();

            static::$connection = 'mysql_vm';

            Log::debug('[DB-PRIMARY] Usando conexão mysql_vm (VM online).');
        } catch (\Throwable $e) {

            static::$connection = 'mysql';

            Log::warning('[DB-PRIMARY] Falha ao conectar mysql_vm, usando mysql local.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Permite forçar uma conexão (se algum dia você quiser em testes).
     */
    public static function setConnection(?string $name): void
    {
        static::$connection = $name;
    }
}
