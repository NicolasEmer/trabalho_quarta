# resources/views/auth/password.blade.php
@extends('layouts.app')

@section('title','Digite sua senha')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Autenticação</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('auth.password.login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" class="form-control" value="{{ $cpfPrefill ?? '' }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
