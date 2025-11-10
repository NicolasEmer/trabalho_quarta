@extends('layouts.app')
@section('title','Novo evento')
@section('h1','Novo evento')

@section('content')
    <div id="alert" class="alert d-none"></div>

    <form id="frm" onsubmit="return false">
        @include('events._form')
    </form>
@endsection

@section('scripts')
    <script>
        (function(){
            const form = document.getElementById('frm');
            const alertBox = document.getElementById('alert');

            function showAlert(msg, type='success'){
                alertBox.className = 'alert alert-'+type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hideAlert(){ alertBox.classList.add('d-none'); }

            function formToPayload(f){
                const fd = new FormData(f);
                return {
                    title:       fd.get('title') || null,
                    description: fd.get('description') || null,
                    start_at:    fd.get('start_at') || null,
                    end_at:      fd.get('end_at') || null,
                    location:    fd.get('location') || null,
                };
            }

            form.addEventListener('submit', async (e)=>{
                e.preventDefault();
                hideAlert();

                const token = localStorage.getItem('token');
                if (!token){
                    return showAlert('Você precisa estar autenticado para criar eventos.', 'danger');
                }

                const payload = formToPayload(form);

                try{
                    const res = await fetch('/api/v1/events', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json().catch(()=> ({}));

                    if (res.ok) {
                        showAlert('Evento criado com sucesso!', 'success');
                        setTimeout(()=> location.href = "{{ route('events.index') }}", 600);
                        return;
                    }


                    if (res.status === 422) {
                        const errs = data.errors || {};
                        document.querySelectorAll('[data-error]').forEach(n => n.textContent = '');
                        Object.entries(errs).forEach(([field, messages])=>{
                            const holder = document.querySelector(`[data-error="${field}"]`);
                            if (holder) holder.textContent = messages.join(' | ');
                        });
                        const flat = Object.values(errs).flat().join(' | ') || data.message || 'Dados inválidos.';
                        return showAlert(flat, 'danger');
                    }

                    if (res.status === 401) {
                        return showAlert('Sessão expirada. Faça login novamente.', 'danger');
                    }

                    showAlert(data.message || 'Falha ao criar evento.', 'danger');

                }catch(err){
                    showAlert('Erro de rede. Tente novamente.', 'danger');
                }
            });
        })();
    </script>
@endsection
