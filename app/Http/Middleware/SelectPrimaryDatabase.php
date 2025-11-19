<?php

namespace App\Http\Middleware;

use App\Services\PrimaryDatabaseResolver;
use Closure;
use Illuminate\Http\Request;

class SelectPrimaryDatabase
{
    public function handle(Request $request, Closure $next)
    {
        // Apenas garante que a detecção seja feita no início da request
        PrimaryDatabaseResolver::detectConnection();

        return $next($request);
    }
}
