@extends('layouts.app')

@section('title','Editar usuário')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Editar</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.update',$user) }}">
                        @csrf @method('PUT')
                        <div class="mb-2">
                            <label class="form-label">CPF</label>
                            <input name="cpf" class="form-control" value="{{ old('cpf',$user->cpf) }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Nome</label>
                            <input name="name" class="form-control" value="{{ old('name',$user->name) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">E-mail</label>
                            <input name="email" class="form-control" type="email" value="{{ old('email',$user->email) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Telefone</label>
                            <input name="phone" class="form-control" value="{{ old('phone',$user->phone) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Senha (deixe em branco para manter)</label>
                            <input name="password" class="form-control" type="password">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="completed" name="completed" value="1" {{ $user->completed ? 'checked' : '' }}>
                            <label class="form-check-label" for="completed">Cadastro completo</label>
                        </div>
                        <button class="btn btn-primary">Atualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

