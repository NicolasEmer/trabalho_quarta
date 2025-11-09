<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $users = User::orderBy('id', 'desc')->paginate($perPage);

        return response()->json($users);
    }


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
