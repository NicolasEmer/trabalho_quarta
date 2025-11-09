@extends('layouts.app')

@section('title','Completar cadastro')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">Complete seu cadastro</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.complete.save') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name',$user->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email',$user->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone',$user->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" value="{{ substr($user->cpf,0,3) }}.***.***-**" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Senha</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar Senha</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3 d-grid">
                            <button class="btn btn-success">Concluir cadastro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
