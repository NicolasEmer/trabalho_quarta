@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header text-center fw-bold">
                    Acessar o Sistema
                </div>

                <div class="card-body">

                    <div id="alert" class="alert d-none"></div>

                    <form id="form-cpf">
                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" id="cpf" class="form-control" placeholder="000.000.000-00" required>
                        </div>
                        <button class="btn btn-primary w-100" id="btn-cpf">
                            Continuar
                        </button>
                    </form>

                    <form id="form-password" class="d-none mt-3">
                        <input type="hidden" id="cpf-hidden">

                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" id="password" class="form-control" required>
                        </div>

                        <button class="btn btn-success w-100" id="btn-password">
                            Entrar
                        </button>

                        <button type="button" class="btn btn-outline-secondary w-100 mt-2"
                                onclick="resetForms()">
                            Voltar
                        </button>
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

        function resetForms() {
            document.getElementById('form-cpf').classList.remove('d-none');
            document.getElementById('form-password').classList.add('d-none');
            hideAlert();
        }

        document.getElementById('form-cpf').addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const cpf = document.getElementById('cpf').value;

            const response = await fetch('/api/v1/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ cpf })
            });

            const data = await response.json().catch(()=>({}));

            if (!response.ok) {
                showAlert(data.error || data.message || 'Falha no login.', 'danger');
                return;
            }

            if (data.needs_completion === false && data.user.completed === true && !data.password_validated) {
                document.getElementById('form-cpf').classList.add('d-none');
                document.getElementById('form-password').classList.remove('d-none');
                document.getElementById('cpf-hidden').value = cpf;
                showAlert('Digite sua senha para continuar.', 'info');
                return;
            }

            if (data.needs_completion === true) {
                localStorage.setItem('token', data.access_token);
                location.href = '/complete-profile';
                return;
            }

            if (data.access_token) {
                localStorage.setItem('token', data.access_token);
                location.href = '/events';
            }
        });

        document.getElementById('form-password').addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();

            const cpf = document.getElementById('cpf-hidden').value;
            const password = document.getElementById('password').value;

            const response = await fetch('/api/v1/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ cpf, password })
            });

            const data = await response.json().catch(()=>({}));

            if (!response.ok) {
                showAlert(data.error || data.message || 'CPF ou senha inválidos.', 'danger');
                return;
            }

            localStorage.setItem('token', data.access_token);
            location.href = '/events';
        });
    </script>

@endsection
