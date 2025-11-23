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
     * GET /api/v1/certificates/{id}
     */
    public function show($id)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');
        $path = $base . '/certificates/' . (int) $id;
        $start = microtime(true);

        try {
            $resp = Http::get($path);
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
     * GET /api/v1/certificates/verify/{code}
     */
    public function verify($code)
    {
        $base = rtrim(env('CERT_API_BASE', ''), '/');
        $path = $base . '/certificates/verify/' . $code;
        $start = microtime(true);

        try {
            $resp = Http::get($path);
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
