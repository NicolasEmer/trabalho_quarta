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
        Schema::disableForeignKeyConstraints();

        foreach ($items as $data) {
            if (empty($data['id']) || empty($data['cpf'])) {
                continue;
            }

            $existingUser = DB::table('users')->where('id', $data['id'])->first();

            if ($existingUser &&
                ($existingUser->updated_at ?? '2000-01-01 00:00:00') > ($data['updated_at'] ?? '2000-01-01 00:00:00')
            ) {
                \Log::info("SYNC LWW: Ignorando atualização para ID {$data['id']}. Versão local mais nova.");
                continue;
            }

            $existingUserByCpf = DB::table('users')->where('cpf', $data['cpf'])->first();

            if ($existingUserByCpf && $existingUserByCpf->id != $data['id']) {
                \Log::warning("SYNC: Conflito de ID para CPF {$data['cpf']}. Ajustando ID local.");

                DB::table('users')->where('id', $data['id'])->delete();

                DB::table('users')
                    ->where('id', $existingUserByCpf->id)
                    ->update(['id' => $data['id']]);

                $existingUser = DB::table('users')->where('id', $data['id'])->first();
            }

            $existing = $existingUser;

            $remoteBool = filter_var($data['completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $localBool = (bool) ($existing->completed ?? false);
            $finalCompleted = ($remoteBool || $localBool) ? 1 : 0;

            $row = [
                'id'                => $data['id'],
                'cpf'               => $data['cpf'],

                'name'              => !empty($data['name'])     ? $data['name']     : ($existing->name ?? null),
                'email'             => !empty($data['email'])    ? $data['email']    : ($existing->email ?? null),
                'phone'             => !empty($data['phone'])    ? $data['phone']    : ($existing->phone ?? null),
                'password'          => !empty($data['password']) ? $data['password'] : ($existing->password ?? null),

                'completed'         => $finalCompleted,

                'email_verified_at' => $data['email_verified_at'] ?? ($existing->email_verified_at ?? null),
                'remember_token'    => $data['remember_token']    ?? ($existing->remember_token ?? null),
                'deleted_at'        => $data['deleted_at']        ?? ($existing->deleted_at ?? null),
                'created_at'        => $data['created_at']        ?? ($existing->created_at ?? now()),
                'updated_at'        => $data['updated_at']        ?? now(),
            ];

            DB::table('users')->updateOrInsert(
                ['id' => $data['id']],
                $row
            );
        }

        Schema::enableForeignKeyConstraints();
    }


    private function applyEvents(array $items): void
    {
        \Log::info('SYNC LOCAL: applyEvents recebeu', [
            'count' => count($items),
        ]);

        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $existing = DB::table('events')->where('id', $data['id'])->first();

            $row = [
                'id'          => $data['id'],

                'title'       => !empty($data['title'])       ? $data['title']       : ($existing->title       ?? null),
                'description' => !empty($data['description']) ? $data['description'] : ($existing->description ?? null),
                'location'    => !empty($data['location'])    ? $data['location']    : ($existing->location    ?? null),

                'start_at'    => !empty($data['start_at'])    ? $data['start_at']    : ($existing->start_at    ?? null),
                'end_at'      => !empty($data['end_at'])      ? $data['end_at']      : ($existing->end_at      ?? null),

                'capacity'    => !empty($data['capacity'])    ? $data['capacity']    : ($existing->capacity    ?? null),

                'is_all_day'  => ($data['is_all_day'] ?? null) !== null
                    ? (int)$data['is_all_day']
                    : ($existing->is_all_day ?? 0),

                'is_public'   => ($data['is_public'] ?? null) !== null
                    ? (int)$data['is_public']
                    : ($existing->is_public ?? 0),

                'created_at'  => $data['created_at'] ?? ($existing->created_at ?? now()),
                'updated_at'  => $data['updated_at'] ?? now(),
                'deleted_at'  => $data['deleted_at'] ?? ($existing->deleted_at ?? null),
            ];

            DB::table('events')->updateOrInsert(
                ['id' => $data['id']],
                $row
            );
        }

        \Log::info('SYNC LOCAL: applyEvents concluído.');
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

            if ($existing) {
                DB::table('event_registrations')
                    ->where('id', $existing->id)
                    ->update([
                        'status'      => $data['status']      ?? $existing->status,
                        'presence_at' => $data['presence_at'] ?? $existing->presence_at,
                        'deleted_at'  => $data['deleted_at']  ?? $existing->deleted_at,
                        'updated_at'  => $data['updated_at']  ?? now(),
                    ]);
            } else {
                DB::table('event_registrations')->insert([
                    'event_id'    => $data['event_id'],
                    'user_id'     => $data['user_id'],
                    'status'      => $data['status']      ?? 'pending',
                    'presence_at' => $data['presence_at'] ?? null,
                    'deleted_at'  => $data['deleted_at']  ?? null,
                    'created_at'  => $data['created_at']  ?? now(),
                    'updated_at'  => $data['updated_at']  ?? now(),
                ]);
            }
        }
    }

    private function applyCertificates(array $items): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($items as $data) {
            if (empty($data['id']) || empty($data['user_id']) || empty($data['event_id'])) {
                \Log::warning("SYNC: Certificado ID " . ($data['id'] ?? '?') . " ignorado. Dados incompletos (sem user_id ou event_id).");
                continue;
            }

            $existing = DB::table('certificates')->where('id', $data['id'])->first();

            $row = [
                'id'             => $data['id'],
                'user_id'        => $data['user_id'],
                'event_id'       => $data['event_id'],

                'user_name'      => $data['user_name']      ?? ($existing->user_name   ?? 'Participante Sem Nome'),
                'event_title'    => $data['event_title']    ?? ($existing->event_title ?? 'Evento Sem Título'),
                'code'           => $data['code']           ?? ($existing->code        ?? \Illuminate\Support\Str::uuid()),
                'pdf_url'        => $data['pdf_url']        ?? ($existing->pdf_url     ?? ''),

                'user_cpf'       => $data['user_cpf']       ?? ($existing->user_cpf       ?? null),
                'event_start_at' => $data['event_start_at'] ?? ($existing->event_start_at ?? now()),
                'issued_at'      => $data['issued_at']      ?? ($existing->issued_at      ?? now()),

                'deleted_at'     => $data['deleted_at']     ?? ($existing->deleted_at     ?? null),
                'created_at'     => $data['created_at']     ?? ($existing->created_at     ?? now()),
                'updated_at'     => $data['updated_at']     ?? now(),
            ];

            if (Schema::hasColumn('certificates', 'pdf_path')) {
                $row['pdf_path'] = $data['pdf_path'] ?? ($existing->pdf_path ?? null);
            }
            if (Schema::hasColumn('certificates', 'metadata')) {
                $row['metadata'] = $data['metadata'] ?? ($existing->metadata ?? null);
            }

            DB::table('certificates')->updateOrInsert(
                ['id' => $data['id']],
                $row
            );
        }

        Schema::enableForeignKeyConstraints();
    }
}
