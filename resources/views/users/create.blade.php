@extends('layouts.app')

@section('title','Novo usuário')

@section('content')
    <style>
        .hidden { display: none !important; }
    </style>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Novo usuário</span>
                    <small class="text-muted">CPF-first • Simplificado ou Completo</small>
                </div>

                <div class="card-body">
                    <div id="alert" class="alert hidden"></div>

                    <form id="form-create" autocomplete="off">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">CPF</label>
                            <input name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00" required>
                            <small class="text-muted">Para cadastro simplificado basta informar o CPF e salvar.</small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="completed" name="completed" value="1" aria-controls="advanced-fields" aria-expanded="false">
                            <label class="form-check-label" for="completed">Cadastrar com dados completos agora</label>
                        </div>

                        <div id="advanced-fields" class="border rounded p-3 mb-3 hidden" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome</label>
                                    <input name="name" class="form-control" placeholder="Nome completo">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-mail</label>
                                    <input name="email" class="form-control" type="email" placeholder="ex: fulano@dominio.com">
                                    <small class="text-muted">Validamos formato e DNS do domínio.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefone</label>
                                    <input name="phone" class="form-control" placeholder="+5551999999999 ou 51999999999">
                                    <small class="text-muted">Aceita 10–11 dígitos, com ou sem +55.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Senha</label>
                                    <input name="password" class="form-control" type="password" placeholder="mín. 6, letras e números">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar senha</label>
                                    <input name="password_confirmation" class="form-control" type="password">
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-success" id="btn-save" type="submit">
                            <span class="btn-text">Salvar</span>
                            <span class="spinner-border spinner-border-sm hidden" role="status" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const form      = document.getElementById('form-create');
            const alertBox  = document.getElementById('alert');
            const completed = document.getElementById('completed');
            const advanced  = document.getElementById('advanced-fields');
            const btn       = document.getElementById('btn-save');
            const btnText   = btn.querySelector('.btn-text');
            const spinner   = btn.querySelector('.spinner-border');
            const cpfInput  = document.getElementById('cpf');

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
                btnText.textContent = on ? 'Salvando...' : 'Salvar';
            }

            completed.addEventListener('change', () => {
                const show = completed.checked;
                completed.setAttribute('aria-expanded', show ? 'true' : 'false');
                advanced.style.display = show ? 'block' : 'none';
                advanced.classList.toggle('hidden', !show);
            });

            cpfInput.addEventListener('input', (e) => {
                const raw = onlyDigits(e.target.value);
                e.target.value = maskCpf(raw);
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                hideAlert();
                toggleLoading(true);

                const rawCpf = onlyDigits(form.cpf.value);
                if (rawCpf.length !== 11){
                    toggleLoading(false);
                    return showAlert('Informe um CPF válido (11 dígitos).', 'danger');
                }

                try {
                    if (!completed.checked) {
                        const res = await fetch('/api/v1/auth', {
                            method: 'POST',
                            headers: {'Content-Type':'application/json','Accept':'application/json'},
                            body: JSON.stringify({ cpf: rawCpf })
                        });
                        const data = await res.json().catch(()=>({}));

                        if (!res.ok) {
                            const msg = data.error || data.message || 'Falha ao cadastrar/login.';
                            showAlert(msg, 'danger');
                        } else {
                            if (data.access_token) localStorage.setItem('token', data.access_token);
                            showAlert('Cadastro simplificado criado. Redirecionando...', 'success');
                            setTimeout(()=> location.href = '/events', 800);
                        }
                    } else {
                        const token = localStorage.getItem('token');
                        if (!token){
                            toggleLoading(false);
                            return showAlert('Faça login para cadastrar usuário completo.', 'danger');
                        }

                        const payload = {
                            cpf: rawCpf,
                            name: form.name.value || null,
                            email: form.email.value || null,
                            phone: form.phone.value || null,
                            password: form.password.value || null,
                            password_confirmation: form.password_confirmation.value || null,
                            completed: 1
                        };

                        const res = await fetch('/api/v1/users', {
                            method: 'POST',
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json().catch(()=>({}));

                        if (res.ok){
                            showAlert('Usuário completo cadastrado com sucesso!', 'success');
                            setTimeout(()=> location.href = '/events', 800);
                        } else {
                            const errs = data.errors ? Object.values(data.errors).flat().join(' | ') : data.message || 'Erro ao salvar.';
                            showAlert(errs, 'danger');
                        }
                    }
                } catch (err) {
                    showAlert('Erro de rede. Tente novamente.', 'danger');
                } finally {
                    toggleLoading(false);
                }
            });
        })();
    </script>
@endsection
