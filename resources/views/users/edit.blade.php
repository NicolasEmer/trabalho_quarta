@extends('layouts.app')

@section('title','Editar usuário')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Editar</div>
                <div class="card-body">

                    <div id="alert" class="alert d-none"></div>

                    <form id="form-edit">
                        <div class="mb-2">
                            <label class="form-label">CPF</label>
                            <input name="cpf" class="form-control" required>
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
                            <label class="form-label">Senha (deixe em branco para manter)</label>
                            <input name="password" class="form-control" type="password">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="completed" name="completed" value="1">
                            <label class="form-check-label" for="completed">Cadastro completo</label>
                        </div>

                        <button class="btn btn-primary" id="btn-save">
                            <span class="btn-text">Atualizar</span>
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const token = localStorage.getItem('token');
            const alertBox = document.getElementById('alert');
            const form = document.getElementById('form-edit');
            const btn = document.getElementById('btn-save');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner-border');

            function showAlert(msg, type='warning') {
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hideAlert() { alertBox.classList.add('d-none'); }

            if (!token) {
                showAlert('Você precisa estar autenticado. Faça login.', 'danger');
                setTimeout(() => location.href = '/login', 1000);
                return;
            }

            const pathParts = location.pathname.split('/').filter(Boolean);
            let userId = (typeof @json(isset($id) ? $id : null) === 'number' ? @json($id ?? null) : null);
            if (!userId) {
                const idx = pathParts.findIndex(p => p === 'users');
                userId = idx >= 0 ? Number(pathParts[idx + 1]) : null;
            }
            if (!userId) {
                showAlert('ID do usuário não encontrado na URL.', 'danger');
                return;
            }

            const $cpf = document.querySelector('[name="cpf"]');
            const $name = document.querySelector('[name="name"]');
            const $email = document.querySelector('[name="email"]');
            const $phone = document.querySelector('[name="phone"]');
            const $password = document.querySelector('[name="password"]');
            const $completed = document.getElementById('completed');

            fetch('/api/v1/users/' + userId, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            })
                .then(async res => {
                    if (res.status === 401) throw new Error('Sessão expirada. Faça login novamente.');
                    if (!res.ok) throw new Error('Não foi possível carregar o usuário.');
                    const data = await res.json();

                    const u = data.data ?? data;
                    $cpf.value = u.cpf || '';
                    $name.value = u.name || '';
                    $email.value = u.email || '';
                    $phone.value = u.phone || '';
                    $completed.checked = !!u.completed;
                })
                .catch(err => showAlert(err.message || 'Falha ao carregar.', 'danger'));

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                hideAlert();

                btn.disabled = true; spinner.classList.remove('d-none'); btnText.textContent = 'Salvando...';

                const payload = {
                    cpf: $cpf.value,
                    name: $name.value || null,
                    email: $email.value || null,
                    phone: $phone.value || null,
                    completed: $completed.checked ? 1 : 0,
                };
                if ($password.value) payload.password = $password.value;

                try {
                    const res = await fetch('/api/v1/users/' + userId, {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        showAlert('Usuário atualizado com sucesso!', 'success');
                        setTimeout(() => history.back(), 800);
                    } else if (res.status === 422) {
                        const errs = data.errors || {};
                        const flat = Object.values(errs).flat().join(' | ') || data.message || 'Dados inválidos.';
                        showAlert(flat, 'danger');
                    } else if (res.status === 401) {
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                        setTimeout(() => location.href = '/login', 900);
                    } else {
                        showAlert(data.message || 'Erro ao salvar.', 'danger');
                    }
                } catch (e) {
                    showAlert('Erro de rede ao salvar.', 'danger');
                } finally {
                    btn.disabled = false; spinner.classList.add('d-none'); btnText.textContent = 'Atualizar';
                }
            });
        })();
    </script>
@endsection
