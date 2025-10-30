<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
// Lista com busca e paginação
public function index(Request $request)
{
$q = $request->input('q');

$events = Event::when($q, function($query) use ($q) {
$like = "%{$q}%";
$query->where(function($q2) use ($like) {
$q2->where('title', 'like', $like)
->orWhere('location', 'like', $like);
});
})
->orderBy('start_at', 'desc')
->paginate(10)
->withQueryString();

return view('events.index', compact('events','q'));
}

public function create()
{
return view('events.create');
}

public function store(Request $request)
{
$data = $this->validateData($request);
Event::create($data);

return redirect()
->route('events.index')
->with('success', 'Evento criado com sucesso!');
}

public function show(Event $event)
{
return view('events.show', compact('event'));
}

public function edit(Event $event)
{
return view('events.edit', compact('event'));
}

public function update(Request $request, Event $event)
{
$data = $this->validateData($request, $event->id);
$event->update($data);

return redirect()
->route('events.index')
->with('success', 'Evento atualizado com sucesso!');
}

public function destroy(Event $event)
{
$event->delete();

return redirect()
->route('events.index')
->with('success', 'Evento excluído com sucesso!');
}

private function validateData(Request $request, ?int $id = null): array
{
$data = $request->validate([
'title'       => ['required','string','max:255'],
'description' => ['nullable','string'],
'location'    => ['nullable','string','max:255'],
'start_at'    => ['required','date'],
'end_at'      => ['nullable','date','after_or_equal:start_at'],
'is_all_day'  => ['sometimes','boolean'],
'is_public'   => ['sometimes','boolean'],
]);

// Normaliza checkboxes
$data['is_all_day'] = (bool) ($request->boolean('is_all_day'));
$data['is_public']  = (bool) ($request->boolean('is_public'));

return $data;
}
}
