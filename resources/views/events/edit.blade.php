<!-- resources/views/events/edit.blade.php -->
@extends('layouts.app')
@section('title','Editar evento')
@section('h1','Editar evento')
@section('content')
    @php $eventId = request()->route('id'); @endphp
    <form id="frm" onsubmit="return false">
        @include('events._form', ['eventId' => request()->route('id')])
    </form>
@endsection
@section('scripts')
    <script>
        (async function(){
            const id = {{ (int) request()->route('id') }};
            const form = document.getElementById('frm');
            const qs = sel => form.querySelector(sel);

            // carrega dados
            try{
                const { data } = await api(`/events/${id}`);
                // preenche
                qs('[name="title"]').value = data.title ?? '';
                qs('[name="description"]').value = data.description ?? '';
                qs('[name="location"]').value = data.location ?? '';
                qs('[name="start_at"]').value = fmtDateTimeLocal(data.start_at);
                qs('[name="end_at"]').value   = data.end_at ? fmtDateTimeLocal(data.end_at) : '';
                qs('[name="is_all_day"]').checked = !!data.is_all_day;
                qs('[name="is_public"]').checked  = !!data.is_public;
            }catch(e){
                toast('Não foi possível carregar o evento.', false);
            }

            form.addEventListener('submit', async (e)=>{
                e.preventDefault();
                const payload = formToPayload(form);
                try{
                    const res = await api(`/events/${id}`, { method:'PUT', body: payload });
                    toast('Evento atualizado!');
                    location.href = "{{ route('events.index') }}";
                }catch(err){
                    toast(err.message, false);
                }
            });
        })();
    </script>
@endsection
