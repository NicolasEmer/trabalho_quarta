<!-- resources/views/events/show.blade.php -->
@extends('layouts.app')
@section('title','Detalhes do evento')
@section('h1','Detalhes do evento')
@section('content')
    <div id="card" aria-live="polite">
        <p class="muted">Carregando…</p>
    </div>
    <div style="margin-top:12px">
        <a id="btnEdit" class="btn">Editar</a>
        <button id="btnDel" class="btn btn-danger">Excluir</button>
        <a class="btn btn-outline" href="{{ route('events.index') }}">Voltar</a>
    </div>
@endsection
@section('scripts')
    <script>
        (async function(){
            const id = {{ (int) request()->route('id') }};
            const $card = document.getElementById('card');
            const $btnEdit = document.getElementById('btnEdit');
            const $btnDel = document.getElementById('btnDel');

            try{
                const { data } = await api(`/events/${id}`);
                $card.innerHTML = `
      <h2 style="margin:0 0 6px 0">${data.title}</h2>
      <div class="muted">${data.is_public ? 'Público' : 'Privado'} · ${data.is_all_day ? 'Dia inteiro' : 'Com horário'}</div>
      <p style="margin:8px 0 0"><strong>Início:</strong> ${data.start_at ? fmtDateTimeLocal(data.start_at).replace('T',' ') : '-'}</p>
      <p style="margin:0"><strong>Término:</strong> ${data.end_at ? fmtDateTimeLocal(data.end_at).replace('T',' ') : '-'}</p>
      <p style="margin:8px 0 0"><strong>Local:</strong> ${data.location ?? '-'}</p>
      <p style="margin:8px 0 0; white-space:pre-wrap">${data.description ?? ''}</p>
    `;
                $btnEdit.href = `{{ url('/events') }}/${id}/edit`;
            }catch(e){
                $card.innerHTML = `<p class="muted">Erro ao carregar.</p>`;
            }

            $btnDel.onclick = async ()=>{
                if(!confirm('Excluir este evento?')) return;
                try{
                    await api(`/events/${id}`, { method:'DELETE' });
                    toast('Evento excluído.');
                    location.href = "{{ route('events.index') }}";
                }catch(e){ toast(e.message, false); }
            };
        })();
    </script>
@endsection
