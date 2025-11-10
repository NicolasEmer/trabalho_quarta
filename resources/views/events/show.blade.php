@extends('layouts.app')
@section('title','Detalhes do evento')
@section('h1','Detalhes do evento')

@section('content')
    <div id="alert" class="alert d-none"></div>

    <div id="card" aria-live="polite">
        <p class="muted">Carregando…</p>
    </div>

    <div class="mt-3">
        <a id="btnEdit" class="btn">Editar</a>
        <button id="btnDel" class="btn btn-danger">Excluir</button>
        <a class="btn btn-outline" href="{{ route('events.index') }}">Voltar</a>
    </div>
@endsection

@section('scripts')
    <script>
        (function(){
            function $(sel, ctx=document){ return ctx.querySelector(sel); }
            function showAlert(msg, ok=true){
                const box = $('#alert');
                box.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
                box.textContent = msg;
                box.classList.remove('d-none');
                if (ok) setTimeout(()=> box.classList.add('d-none'), 2000);
            }
            function fmtDateTimeLocal(str){
                if (!str) return '';
                return String(str).replace('Z','').slice(0,16); // yyyy-MM-ddTHH:mm
            }

            const id      = {{ (int) request()->route('id') }};
            const $card   = $('#card');
            const $btnEdit= $('#btnEdit');
            const $btnDel = $('#btnDel');

            async function loadEvent(){
                try{
                    const res  = await fetch(`/api/v1/events/${id}`, { headers: { 'Accept':'application/json' } });
                    const json = await res.json().catch(()=> ({}));
                    if (!res.ok){
                        $card.innerHTML = `<p class="muted">Erro ao carregar: ${json.message || res.status}</p>`;
                        return;
                    }

                    const e = json.data || json;
                    const start = e.start_at ? fmtDateTimeLocal(e.start_at).replace('T',' ') : '-';
                    const end   = e.end_at   ? fmtDateTimeLocal(e.end_at).replace('T',' ')   : '-';
                    const isPublic = !!e.is_public;
                    const isAllDay = !!e.is_all_day;

                    $card.innerHTML = `
        <h2 class="mb-1">${e.title ?? '-'}</h2>
        <div class="muted">${isPublic ? 'Público' : 'Privado'} · ${isAllDay ? 'Dia inteiro' : 'Com horário'}</div>
        <p class="mt-2 mb-1"><strong>Início:</strong> ${start}</p>
        <p class="m-0"><strong>Término:</strong> ${end}</p>
        <p class="mt-2 mb-1"><strong>Local:</strong> ${e.location ?? '-'}</p>
        <p class="mt-2" style="white-space:pre-wrap">${e.description ?? ''}</p>
      `;

                    $btnEdit.href = `{{ url('/events') }}/${id}/edit`;
                }catch(_){
                    $card.innerHTML = `<p class="muted">Erro de rede ao carregar.</p>`;
                }
            }


            $btnDel.addEventListener('click', async ()=>{
                if (!confirm('Excluir este evento?')) return;

                const token = localStorage.getItem('token');
                if (!token){ showAlert('Sessão expirada. Faça login novamente.', false); return; }

                try{
                    const res  = await fetch(`/api/v1/events/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json().catch(()=> ({}));

                    if (res.ok){
                        showAlert('Evento excluído.');
                        setTimeout(()=> location.href = "{{ route('events.index') }}", 600);
                    } else if (res.status === 401){
                        showAlert('Sessão expirada. Faça login novamente.', false);
                    } else {
                        showAlert(json.message || 'Erro ao excluir.', false);
                    }
                }catch(_){
                    showAlert('Erro de rede ao excluir.', false);
                }
            });

            loadEvent();
        })();
    </script>
@endsection
