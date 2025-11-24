<?php

// app/Http/Controllers/Api/EventController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Eventos",
 *     description="Operações de consulta e gestão de eventos."
 * )
 *
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     description="Evento cadastrado no sistema",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Semana Acadêmica de TI"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Evento com palestras e oficinas."),
 *     @OA\Property(property="location", type="string", nullable=true, example="Auditório Principal"),
 *     @OA\Property(property="start_at", type="string", format="date-time", nullable=true, example="2025-11-25T19:00:00"),
 *     @OA\Property(property="end_at", type="string", format="date-time", nullable=true, example="2025-11-25T22:00:00")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */

class EventController extends Controller
{
    /**
     * Consulta todos os eventos vigentes.
     *
     * @OA\Get(
     *     path="/api/v1/events",
     *     tags={"Eventos"},
     *     summary="Lista eventos vigentes",
     *     description="Retorna uma lista paginada de eventos vigentes. Pode filtrar por texto livre.",
     *     @OA\Parameter(
     *      name="id",
     *      in="query",
     *      description="Filtra o evento pelo ID exato.",
     *      required=false,
     *      @OA\Schema(type="integer", example=5)
     *      ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número da página",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Itens por página (máx. 100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de eventos retornada com sucesso.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Event")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=35),
     *                 @OA\Property(property="last_page", type="integer", example=4)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /**
         * Consulta um evento específico.
         *
         * @OA\Get(
         *     path="/events/{id}",
         *     tags={"Eventos"},
         *     summary="Consulta um evento",
         *     description="Retorna os dados de um evento específico pelo ID.",
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         description="ID do evento",
         *         required=true,
         *         @OA\Schema(type="integer", example=1)
         *     ),
         *     @OA\Response(
         *         response=200,
         *         description="Evento encontrado.",
         *         @OA\JsonContent(
         *             type="object",
         *             @OA\Property(property="data", ref="#/components/schemas/Event")
         *         )
         *     ),
         *     @OA\Response(
         *         response=404,
         *         description="Evento não encontrado."
         *     )
         * )
         */

        $q = trim((string)$request->query('q'));
        $perPage = (int)$request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $id = (int) $request->query('id');
        $events = Event::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($q2) use ($like) {
                    $q2->where('title', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })

            ->when($id > 0, fn($q) => $q->where('id', $id))

            ->orderBy('id', 'desc')
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
