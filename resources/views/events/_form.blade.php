@php
    $editing = isset($event);
@endphp

<div class="row">
    <div class="grow">
        <label>Título</label>
        <input type="text" name="title" value="{{ old('title', $event->title ?? '') }}" required>
        @error('title') <div class="muted">⚠ {{ $message }}</div> @enderror
    </div>
</div>

<div style="margin-top:8px">
    <label>Descrição</label>
    <textarea name="description" rows="4">{{ old('description', $event->description ?? '') }}</textarea>
    @error('description') <div class="muted">⚠ {{ $message }}</div> @enderror
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label>Local</label>
        <input type="text" name="location" value="{{ old('location', $event->location ?? '') }}">
        @error('location') <div class="muted">⚠ {{ $message }}</div> @enderror
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label>Início</label>
        <input type="datetime-local" name="start_at"
               value="{{ old('start_at', isset($event)? $event->start_at?->format('Y-m-d\TH:i') : '') }}" required>
        @error('start_at') <div class="muted">⚠ {{ $message }}</div> @enderror
    </div>
    <div class="grow">
        <label>Término (opcional)</label>
        <input type="datetime-local" name="end_at"
               value="{{ old('end_at', isset($event) && $event->end_at ? $event->end_at->format('Y-m-d\TH:i') : '') }}">
        @error('end_at') <div class="muted">⚠ {{ $message }}</div> @enderror
    </div>
</div>

<div class="row" style="margin-top:8px">
    <div class="grow">
        <label><input type="checkbox" name="is_all_day" value="1" {{ old('is_all_day', $event->is_all_day ?? false) ? 'checked' : '' }}> Evento do dia todo</label>
    </div>
    <div class="grow">
        <label><input type="checkbox" name="is_public" value="1" {{ old('is_public', ($event->is_public ?? true)) ? 'checked' : '' }}> Evento público</label>
    </div>
</div>

<div style="margin-top:12px">
    <button class="btn btn-primary" type="submit">{{ $editing ? 'Salvar' : 'Criar' }}</button>
    <a class="btn btn-outline" href="{{ route('events.index') }}">Cancelar</a>
</div>
