<?php

namespace App\Http\Controllers\Api;

use App\Models\Certificate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Event;

class CertificateProxyController extends Controller
{
    /**
     * POST /api/v1/certificates
     * Body: { event_id, user_id? }
     */
    public function emit(Request $request)
    {

        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);


        $user = $request->user();

        if (!$user && !empty($data['user_id'])) {
            $user = User::find($data['user_id']);
        }

        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $event = Event::find($data['event_id']);
        if (!$event) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        $base = rtrim(env('CERT_API_BASE', ''), '/');
        $key  = env('CERT_API_KEY');

        if (!$base || !$key) {
            return response()->json([
                'message' => 'CERT_API_BASE ou CERT_API_KEY não configurados.',
            ], 500);
        }

        try {

            $resp = Http::withHeaders([
                'X-API-Key' => $key,
            ])->post($base . '/certificates', [
                'user_id'        => $user->id,
                'user_name'      => $user->name,
                'user_cpf'       => $user->cpf ?? '',
                'user_email'     => $user->email,
                'event_id'       => $event->id,
                'event_title'    => $event->title,
                'event_start_at' => $event->start_at,
            ]);

            $json = $resp->json();

            if ($json === null) {
                return response()->json([
                    'message' => 'Serviço de certificados retornou resposta não JSON.',
                    'raw'     => $resp->body(),
                ], $resp->status());
            }

            return response()->json($json, $resp->status());
        } catch (\Throwable $e) {
            Log::error('Erro ao chamar CERT API: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao comunicar com serviço de certificados.',
            ], 502);
        }
    }

    /**
     * GET /api/v1/certificates/{id}
     */
    public function show($id)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');

        try {
            $resp = Http::get($base . '/certificates/' . (int) $id);
            return response()->json($resp->json(), $resp->status());
        } catch (\Throwable $e) {
            Log::error('Erro ao consultar certificado: ' . $e->getMessage());
            return response()->json(['message' => 'Erro ao consultar certificado.'], 502);
        }
    }

    /**
     * GET /api/v1/certificates/verify/{code}
     */
    public function verify($code)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');

        try {
            $resp = Http::get($base . '/certificates/verify/' . $code);
            return response()->json($resp->json(), $resp->status());
        } catch (\Throwable $e) {
            Log::error('Erro ao verificar certificado: ' . $e->getMessage());
            return response()->json(['message' => 'Erro ao verificar certificado.'], 502);
        }
    }

    public function myForEvent(Request $request, $eventId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado.'], 401);
        }

        $cert = Certificate::where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->orderByDesc('issued_at')
            ->first();

        if (!$cert) {
            return response()->json(['message' => 'Nenhum certificado encontrado para este evento.'], 404);
        }

        return response()->json([
            'data' => $cert,
        ]);
    }

}
