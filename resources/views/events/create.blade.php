<!-- resources/views/events/create.blade.php -->
@extends('layouts.app')
@section('title','Novo evento')
@section('h1','Novo evento')
@section('content')
    <form id="frm" onsubmit="return false">
        @include('events._form')
    </form>
@endsection
@section('scripts')
    <script>
        (function(){
            const form = document.getElementById('frm');
            form.addEventListener('submit', async (e)=>{
                e.preventDefault();
                clearErrors();
                const payload = formToPayload(form);
                try{
                    const res = await api('/events', { method:'POST', body: payload });
                    toast('Evento criado!');
                    location.href = "{{ route('events.index') }}";
                }catch(err){
                    handleError(err);
                }
            });

            function clearErrors(){ document.querySelectorAll('[data-error]').forEach(n=>n.textContent=''); }
            function handleError(err){
                // se backend enviar bag de errors (Laravel Validation), exiba
                try{
                    const raw = err.message;
                    // sem parsing sofisticado: apenas alerta
                    toast(raw, false);
                }catch{ toast('Erro ao salvar.', false); }
            }
        })();
    </script>
@endsection
