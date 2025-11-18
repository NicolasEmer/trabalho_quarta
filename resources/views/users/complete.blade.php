@extends('layouts.app')

@section('title','Completar cadastro')

@section('head')
    <style>
        .page-card { padding: 18px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; }
        .page-title { margin:0 0 10px 0; font-weight:600; }
        .form-grid {
            display:grid; grid-template-columns: repeat(2, minmax(0,1fr));
            gap:14px;
        }
        @media (max-width: 720px){ .form-grid { grid-template-columns: 1fr; } }
        .field label { font-weight:600; display:block; margin-bottom:6px; }
        .help { color:#6b7280; font-size:.85rem; margin-top:4px }
        .actions { display:flex; gap:8px; margin-top:14px }
        .is-invalid { border-color:#dc2626 !important; outline-color:#dc2626 !important; }
    </style>
@endsection

@section('content')
    <div class="page-card">
        <h2 class="page-title">Dados da Conta</h2>

        <div id="alert" class="alert d-none"></div>

        <form id="complete-form" autocomplete="off" novalidate>
            <div class="form-grid">
                <div class="field">
                    <label for="name">Nome</label>
                    <input id="name" name="name" class="form-control" placeholder="Seu nome" required>
                    <div class="help">Usado no certificado</div>
                </div>

                <div class="field">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" class="form-control" type="email" placeholder="seu@email.com" required>
                    <div class="help">Receberá confirmações e certificados</div>
                </div>

                <div class="field">
                    <label for="phone">Celular</label>
                    <input id="phone" name="phone" class="form-control" inputmode="tel" placeholder="+55 51 99999-8888">
                    <div class="help">Formato BR: 51999998888, 51 99999-8888 ou +55 51 99999-8888</div>
                </div>

                <div class="field">
                    <label for="cpf-mask">CPF</label>
                    <input id="cpf-mask" class="form-control" disabled>
                    <div class="help">Vinculado à sua conta</div>
                </div>

                <div class="field">
                    <label for="password">Senha</label>
                    <input id="password" name="password" class="form-control" type="password" minlength="6" placeholder="mín. 6, com letras e números">
                    <div class="help" id="pwdHelp">Mínimo 6 caracteres, com letras e números</div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar senha</label>
                    <input id="password_confirmation" name="password_confirmation" class="form-control" type="password" minlength="6" placeholder="repita a senha">
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-success" id="submit-btn">
                    <span class="btn-text">Salvar</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
                <a class="btn btn-outline" href="{{ route('events.index') }}">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        const alertBox = document.getElementById('alert');

        function showAlert(msg, type='warning') {
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = msg;
            alertBox.classList.remove('d-none');
        }

        function hideAlert() {
            alertBox.classList.add('d-none');
        }

        function maskCpf(digits) {
            const only = String(digits || '').replace(/\D+/g,'');
            if (only.length !== 11) return digits || '';
            return `${only.slice(0,3)}.${only.slice(3,6)}.${only.slice(6,9)}-${only.slice(9,11)}`;
        }

        function formatPhone(val){
            if (!val) return '';
            const keepPlus = val.trim().startsWith('+');
            let d = val.replace(/\D+/g,'');
            if (keepPlus && !d.startsWith('55')) d = '55' + d;

            if (d.length >= 12) {
                return (keepPlus?'+':'') + d.replace(
                    /^(\d{2})(\d{2})(\d{5})(\d{0,4}).*$/,
                    (_,ddi,ddd,p1,p2)=>`${ddi} ${ddd} ${p1}${p2?('-'+p2):''}`
                );
            }
            if (d.length >= 11) {
                return d.replace(
                    /^(\d{2})(\d{5})(\d{0,4}).*$/,
                    (_,ddd,p1,p2)=>`${ddd} ${p1}${p2?('-'+p2):''}`
                );
            }
            if (d.length >= 10) {
                return d.replace(
                    /^(\d{2})(\d{4})(\d{0,4}).*$/,
                    (_,ddd,p1,p2)=>`${ddd} ${p1}${p2?('-'+p2):''}`
                );
            }
            return val;
        }

        function normalizePhone(val){
            if (!val) return null;
            const keepPlus = val.trim().startsWith('+');
            const digits = val.replace(/\D+/g,'');
            return keepPlus ? ('+' + digits) : digits;
        }

        function isValidBrMobile(phone){
            if (!phone) return true;
            const p = phone.replace(/\s+/g,'');
            const re = /^(\+?55)?\s?\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/;      // com separadores
            const rePlain = /^(\+?55)?\d{10,11}$/;                      // só dígitos (com/sem 9)
            return re.test(p) || rePlain.test(p.replace(/[()\-]/g,''));
        }

        function isStrongPassword(pwd){
            if (!pwd || pwd.length < 6) return false;
            return /[A-Za-z]/.test(pwd) && /\d/.test(pwd);
        }

        (function(){
            const token = localStorage.getItem('token');
            if (!token) {
                showAlert('Você precisa estar autenticado. Faça login.', 'danger');
                setTimeout(()=> location.href = '/login', 900);
                return;
            }

            const form      = document.getElementById('complete-form');
            const btn       = document.getElementById('submit-btn');
            const btnTxt    = btn.querySelector('.btn-text');
            const spn       = btn.querySelector('.spinner-border');

            const $name     = document.getElementById('name');
            const $email    = document.getElementById('email');
            const $phone    = document.getElementById('phone');
            const $cpf      = document.getElementById('cpf-mask');
            const $pwd      = document.getElementById('password');
            const $pwdc     = document.getElementById('password_confirmation');
            const $pwdHelp  = document.getElementById('pwdHelp');

            let currentUserId    = null;
            let alreadyCompleted = false;

            // máscara de telefone em tempo real
            $phone.addEventListener('input', () => {
                $phone.value = formatPhone($phone.value);
            });

            // Carrega dados do usuário logado
            fetch('/api/v1/me', {
                headers: {
                    'Authorization':'Bearer ' + token,
                    'Accept':'application/json'
                }
            })
                .then(async (res) => {
                    if (res.status === 401) {
                        throw new Error('Sessão expirada. Faça login novamente.');
                    }
                    const data = await res.json();

                    currentUserId    = data.id;
                    alreadyCompleted = !!data.completed;

                    $name.value  = data.name  ?? '';
                    $email.value = data.email ?? '';
                    $phone.value = data.phone ? formatPhone(String(data.phone)) : '';
                    $cpf.value   = maskCpf(data.cpf);

                    if (alreadyCompleted){
                        $pwd.required  = false;
                        $pwdc.required = false;
                        $pwd.placeholder  = 'Nova senha';
                        $pwdc.placeholder = 'Repita a nova senha';
                        $pwdHelp.textContent = 'Use letras e números.';
                        showAlert('Seu cadastro já está completo. Você pode apenas atualizar dados ou alterar a senha.', 'info');
                    } else {
                        $pwd.required  = true;
                        $pwdc.required = true;
                    }
                })
                .catch(err => {
                    showAlert(err.message || 'Falha ao carregar seus dados.', 'danger');
                });


            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                hideAlert();

                [$name,$email,$phone,$pwd,$pwdc].forEach(el => el.classList.remove('is-invalid'));

                if (!currentUserId) {
                    showAlert('Não foi possível identificar o usuário.', 'danger');
                    return;
                }

                const phoneRaw = $phone.value.trim();
                const pwd      = $pwd.value;
                const pwdc     = $pwdc.value;

                if (phoneRaw && !isValidBrMobile(phoneRaw)){
                    $phone.classList.add('is-invalid');
                    return showAlert(
                        'Telefone inválido. Use um celular BR (ex.: 51999998888 ou +55 51 99999-8888).',
                        'danger'
                    );
                }


                if (!alreadyCompleted || pwd || pwdc){
                    if (!isStrongPassword(pwd)){
                        $pwd.classList.add('is-invalid');
                        return showAlert('Senha fraca. Use no mínimo 6 caracteres com letras e números.', 'danger');
                    }
                    if (pwd !== pwdc){
                        $pwdc.classList.add('is-invalid');
                        return showAlert('As senhas não conferem.', 'danger');
                    }
                }

                btn.disabled = true;
                spn.classList.remove('d-none');
                btnTxt.textContent = 'Salvando...';

                const payload = {
                    name:      $name.value || null,
                    email:     $email.value || null,
                    phone:     normalizePhone(phoneRaw),
                    completed: true
                };


                if (pwd) {
                    payload.password = pwd;
                    payload.password_confirmation = pwdc;
                }

                try {
                    const res = await fetch('/api/v1/users/' + currentUserId, {
                        method: 'PUT',
                        headers: {
                            'Authorization':'Bearer ' + token,
                            'Content-Type':'application/json',
                            'Accept':'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        showAlert('Cadastro salvo com sucesso!', 'success');
                        setTimeout(() => location.href = '/events', 800);
                    } else if (res.status === 422) {
                        const flat = Object.values(data.errors || {})
                            .flat()
                            .join(' | ') || data.message || 'Dados inválidos.';
                        showAlert(flat, 'danger');
                    } else if (res.status === 401) {
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                        setTimeout(() => location.href = '/login', 800);
                    } else {
                        showAlert(data.message || 'Erro ao salvar.', 'danger');
                    }
                } catch (_) {
                    showAlert('Erro de rede ao salvar.', 'danger');
                } finally {
                    btn.disabled = false;
                    spn.classList.add('d-none');
                    btnTxt.textContent = 'Salvar';
                }
            });
        })();
    </script>
@endsection
