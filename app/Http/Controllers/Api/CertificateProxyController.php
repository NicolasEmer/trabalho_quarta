<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GenericMail;
use App\Models\ApiLog;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Certificados",
 *     description="Emissão, consulta e verificação de certificados."
 * )
 *
 * @OA\Schema(
 *     schema="Certificate",
 *     type="object",
 *     description="Certificado emitido para um participante",
 *     @OA\Property(property="id", type="integer", example=10),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="ABC123DEF"),
 *     @OA\Property(property="issued_at", type="string", format="date-time", example="2025-11-20T20:00:00Z"),
 *     @OA\Property(property="url", type="string", nullable=true, example="https://exemplo.com/certificados/ABC123DEF.pdf")
 * )
 */

class CertificateProxyController extends Controller
{

    /**
     * Emite um certificado para um participante.
     *
     * Endpoint real: POST /api/v1/certificates
     *
     * @OA\Post(
     *     path="/api/v1/certificates",
     *     tags={"Certificados"},
     *     summary="Emite um certificado",
     *     security={{"bearerAuth":{}}},
     *     description="Emite um certificado para o usuário autenticado (ou para um user_id específico) em um determinado evento.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"event_id"},
     *             @OA\Property(
     *                 property="event_id",
     *                 type="integer",
     *                 example=1,
     *                 description="ID do evento para o qual o certificado será emitido."
     *             ),
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=5,
     *                 description="Opcional. Se informado, emite o certificado para esse usuário; caso contrário usa o usuário autenticado."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Certificado emitido com sucesso.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Certificado emitido.",
     *                 "certificate": {
     *                     "id": 10,
     *                     "code": "ABC123DEF",
     *                     "url": "https://exemplo.com/certificados/ABC123DEF.pdf"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Usuário não autenticado."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Evento não encontrado."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="CERT_API_BASE ou CERT_API_KEY não configurados."
     *     ),
     *     @OA\Response(
     *         response=502,
     *         description="Falha ao comunicar com o serviço externo de certificados."
     *     )
     * )
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

        $externalPath = $base . '/certificates';
        $start = microtime(true);
        $payload = [
            'user_id'        => $user->id,
            'user_name'      => $user->name,
            'user_cpf'       => $user->cpf ?? '',
            'user_email'     => $user->email,
            'event_id'       => $event->id,
            'event_title'    => $event->title,
            'event_start_at' => $event->start_at,
        ];

        try {
            $resp = Http::withHeaders([
                'X-API-Key' => $key,
            ])->post($externalPath, $payload);

            $duration = (microtime(true) - $start) * 1000;
            $json = $resp->json();

            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'POST',
                    'path'          => $externalPath,
                    'status_code'   => $resp->status(),
                    'user_id'       => $user->id,
                    'ip'            => $request->ip(),
                    'request_body'  => $payload,
                    'response_body' => $json ?? ['raw' => $resp->body()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e) {
                Log::error('Falha ao registrar ApiLog (cert-api emit): ' . $e->getMessage());
            }

            if (
                $resp->successful() &&
                is_array($json) &&
                isset($json['data']) &&
                is_array($json['data'])
            ) {
                try {
                    $this->sendCertificateEmail($user, $event, $json['data']);
                } catch (\Throwable $mailEx) {
                    Log::error('Falha ao enviar e-mail de certificado: ' . $mailEx->getMessage());
                }
            }

            if ($json === null) {
                return response()->json([
                    'message' => 'Serviço de certificados retornou resposta não JSON.',
                    'raw'     => $resp->body(),
                ], $resp->status());
            }

            return response()->json($json, $resp->status());
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            // ----- LOG de erro na chamada externa -----
            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'POST',
                    'path'          => $externalPath,
                    'status_code'   => null,
                    'user_id'       => $user->id,
                    'ip'            => $request->ip(),
                    'request_body'  => $payload,
                    'response_body' => ['error' => $e->getMessage()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e2) {
                Log::error('Falha ao registrar ApiLog (cert-api emit exception): ' . $e2->getMessage());
            }

            Log::error('Erro ao chamar CERT API: ' . $e->getMessage());

            return response()->json([
                'message' => 'Falha ao comunicar com serviço de certificados.',
            ], 502);
        }
    }

    /**
     * Consulta um certificado pelo ID.
     *
     * Endpoint real: GET /api/v1/certificates/{id}
     *
     * @OA\Get(
     *     path="/api/v1/certificates/{id}",
     *     tags={"Certificados"},
     *     summary="Consulta certificado",
     *     security={{"bearerAuth":{}}},
     *     description="Busca detalhes de um certificado emitido no serviço externo.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID numérico do certificado no serviço externo",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificado encontrado.",
     *         @OA\JsonContent(ref="#/components/schemas/Certificate")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Certificado não encontrado."
     *     ),
     *     @OA\Response(
     *         response=502,
     *         description="Erro ao consultar certificado no serviço externo."
     *     )
     * )
     */
    public function show($id)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');
        $key  = env('CERT_API_KEY');

        if (!$base || !$key) {
            return response()->json([
                'message' => 'CERT_API_BASE ou CERT_API_KEY não configurados.',
            ], 500);
        }

        $path = $base . '/certificates/' . (int) $id;
        $start = microtime(true);

        try {
            $resp = Http::withHeaders([
                'X-API-Key' => $key,
            ])->get($path);

            $duration = (microtime(true) - $start) * 1000;
            $json = $resp->json();

            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'GET',
                    'path'          => $path,
                    'status_code'   => $resp->status(),
                    'user_id'       => optional(auth()->user())->id,
                    'ip'            => request()->ip(),
                    'request_body'  => null,
                    'response_body' => $json ?? ['raw' => $resp->body()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e) {
                Log::error('Falha ao registrar ApiLog (cert-api show): ' . $e->getMessage());
            }

            return response()->json($json, $resp->status());
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'GET',
                    'path'          => $path,
                    'status_code'   => null,
                    'user_id'       => optional(auth()->user())->id,
                    'ip'            => request()->ip(),
                    'request_body'  => null,
                    'response_body' => ['error' => $e->getMessage()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e2) {
                Log::error('Falha ao registrar ApiLog (cert-api show exception): ' . $e2->getMessage());
            }

            Log::error('Erro ao consultar certificado: ' . $e->getMessage());
            return response()->json(['message' => 'Erro ao consultar certificado.'], 502);
        }
    }


    /**
     * Verifica a autenticidade de um certificado através do código.
     *
     * Endpoint real: GET /api/v1/certificates/verify/{code}
     *
     * @OA\Get(
     *     path="/api/v1/certificates/verify/{code}",
     *     tags={"Certificados"},
     *     summary="Verifica autenticidade de certificado",
     *     security={{"bearerAuth":{}}},
     *     description="Consulta o serviço externo para verificar se o código de certificado é válido.",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Código único de verificação do certificado",
     *         @OA\Schema(type="string", example="ABC123DEF")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Certificado válido ou informações detalhadas.",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "valid": true,
     *                 "certificate": {
     *                     "id": 10,
     *                     "code": "ABC123DEF",
     *                     "user_name": "Fulano de Tal",
     *                     "event_title": "Semana Acadêmica de TI"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Certificado não encontrado ou código inválido."
     *     ),
     *     @OA\Response(
     *         response=502,
     *         description="Erro ao verificar certificado no serviço externo."
     *     )
     * )
     */
    public function verify($code)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');
        $key  = env('CERT_API_KEY');

        if (!$base || !$key) {
            return response()->json([
                'message' => 'CERT_API_BASE ou CERT_API_KEY não configurados.',
            ], 500);
        }

        $path = $base . '/certificates/verify/' . $code;
        $start = microtime(true);

        try {
            $resp = Http::withHeaders([
                'X-API-Key' => $key,
                'X-Requested-By' => 'Laravel',
            ])->get($path);

            $duration = (microtime(true) - $start) * 1000;
            $json = $resp->json();

            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'GET',
                    'path'          => $path,
                    'status_code'   => $resp->status(),
                    'user_id'       => optional(auth()->user())->id,
                    'ip'            => request()->ip(),
                    'request_body'  => null,
                    'response_body' => $json ?? ['raw' => $resp->body()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e) {
                Log::error('Falha ao registrar ApiLog (cert-api verify): ' . $e->getMessage());
            }

            return response()->json($json, $resp->status());
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            try {
                ApiLog::create([
                    'direction'     => 'out',
                    'service'       => 'cert-api',
                    'method'        => 'GET',
                    'path'          => $path,
                    'status_code'   => null,
                    'user_id'       => optional(auth()->user())->id,
                    'ip'            => request()->ip(),
                    'request_body'  => null,
                    'response_body' => ['error' => $e->getMessage()],
                    'duration_ms'   => $duration,
                ]);
            } catch (\Throwable $e2) {
                Log::error('Falha ao registrar ApiLog (cert-api verify exception): ' . $e2->getMessage());
            }

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

    private function sendCertificateEmail(User $user, Event $event, array $certData): void
    {
        if (empty($user->email)) {
            return;
        }

        $pdfUrl = $certData['pdf_url'] ?? null;

        if (!$pdfUrl && !empty($certData['code'])) {
            $pdfUrl = url('/certificates/verify/' . $certData['code']);
        }

        if (!$pdfUrl) {
            return;
        }

        $subject = 'Certificado - ' . ($event->title ?? 'Evento');

        $html = sprintf(
            '<p>Olá, %s!</p>
         <p>Seu certificado de participação no evento <strong>%s</strong> foi emitido com sucesso.</p>
         <p>Você pode acessar o certificado pelo link abaixo:</p>
         <p><a href="%s" target="_blank">%s</a></p>
         <p>Obrigado pela participação.</p>',
            e($user->name ?? 'Participante'),
            e($event->title ?? 'Evento'),
            e($pdfUrl),
            e($pdfUrl)
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
