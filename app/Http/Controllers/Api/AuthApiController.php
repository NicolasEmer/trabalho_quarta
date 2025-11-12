<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    public function auth(AuthRequest $request)
    {
        $data = $request->validated();
        $cpf = preg_replace('/\D+/', '', (string) ($data['cpf'] ?? ''));
        $deviceName = $data['device'] ?? 'api';

        $user = User::where('cpf', $cpf)->first();

        if (!$user) {
            $user = User::create([
                'cpf'       => $cpf,
                'completed' => false,
            ]);

            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'access_token'     => $token,
                'token_type'       => 'Bearer',
                'user'             => [
                    'id'        => $user->id,
                    'cpf'       => $user->cpf,
                    'completed' => $user->completed,
                    'name'      => $user->name,
                    'email'     => $user->email,
                ],
                'message'           => 'Cadastro simplificado criado. Complete seus dados.',
                'needs_completion'  => true,
                'password_required' => false,
            ], 201);
        }

        if ($user->completed) {
            $password = (string) ($data['password'] ?? '');

            if ($password === '') {
                return response()->json([
                    'password_required' => true,
                    'message'           => 'Informe a senha para prosseguir.',
                ], 200);
            }

            if (!$user->password || !Hash::check($password, $user->password)) {
                return response()->json(['error' => 'CPF/senha inválidos.'], 401);
            }

            $token = $user->createToken($deviceName)->plainTextToken;

            return response()->json([
                'access_token'     => $token,
                'token_type'       => 'Bearer',
                'user'             => [
                    'id'        => $user->id,
                    'cpf'       => $user->cpf,
                    'completed' => $user->completed,
                    'name'      => $user->name,
                    'email'     => $user->email,
                ],
                'message'           => 'Autenticado com sucesso.',
                'needs_completion'  => false,
                'password_required' => false,
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'access_token'     => $token,
            'token_type'       => 'Bearer',
            'user'             => [
                'id'        => $user->id,
                'cpf'       => $user->cpf,
                'completed' => $user->completed,
                'name'      => $user->name,
                'email'     => $user->email,
            ],
            'message'           => 'Login com cadastro simplificado. Complete seus dados.',
            'needs_completion'  => true,
            'password_required' => false,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logout efetuado. Token revogado.']);
    }
}
