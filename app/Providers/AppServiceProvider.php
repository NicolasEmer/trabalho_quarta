<?php

namespace App\Providers;

use App\Models\Token;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Diz explicitamente ao Sanctum para usar nosso model Token
        Sanctum::usePersonalAccessTokenModel(Token::class);
    }
}
