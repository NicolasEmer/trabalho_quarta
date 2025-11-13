@extends('layouts.app')

@section('title','Acessar o Sistema')

@section('content')
    <style>
        .hidden { display: none !important; }
    </style>

    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Acessar o Sistema</div>
                <div class="card-body">

                    <div id="alert" class="alert hidden"></div>

                    <form id="login-form" autocomplete="off">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00" required>
                            <small class="text-muted">Digite seu CPF e clique em Continuar.</small>
                        </div>

                        <div id="password-block" class="mb-3 hidden" style="display:none;">
                            <label class="form-label">Senha</label>
                            <input type="password" name="password" id="password" class="form-control" minlength="3">
                            <small class="text-muted">Informe sua senha para entrar.</small>
                            <div class="mt-2">
                                <a href="#" id="change-cpf" class="small">Trocar CPF</a>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100" id="submit-btn" type="submit">
                            <span class="btn-text">Continuar</span>
                            <span class="spinner-border spinner-border-sm hidden" role="status" aria-hidden="true"></span>
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const form       = document.getElementById('login-form');
            const alertBox   = document.getElementById('alert');
            const cpfInput   = document.getElementById('cpf');
            const pwdBlock   = document.getElementById('password-block');
            const pwdInput   = document.getElementById('password');
            const btn        = document.getElementById('submit-btn');
            const btnText    = btn.querySelector('.btn-text');
            const spinner    = btn.querySelector('.spinner-border');
            const changeCpf  = document.getElementById('change-cpf');

            let awaitPassword = false;

            function showAlert(msg, type='warning'){
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = msg;
                alertBox.classList.remove('hidden');
            }
            function hideAlert(){ alertBox.classList.add('hidden'); }
            function onlyDigits(s){ return String(s || '').replace(/\D+/g,''); }
            function maskCpf(d){
                const v = onlyDigits(d);
                if (v.length <= 3) return v;
                if (v.length <= 6) return v.slice(0,3)+'.'+v.slice(3);
                if (v.length <= 9) return v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
                return v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9,11);
            }
            function toggleLoading(on){
                btn.disabled = on;
                spinner.classList.toggle('hidden', !on);
                btnText.textContent = on
                    ? (awaitPassword ? 'Entrando...' : 'Validando...')
                    : (awaitPassword ? 'Entrar' : 'Continuar');
            }
            function showPasswordBlock(show){
                pwdBlock.style.display = show ? 'block' : 'none';
                pwdBlock.classList.toggle('hidden', !show);
                awaitPassword = show;

                cpfInput.readOnly = show;
                btnText.textContent = show ? 'Entrar' : 'Continuar';
                if (show) { setTimeout(()=> pwdInput.focus(), 50); }
            }

            function saveTokenFromResponse(data) {
                const token = data.access_token || data.token || data.accessToken;
                if (token) {
                    localStorage.setItem('token', token);
                }
            }

            cpfInput.addEventListener('input', (e)=>{
                const raw = onlyDigits(e.target.value);
                e.target.value = maskCpf(raw);
            });

            changeCpf.addEventListener('click', (e)=>{
                e.preventDefault();
                showPasswordBlock(false);
                pwdInput.value = '';
                hideAlert();
                cpfInput.readOnly = false;
                cpfInput.focus();
            });

            form.addEventListener('submit', async (e)=>{
                e.preventDefault();
                hideAlert();
                toggleLoading(true);

                const cpfDigits = onlyDigits(cpfInput.value);
                if (cpfDigits.length !== 11){
                    toggleLoading(false);
                    return showAlert('CPF inválido: informe 11 dígitos.', 'danger');
                }

                try {
                    // 1ª etapa: só CPF
                    if (!awaitPassword){
                        const res = await fetch('/api/v1/auth', {
                            method: 'POST',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json'
                            },
                            body: JSON.stringify({ cpf: cpfDigits })
                        });
                        const data = await res.json().catch(()=>({}));

                        if (!res.ok) {
                            const msg = data.error || data.message || 'Falha na validação do CPF.';
                            showAlert(msg, 'danger');
                        } else {
                            if (data.needs_completion === true) {
                                saveTokenFromResponse(data);
                                return location.href = '/complete-profile';
                            }

                            if (data.password_required === true) {
                                showPasswordBlock(true);
                                showAlert('Digite sua senha para continuar.', 'info');
                            }

                            else {
                                saveTokenFromResponse(data);
                                if (data.access_token) {
                                    return location.href = '/events';
                                }
                                showAlert(data.message || 'Não foi possível continuar.', 'danger');
                            }
                        }
                    }

                    // 2ª etapa: CPF + senha
                    else {
                        const password = String(pwdInput.value || '');
                        if (password.length < 3) {
                            toggleLoading(false);
                            return showAlert('Informe sua senha (mínimo 3 caracteres).', 'danger');
                        }

                        const res = await fetch('/api/v1/auth', {
                            method: 'POST',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json'
                            },
                            body: JSON.stringify({ cpf: cpfDigits, password })
                        });
                        const data = await res.json().catch(()=>({}));

                        if (res.status === 401) {
                            showAlert('CPF/senha inválidos.', 'danger');
                        } else if (res.ok) {
                            saveTokenFromResponse(data);

                            if (data.needs_completion === true) {
                                return location.href = '/complete-profile';
                            }

                            if (data.access_token) {
                                return location.href = '/events';
                            }

                            showAlert(data.message || 'Falha ao autenticar.', 'danger');
                        } else {
                            showAlert(data.message || 'Falha ao autenticar.', 'danger');
                        }
                    }
                } catch (err) {
                    console.error(err);
                    showAlert('Erro de rede. Tente novamente.', 'danger');
                } finally {
                    toggleLoading(false);
                }
            });
        })();
    </script>
@endsection
