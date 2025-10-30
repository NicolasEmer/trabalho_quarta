@extends('layouts.app')

@section('title','Eventos')

@section('content')
    <form method="GET" action="{{ route('events.index') }}" class="row" style="margin-bottom:12px">
        <div class="grow">
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por título ou local...">
        </div>
        <div>
            <button class="btn btn-outline" type="submit">Buscar</button>
        </div>
    </form>

    @if ($events->count() === 0)
        <p class="muted">Nenhum evento encontrado.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Título</th>
                <th class="right">Quando</th>
                <th>Local</th>
                <th>Público</th>
                <th class="right">Ações</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($events as $event)
                <tr>
                    <td>
                        <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                        @if($event->is_all_day)
                            <span class="badge">Dia todo</span>
                        @endif
                    </td>
                    <td class="right">
                        {{ $event->start_at?->format('d/m/Y H:i') }}
                        @if($event->end_at) — {{ $event->end_at->format('d/m/Y H:i') }} @endif
                    </td>
                    <td>{{ $event->location ?? '—' }}</td>
                    <td>{{ $event->is_public ? 'Sim' : 'Não' }}</td>
                    <td class="right actions">
                        <a class="btn btn-outline" href="{{ route('events.edit', $event) }}">Editar</a>
                        <form class="inline" method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Excluir este evento?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:14px">
            {{ $events->links() }}
        </div>
    @endif
@endsection
