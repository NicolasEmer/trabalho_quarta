<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Autenticação",
 *     description="Rotinas de autenticação via CPF e senha."
 * )
 */

class AuthApiController extends Controller
{
    /**
     * Rotina de autenticação.
     *
     * @OA\Post(
     *     path="/api/v1/auth",
     *     tags={"Autenticação"},
     *     summary="Autentica usuário via CPF",
     *     description="Se o CPF não existir, cria um cadastro simplificado e gera token. Se já for completo, exige senha.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"cpf"},
     *             @OA\Property(
     *                 property="cpf",
     *                 type="string",
     *                 example="806.199.710-02"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 nullable=true,
     *                 example="abc123"
     *             ),
     *             @OA\Property(
     *                 property="device",
     *                 type="string",
     *                 example="app-mobile"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Autenticado com sucesso ou senha requerida.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "access_token":"TOKEN...",
     *                 "token_type":"Bearer",
     *                 "user":{"id":1,"cpf":"12345678909","completed":true,"name":"Fulano","email":"fulano@exemplo.com"},
     *                 "message":"Autenticado com sucesso.",
     *                 "needs_completion":false,
     *                 "password_required":false
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cadastro simplificado criado.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "access_token":"TOKEN...",
     *                 "token_type":"Bearer",
     *                 "user":{"id":10,"cpf":"12345678909","completed":false,"name":null,"email":null},
     *                 "message":"Cadastro simplificado criado. Complete seus dados.",
     *                 "needs_completion":true,
     *                 "password_required":false
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="CPF/senha inválidos."
     *     )
     * )
     */

    public function auth(AuthRequest $request)
    {

        /**
         * Logout (revoga o token atual).
         *
         * @OA\Post(
         *     path="/auth/logout",
         *     tags={"Autenticação"},
         *     summary="Logout do usuário autenticado",
         *     security={{"sanctum":{}}},
         *     @OA\Response(
         *         response=200,
         *         description="Logout efetuado."
         *     ),
         *     @OA\Response(
         *         response=401,
         *         description="Não autenticado."
         *     )
         * )
         */

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
