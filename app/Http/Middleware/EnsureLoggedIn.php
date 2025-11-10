<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLoggedIn
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('user_id')) {
            return redirect()->route('auth.cpf.form')->with('warning','Faça login pelo CPF.');
        }
        return $next($request);
    }
}
