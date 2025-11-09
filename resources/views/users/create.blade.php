@extends('layouts.app')

@section('title','Novo usuário')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Novo</div>
            <div class="card-body">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">CPF</label>
                        <input name="cpf" class="form-control" required placeholder="000.000.000-00">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nome</label>
                        <input name="name" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">E-mail</label>
                        <input name="email" class="form-control" type="email">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Telefone</label>
                        <input name="phone" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Senha</label>
                        <input name="password" class="form-control" type="password">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="completed" name="completed" value="1">
                        <label class="form-check-label" for="completed">Cadastro completo</label>
                    </div>
                    <button class="btn btn-success">Salvar</button>
                </form>
                <script>
                    document.getElementById('form-create').addEventListener('submit', async (e) => {
                        e.preventDefault();

                        const token = localStorage.getItem('token'); // salve o token após /api/v1/auth
                        const body = {
                            cpf: document.querySelector('[name="cpf"]').value,
                            name: document.querySelector('[name="name"]').value || null,
                            email: document.querySelector('[name="email"]').value || null,
                            phone: document.querySelector('[name="phone"]').value || null,
                            password: document.querySelector('[name="password"]').value || null,
                            completed: document.querySelector('#completed')?.checked ? 1 : 0,
                        };

                        const res = await fetch('/api/v1/users', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        });

                        if (res.ok) {
                            location.href = '/users';
                        } else {
                            const data = await res.json().catch(() => ({}));
                            alert('Erro ao salvar: ' + (data.message || JSON.stringify(data)));
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</div>
@endsection
