<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;


class DatabaseSyncService
{
    public function run(): array
    {
        $remoteUrl = config('services.sync.remote_url');
        $apiKey    = config('services.sync.api_key');

        \Log::info('SYNC → Iniciando sincronização', [
            'remote_url' => $remoteUrl,
            'api_key_ok' => (bool) $apiKey,
        ]);

        if (empty($remoteUrl)) {
            throw new \RuntimeException('SYNC_REMOTE_URL não configurada. Verifique .env e config/services.php.');
        }

        if (empty($apiKey)) {
            throw new \RuntimeException('SYNC_API_KEY não configurada. Verifique .env e config/services.php.');
        }

        $certInDb = DB::table('certificates')->where('id', 4)->first();
        \Log::info('SYNC → CERTIFICATE ID=4 NO BANCO LOCAL', [
            'exists' => $certInDb ? 'SIM' : 'NÃO',
            'data' => $certInDb ? (array) $certInDb : null,
        ]);

        $payload = [
            'users'               => DB::table('users')->get()->map(fn ($r) => (array) $r)->toArray(),
            'events'              => DB::table('events')->get()->map(fn ($r) => (array) $r)->toArray(),
            'event_registrations' => DB::table('event_registrations')->get()->map(fn ($r) => (array) $r)->toArray(),
            'certificates'        => DB::table('certificates')->get()->map(fn ($r) => (array) $r)->toArray(),
        ];

        $certInPayload = collect($payload['certificates'])->firstWhere('id', 4);
        \Log::info('SYNC → CERTIFICATE ID=4 NO PAYLOAD', [
            'exists' => $certInPayload ? 'SIM' : 'NÃO',
            'data' => $certInPayload,
            'keys' => $certInPayload ? array_keys($certInPayload) : null,
        ]);


        \Log::info('SYNC → PRIMEIROS 3 CERTIFICATES', [
            'certificates' => array_slice($payload['certificates'], 0, 3),
        ]);

        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
        ])->post($remoteUrl, $payload);

        \Log::info('SYNC → Resposta da VM', [
            'status' => $response->status(),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Erro ao sincronizar com a VM (status ' . $response->status() . ', url ' . $remoteUrl . '): ' .
                $response->body()
            );
        }

        $data = $response->json() ?? [];


        DB::beginTransaction();

        try {
            $this->applyUsers($data['users'] ?? []);
            $this->applyEvents($data['events'] ?? []);
            $this->applyEventRegistrations($data['event_registrations'] ?? []);
            $this->applyCertificates($data['certificates'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('SYNC → ERRO ao aplicar dados localmente', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw new \RuntimeException('Falha ao aplicar dados locais: ' . $e->getMessage());
        }

        \Log::info('SYNC → Concluído com sucesso', [
            'synced_users'               => count($data['users'] ?? []),
            'synced_events'              => count($data['events'] ?? []),
            'synced_event_registrations' => count($data['event_registrations'] ?? []),
            'synced_certificates'        => count($data['certificates'] ?? []),
        ]);

        return [
            'synced_users'               => count($data['users'] ?? []),
            'synced_events'              => count($data['events'] ?? []),
            'synced_event_registrations' => count($data['event_registrations'] ?? []),
            'synced_certificates'        => count($data['certificates'] ?? []),
        ];
    }


    private function applyUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) {
                continue;
            }

            $row = [
                'cpf'               => $data['cpf'],
                'name'              => $data['name'] ?? null,
                'email'             => $data['email'] ?? null,
                'phone'             => $data['phone'] ?? null,
                'email_verified_at' => $data['email_verified_at'] ?? null,
                'password'          => $data['password'] ?? null,
                'completed'         => $data['completed'] ?? 0,
                'remember_token'    => $data['remember_token'] ?? null,
                'deleted_at'        => $data['deleted_at'] ?? null,
                'created_at'        => $data['created_at'] ?? now(),
                'updated_at'        => $data['updated_at'] ?? now(),
            ];

            DB::table('users')->updateOrInsert(
                ['cpf' => $data['cpf']],
                $row
            );
        }
    }


    private function applyEvents(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $event = Event::withTrashed()->find($data['id'])
                ?? new Event();

            $event->forceFill($data);
            $event->save();
        }
    }

    private function applyEventRegistrations(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            if (empty($data['event_id']) || empty($data['user_id'])) {
                \Log::warning('SYNC: EventRegistration sem event_id ou user_id', [
                    'data' => $data,
                ]);
                continue;
            }

            $existing = DB::table('event_registrations')
                ->where('event_id', $data['event_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            $row = [
                'id'          => $data['id'],
                'event_id'    => $data['event_id'],
                'user_id'     => $data['user_id'],
                'status'      => $data['status']      ?? ($existing->status      ?? 'pending'),
                'presence_at' => $data['presence_at'] ?? ($existing->presence_at ?? null),
                'deleted_at'  => $data['deleted_at']  ?? ($existing->deleted_at  ?? null),
                'created_at'  => $data['created_at']  ?? ($existing->created_at  ?? now()),
                'updated_at'  => $data['updated_at']  ?? now(),
            ];

            DB::table('event_registrations')->updateOrInsert(
                [
                    'event_id' => $data['event_id'],
                    'user_id'  => $data['user_id'],
                ],
                $row
            );
        }
    }

    private function applyCertificates(array $items): void
    {
        $hasPdfPath  = Schema::hasColumn('certificates', 'pdf_path');
        $hasMetadata = Schema::hasColumn('certificates', 'metadata');

        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $existing = DB::table('certificates')
                ->where('id', $data['id'])
                ->first();

            $row = [
                'id'             => $data['id'],
                'user_id'        => $data['user_id']        ?? ($existing->user_id        ?? null),
                'user_name'      => $data['user_name']      ?? ($existing->user_name      ?? null),
                'user_cpf'       => $data['user_cpf']       ?? ($existing->user_cpf       ?? null),
                'event_id'       => $data['event_id']       ?? ($existing->event_id       ?? null),
                'event_title'    => $data['event_title']    ?? ($existing->event_title    ?? null),
                'event_start_at' => $data['event_start_at'] ?? ($existing->event_start_at ?? null),
                'code'           => $data['code']           ?? ($existing->code           ?? null),
                'issued_at'      => $data['issued_at']      ?? ($existing->issued_at      ?? null),
                'pdf_url'        => $data['pdf_url']        ?? ($existing->pdf_url        ?? null),
                'deleted_at'     => $data['deleted_at']     ?? ($existing->deleted_at     ?? null),
                'created_at'     => $data['created_at']     ?? ($existing->created_at     ?? now()),
                'updated_at'     => $data['updated_at']     ?? now(),
            ];

            if ($hasPdfPath) {
                $row['pdf_path'] = $data['pdf_path'] ?? ($existing->pdf_path ?? null);
            }

            if ($hasMetadata) {
                $row['metadata'] = $data['metadata'] ?? ($existing->metadata ?? null);
            }

            if (!$existing) {
                DB::table('certificates')->insert($row);
            } else {
                DB::table('certificates')
                    ->where('id', $data['id'])
                    ->update($row);
            }
        }
    }
}
