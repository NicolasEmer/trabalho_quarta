@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <div class="row" style="margin-bottom:12px">
        <div class="grow">
            <div class="muted">Quando</div>
            <div>
                {{ $event->start_at->format('d/m/Y H:i') }}
                @if($event->end_at) — {{ $event->end_at->format('d/m/Y H:i') }} @endif
                @if($event->is_all_day) <span class="badge">Dia todo</span> @endif
            </div>
        </div>
        <div style="min-width:220px">
            <div class="muted">Local</div>
            <div>{{ $event->location ?? '—' }}</div>
        </div>
    </div>

    <div style="margin-bottom:12px">
        <div class="muted">Descrição</div>
        <div>{{ $event->description ?? '—' }}</div>
    </div>

    <div class="actions">
        <a class="btn btn-outline" href="{{ route('events.edit', $event) }}">Editar</a>
        <form class="inline" method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Excluir este evento?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger" type="submit">Excluir</button>
        </form>
        <a class="btn" href="{{ route('events.index') }}">Voltar</a>
    </div>
@endsection
