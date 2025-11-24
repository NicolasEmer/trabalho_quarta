<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Usuários",
 *     description="Gestão de usuários do sistema de eventos."
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="cpf", type="string", example="12345678909"),
 *     @OA\Property(property="name", type="string", nullable=true, example="Fulano de Tal"),
 *     @OA\Property(property="email", type="string", nullable=true, example="fulano@exemplo.com"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="51999998888"),
 *     @OA\Property(property="completed", type="boolean", example=true)
 * )
 */
class UserController extends Controller
{
    public function index(Request $request)
    {

        $perPage = (int) $request->get('per_page', 15);
        $users = User::orderBy('id', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Cria um usuário.
     *
     * Endpoint real: POST /api/v1/users
     *
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"Usuários"},
     *     summary="Cria um usuário",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"cpf"},
     *             @OA\Property(property="cpf", type="string", example="123.456.789-09"),
     *             @OA\Property(property="name", type="string", nullable=true, example="Fulano de Tal"),
     *             @OA\Property(property="email", type="string", nullable=true, example="fulano@exemplo.com"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="51999998888"),
     *             @OA\Property(property="password", type="string", nullable=true, example="123456"),
     *             @OA\Property(property="completed", type="boolean", nullable=true, example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuário criado.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Usuário criado."),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação (CPF já cadastrado, etc.)."
     *     )
     * )
     */

    public function store(Request $request)
    {

        $validated = $request->validate([
            'cpf'       => ['required', 'cpf'],
            'name'      => ['nullable', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['nullable', 'string', 'min:3'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $digits = preg_replace('/\D+/', '', (string) $validated['cpf']);
        if (User::where('cpf', $digits)->exists()) {
            return response()->json([
                'message' => 'CPF já cadastrado.'
            ], 422);
        }
        $validated['cpf'] = $digits;

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user = User::create($validated);

        return response()->json([
            'message' => 'Usuário criado.',
            'user'    => $user,
        ], 201);
    }


    public function show(User $user)
    {

        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {

        $validated = $request->validate([
            'name'      => ['nullable', 'string', 'max:120'],
            'email'     => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['nullable', 'string', 'min:3'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        unset($validated['cpf']);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuário atualizado.',
            'user'    => $user->fresh(),
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuário removido.'
        ]);
    }
}
