<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class EnsureProfileCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $bypass = [
            'auth.cpf.form','auth.cpf.handle','auth.password.form','auth.password.login',
            'users.complete.form','users.complete.save','events.index','logout',
        ];
        if ($request->route() && in_array($request->route()->getName(), $bypass, true)) {
            return $next($request);
        }

        $userId = $request->session()->get('user_id');
        if ($userId) {
            $user = User::find($userId);
            if ($user && !$user->completed) {
                return redirect()->route('users.complete.form')
                    ->with('warning','Complete seus dados para continuar.');
            }
            if ($user) {
                view()->share('authUser', $user);
            }
        }

        return $next($request);
    }
}
