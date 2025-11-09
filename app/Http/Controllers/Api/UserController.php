<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index() {
        $users = User::orderBy('id','desc')->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create() {
        return view('users.create');
    }

    public function store(Request $request) {
        $data = $request->validate([
            'cpf' => ['required','string','max:14','regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/','unique:users,cpf'],
            'name' => ['nullable','string','max:120'],
            'email' => ['nullable','email','max:150','unique:users,email'],
            'phone' => ['nullable','string','max:20'],
            'password' => ['nullable','string','min:3'],
            'completed' => ['sometimes','boolean'],
        ]);
        $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        return redirect()->route('users.index')->with('status','Usuário criado.');
    }

    public function edit(User $user) {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user) {
        $data = $request->validate([
            'cpf' => ['required','string','max:14','regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/','unique:users,cpf,'.$user->id],
            'name' => ['nullable','string','max:120'],
            'email' => ['nullable','email','max:150','unique:users,email,'.$user->id],
            'phone' => ['nullable','string','max:20'],
            'password' => ['nullable','string','min:3'],
            'completed' => ['sometimes','boolean'],
        ]);
        $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']); else unset($data['password']);
        $user->update($data);
        return redirect()->route('users.index')->with('status','Usuário atualizado.');
    }

    public function destroy(User $user) {
        $user->delete();
        return redirect()->route('users.index')->with('status','Usuário removido.');
    }
}
