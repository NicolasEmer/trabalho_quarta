@extends('layouts.app')
@section('title','Editar evento')
@section('h1','Editar evento')

@section('content')
    <div id="alert" class="alert d-none"></div>

    <form id="frm" onsubmit="return false">
        @include('events._form', ['eventId' => $id ?? null])
    </form>
@endsection

@section('scripts')
    <script>
        (function(){
            const id       = @json($id);
            const form     = document.getElementById('frm');
            const alertBox = document.getElementById('alert');

            function showAlert(msg, type='success'){
                alertBox.className = 'alert alert-'+type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hideAlert(){ alertBox.classList.add('d-none'); }

            function setValue(name, val){
                const el = form.querySelector(`[name="${name}"]`);
                if (el) el.value = val ?? '';
            }

            function formToPayload(f){
                const fd = new FormData(f);
                return {
                    title:       fd.get('title') || null,
                    description: fd.get('description') || null,
                    start_at:    fd.get('start_at') || null,
                    end_at:      fd.get('end_at') || null,
                    location:    fd.get('location') || null,
                    is_public:   fd.get('is_public') || null,
                    is_all_day:  fd.get('is_all_day') || null,
                };
            }

            async function loadEvent(){
                hideAlert();
                try{
                    const res  = await fetch(`/api/v1/events/${id}`, { headers: { 'Accept':'application/json' } });
                    const json = await res.json().catch(()=> ({}));
                    if(!res.ok){
                        return showAlert(json.message || 'Falha ao carregar evento.', 'danger');
                    }
                    const e = json.data || json; // compatível com seu resource
                    setValue('title',       e.title);
                    setValue('description', e.description);
                    setValue('location',    e.location);
                    setValue('start_at',    e.start_at ? e.start_at.substring(0,16) : ''); // ISO -> yyyy-MM-ddTHH:mm
                    setValue('end_at',      e.end_at ? e.end_at.substring(0,16) : '');
                }catch(_){
                    showAlert('Erro de rede ao carregar.', 'danger');
                }
            }

            form.addEventListener('submit', async (ev)=>{
                ev.preventDefault();
                hideAlert();

                const token = localStorage.getItem('token');
                if(!token) return showAlert('Sessão expirada. Faça login.', 'danger');

                const payload = formToPayload(form);

                try{
                    const res = await fetch(`/api/v1/events/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(()=> ({}));

                    if (res.ok){
                        showAlert('Evento atualizado!', 'success');
                        return setTimeout(()=> location.href = "{{ route('events.index') }}", 600);
                    }
                    if (res.status === 422){
                        const errs = data.errors || {};
                        document.querySelectorAll('[data-error]').forEach(n => n.textContent = '');
                        Object.entries(errs).forEach(([field, messages])=>{
                            const holder = document.querySelector(`[data-error="${field}"]`);
                            if (holder) holder.textContent = messages.join(' | ');
                        });
                        const flat = Object.values(errs).flat().join(' | ') || data.message || 'Dados inválidos.';
                        return showAlert(flat, 'danger');
                    }
                    if (res.status === 401){
                        return showAlert('Sessão expirada. Faça login novamente.', 'danger');
                    }
                    showAlert(data.message || 'Falha ao atualizar.', 'danger');

                }catch(_){
                    showAlert('Erro de rede ao salvar.', 'danger');
                }
            });

            loadEvent();
        })();
    </script>
@endsection
