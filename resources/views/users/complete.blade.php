@extends('layouts.app')

@section('title','Completar cadastro')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">Complete seu cadastro</div>
                <div class="card-body">

                    <div id="alert" class="alert d-none"></div>

                    <form id="complete-form" autocomplete="off">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" id="cpf-mask" class="form-control" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Senha</label>
                                <input type="password" name="password" class="form-control" minlength="3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar Senha</label>
                                <input type="password" name="password_confirmation" class="form-control" minlength="3" required>
                            </div>
                        </div>

                        <div class="mt-3 d-grid">
                            <button class="btn btn-success" id="submit-btn">
                                <span class="btn-text">Concluir cadastro</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        const alertBox = document.getElementById('alert');
        function showAlert(msg, type='warning') {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = msg;
            alertBox.classList.remove('d-none');
        }
        function hideAlert() { alertBox.classList.add('d-none'); }

        function maskCpf(digits) {
            if (!digits) return '';
            const only = String(digits).replace(/\D+/g,'');
            if (only.length !== 11) return only;
            return `${only.slice(0,3)}.${only.slice(3,6)}.${only.slice(6,9)}-${only.slice(9,11)}`;
        }

        (function () {
            const token = localStorage.getItem('token');
            if (!token) {
                showAlert('Você precisa estar autenticado. Faça login.', 'danger');
                setTimeout(() => location.href = '/login', 1200);
                return;
            }

            const nameInput  = document.querySelector('input[name="name"]');
            const emailInput = document.querySelector('input[name="email"]');
            const phoneInput = document.querySelector('input[name="phone"]');
            const cpfMask    = document.getElementById('cpf-mask');
            const form       = document.getElementById('complete-form');
            const btn        = document.getElementById('submit-btn');
            const btnText    = btn.querySelector('.btn-text');
            const spinner    = btn.querySelector('.spinner-border');

            let currentUserId = null;

            fetch('/api/v1/me', {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            })
                .then(async (res) => {
                    if (res.status === 401) throw new Error('Sessão expirada. Faça login novamente.');
                    const data = await res.json();
                    currentUserId   = data.id;
                    nameInput.value  = data.name  ?? '';
                    emailInput.value = data.email ?? '';
                    phoneInput.value = data.phone ?? '';
                    cpfMask.value    = maskCpf(data.cpf ?? '');
                    if (data.completed) showAlert('Seu cadastro já está completo.', 'info');
                })
                .catch(err => showAlert(err.message || 'Falha ao carregar seus dados.', 'danger'));

            form.addEventListener('submit', async (e) => {
                e.preventDefault(); hideAlert();

                if (!currentUserId) {
                    showAlert('Não foi possível identificar o usuário autenticado.', 'danger');
                    return;
                }
                const pwd  = form.querySelector('input[name="password"]').value;
                const pwdc = form.querySelector('input[name="password_confirmation"]').value;
                if (pwd !== pwdc) {
                    showAlert('As senhas não conferem.', 'danger');
                    return;
                }

                btn.disabled = true; spinner.classList.remove('d-none'); btnText.textContent = 'Salvando...';

                const payload = {
                    name:      nameInput.value || null,
                    email:     emailInput.value || null,
                    phone:     phoneInput.value || null,
                    password:  pwd,
                    completed: true
                };

                try {
                    const res = await fetch('/api/v1/users/' + currentUserId, {
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
                        showAlert('Cadastro concluído com sucesso!', 'success');
                        setTimeout(() => location.href = '/events', 900);
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
                    btn.disabled = false; spinner.classList.add('d-none'); btnText.textContent = 'Concluir cadastro';
                }
            });
        })();
    </script>
@endsection
