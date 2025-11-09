# resources/views/users/index.blade.php (lista simples, CRUD)
@extends('layouts.app')

@section('title','Usuários')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Usuários</h3>
        <a class="btn btn-success btn-sm" href="{{ route('users.create') }}">Novo</a>
    </div>

    <table class="table table-bordered table-sm">
        <thead>
        <tr>
            <th>#</th><th>CPF</th><th>Nome</th><th>Email</th><th>Completo?</th><th>Ações</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->cpf }}</td>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    @if($u->completed)
                        <span class="badge text-bg-success">Sim</span>
                    @else
                        <span class="badge text-bg-warning">Não</span>
                    @endif
                </td>
                <td class="d-flex gap-2">
                    <a class="btn btn-primary btn-sm" href="{{ route('users.edit',$u) }}">Editar</a>
                    <form method="POST" action="{{ route('users.destroy',$u) }}" onsubmit="return confirm('Excluir?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm">Remover</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $users->links() }}
@endsection
