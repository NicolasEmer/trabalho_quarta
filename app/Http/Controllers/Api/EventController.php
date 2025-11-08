<?php

// app/Http/Controllers/Api/EventController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    /**
     * GET /api/v1/events?q=...&page=1&per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string)$request->query('q'));
        $perPage = (int)$request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $events = Event::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($q2) use ($like) {
                    $q2->where('title', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderByDesc('start_at')
            ->paginate($perPage)
            ->appends(['q' => $q, 'per_page' => $perPage]);

        return response()->json([
            'data' => EventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
            'links' => [
                'first' => $events->url(1),
                'prev' => $events->previousPageUrl(),
                'next' => $events->nextPageUrl(),
                'last' => $events->url($events->lastPage()),
            ],
        ]);
    }

    /**
     * POST /api/v1/events
     * Body JSON validado por EventRequest
     */
    public function store(EventRequest $request): JsonResponse
    {
        $event = Event::create($request->validated());

        return response()->json([
            'message' => 'Evento criado com sucesso.',
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * GET /api/v1/events/{event}
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'data' => new EventResource($event),
        ]);
    }

    /**
     * PUT /api/v1/events/{event}
     */
    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $event->update($request->validated());

        return response()->json([
            'message' => 'Evento atualizado com sucesso.',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * DELETE /api/v1/events/{event}
     */
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'message' => 'Evento excluído com sucesso.',
        ], 200);
    }
}
