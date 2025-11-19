<!-- resources/views/events/_form.blade.php -->
@php $editing = isset($eventId); @endphp

<div class="row">
    <div class="grow">
        <label>Título</label>
        <input type="text" name="title" required>
        <div class="muted" data-error="title"></div>
    </div>
</div>

<div style="margin-top:8px">
    <label>Descrição</label>
    <textarea name="description" rows="4"></textarea>
    <div class="muted" data-error="description"></div>
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label>Local</label>
        <input type="text" name="location">
        <div class="muted" data-error="location"></div>
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label>Início</label>
        <input type="datetime-local" name="start_at">
        <div class="muted" data-error="start_at"></div>
    </div>
    <div class="grow">
        <label>Término</label>
        <input type="datetime-local" name="end_at">
        <div class="muted" data-error="end_at"></div>
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label><input type="checkbox" name="is_all_day" value="1"> Evento do dia todo</label>
    </div>
    <div class="grow">
        <label><input type="checkbox" name="is_public" value="1" checked> Evento público</label>
    </div>
</div>

<div style="margin-top:12px">
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Salvar' : 'Criar' }}</button>
    <a class="btn btn-outline" href="{{ route('events.index') }}">Cancelar</a>
</div>
