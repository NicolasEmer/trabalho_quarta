@extends('layouts.app')

@section('title','Entrar com CPF')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Entrar / Cadastrar por CPF</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('auth.cpf.handle') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00" required>
                            <small class="text-muted">Informe seu CPF para login rápido ou cadastro simplificado.</small>
                        </div>
                        <button class="btn btn-primary w-100">Continuar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
