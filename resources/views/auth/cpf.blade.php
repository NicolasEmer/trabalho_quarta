@extends('layouts.app')

@section('title','Entrar com CPF')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-5">

            <div class="card">
                <div class="card-header">Entrar / Cadastrar por CPF</div>
                <div class="card-body">

                    <div id="alert" class="alert d-none"></div>

                    <form id="cpf-form" autocomplete="off">
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

    <script>
        (function(){
            const alertBox = document.getElementById('alert');
            function show(msg, type='warning'){
                alertBox.className = 'alert alert-'+type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hide(){ alertBox.classList.add('d-none'); }

            const form = document.getElementById('cpf-form');
            form.addEventListener('submit', async (e)=>{
                e.preventDefault(); hide();

                const rawCpf = form.cpf.value.replace(/\D+/g, '');

                const res = await fetch('/api/v1/auth', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json','Accept':'application/json' },
                    body: JSON.stringify({ cpf: rawCpf })
                });

                const data = await res.json().catch(()=>({}));

                if (data.password_required === true) {
                    return location.href = '/auth/password?cpf=' + rawCpf;
                }

                if (data.needs_completion === true) {
                    localStorage.setItem('token', data.access_token);
                    return location.href = '/complete-profile';
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
