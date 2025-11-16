<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventRegistrationController extends Controller
{
    public function register(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            $v = Validator::make($request->all(), [
                'cpf' => ['required','regex:/^\d{11}$/'],
                'name' => ['nullable','string','max:100'],
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
                ['name' => $request->name ?? 'Participante', 'password' => bcrypt(str()->random(16))]
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

        return response()->json([
            'message' => 'Inscrição realizada com sucesso.'
        ], 201);
    }


    public function unregister(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $reg = EventRegistration::where('event_id',$event->id)
            ->where('user_id',$user->id)
            ->where('status','confirmed')
            ->first();

        if (!$reg) {
            return response()->json(['message' => 'Inscrição não encontrada'], 404);
        }

        $reg->update(['status' => 'canceled']);

        return response()->json(['message' => 'Inscrição cancelada'], 200);
    }

    public function myRegistrations(Request $request): JsonResponse
    {
        $user = $request->user();

        $regs = EventRegistration::with('event')
            ->where('user_id', $user->id)
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
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
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
}
