@extends('layouts.app')

@section('title','Digite sua senha')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">

            <div class="card">
                <div class="card-header">Autenticação</div>
                <div class="card-body">

                    <div id="alert" class="alert d-none"></div>

                    @php
                        $prefillCpf = request()->get('cpf');
                    @endphp

                    <form id="pwd-form">
                        <input type="hidden" name="cpf" value="{{ $prefillCpf }}">

                        <div class="mb-3">
                            <label class="form-label">Senha</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button class="btn btn-primary w-100">Entrar</button>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <script>
        (function(){
            const alertBox = document.getElementById('alert');

            function show(msg, type='warning'){
                alertBox.className = 'alert alert-'+type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hide(){ alertBox.classList.add('d-none'); }

            const form = document.getElementById('pwd-form');

            form.addEventListener('submit', async (e)=>{
                e.preventDefault(); hide();

                const cpf = form.cpf.value;
                const password = form.password.value;

                const res = await fetch('/api/v1/auth', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json'},
                    body: JSON.stringify({ cpf, password })
                });

                const data = await res.json().catch(()=>({}));

                if (res.status === 401) {
                    return show('Senha incorreta.', 'danger');
                }

                if (data.access_token) {
                    localStorage.setItem('token', data.access_token);
                    return location.href = '/events';
                }

                show(data.message || 'Falha ao autenticar.', 'danger');
            });
        })();
    </script>
@endsection
