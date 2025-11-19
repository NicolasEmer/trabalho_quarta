<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrimaryDatabaseResolver
{
    protected static ?string $connection = null;

    /**
     * Retorna o nome da conexão primária atual.
     * No local: mysql_vm se disponível, senão mysql.
     * Na VM/produção: sempre mysql.
     */
    public static function getConnectionName(): ?string
    {
        // Se já foi decidido nesta request, reaproveita
        if (static::$connection !== null) {
            return static::$connection;
        }

        // Fora do ambiente local: não mexe, usa default (mysql)
        if (config('app.env') !== 'local') {
            static::$connection = config('database.default', 'mysql');
            return static::$connection;
        }

        // Ambiente local → tenta VM primeiro
        static::detectConnection();

        return static::$connection;
    }

    /**
     * Detecta se a VM está acessível.
     * Se sim → usa mysql_vm.
     * Se não → cai pro mysql local.
     */
    public static function detectConnection(): void
    {
        // Já detectado
        if (static::$connection !== null) {
            return;
        }

        // Tenta conectar na VM
        try {
            DB::connection('mysql_vm')->getPdo();
            static::$connection = 'mysql_vm';
            Log::debug('[DB-PRIMARY] Usando conexão mysql_vm (VM online).');
        } catch (\Throwable $e) {
            // Se falhar, usa banco local
            static::$connection = 'mysql';
            Log::warning('[DB-PRIMARY] Falha ao conectar mysql_vm, usando mysql local.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Permite forçar uma conexão (se quiser, em testes).
     */
    public static function setConnection(?string $name): void
    {
        static::$connection = $name;
    }
}
