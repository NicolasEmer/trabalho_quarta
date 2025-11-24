<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GenericMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Inscrições",
 *     description="Registro, consulta e cancelamento de inscrições em eventos."
 * )
 *
 * @OA\Tag(
 *     name="Presenças",
 *     description="Registro de presença dos participantes."
 * )
 *
 * @OA\Schema(
 *     schema="EventRegistration",
 *     type="object",
 *     description="Inscrição de usuário em um evento",
 *     @OA\Property(property="id", type="integer", example=123),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="status", type="string", example="confirmed"),
 *     @OA\Property(property="presence_at", type="string", format="date-time", nullable=true, example="2025-11-20T19:30:00Z")
 * )
 */

class EventRegistrationController extends Controller
{

    /**
     * Registra uma inscrição em um evento.
     *
     * Endpoint real: POST /api/v1/events/{event}/register
     *
     * @OA\Post(
     *     path="/api/v1/events/{event}/register",
     *     tags={"Inscrições"},
     *     summary="Registra inscrição em evento",
     *     security={{"bearerAuth":{}}},
     *     description="Cria ou reativa a inscrição do usuário autenticado (ou cria via CPF) em um evento.",
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="ID do evento",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="cpf",
     *                 type="string",
     *                 nullable=true,
     *                 example="12345678909",
     *                 description="CPF do participante (usado quando não há usuário autenticado)."
     *             ),
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 nullable=true,
     *                 example="Participante Externo"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inscrição criada com sucesso.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={"message": "Inscrição realizada com sucesso."}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inscrição já existia e foi reativada ou já estava confirmada.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={"message": "Você já está inscrito neste evento."}
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Evento lotado."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="CPF inválido ou dados de inscrição inválidos."
     *     )
     * )
     */

    public function register(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user && $request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
        }

        if (!$user) {
            $v = Validator::make($request->all(), [
                'cpf'  => ['required', 'regex:/^\d{11}$/'],
                'name' => ['nullable', 'string', 'max:100'],
            ]);

            if ($v->fails()) {
                return response()->json(['errors' => $v->errors()], 422);
            }

            $cpf = preg_replace('/\D+/', '', $request->cpf);

            if (!$this->validCpf($cpf)) {
                return response()->json(['message' => 'CPF inválido'], 422);
            }

            $user = User::firstOrCreate(
                ['cpf' => $cpf],
                [
                    'name'     => $request->name ?? 'Participante',
                    'password' => bcrypt(str()->random(16)),
                ]
            );
        }

        $existing = EventRegistration::where('event_id', $event->id)
            ->where('user_id',  $user->id)
            ->first();

        if ($existing && $existing->status === 'confirmed') {
            return response()->json([
                'message' => 'Você já está inscrito neste evento.'
            ], 200);
        }

        if ($existing && $existing->status === 'canceled') {

            if ($event->isFull()) {
                return response()->json([
                    'message' => 'Evento lotado.'
                ], 409);
            }

            $existing->update([
                'status' => 'confirmed'
            ]);

            $this->sendRegistrationEmail($user, $event, true);

            return response()->json([
                'message' => 'Inscrição reativada com sucesso.'
            ], 200);
        }

        if ($event->isFull()) {
            return response()->json([
                'message' => 'Evento lotado.'
            ], 409);
        }

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id'  => $user->id,
            'status'   => 'confirmed'
        ]);

        $this->sendRegistrationEmail($user, $event, false);

        return response()->json([
            'message' => 'Inscrição realizada com sucesso.'
        ], 201);
    }

    /**
     * Cancela a inscrição do usuário autenticado em um evento.
     *
     * Endpoint real: DELETE /api/v1/events/{event}/register
     *
     * @OA\Delete(
     *     path="/api/v1/events/{event}/register",
     *     tags={"Inscrições"},
     *     summary="Cancela inscrição em evento",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="ID do evento",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inscrição cancelada.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={"message": "Inscrição cancelada"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Inscrição não encontrada."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Não é possível cancelar após presença/certificado."
     *     )
     * )
     */

    public function unregister(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user && $request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
        }

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $reg = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->first();

        if (!$reg) {
            return response()->json(['message' => 'Inscrição não encontrada'], 404);
        }

        if ($reg->presence_at) {
            return response()->json([
                'message' => 'Não é possível cancelar a inscrição após confirmar presença ou emitir certificado.'
            ], 422);
        }

        $reg->update(['status' => 'canceled']);

        $this->sendCancelEmail($user, $event);

        return response()->json(['message' => 'Inscrição cancelada'], 200);
    }

    /**
     * Lista as inscrições do usuário autenticado ou de um usuário específico.
     *
     * Endpoint real: GET /api/v1/my-registrations
     *
     * @OA\Get(
     *     path="/api/v1/my-registrations",
     *     tags={"Inscrições"},
     *     summary="Lista inscrições do usuário",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="ID do usuário para listar as inscrições. Se omitido, usa o usuário autenticado.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de inscrições do usuário.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     example={
     *                         "id":123,
     *                         "status":"confirmed",
     *                         "created_at":"2025-11-20T18:00:00Z",
     *                         "updated_at":"2025-11-20T18:10:00Z",
     *                         "presence_at":null,
     *                         "event":{
     *                             "id":1,
     *                             "title":"Semana Acadêmica",
     *                             "location":"Auditório",
     *                             "start_at":"2025-11-25T19:00:00Z",
     *                             "end_at":"2025-11-25T22:00:00Z",
     *                             "capacity":100
     *                         }
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado."
     *     )
     * )
     */

    public function myRegistrations(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $userId = (int) $request->query('user_id', $authUser?->id);

        if (!$userId) {
            return response()->json([
                'message' => 'Não autenticado.'
            ], 401);
        }

        $regs = EventRegistration::with('event')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $regs = EventRegistration::with('event')
            ->where('user_id', $authUser->id)
            ->orderByDesc('created_at')
            ->get();

        $data = $regs->map(function (EventRegistration $reg) {
            return [
                'id'         => $reg->id,
                'status'     => $reg->status,
                'created_at' => optional($reg->created_at)->toISOString(),
                'updated_at' => optional($reg->updated_at)->toISOString(),
                'event'      => $reg->event ? [
                    'id'        => $reg->event->id,
                    'title'     => $reg->event->title,
                    'location'  => $reg->event->location,
                    'start_at'  => optional($reg->event->start_at)->toISOString(),
                    'end_at'    => optional($reg->event->end_at)->toISOString(),
                    'capacity'  => $reg->event->capacity ?? null,
                ] : null,
                'presence_at' => optional($reg->presence_at)->toISOString(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Registra presença do usuário autenticado em um evento.
     *
     * Endpoint real: POST /api/v1/events/{event}/presence
     *
     * @OA\Post(
     *     path="/api/v1/events/{event}/presence",
     *     tags={"Presenças"},
     *     summary="Registra presença em evento",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="event",
     *         in="path",
     *         required=true,
     *         description="ID do evento",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Presença registrada com sucesso.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Presença registrada com sucesso.",
     *                 "data": {
     *                     "id":123,
     *                     "status":"confirmed",
     *                     "presence_at":"2025-11-20T19:30:00Z"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Inscrição ativa não encontrada para o evento."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Evento não começou ou presença já registrada."
     *     )
     * )
     */

    public function markPresence(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $registration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'Você não possui inscrição ativa neste evento.'
            ], 404);
        }

        $now = now();

        if ($event->start_at && $now->lt($event->start_at)) {
            return response()->json([
                'message' => 'Ainda não é possível marcar presença, o evento não começou.'
            ], 422);
        }

        if ($registration->presence_at) {
            return response()->json([
                'message' => 'Presença já foi registrada para esta inscrição.'
            ], 422);
        }

        $registration->presence_at = $now;
        $registration->save();

        return response()->json([
            'message' => 'Presença registrada com sucesso.',
            'data'    => [
                'id'           => $registration->id,
                'status'       => $registration->status,
                'presence_at'  => optional($registration->presence_at)->toISOString(),
                'created_at'   => optional($registration->created_at)->toISOString(),
                'updated_at'   => optional($registration->updated_at)->toISOString(),
                'event'        => $registration->event ? [
                    'id'        => $registration->event->id,
                    'title'     => $registration->event->title,
                    'location'  => $registration->event->location,
                    'start_at'  => optional($registration->event->start_at)->toISOString(),
                    'end_at'    => optional($registration->event->end_at)->toISOString(),
                    'capacity'  => $registration->event->capacity ?? null,
                ] : null,
            ],
        ], 200);
    }

    private function validCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(.)\\1{10}$/', $cpf)) return false;

        $sum = 0;
        for ($i=0,$w=10;$i<9;$i++,$w--) $sum += intval($cpf[$i]) * $w;
        $rest = $sum % 11;
        $d1 = $rest < 2 ? 0 : 11 - $rest;

        $sum = 0;
        for ($i=0,$w=11;$i<10;$i++,$w--) $sum += intval($cpf[$i]) * $w;
        $rest = $sum % 11;
        $d2 = $rest < 2 ? 0 : 11 - $rest;

        return $cpf[9] == $d1 && $cpf[10] == $d2;
    }

    private function sendRegistrationEmail(User $user, Event $event, bool $reactivated = false): void
    {
        if (empty($user->email)) {
            return;
        }

        $subject = $reactivated
            ? 'Inscrição reativada - ' . ($event->title ?? 'Evento')
            : 'Inscrição confirmada - ' . ($event->title ?? 'Evento');

        $html = sprintf(
            '<p>Olá, %s!</p>
             <p>Sua inscrição %s no evento <strong>%s</strong> foi registrada com sucesso.</p>
             <p><strong>Data/hora de início:</strong> %s<br>
             <strong>Local:</strong> %s</p>
             <p>Obrigado pela participação.</p>',
            e($user->name ?? 'Participante'),
            $reactivated ? 'foi reativada' : 'foi confirmada',
            e($event->title ?? 'Evento'),
            optional($event->start_at)->format('d/m/Y H:i') ?? '-',
            e($event->location ?? '-')
        );

        $mailable = new GenericMail(
            subject: $subject,
            html: $html,
            text: strip_tags($html),
            headers: []
        );


        Mail::to($user->email)->send($mailable);
    }

    private function sendCancelEmail(User $user, Event $event): void
    {
        if (empty($user->email)) {
            return;
        }

        $subject = 'Inscrição cancelada - ' . ($event->title ?? 'Evento');

        $html = sprintf(
            '<p>Olá, %s.</p>
             <p>Sua inscrição no evento <strong>%s</strong> foi cancelada conforme sua solicitação.</p>
             <p>Se este cancelamento não foi realizado por você, entre em contato com a organização.</p>',
            e($user->name ?? 'Participante'),
            e($event->title ?? 'Evento')
        );

        $mailable = new GenericMail(
            subject: $subject,
            html: $html,
            text: strip_tags($html),
            headers: []
        );

        Mail::to($user->email)->send($mailable);
    }

}
